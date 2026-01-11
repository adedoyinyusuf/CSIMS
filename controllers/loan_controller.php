<?php

// Legacy-compatible LoanController shim that delegates to modern services

require_once __DIR__ . '/../src/bootstrap.php';

use CSIMS\Services\LoanService;
use CSIMS\Services\AuditLogger;
use CSIMS\Container\Container;
use CSIMS\Repositories\LoanRepository;
use CSIMS\Core\BaseController;

class LoanController extends BaseController
{
    private Container $container;
    private LoanService $loanService;
    private AuditLogger $auditLogger;
    private mysqli $conn;

    // Accept optional legacy $conn for compatibility; ignored as BaseController manages DB
    public function __construct($conn = null)
    {
        parent::__construct(); // Initialize BaseController services (DB, Session, Security)
        
        // Initialize modern container and resolve services
        $this->container = CSIMS\bootstrap();
        $this->loanService = $this->container->resolve(LoanService::class);
        $this->auditLogger = $this->container->resolve(AuditLogger::class);
        
        // Map BaseController's DB connection to local property for backward compat
        $this->conn = $this->db;
    }

    /**
     * Get interest rate based on loan amount and term
     * Mirrors legacy logic to keep UX consistent in views
     */
    public function getInterestRate($amount, $term_months)
    {
        $amount = (float)$amount;
        $term_months = (int)$term_months;

        $base_rate = 5.0; // 5% base rate

        // Adjust rate based on loan amount (higher amounts get better rates)
        if ($amount >= 500000) {
            $amount_adjustment = -0.5;
        } elseif ($amount >= 100000) {
            $amount_adjustment = 0.0;
        } else {
            $amount_adjustment = 1.0;
        }

        // Adjust rate based on term (longer terms have higher rates)
        if ($term_months <= 6) {
            $term_adjustment = 0.0;
        } elseif ($term_months <= 12) {
            $term_adjustment = 0.5;
        } elseif ($term_months <= 24) {
            $term_adjustment = 1.0;
        } else {
            $term_adjustment = 1.5;
        }

        $final_rate = $base_rate + $amount_adjustment + $term_adjustment;
        return max(3.0, min(15.0, $final_rate));
    }

