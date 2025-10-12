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
        
        // Get last 6 months of mandatory contributions
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as compliant_months
            FROM contributions 
            WHERE member_id = ? 
            AND contribution_type = 'mandatory'
            AND amount >= ?
            AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ");
        $stmt->execute([$memberId, $minMandatory]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['compliant_months'] < 6) {
            $errors[] = "Member must have 6 consecutive months of minimum mandatory contributions (₦" . number_format($minMandatory, 2) . "). Found {$result['compliant_months']} compliant months";
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
     * Validate mandatory savings contribution
     */
    public function validateMandatorySavingsContribution(int $memberId, float $amount): array 
    {
        $errors = [];
        
        $minMandatory = $this->config->getMinMandatorySavings();
        $maxMandatory = $this->config->getMaxMandatorySavings();
        
        if ($amount < $minMandatory) {
            $errors[] = "Mandatory contribution must be at least ₦" . number_format($minMandatory, 2);
        }
        
        if ($amount > $maxMandatory) {
            $errors[] = "Mandatory contribution cannot exceed ₦" . number_format($maxMandatory, 2);
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
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_savings
            FROM contributions 
            WHERE member_id = ? 
            AND status = 'completed'
        ");
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)$result['total_savings'];
    }

    /**
     * Get member's voluntary savings only
     */
    private function getMemberVoluntarySavings(int $memberId): float 
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as voluntary_savings
            FROM contributions 
            WHERE member_id = ? 
            AND contribution_type = 'voluntary'
            AND status = 'completed'
        ");
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)$result['voluntary_savings'];
    }

    /**
     * Get member's mandatory savings only
     */
    private function getMemberMandatorySavings(int $memberId): float 
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as mandatory_savings
            FROM contributions 
            WHERE member_id = ? 
            AND contribution_type = 'mandatory'
            AND status = 'completed'
        ");
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)$result['mandatory_savings'];
    }

    /**
     * Check if member has overdue loans
     */
    public function hasOverdueLoans(int $memberId): bool 
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as overdue_count
            FROM loan_payment_schedule lps
            INNER JOIN loans l ON lps.loan_id = l.id
            WHERE l.member_id = ?
            AND lps.due_date < CURDATE()
            AND lps.status = 'pending'
            AND l.status = 'active'
        ");
        $stmt->execute([$memberId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['overdue_count'] > 0;
    }

    /**
     * Get member's credit score based on payment history
     */
    public function getMemberCreditScore(int $memberId): array 
    {
        // Calculate based on payment history, savings consistency, etc.
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_date <= due_date THEN 1 ELSE 0 END) as on_time_payments,
                AVG(DATEDIFF(payment_date, due_date)) as avg_delay_days
            FROM loan_payment_schedule lps
            INNER JOIN loans l ON lps.loan_id = l.id
            WHERE l.member_id = ?
            AND lps.status = 'paid'
        ");
        $stmt->execute([$memberId]);
        $paymentHistory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $score = 500; // Base score
        
        if ($paymentHistory['total_payments'] > 0) {
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
            'total_payments' => $paymentHistory['total_payments'],
            'on_time_percentage' => $paymentHistory['total_payments'] > 0 
                ? round(($paymentHistory['on_time_payments'] / $paymentHistory['total_payments']) * 100, 1) 
                : 0
        ];
    }

    /**
     * Get savings consistency score
     */
    private function getSavingsConsistencyScore(int $memberId): int 
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT DATE_FORMAT(created_at, '%Y-%m')) as consistent_months
            FROM contributions 
            WHERE member_id = ? 
            AND contribution_type = 'mandatory'
            AND amount >= ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        ");
        $minMandatory = $this->config->getMinMandatorySavings();
        $stmt->execute([$memberId, $minMandatory]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return min(100, $result['consistent_months'] * 8); // Up to 100 points for consistency
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