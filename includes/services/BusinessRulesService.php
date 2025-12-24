<?php

require_once __DIR__ . '/../config/SystemConfigService.php';

/**
 * CSIMS Business Rules Validation Service
 * 
 * Enforces business rules for loans, savings, and member operations.
 * Integrates with SystemConfigService for configurable rule parameters.
 * 
 * @package CSIMS\Services
 * @version 1.0.0
 */

class BusinessRulesService 
{
    private $pdo;
    private $config;

    public function __construct($pdo) 
    {
        $this->pdo = $pdo;
        $this->config = SystemConfigService::getInstance($pdo);
    }

    // ============ LOAN ELIGIBILITY VALIDATION ============

    /**
     * Validate if member is eligible for a loan
     */
    public function validateLoanEligibility(int $memberId, float $requestedAmount, int $loanTypeId): array 
    {
        $errors = [];
        $member = $this->getMemberDetails($memberId);

        if (!$member) {
            $errors[] = "Member not found";
            return $errors;
        }

        // Check membership duration
        $membershipErrors = $this->validateMembershipDuration($member);
        if (!empty($membershipErrors)) {
            $errors = array_merge($errors, $membershipErrors);
        }

        // Check member status
        $statusErrors = $this->validateMemberStatus($member);
        if (!empty($statusErrors)) {
            $errors = array_merge($errors, $statusErrors);
        }

        // Check mandatory savings compliance
        $savingsErrors = $this->validateMandatorySavingsCompliance($memberId);
        if (!empty($savingsErrors)) {
            $errors = array_merge($errors, $savingsErrors);
        }

        // Check loan amount limits
        $amountErrors = $this->validateLoanAmount($memberId, $requestedAmount);
        if (!empty($amountErrors)) {
            $errors = array_merge($errors, $amountErrors);
        }

        // Check existing loans
        $existingLoanErrors = $this->validateExistingLoans($memberId);
        if (!empty($existingLoanErrors)) {
            $errors = array_merge($errors, $existingLoanErrors);
        }

        // Check guarantor requirements
        $guarantorErrors = $this->validateGuarantorRequirement($memberId, $requestedAmount);
        if (!empty($guarantorErrors)) {
            $errors = array_merge($errors, $guarantorErrors);
        }

        return $errors;
    }

    /**
     * Check if member has been a member long enough
     */
    private function validateMembershipDuration(array $member): array 
    {
        $errors = [];
        $minMonths = $this->config->getMinMembershipMonths();
        
        $joinDate = new DateTime($member['date_joined']);
        $monthsDiff = $joinDate->diff(new DateTime())->m + ($joinDate->diff(new DateTime())->y * 12);
        
        if ($monthsDiff < $minMonths) {
            $errors[] = "Minimum membership period is {$minMonths} months. Member has been active for {$monthsDiff} months";
        }
        
        return $errors;
    }

    /**
     * Check member status (active, probation, etc.)
     */
    private function validateMemberStatus(array $member): array 
    {
        $errors = [];
        
        if ($member['status'] !== 'active') {
            $errors[] = "Member must have active status to be eligible for loans. Current status: {$member['status']}";
        }
        
        // Check if member is still in probation period
        $probationMonths = $this->config->get('MEMBERSHIP_PROBATION_MONTHS', 3);
        $joinDate = new DateTime($member['date_joined']);
        $monthsSinceJoining = $joinDate->diff(new DateTime())->m + ($joinDate->diff(new DateTime())->y * 12);
        
        if ($monthsSinceJoining < $probationMonths) {
            $errors[] = "Member is still in probation period. Probation lasts {$probationMonths} months";
        }
        
        return $errors;
    }