    /**
     * Legacy-compatible API that creates a loan application.
     * Delegates to modern LoanService::createLoan and returns the new loan ID.
     *
     * @param array $data
     * @return int|false
     */
    public function addLoanApplication(array $data)
    {
        try {
            // Normalize legacy keys to modern model expectations
            $normalized = [
                'member_id' => isset($data['member_id']) ? (int)$data['member_id'] : 0,
                'amount' => isset($data['amount']) ? (float)$data['amount'] : 0.0,
                'purpose' => $data['purpose'] ?? '',
                'term_months' => isset($data['term_months']) ? (int)$data['term_months'] : (isset($data['term']) ? (int)$data['term'] : 12),
                'interest_rate' => isset($data['interest_rate']) ? (float)$data['interest_rate'] : 0.0,
                'monthly_payment' => isset($data['monthly_payment']) ? (float)$data['monthly_payment'] : null,
                'application_date' => $data['application_date'] ?? date('Y-m-d'),
                'status' => $data['status'] ?? 'Pending',
                'collateral' => $data['collateral'] ?? null,
                'guarantor' => $data['guarantor'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            // Allow passthrough of any extra fields for future compatibility
            foreach (['savings','month_deduction_started','month_deduction_end','other_payment_plans','remarks'] as $extra) {
                if (array_key_exists($extra, $data)) {
                    $normalized[$extra] = $data[$extra];
                }
            }

            $loan = $this->loanService->createLoan($normalized);
            return method_exists($loan, 'getId') ? $loan->getId() : false;
        } catch (\Throwable $e) {
            error_log('LoanController shim addLoanApplication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy-compatible API: return loans for a member as associative arrays
     * compatible with legacy views.
     *
     * @param int $memberId
     * @return array|false
     */
    public function getMemberLoans($memberId)
    {
        try {
            $memberId = (int)$memberId;
            $loans = $this->loanService->getLoansByMember($memberId);
            // Convert Loan models to arrays
            $out = [];
            foreach ($loans as $loan) {
                if (is_object($loan) && method_exists($loan, 'toArray')) {
                    $out[] = $loan->toArray();
                } elseif (is_array($loan)) {
                    $out[] = $loan; // Already array
                }
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('LoanController shim getMemberLoans error: ' . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // Admin: Aggregate members with loans
    // ========================================
    public function getMembersWithLoans(int $limit = 10, ?string $search = null): array
    {
        if (!$this->hasTable('loans')) {
            return [];
        }

        // Determine available columns for robust querying
        $hasMembers = $this->hasTable('members');
        $join_clause = '';
        $select_member = '';
        $name_expr = '';
        if ($hasMembers) {
            $join_clause = 'LEFT JOIN members m ON l.member_id = m.member_id';
            if ($this->hasColumn('members', 'first_name') && $this->hasColumn('members', 'last_name')) {
                $name_expr = "CONCAT(m.first_name, ' ', m.last_name)";
                $select_member = ", $name_expr as member_name";
            } elseif ($this->hasColumn('members', 'name')) {
                $name_expr = 'm.name';
                $select_member = ', m.name as member_name';
            }
        }

        $hasAppDate = $this->hasColumn('loans', 'application_date');
        $hasStatus = $this->hasColumn('loans', 'status');
        $statusLatestExpr = ($hasAppDate && $hasStatus)
            ? "SUBSTRING_INDEX(GROUP_CONCAT(l.status ORDER BY l.application_date DESC), ',', 1)"
            : ($hasStatus ? 'MAX(l.status)' : "NULL");
        $lastDateExpr = $hasAppDate ? 'MAX(l.application_date)' : 'NULL';

        // Per-member counts by status buckets
        $activeExpr = $hasStatus ? "SUM(CASE WHEN l.status IN ('Active','Disbursed') THEN 1 ELSE 0 END)" : '0';
        $overdueExpr = $hasStatus ? "SUM(CASE WHEN l.status IN ('Overdue','Defaulted') THEN 1 ELSE 0 END)" : '0';

        // Build base SQL
        $sql = "SELECT l.member_id, COUNT(*) AS loan_count, COALESCE(SUM(l.amount),0) AS total_amount, ".$lastDateExpr." AS last_application_date, ".$statusLatestExpr." AS latest_status, ".$activeExpr." AS active_count, ".$overdueExpr." AS overdue_count".$select_member.
               " FROM loans l ".$join_clause;

        $where = [];
        $params = [];
        $types = '';

        // Optional search by member name, id, or status
        if (!empty($search)) {
            $like = "%".$search."%";
            $searchConditions = [];
            if (!empty($name_expr)) {
                $searchConditions[] = $name_expr." LIKE ?";
                $params[] = $like;
                $types .= 's';
            }
            $searchConditions[] = 'CAST(l.member_id AS CHAR) LIKE ?';
            $params[] = $like;
            $types .= 's';
            if ($this->hasColumn('loans', 'status')) {
                $searchConditions[] = 'l.status LIKE ?';
                $params[] = $like;
                $types .= 's';
            }
            if (!empty($searchConditions)) {
                $where[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
        }

        $whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';
        $sql .= $whereSql . ' GROUP BY l.member_id';

        // Order by loan count then total amount
        $sql .= ' ORDER BY loan_count DESC, total_amount DESC LIMIT ?';
        $params[] = $limit;
        $types .= 'i';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log('LoanController getMembersWithLoans: failed to prepare statement: ' . ($this->conn->error ?? 'unknown error'));
            return [];
        }
        if (!empty($types)) {
            if (!$stmt->bind_param($types, ...$params)) {
                error_log('LoanController getMembersWithLoans: failed to bind params: ' . ($stmt->error ?? 'unknown error'));
                return [];
            }
        } else {
            // Only limit parameter
            if (!$stmt->bind_param('i', $limit)) {
                error_log('LoanController getMembersWithLoans: failed to bind limit param: ' . ($stmt->error ?? 'unknown error'));
                return [];
            }
        }
        if (!$stmt->execute()) {
            error_log('LoanController getMembersWithLoans: failed to execute: ' . ($stmt->error ?? 'unknown error'));
            return [];
        }
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'member_id' => (int)($row['member_id'] ?? 0),
                'member_name' => $row['member_name'] ?? null,
                'loan_count' => (int)($row['loan_count'] ?? 0),
                'total_amount' => (float)($row['total_amount'] ?? 0),
                'latest_status' => $row['latest_status'] ?? null,
                'last_application_date' => $row['last_application_date'] ?? null,
                'active_count' => (int)($row['active_count'] ?? 0),
                'overdue_count' => (int)($row['overdue_count'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Legacy alias maintained for backward compatibility in views.
     */
    public function getLoansByMemberId($memberId)
    {
        return $this->getMemberLoans($memberId);
    }

    /**
     * Provide monthly payment calculation for compatibility if needed by views.
     * Not currently used by the canonical view, but kept for parity.
     */
    public function calculateMonthlyPayment($principal, $annual_interest_rate, $term_months)
    {
        $principal = (float)$principal;
        $annual_interest_rate = (float)$annual_interest_rate;
        $term_months = (int)$term_months;

        if ($principal <= 0 || $term_months <= 0) {
            return 0.0;
        }
        $monthly_interest_rate = ($annual_interest_rate / 100) / 12;
        if ($monthly_interest_rate == 0.0) {
            return round($principal / $term_months, 2);
        }
        $payment = ($principal * $monthly_interest_rate * pow(1 + $monthly_interest_rate, $term_months)) /
                   (pow(1 + $monthly_interest_rate, $term_months) - 1);
        return round($payment, 2);
    }

    // ========================================
    // Admin: Get all loans with pagination, search, sort, and filters
    // ========================================
    public function getAllLoans($page = 1, $limit = 10, $search = '', $sort_by = 'application_date', $sort_order = 'DESC', $filters = [])
    {
        $offset = ($page - 1) * $limit;
        $where_conditions = [];
        $params = [];
        $types = '';

        // Base query - check if loans table exists
        if (!$this->hasTable('loans')) {
            return ['loans' => [], 'total' => 0, 'pages' => 0];
        }

        // Build WHERE conditions
        if (!empty($search)) {
            $search_conditions = [];
            if ($this->hasColumn('loans', 'purpose')) {
                $search_conditions[] = "l.purpose LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            }
            if ($this->hasTable('members') && $this->hasColumn('members', 'first_name')) {
                $search_conditions[] = "CONCAT(m.first_name, ' ', m.last_name) LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            }
            if (!empty($search_conditions)) {
                $where_conditions[] = '(' . implode(' OR ', $search_conditions) . ')';
            }
        }

        // Status filter
        if (!empty($filters['status'])) {
            // Be resilient to accidental whitespace or case variations in stored status
            $where_conditions[] = "UPPER(TRIM(l.status)) = UPPER(?)";
            $params[] = $filters['status'];
            $types .= 's';
        }

        // Loan type filter (if loan_type column exists)
        if (!empty($filters['loan_type']) && $this->hasColumn('loans', 'loan_type')) {
            $where_conditions[] = "l.loan_type = ?";
            $params[] = $filters['loan_type'];
            $types .= 's';
        }

        // Amount range filters
        if (!empty($filters['min_amount'])) {
            $where_conditions[] = "l.amount >= ?";
            $params[] = (float)$filters['min_amount'];
            $types .= 'd';
        }
        if (!empty($filters['max_amount'])) {
            $where_conditions[] = "l.amount <= ?";
            $params[] = (float)$filters['max_amount'];
            $types .= 'd';
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Validate sort column
        $valid_sort_columns = ['loan_id', 'amount', 'application_date', 'status'];
        if ($this->hasColumn('loans', 'term_months')) $valid_sort_columns[] = 'term_months';
        if ($this->hasColumn('loans', 'interest_rate')) $valid_sort_columns[] = 'interest_rate';
        
        if (!in_array($sort_by, $valid_sort_columns)) {
            $sort_by = 'application_date';
        }
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        // Build JOIN clause
        $join_clause = '';
        $select_member = '';
        $select_loan_type = '';
        
        if ($this->hasTable('members')) {
            $join_clause = "LEFT JOIN members m ON l.member_id = m.member_id";
            if ($this->hasColumn('members', 'first_name')) {
                $select_member = ", m.first_name, m.last_name, CONCAT(m.first_name, ' ', m.last_name) as member_name";
            }
        }
        
        // Join loan_types table if it exists
        if ($this->hasTable('loan_types')) {
            if ($this->hasColumn('loans', 'loan_type_id')) {
                $join_clause .= " LEFT JOIN loan_types lt ON l.loan_type_id = lt.id";
                $select_loan_type = ", lt.name as loan_type_name";
            } elseif ($this->hasColumn('loans', 'loan_type')) {
                // If loan_type is a string column, try to match it with loan_types.name
                $join_clause .= " LEFT JOIN loan_types lt ON l.loan_type = lt.name";
                $select_loan_type = ", lt.name as loan_type_name";
            }
        }

        // Count query
        $count_sql = "SELECT COUNT(*) as total FROM loans l $join_clause $where_clause";
        $count_stmt = $this->conn->prepare($count_sql);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];

        // Main query
        $sql = "SELECT l.* $select_member $select_loan_type FROM loans l $join_clause $where_clause ORDER BY l.$sort_by $sort_order LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $loans = [];
        while ($row = $result->fetch_assoc()) {
            $loans[] = $row;
        }

        return [
            'loans' => $loans,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }

    // ========================================
    // Admin: Get loan statistics for dashboard
    // ========================================
    public function getLoanStatistics()
    {
        if (!$this->hasTable('loans')) {
            return [
                'total_loans' => 0,
                'total_amount' => 0,
                'pending_loans' => 0,
                'approved_loans' => 0,
                'overdue_loans' => 0,
                'paid_loans' => 0,
                'monthly_repayments' => 0,
                'recent_loans' => []
            ];
        }

        $stats = [];

        // Total loans and amount
        $sql = "SELECT COUNT(*) as total_loans, COALESCE(SUM(amount), 0) as total_amount FROM loans";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        $stats['total_loans'] = $row['total_loans'];
        $stats['total_amount'] = $row['total_amount'];

        // Loans by status - Include all possible statuses from the database ENUM
        $statuses = ['Pending', 'Approved', 'Rejected', 'Disbursed', 'Overdue', 'Paid'];
        foreach ($statuses as $status) {
            // Be resilient to accidental whitespace or case variations in stored status
            $sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount FROM loans WHERE UPPER(TRIM(status)) = UPPER(?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('s', $status);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $count = (int)($result['count'] ?? 0);
            $amount = (float)($result['amount'] ?? 0);
            
            // Store as array for consistency with view expectations
            $stats[strtolower($status) . '_loans'] = [
                'count' => $count,
                'amount' => $amount
            ];
            
            // Also store count as integer for backward compatibility
            $stats[strtolower($status) . '_count'] = $count;
        }

        // Duplicate counts with legacy-friendly keys used by views (already created above, but ensure compatibility)
        $stats['pending_count'] = is_array($stats['pending_loans'] ?? null) ? $stats['pending_loans']['count'] : (int)($stats['pending_loans'] ?? 0);
        
        // Approved count should include both Approved and Disbursed loans (Disbursed = Approved + Money Given Out)
        $approvedOnly = is_array($stats['approved_loans'] ?? null) ? $stats['approved_loans']['count'] : 0;
        $disbursedAlso = is_array($stats['disbursed_loans'] ?? null) ? $stats['disbursed_loans']['count'] : 0;
        $stats['approved_count'] = (int)($approvedOnly + $disbursedAlso);
        
        $stats['overdue_count'] = is_array($stats['overdue_loans'] ?? null) ? $stats['overdue_loans']['count'] : (int)($stats['overdue_loans'] ?? 0);

        // Approved amount should include both Approved and Disbursed loans
        $sql = "SELECT COALESCE(SUM(amount), 0) as approved_amount FROM loans WHERE UPPER(TRIM(status)) IN ('APPROVED', 'DISBURSED')";
        $resAmt = $this->conn->query($sql);
        if ($resAmt) {
            $rowAmt = $resAmt->fetch_assoc();
            $stats['approved_amount'] = (float)($rowAmt['approved_amount'] ?? 0);
        } else {
            $stats['approved_amount'] = 0.0;
        }

        // Monthly repayments (if monthly_payment column exists)
        if ($this->hasColumn('loans', 'monthly_payment')) {
            $sql = "SELECT COALESCE(SUM(monthly_payment), 0) as monthly_repayments FROM loans WHERE UPPER(TRIM(status)) IN ('APPROVED', 'OVERDUE')";
            $result = $this->conn->query($sql);
            $stats['monthly_repayments'] = $result->fetch_assoc()['monthly_repayments'];
        } else {
            $stats['monthly_repayments'] = 0;
        }

        // Recent loans (last 5)
        $join_clause = '';
        $select_member = '';
        if ($this->hasTable('members') && $this->hasColumn('members', 'first_name')) {
            $join_clause = "LEFT JOIN members m ON l.member_id = m.member_id";
            $select_member = ", CONCAT(m.first_name, ' ', m.last_name) as member_name";
        }

        $sql = "SELECT l.* $select_member FROM loans l $join_clause ORDER BY l.application_date DESC LIMIT 5";
        $result = $this->conn->query($sql);
        $recent_loans = [];
        while ($row = $result->fetch_assoc()) {
            $recent_loans[] = $row;
        }
        $stats['recent_loans'] = $recent_loans;

        return $stats;
    }

    // ========================================
    // Admin: Get loan types (if loan_types table exists)
    // ========================================
    public function getLoanTypes()
    {
        if (!$this->hasTable('loan_types')) {
            return [
                ['id' => 1, 'name' => 'Personal Loan'],
                ['id' => 2, 'name' => 'Business Loan'],
                ['id' => 3, 'name' => 'Emergency Loan'],
                ['id' => 4, 'name' => 'Education Loan']
            ];
        }

        $sql = "SELECT * FROM loan_types ORDER BY name";
        $result = $this->conn->query($sql);
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        return $types;
    }

    // ========================================
    // Admin: Get loan statuses
    // ========================================
    public function getLoanStatuses()
    {
        return [
            'Pending' => 'Pending',
            'Approved' => 'Approved',
            'Rejected' => 'Rejected',
            'Disbursed' => 'Disbursed',
            'Overdue' => 'Overdue',
            'Paid' => 'Paid',
            'Cancelled' => 'Cancelled'
        ];
    }

    // ========================================
    // Admin: Get single loan by ID with member info
    // ========================================
    public function getLoanById($id)
    {
        if (!$this->hasTable('loans')) {
            return null;
        }

        $join_clause = '';
        $select_member = '';
        if ($this->hasTable('members')) {
            $join_clause = "LEFT JOIN members m ON l.member_id = m.member_id";
            if ($this->hasColumn('members', 'first_name')) {
                $select_member = ", m.first_name, m.last_name, m.email, m.phone";
            }
        }

        $sql = "SELECT l.* $select_member FROM loans l $join_clause WHERE l.loan_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    // ========================================
    // Admin: Get loan repayments (if loan_repayments table exists)
    // ========================================
    public function getLoanRepayments($loan_id)
    {
        if (!$this->hasTable('loan_repayments')) {
            return [];
        }

        $sql = "SELECT * FROM loan_repayments WHERE loan_id = ? ORDER BY payment_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $repayments = [];
        while ($row = $result->fetch_assoc()) {
            $repayments[] = $row;
        }
        return $repayments;
    }

    // ========================================
    // Admin: Update loan application
    // ========================================
    public function updateLoanApplication($id, $data)
    {
        if (!$this->hasTable('loans')) {
            return false;
        }

        $updates = [];
        $params = [];
        $types = '';

        // Build dynamic update based on available columns
        $updateable_fields = [
            'amount' => 'd',
            'term_months' => 'i',
            'interest_rate' => 'd',
            'application_date' => 's',
            'purpose' => 's',
            'notes' => 's',
            'collateral' => 's',
            'guarantor' => 's',
            'monthly_payment' => 'd'
        ];

        foreach ($updateable_fields as $field => $type) {
            if (isset($data[$field]) && $this->hasColumn('loans', $field)) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE loans SET " . implode(', ', $updates) . " WHERE loan_id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    // ========================================
    // Admin: Delete loan
    // ========================================
    public function deleteLoan($id)
    {
        if (!$this->hasTable('loans')) {
            return false;
        }

        $sql = "DELETE FROM loans WHERE loan_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ========================================
    // Admin: Approve loan
    // ========================================
    public function approveLoan($id, $notes = '')
    {
        $id = (int)$id;
        $performedBy = $this->getActorName();
        try {
            // Delegate to modern service for business rules and logging
            $result = $this->loanService->approveLoan($id, $performedBy);
        } catch (\Throwable $e) {
            // Fallback to legacy status update when service is unavailable
            $result = $this->setLoanStatus($id, 'Approved', (string)$notes);
        }
        // Record audit entry (include optional notes)
        $this->auditLogger->log('loan_approved', 'loan', $id, [
            'performed_by' => $performedBy,
            'notes' => (string)$notes,
            'new_status' => 'Approved'
        ]);
        return $result;
    }

    // ========================================
    // Admin: Reject loan
    // ========================================
    public function rejectLoan($id, $notes = '')
    {
        $id = (int)$id;
        $performedBy = $this->getActorName();
        try {
            // Modern service expects a rejection reason; use notes as reason
            $result = $this->loanService->rejectLoan($id, $performedBy, (string)$notes !== '' ? (string)$notes : 'No reason provided');
        } catch (\Throwable $e) {
            // Fallback to legacy status update
            $result = $this->setLoanStatus($id, 'Rejected', (string)$notes);
        }
        $this->auditLogger->log('loan_rejected', 'loan', $id, [
            'performed_by' => $performedBy,
            'rejection_reason' => (string)$notes,
            'new_status' => 'Rejected'
        ]);
        return $result;
    }

    // ========================================
    // Admin: Disburse loan
    // ========================================
    public function disburseLoan($id, $notes = '')
    {
        $id = (int)$id;
        $performedBy = $this->getActorName();
        try {
            $result = $this->loanService->disburseLoan($id, $performedBy);
        } catch (\Throwable $e) {
            $result = $this->setLoanStatus($id, 'Disbursed', (string)$notes);
        }
        $this->auditLogger->log('loan_disbursed', 'loan', $id, [
            'performed_by' => $performedBy,
            'notes' => (string)$notes,
            'new_status' => 'Disbursed'
        ]);
        return $result;
    }

    // ========================================
    // Admin: Mark loan as paid
    // ========================================
    public function markLoanAsPaid($id)
    {
        return $this->setLoanStatus($id, 'Paid');
    }

    // ========================================
    // Admin: Add repayment (if loan_repayments table exists)
    // ========================================
    public function addRepayment($loan_id, $amount, $payment_date, $payment_method = 'Cash', $notes = '')
    {
        if (!$this->hasTable('loan_repayments')) {
            return false;
        }

        $sql = "INSERT INTO loan_repayments (loan_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('idsss', $loan_id, $amount, $payment_date, $payment_method, $notes);
        return $stmt->execute();
    }

    // ========================================
    // Admin: Get payment methods
    // ========================================
    public function getPaymentMethods()
    {
        return [
            'Cash' => 'Cash',
            'Bank Transfer' => 'Bank Transfer',
            'Check' => 'Check',
            'Mobile Money' => 'Mobile Money',
            'Online Payment' => 'Online Payment'
        ];
    }

    // ========================================
    // Admin: Get status badge class for UI
    // ========================================
    public function getStatusBadgeClass($status)
    {
        $classes = [
            'Pending' => 'bg-yellow-100 text-yellow-800',
            'Approved' => 'bg-green-100 text-green-800',
            'Rejected' => 'bg-red-100 text-red-800',
            'Disbursed' => 'bg-blue-100 text-blue-800',
            'Overdue' => 'bg-red-100 text-red-800',
            'Paid' => 'bg-green-100 text-green-800',
            'Cancelled' => 'bg-gray-100 text-gray-800'
        ];
        return $classes[$status] ?? 'bg-gray-100 text-gray-800';
    }

    // ========================================
    // Admin: Get loan guarantors (if loan_guarantors table exists)
    // ========================================
    public function getLoanGuarantors($loan_id)
    {
        if (!$this->hasTable('loan_guarantors')) {
            return [];
        }

        $sql = "SELECT * FROM loan_guarantors WHERE loan_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $guarantors = [];
        while ($row = $result->fetch_assoc()) {
            $guarantors[] = $row;
        }
        return $guarantors;
    }

    // ========================================
    // Admin: Get loan collateral (if loan_collateral table exists)
    // ========================================
    public function getLoanCollateral($loan_id)
    {
        if (!$this->hasTable('loan_collateral')) {
            return [];
        }

        $sql = "SELECT * FROM loan_collateral WHERE loan_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $collateral = [];
        while ($row = $result->fetch_assoc()) {
            $collateral[] = $row;
        }
        return $collateral;
    }

    // ========================================
    // Member/Admin: Generate payment schedule for a loan
    // ========================================
    public function getLoanPaymentSchedule($loan_id)
    {
        // Fetch the loan first
        $loan = $this->getLoanById($loan_id);
        if (!$loan) {
            return [];
        }

        // Gather inputs with sane defaults
        $principal = (float)($loan['amount'] ?? 0);
        $termMonths = (int)($loan['term_months'] ?? ($loan['term'] ?? 0));
        $annualRate = (float)($loan['interest_rate'] ?? 0);
        $monthlyPayment = isset($loan['monthly_payment']) ? (float)$loan['monthly_payment'] : $this->calculateMonthlyPayment($principal, $annualRate, $termMonths);

        // Start date: use application_date if available, else today
        $startDateStr = $loan['application_date'] ?? ($loan['created_at'] ?? date('Y-m-d'));
        $startTs = strtotime($startDateStr) ?: time();

        if ($principal <= 0 || $termMonths <= 0 || $monthlyPayment <= 0) {
            return [];
        }

        // Build schedule: interest calculated on simple amortization basis
        $schedule = [];
        $balance = $principal;
        $monthlyRate = ($annualRate / 100) / 12;

        for ($i = 1; $i <= $termMonths; $i++) {
            // Compute interest and principal for the installment
            $interest = $monthlyRate > 0 ? round($balance * $monthlyRate, 2) : 0.0;
            $principalPart = round($monthlyPayment - $interest, 2);
            if ($principalPart > $balance) {
                $principalPart = $balance;
                $monthlyPayment = $principalPart + $interest; // final payment adjustment
            }

            // Payment date: monthly increments from start
            $paymentDate = date('Y-m-d', strtotime("+{$i} month", $startTs));

            $schedule[] = [
                'installment' => $i,
                'payment_date' => $paymentDate,
                'amount' => $monthlyPayment,
                'principal' => $principalPart,
                'interest' => $interest,
                'starting_balance' => round($balance, 2),
                'ending_balance' => round(max(0, $balance - $principalPart), 2),
            ];

            $balance = max(0, $balance - $principalPart);
            if ($balance <= 0) {
                break;
            }
        }

        return $schedule;
    }

    // ========================================
    // Private utility methods
    // ========================================
    private function setLoanStatus($id, $status, $notes = '')
    {
        if (!$this->hasTable('loans')) {
            return false;
        }
        $updates = ['status = ?'];
        $params = [$status];
        $types = 's';

        // Persist notes if appropriate column exists
        $notes = (string)$notes;
        if ($notes !== '') {
            foreach (['status_notes', 'notes', 'approval_notes'] as $col) {
                if ($this->hasColumn('loans', $col)) {
                    $updates[] = "$col = ?";
                    $params[] = $notes;
                    $types .= 's';
                    break;
                }
            }
        }

        $sql = "UPDATE loans SET " . implode(', ', $updates) . " WHERE loan_id = ?";
        $params[] = (int)$id;
        $types .= 'i';

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) { return false; }
        if (!$stmt->bind_param($types, ...$params)) { return false; }
        $ok = $stmt->execute();

        // Central audit log for any status change
        $this->auditLogger->log('loan_status_change', 'loan', (int)$id, [
            'new_status' => $status,
            'notes' => $notes,
        ]);

        return $ok;
    }

    // ========================================
    // Private: derive actor name for audit context
    // ========================================
    private function getActorName(): string
    {
        if (!isset($_SESSION)) { @session_start(); }
        $u = $_SESSION['admin_user'] ?? $_SESSION['member_user'] ?? null;
        if (is_array($u)) {
            $name = ($u['username'] ?? '') ?: trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            if ($name !== '') { return $name; }
        }
        // Fallback to generic session keys
        $generic = ($_SESSION['username'] ?? '') ?: ($_SESSION['full_name'] ?? '');
        return $generic !== '' ? (string)$generic : 'System';
    }

    private function hasTable($table_name)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $table_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return isset($row['cnt']) ? ((int)$row['cnt'] > 0) : false;
    }

    private function hasColumn($table_name, $column_name)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $table_name, $column_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return isset($row['cnt']) ? ((int)$row['cnt'] > 0) : false;
    }
}