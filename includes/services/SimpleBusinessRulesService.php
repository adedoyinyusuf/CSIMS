<?php
/**
 * Simple BusinessRulesService for Admin Template System
 * 
 * This is a simplified version that provides basic business rules functionality
 * for the admin dashboard without complex dependencies.
 */

class SimpleBusinessRulesService 
{
    private $pdo; // using mysqli connection from Database service
    private $emailService;
    private $smsService;
    
    public function __construct($pdo = null) 
    {
        // Accept injected connection or lazily resolve from Database singleton
        $this->pdo = $pdo ?: (class_exists('Database') ? Database::getInstance()->getConnection() : null);
        // Initialize notification services if available
        try { if (class_exists('EmailService')) { $this->emailService = new EmailService(); } } catch (Throwable $e) {}
        try { if (class_exists('SMSService')) { $this->smsService = new SMSService(); } } catch (Throwable $e) {}
    }
    
    /**
     * Get business rule alerts for admin dashboard
     */
    public function getBusinessRuleAlerts(): array 
    {
        // If database connection is unavailable, do not emit mock alerts
        if (!$this->pdo) { return []; }

        // If there are no loans in the system, return no alerts
        $hasLoans = false;
        try {
            $res = $this->pdo->query("SELECT COUNT(*) AS c FROM loans");
            if ($res && ($row = $res->fetch_assoc())) { $hasLoans = ((int)$row['c']) > 0; }
        } catch (Throwable $e) {
            // Table may not exist yet; be conservative and emit no alerts
            return [];
        }
        if (!$hasLoans) { return []; }

        $alerts = [];

        // Detect loan PK
        $loanPk = $this->columnExists('loans', 'loan_id') ? 'loan_id' : ($this->columnExists('loans', 'id') ? 'id' : 'id');
        $memberPk = $this->columnExists('members', 'member_id') ? 'member_id' : ($this->columnExists('members', 'id') ? 'id' : 'member_id');
        $amountCol = $this->detectLoanAmountColumn();

        // 1) Pending applications needing eligibility review (simple: status pending)
        try {
            $res = $this->pdo->query("SELECT COUNT(*) AS c FROM loans WHERE LOWER(status) = 'pending'");
            if ($res && ($row = $res->fetch_assoc())) {
                $pending = (int)$row['c'];
                if ($pending > 0) {
                    $alerts[] = [
                        'type' => 'info',
                        'message' => $pending . ' loan application(s) pending eligibility check',
                        'priority' => 'medium',
                        'action_url' => 'loans.php?status=pending'
                    ];
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // 2) Guarantor verification required: loans of types that require guarantors but lack signed/active guarantors
        try {
            if ($this->tableExists('loan_types') && $this->tableExists('loan_guarantors')) {
                $sql = "SELECT l.$loanPk AS loan_id, l.member_id, COALESCE(l.$amountCol, 0) AS amount, lt.requires_guarantor, lt.guarantor_count,
                               SUM(CASE WHEN LOWER(COALESCE(g.status,'')) IN ('active','signed','approved') THEN 1 ELSE 0 END) AS signed_count,
                               COUNT(g.$loanPk) AS total_guarantors
                        FROM loans l
                        INNER JOIN loan_types lt ON l.loan_type_id = lt.id
                        LEFT JOIN loan_guarantors g ON g.loan_id = l.$loanPk
                        WHERE lt.requires_guarantor = 1 AND LOWER(COALESCE(l.status,'')) IN ('pending','pending_approval')
                        GROUP BY l.$loanPk, l.member_id, lt.requires_guarantor, lt.guarantor_count";
                $res = $this->pdo->query($sql);
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $required = (int)($row['guarantor_count'] ?? 0);
                        $signed = (int)($row['signed_count'] ?? 0);
                        if ($required > 0 && $signed < $required) {
                            $alerts[] = [
                                'type' => 'warning',
                                'loan_id' => (int)$row['loan_id'],
                                'reason' => 'Guarantor sign-off required',
                                'message' => 'Guarantor verification pending',
                                'priority' => 'high',
                                'action_url' => 'loan_guarantor_management.php?loan_id=' . (int)$row['loan_id']
                            ];
                            // Trigger notifications to guarantors to sign off
                            $this->notifyGuarantorsToSign((int)$row['loan_id']);
                        }
                    }
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // 3) Loans exceeding type limit: alert admin
        try {
            if ($this->tableExists('loan_types')) {
                $sql = "SELECT l.$loanPk AS loan_id, COALESCE(l.$amountCol, 0) AS amount, lt.max_amount
                        FROM loans l INNER JOIN loan_types lt ON l.loan_type_id = lt.id
                        WHERE lt.max_amount IS NOT NULL AND COALESCE(l.$amountCol,0) > lt.max_amount";
                $res = $this->pdo->query($sql);
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $alerts[] = [
                            'type' => 'error',
                            'loan_id' => (int)$row['loan_id'],
                            'reason' => 'Loan exceeds configured limit',
                            'message' => 'Amount exceeds loan type maximum',
                            'priority' => 'high',
                            'action_url' => 'edit_loan.php?id=' . (int)$row['loan_id']
                        ];
                    }
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // 4) Gate ineligible applications: mark as inactive if failing simple checks
        try {
            // Simple rule: if loan requires guarantor and none signed OR exceeds max, set status to 'inactive' for pending loans
            if ($this->columnExists('loans', 'status')) {
                $this->pdo->query("UPDATE loans l
                    INNER JOIN loan_types lt ON l.loan_type_id = lt.id
                    LEFT JOIN (
                        SELECT loan_id, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('active','signed','approved') THEN 1 ELSE 0 END) AS signed_count
                        FROM loan_guarantors GROUP BY loan_id
                    ) g ON g.loan_id = l.$loanPk
                    SET l.status = 'inactive'
                    WHERE LOWER(l.status) IN ('pending','pending_approval')
                      AND (
                        (lt.requires_guarantor = 1 AND COALESCE(g.signed_count,0) < lt.guarantor_count)
                        OR (lt.max_amount IS NOT NULL AND COALESCE(l.$amountCol,0) > lt.max_amount)
                      )");
            }
        } catch (Throwable $e) { /* ignore */ }

        // 5) Overdue loan alerts to members
        try {
            if ($this->tableExists('loan_payment_schedule') && $this->tableExists('members')) {
                $scheduleStatusCol = $this->columnExists('loan_payment_schedule', 'payment_status') ? 'payment_status' : ($this->columnExists('loan_payment_schedule', 'status') ? 'status' : null);
                $statusFilter = $scheduleStatusCol ? " AND LOWER(s.$scheduleStatusCol) IN ('pending','due','unpaid')" : '';
                $sql = "SELECT l.$loanPk AS loan_id, l.member_id, m.email, m.first_name, m.last_name, SUM(s.scheduled_amount) AS overdue_amount, COUNT(*) AS overdue_count
                        FROM loan_payment_schedule s
                        JOIN loans l ON s.loan_id = l.$loanPk
                        JOIN members m ON l.member_id = m.$memberPk
                        WHERE s.due_date < CURDATE() $statusFilter AND LOWER(l.status) IN ('active','disbursed','approved')
                        GROUP BY l.$loanPk, l.member_id, m.email, m.first_name, m.last_name";
                $res = $this->pdo->query($sql);
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        // Notify member via email (best-effort)
                        $this->notifyMemberOverdue(
                            (int)$row['loan_id'],
                            $row['email'] ?? '',
                            trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                            (float)($row['overdue_amount'] ?? 0),
                            (int)($row['overdue_count'] ?? 0)
                        );
                        // Also surface an admin alert
                        $alerts[] = [
                            'type' => 'warning',
                            'loan_id' => (int)$row['loan_id'],
                            'reason' => 'Loan has overdue payment(s)',
                            'message' => 'Member notified of overdue schedule',
                            'priority' => 'high',
                            'action_url' => 'view_loan.php?id=' . (int)$row['loan_id']
                        ];
                    }
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // Additional rules can be added here (e.g., exceeded limits, guarantor verification)
        // Only emit alerts based on actual data to avoid misleading placeholders.

        return $alerts;
    }

    /**
     * Compatibility alias used by views/admin/loans.php
     */
    public function getLoanAlerts(): array 
    {
        // Delegate to the existing method for consistency
        return $this->getBusinessRuleAlerts();
    }
    
    /**
     * Get pending rule violations count
     */
    public function getPendingViolationsCount(): int 
    {
        if (!$this->pdo) { return 0; }
        try {
            $res = $this->pdo->query("SELECT COUNT(*) AS c FROM loans WHERE LOWER(status) = 'pending'");
            if ($res && ($row = $res->fetch_assoc())) { return (int)$row['c']; }
        } catch (Throwable $e) { /* ignore */ }
        return 0;
    }

    // ===== Helpers =====
    private function tableExists(string $table): bool {
        try { $r = $this->pdo->query("SHOW TABLES LIKE '".$this->pdo->real_escape_string($table)."'"); return (bool)($r && $r->num_rows > 0); } catch (Throwable $e) { return false; }
    }
    private function columnExists(string $table, string $column): bool {
        try { $r = $this->pdo->query("SHOW COLUMNS FROM " . $table . " LIKE '".$this->pdo->real_escape_string($column)."'"); return (bool)($r && $r->num_rows > 0); } catch (Throwable $e) { return false; }
    }
    private function detectLoanAmountColumn(): string {
        foreach (['principal_amount','amount','loan_amount'] as $c) { if ($this->columnExists('loans',$c)) return $c; }
        return 'amount';
    }

    // ===== Notifications =====
    private function notifyGuarantorsToSign(int $loanId): void {
        if (!$this->emailService && !$this->smsService) { return; }
        try {
            $sql = "SELECT g.guarantor_member_id, m.email, m.first_name, m.last_name, m.phone
                    FROM loan_guarantors g LEFT JOIN members m ON g.guarantor_member_id = m.member_id
                    WHERE g.loan_id = ?";
            $stmt = $this->pdo->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $loanId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    $msg = "You have been requested to guarantee Loan #$loanId. Please sign off to continue approval.";
                    if ($this->emailService && !empty($row['email'])) { $this->emailService->send($row['email'], 'Guarantor Verification Request', $msg, $name); }
                    if ($this->smsService && !empty($row['phone'])) { $this->smsService->send($row['phone'], $msg); }
                }
            }
        } catch (Throwable $e) { /* ignore notification errors */ }
    }

    private function notifyMemberOverdue(int $loanId, string $email, string $name, float $amount, int $count): void {
        if ($this->emailService && !empty($email)) {
            $content = "Dear {$name},\n\nOur records show {$count} overdue payment(s) totaling ₦" . number_format($amount,2) . " for your Loan #{$loanId}. Please make payment or contact support.";
            try { $this->emailService->send($email, 'Overdue Loan Payment Notice', $content, $name); } catch (Throwable $e) { /* ignore */ }
        }
    }
    
    /**
     * Check if a member meets basic loan eligibility (simplified)
     */
    public function checkLoanEligibility($memberId): array 
    {
        // Simplified eligibility check - replace with actual logic
        return [
            'eligible' => true,
            'reasons' => [],
            'warnings' => ['Member should have 6+ months of savings']
        ];
    }
    
    /**
     * Get system compliance status
     */
    public function getComplianceStatus(): array 
    {
        // Mock compliance data
        return [
            'overall_score' => 85,
            'categories' => [
                'member_compliance' => 90,
                'loan_compliance' => 80,
                'savings_compliance' => 85
            ]
        ];
    }
    
    /**
     * Validate business rule (generic)
     */
    public function validateRule($ruleType, $data): array 
    {
        // Generic rule validation - implement specific rules as needed
        return [
            'valid' => true,
            'violations' => [],
            'warnings' => []
        ];
    }
}
?>