    /**
     * Check mandatory savings compliance
     */
    private function validateMandatorySavingsCompliance(int $memberId): array 
    {
        $errors = [];
        $minMandatory = $this->config->getMinMandatorySavings();
        
        // Get last 6 months of mandatory savings deposits meeting minimum
        $stmt = $this->pdo->prepare("\n            SELECT COUNT(DISTINCT DATE_FORMAT(st.transaction_date, '%Y-%m')) as compliant_months\n            FROM savings_transactions st\n            JOIN savings_accounts sa ON st.account_id = sa.id\n            WHERE st.member_id = ? \n              AND sa.account_type LIKE 'Mandatory%'\n              AND st.transaction_type = 'Deposit'\n              AND st.transaction_status = 'Completed'\n              AND st.amount >= ?\n              AND DATE(st.transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)\n        ");
        $stmt->execute([$memberId, $minMandatory]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
            if ($result['compliant_months'] < 6) {
                $errors[] = "Member must have 6 consecutive months of minimum mandatory savings (₦" . number_format($minMandatory, 2) . "). Found {$result['compliant_months']} compliant months";
            }
        
        return $errors;
    }

    /**
     * Validate loan amount against member's savings and system limits
     */
    private function validateLoanAmount(int $memberId, float $requestedAmount): array 
    {
        $errors = [];
        
        // Check maximum loan amount
        $maxLoanAmount = $this->config->getMaxLoanAmount();
        if ($requestedAmount > $maxLoanAmount) {
            $errors[] = "Requested amount (₦" . number_format($requestedAmount, 2) . ") exceeds maximum loan limit (₦" . number_format($maxLoanAmount, 2) . ")";
        }
        
        // Check loan-to-savings ratio
        $totalSavings = $this->getMemberTotalSavings($memberId);
        $loanToSavingsMultiplier = $this->config->getLoanToSavingsMultiplier();
        $maxLoanBasedOnSavings = $totalSavings * $loanToSavingsMultiplier;
        
        if ($requestedAmount > $maxLoanBasedOnSavings) {
            $errors[] = "Requested amount (₦" . number_format($requestedAmount, 2) . ") exceeds {$loanToSavingsMultiplier}x member savings (₦" . number_format($maxLoanBasedOnSavings, 2) . ")";
        }
        
        return $errors;
    }

    /**
     * Check existing loans and limits
     */
    private function validateExistingLoans(int $memberId): array 
    {
        $errors = [];
        $maxActiveLoans = $this->config->getMaxActiveLoansPer();
        
        // Count active loans
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as active_loans
            FROM loans 
            WHERE member_id = ? 
            AND status IN ('active', 'approved', 'disbursed')
        ");
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['active_loans'] >= $maxActiveLoans) {
            $errors[] = "Member has reached maximum active loans limit ({$maxActiveLoans})";
        }
        
        // Check for defaulted loans
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as defaulted_loans
            FROM loans 
            WHERE member_id = ? 
            AND status = 'defaulted'
        ");
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['defaulted_loans'] > 0) {
            $errors[] = "Member has {$result['defaulted_loans']} defaulted loan(s). Must clear defaults before new loan";
        }
        
        return $errors;
    }

    /**
     * Validate guarantor requirements
     */
    private function validateGuarantorRequirement(int $memberId, float $requestedAmount): array 
    {
        $errors = [];
        $guarantorThreshold = $this->config->getGuarantorRequirementThreshold();
        
        if ($requestedAmount >= $guarantorThreshold) {
            $minGuarantors = $this->config->getMinGuarantorsRequired();
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT guarantor_member_id) as guarantor_count
                FROM loan_guarantors lg
                INNER JOIN members m ON lg.guarantor_member_id = m.id
                WHERE lg.member_id = ?
                AND m.status = 'active'
                AND lg.status = 'active'
            ");
            $stmt->execute([$memberId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['guarantor_count'] < $minGuarantors) {
                $errors[] = "Loans ≥ ₦" . number_format($guarantorThreshold, 2) . " require {$minGuarantors} active guarantors. Found {$result['guarantor_count']}";
            }
        }
        
        return $errors;
    }

    // ============ SAVINGS VALIDATION ============

    /**
     * Validate mandatory savings deposit
     */
    public function validateMandatorySavingsDeposit(int $memberId, float $amount): array 
    {
        $errors = [];
        
        $minMandatory = $this->config->getMinMandatorySavings();
        $maxMandatory = $this->config->getMaxMandatorySavings();
        
        if ($amount < $minMandatory) {
            $errors[] = "Mandatory deposit must be at least ₦" . number_format($minMandatory, 2);
        }
        
        if ($amount > $maxMandatory) {
            $errors[] = "Mandatory deposit cannot exceed ₦" . number_format($maxMandatory, 2);
        }
        
        return $errors;
    }

    /**
     * Validate voluntary savings withdrawal
     */
    public function validateSavingsWithdrawal(int $memberId, float $requestedAmount): array 
    {
        $errors = [];
        
        $voluntarySavings = $this->getMemberVoluntarySavings($memberId);
        $maxWithdrawalPercent = $this->config->getWithdrawalMaxPercentage();
        $maxWithdrawalAmount = $voluntarySavings * ($maxWithdrawalPercent / 100);
        
        if ($requestedAmount > $maxWithdrawalAmount) {
            $errors[] = "Withdrawal amount (₦" . number_format($requestedAmount, 2) . ") exceeds {$maxWithdrawalPercent}% of voluntary savings (₦" . number_format($maxWithdrawalAmount, 2) . ")";
        }
        
        $minTransaction = $this->config->get('MINIMUM_TRANSACTION_AMOUNT', 100.00);
        if ($requestedAmount < $minTransaction) {
            $errors[] = "Withdrawal amount must be at least ₦" . number_format($minTransaction, 2);
        }
        
        return $errors;
    }

    // ============ PENALTY AND INTEREST CALCULATIONS ============

    /**
     * Calculate loan penalty for overdue payment
     */
    public function calculateLoanPenalty(int $loanId, DateTime $dueDate): float 
    {
        $penalty = 0.0;
        
        $now = new DateTime();
        $gracePeriod = $this->config->getDefaultGracePeriod();
        $penaltyRate = $this->config->getLoanPenaltyRate();
        
        // Add grace period to due date
        $graceEndDate = clone $dueDate;
        $graceEndDate->modify("+{$gracePeriod} days");
        
        if ($now > $graceEndDate) {
            // Get loan details
            $stmt = $this->pdo->prepare("SELECT principal_amount, monthly_payment FROM loans WHERE id = ?");
            $stmt->execute([$loanId]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($loan) {
                $monthsOverdue = $graceEndDate->diff($now)->m + ($graceEndDate->diff($now)->y * 12);
                if ($graceEndDate->diff($now)->d > 15) {
                    $monthsOverdue++; // Round up if over 15 days
                }
                
                $penalty = $loan['monthly_payment'] * ($penaltyRate / 100) * $monthsOverdue;
            }
        }
        
        return $penalty;
    }

    /**
     * Calculate interest for savings
     */
    public function calculateSavingsInterest(int $memberId, float $savingsBalance): float 
    {
        $annualRate = $this->config->getSavingsInterestRate();
        $compoundFreq = $this->config->get('SAVINGS_INTEREST_COMPOUND_FREQ', 'monthly');
        
        $monthlyRate = $annualRate / 12 / 100;
        
        switch ($compoundFreq) {
            case 'monthly':
                return $savingsBalance * $monthlyRate;
            case 'quarterly':
                return $savingsBalance * ($annualRate / 4 / 100);
            case 'annually':
                return $savingsBalance * ($annualRate / 100);
            default:
                return $savingsBalance * $monthlyRate;
        }
    }

    // ============ HELPER METHODS ============

    /**
     * Detect savings schema columns for PDO environments
     */
    private function getSavingsSchemaPDO(): array
    {
        $schema = [
            'transactions' => [
                'status' => 'transaction_status',
                'type' => 'transaction_type',
                'date' => 'transaction_date',
                'account_id' => 'account_id',
                'member_id' => 'member_id',
            ],
            'accounts' => [
                'account_id' => 'id',
                'member_id' => 'member_id'
            ]
        ];

        try {
            // Current database name
            $dbStmt = $this->pdo->query("SELECT DATABASE()");
            $dbName = $dbStmt ? $dbStmt->fetchColumn() : null;
            if (!$dbName) {
                return $schema;
            }

            $checkCol = function(string $table, string $col) use ($dbName) {
                $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $stmt->execute([$dbName, $table, $col]);
                return (bool)$stmt->fetchColumn();
            };

            // Transactions table
            $schema['transactions']['status'] = $checkCol('savings_transactions','transaction_status') ? 'transaction_status'
                : ($checkCol('savings_transactions','status') ? 'status' : 'transaction_status');
            $schema['transactions']['type'] = $checkCol('savings_transactions','transaction_type') ? 'transaction_type'
                : ($checkCol('savings_transactions','type') ? 'type' : 'transaction_type');
            $schema['transactions']['date'] = $checkCol('savings_transactions','transaction_date') ? 'transaction_date'
                : ($checkCol('savings_transactions','date') ? 'date'
                    : ($checkCol('savings_transactions','created_at') ? 'created_at' : 'transaction_date'));
            $schema['transactions']['account_id'] = $checkCol('savings_transactions','account_id') ? 'account_id'
                : ($checkCol('savings_transactions','savings_account_id') ? 'savings_account_id' : 'account_id');
            $schema['transactions']['member_id'] = $checkCol('savings_transactions','member_id') ? 'member_id' : 'member_id';

            // Accounts table
            $schema['accounts']['account_id'] = $checkCol('savings_accounts','account_id') ? 'account_id'
                : ($checkCol('savings_accounts','id') ? 'id' : 'account_id');
            $schema['accounts']['member_id'] = $checkCol('savings_accounts','member_id') ? 'member_id' : 'member_id';
        } catch (\Throwable $t) {
            // Leave defaults
        }

        return $schema;
    }

    /**
     * Detect loan-related schema columns for PDO environments
     */
    private function getLoanSchemaPDO(): array
    {
        $schema = [
            'loans' => [
                'pk' => 'id',
                'status' => 'status'
            ],
            'schedule' => [
                'status' => 'status',
                'payment_date' => 'payment_date',
                'due_date' => 'due_date'
            ]
        ];

        try {
            $dbStmt = $this->pdo->query("SELECT DATABASE()");
            $dbName = $dbStmt ? $dbStmt->fetchColumn() : null;
            if (!$dbName) { return $schema; }

            $hasCol = function(string $table, string $col) use ($dbName) {
                $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $stmt->execute([$dbName, $table, $col]);
                return (bool)$stmt->fetchColumn();
            };

            // Loans primary key
            $schema['loans']['pk'] = $hasCol('loans','loan_id') ? 'loan_id'
                : ($hasCol('loans','id') ? 'id' : 'id');

            // Loans status column
            $schema['loans']['status'] = $hasCol('loans','status') ? 'status' : null;

            // Schedule table columns
            $schema['schedule']['status'] = $hasCol('loan_payment_schedule','status') ? 'status'
                : ($hasCol('loan_payment_schedule','payment_status') ? 'payment_status' : null);
            $schema['schedule']['payment_date'] = $hasCol('loan_payment_schedule','payment_date') ? 'payment_date'
                : ($hasCol('loan_payment_schedule','date_paid') ? 'date_paid'
                : ($hasCol('loan_payment_schedule','paid_date') ? 'paid_date' : null));
            $schema['schedule']['due_date'] = $hasCol('loan_payment_schedule','due_date') ? 'due_date' : 'due_date';
        } catch (\Throwable $t) { /* keep defaults */ }

        return $schema;
    }

    /**
     * Get member details
     */
    private function getMemberDetails(int $memberId): ?array 
    {
        $stmt = $this->pdo->prepare("
            SELECT id, member_number, first_name, last_name, email, phone, 
                   status, date_joined, created_at
            FROM members 
            WHERE id = ?
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get member's total savings (mandatory + voluntary)
     */
    private function getMemberTotalSavings(int $memberId): float 
    {
        $schema = $this->getSavingsSchemaPDO();
        $typeCol = $schema['transactions']['type'];
        $statusCol = $schema['transactions']['status'];
        $memberIdCol = $schema['transactions']['member_id'];

        $sql = "SELECT COALESCE(SUM(st.amount), 0) as total_savings
                FROM savings_transactions st
                WHERE st.$memberIdCol = ?
                  AND UPPER(st.$typeCol) = 'DEPOSIT'
                  AND UPPER(st.$statusCol) = 'COMPLETED'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)$result['total_savings'];
    }

    /**
     * Get member's voluntary savings only
     */
    private function getMemberVoluntarySavings(int $memberId): float 
    {
        $schema = $this->getSavingsSchemaPDO();
        $typeCol = $schema['transactions']['type'];
        $statusCol = $schema['transactions']['status'];
        $memberIdCol = $schema['transactions']['member_id'];
        $txAccountIdCol = $schema['transactions']['account_id'];
        $accIdCol = $schema['accounts']['account_id'];

        $sql = "SELECT COALESCE(SUM(st.amount), 0) as voluntary_savings
                FROM savings_transactions st
                JOIN savings_accounts sa ON st.$txAccountIdCol = sa.$accIdCol
                WHERE st.$memberIdCol = ?
                  AND sa.account_type LIKE 'Voluntary%'
                  AND UPPER(st.$typeCol) = 'DEPOSIT'
                  AND UPPER(st.$statusCol) = 'COMPLETED'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)$result['voluntary_savings'];
    }

    /**
     * Get member's mandatory savings only
     */
    private function getMemberMandatorySavings(int $memberId): float 
    {
        $schema = $this->getSavingsSchemaPDO();
        $typeCol = $schema['transactions']['type'];
        $statusCol = $schema['transactions']['status'];
        $memberIdCol = $schema['transactions']['member_id'];
        $txAccountIdCol = $schema['transactions']['account_id'];
        $accIdCol = $schema['accounts']['account_id'];

        $sql = "SELECT COALESCE(SUM(st.amount), 0) as mandatory_savings
                FROM savings_transactions st
                JOIN savings_accounts sa ON st.$txAccountIdCol = sa.$accIdCol
                WHERE st.$memberIdCol = ?
                  AND sa.account_type LIKE 'Mandatory%'
                  AND UPPER(st.$typeCol) = 'DEPOSIT'
                  AND UPPER(st.$statusCol) = 'COMPLETED'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)$result['mandatory_savings'];
    }

    /**
     * Get savings consistency score
     */
    private function getSavingsConsistencyScore(int $memberId): int 
    {
        $schema = $this->getSavingsSchemaPDO();
        $typeCol = $schema['transactions']['type'];
        $statusCol = $schema['transactions']['status'];
        $dateCol = $schema['transactions']['date'];
        $memberIdCol = $schema['transactions']['member_id'];
        $txAccountIdCol = $schema['transactions']['account_id'];
        $accIdCol = $schema['accounts']['account_id'];

        $sql = "SELECT COUNT(DISTINCT DATE_FORMAT(st.$dateCol, '%Y-%m')) as consistent_months
                FROM savings_transactions st
                JOIN savings_accounts sa ON st.$txAccountIdCol = sa.$accIdCol
                WHERE st.$memberIdCol = ?
                  AND sa.account_type LIKE 'Mandatory%'
                  AND UPPER(st.$typeCol) = 'DEPOSIT'
                  AND UPPER(st.$statusCol) = 'COMPLETED'
                  AND st.amount >= ?
                  AND st.$dateCol >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $stmt = $this->pdo->prepare($sql);
        $minMandatory = $this->config->getMinMandatorySavings();
        $stmt->execute([$memberId, $minMandatory]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return min(100, ($result['consistent_months'] ?? 0) * 8);
    }

    /**
     * Aggregate savings and loan info for dashboards and applications
     */
    public function getMemberSavingsData(int $memberId): array 
    {
        $totalSavings = $this->getMemberTotalSavings($memberId);
        $mandatorySavings = $this->getMemberMandatorySavings($memberId);
        $voluntarySavings = $this->getMemberVoluntarySavings($memberId);

        // Count active loans (active, approved, or disbursed)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS active_loans FROM loans WHERE member_id = ? AND status IN ('active','approved','disbursed')");
        $stmt->execute([$memberId]);
        $activeLoansRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $activeLoans = isset($activeLoansRow['active_loans']) ? (int)$activeLoansRow['active_loans'] : 0;

        // Contribution months in the last 12 months (based on completed savings deposits)
        $schema = $this->getSavingsSchemaPDO();
        $typeCol = $schema['transactions']['type'];
        $statusCol = $schema['transactions']['status'];
        $dateCol = $schema['transactions']['date'];
        $memberIdCol = $schema['transactions']['member_id'];

        $monthsSql = "SELECT COUNT(DISTINCT DATE_FORMAT(st.$dateCol, '%Y-%m')) AS months
                      FROM savings_transactions st
                      WHERE st.$memberIdCol = ?
                        AND UPPER(st.$typeCol) = 'DEPOSIT'
                        AND UPPER(st.$statusCol) = 'COMPLETED'
                        AND st.$dateCol >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $stmt = $this->pdo->prepare($monthsSql);
        $stmt->execute([$memberId]);
        $monthsRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $savingsMonths = isset($monthsRow['months']) ? (int)$monthsRow['months'] : 0;

        // Last deposit date (based on latest completed savings deposit)
        $lastDateSql = "SELECT MAX(st.$dateCol) AS last_date
                        FROM savings_transactions st
                        WHERE st.$memberIdCol = ?
                          AND UPPER(st.$typeCol) = 'DEPOSIT'
                          AND UPPER(st.$statusCol) = 'COMPLETED'";
        $stmt = $this->pdo->prepare($lastDateSql);
        $stmt->execute([$memberId]);
        $lastDateRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastDepositDate = $lastDateRow && isset($lastDateRow['last_date']) ? $lastDateRow['last_date'] : null;

        return [
            'total_savings' => (float)$totalSavings,
            'mandatory_savings' => (float)$mandatorySavings,
            'voluntary_savings' => (float)$voluntarySavings,
            'active_loans' => $activeLoans,
            'savings_months' => $savingsMonths,
            'last_deposit_date' => $lastDepositDate,
        ];
    }

    /**
     * Check if member has overdue loans
     */
    public function hasOverdueLoans(int $memberId): bool 
    {
        $loanSchema = $this->getLoanSchemaPDO();
        $loanPk = $loanSchema['loans']['pk'] ?? 'id';
        $loanStatusCol = $loanSchema['loans']['status'] ?? null;
        $scheduleStatusCol = $loanSchema['schedule']['status'] ?? null;
        $dueCol = $loanSchema['schedule']['due_date'] ?? 'due_date';

        $sql = "SELECT COUNT(*) as overdue_count\n                FROM loan_payment_schedule lps\n                INNER JOIN loans l ON lps.loan_id = l.$loanPk\n                WHERE l.member_id = ?\n                  AND lps.$dueCol < CURDATE()";
        if ($scheduleStatusCol) { $sql .= "\n                  AND lps.$scheduleStatusCol = 'pending'"; }
        if ($loanStatusCol) { $sql .= "\n                  AND l.$loanStatusCol = 'active'"; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$memberId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['overdue_count']) ? ($row['overdue_count'] > 0) : false;
    }

    /**
     * Get member's credit score based on payment history and savings consistency
     */
    public function getMemberCreditScore(int $memberId): array 
    {
        $loanSchema = $this->getLoanSchemaPDO();
        $loanPk = $loanSchema['loans']['pk'] ?? 'id';
        $scheduleStatusCol = $loanSchema['schedule']['status'] ?? null;
        $paymentDateCol = $loanSchema['schedule']['payment_date'] ?? 'payment_date';
        $dueDateCol = $loanSchema['schedule']['due_date'] ?? 'due_date';

        $select = "SELECT \n                COUNT(*) as total_payments,\n                SUM(CASE WHEN $paymentDateCol <= $dueDateCol THEN 1 ELSE 0 END) as on_time_payments,\n                AVG(DATEDIFF($paymentDateCol, $dueDateCol)) as avg_delay_days\n            FROM loan_payment_schedule lps\n            INNER JOIN loans l ON lps.loan_id = l.$loanPk\n            WHERE l.member_id = ?";
        if ($scheduleStatusCol) { $select .= "\n              AND lps.$scheduleStatusCol = 'paid'"; }
        $stmt = $this->pdo->prepare($select);
        $stmt->execute([$memberId]);
        $paymentHistory = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_payments' => 0,
            'on_time_payments' => 0,
            'avg_delay_days' => null
        ];
        
        $score = 500; // Base score
        
        if (($paymentHistory['total_payments'] ?? 0) > 0) {
            $onTimePercent = ($paymentHistory['on_time_payments'] / $paymentHistory['total_payments']) * 100;
            $score += ($onTimePercent - 50) * 4; // Add/subtract based on on-time percentage
        }
        
        // Adjust for savings consistency
        $savingsConsistency = $this->getSavingsConsistencyScore($memberId);
        $score += $savingsConsistency;
        
        // Cap score between 300-850
        $score = max(300, min(850, $score));
        
        return [
            'score' => (int)$score,
            'rating' => $this->getCreditRating($score),
            'total_payments' => (int)($paymentHistory['total_payments'] ?? 0),
            'on_time_percentage' => (($paymentHistory['total_payments'] ?? 0) > 0)
                ? round(($paymentHistory['on_time_payments'] / $paymentHistory['total_payments']) * 100, 1)
                : 0
        ];
    }

    /**
     * Get credit rating from score
     */
    private function getCreditRating(float $score): string 
    {
        if ($score >= 750) return 'Excellent';
        if ($score >= 700) return 'Good';
        if ($score >= 650) return 'Fair';
        if ($score >= 600) return 'Poor';
        return 'Very Poor';
    }
}