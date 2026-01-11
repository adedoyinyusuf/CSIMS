<?php

namespace CSIMS\Services;

use CSIMS\Models\Loan;
use CSIMS\Models\LoanGuarantor;
use CSIMS\Repositories\LoanRepository;
use CSIMS\Repositories\LoanGuarantorRepository;
use CSIMS\Repositories\MemberRepository;
use CSIMS\Services\SecurityService;
use CSIMS\Services\AuditLogger;
use CSIMS\DTOs\ValidationResult;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\CSIMSException;

/**
 * Loan Service
 * 
 * Handles loan-related business logic and operations
 */
class LoanService
{
    private LoanRepository $loanRepository;
    private MemberRepository $memberRepository;
    private SecurityService $securityService;
    private ?LoanGuarantorRepository $guarantorRepository;
    private AuditLogger $auditLogger;
    
    public function __construct(
        LoanRepository $loanRepository,
        MemberRepository $memberRepository,
        SecurityService $securityService,
        ?LoanGuarantorRepository $guarantorRepository = null
    ) {
        $this->loanRepository = $loanRepository;
        $this->memberRepository = $memberRepository;
        $this->securityService = $securityService;
        $this->guarantorRepository = $guarantorRepository;
        $this->auditLogger = new AuditLogger();
    }
    
    /**
     * Create new loan with validation
     * 
     * @param array $data
     * @return Loan
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function createLoan(array $data, array $guarantorsData = []): Loan
    {
        // Sanitize input data
        $data = $this->securityService->sanitizeArray($data);
        
        // Validate member exists
        if (!isset($data['member_id']) || !$this->memberRepository->find($data['member_id'])) {
            throw new ValidationException('Invalid member ID');
        }
        
        // Create loan instance
        $loan = Loan::fromArray($data);
        
        // Validate loan data
        $validation = $loan->validate();
        if (!$validation->isValid()) {
            throw new ValidationException($validation->getFirstError());
        }
        
        // Set default values
        $loan->setApplicationDate($data['application_date'] ?? date('Y-m-d'));
        $loan->setStatus($data['status'] ?? 'Pending');
        
        // Auto-calculate monthly payment if not provided
        if (!$loan->getMonthlyPayment() || $loan->getMonthlyPayment() <= 0) {
            $loan->autoCalculatePayment();
        }
        
        // Validate business rules
        $this->validateLoanBusinessRules($loan);
        
        // Save loan
        $createdLoan = $this->loanRepository->create($loan);

        // If guarantors were provided, add them and dispatch sign-off requests
        if (!empty($guarantorsData) && $this->guarantorRepository) {
            $addedGuarantors = $this->addGuarantorsToLoan($createdLoan->getId(), $guarantorsData);

            // Dispatch sign-off requests via legacy helper (email with tokenized link)
            try {
                // Bridge to legacy includes service for email dispatch and sign-off token
                require_once __DIR__ . '/../../includes/db.php';
                require_once __DIR__ . '/../../includes/services/guarantor_signoff_service.php';
                $db = \Database::getInstance();
                $conn = $db->getConnection();
                ensure_guarantor_signoff_tables($conn);

                foreach ($addedGuarantors as $g) {
                    // Re-fetch enriched guarantor to access email/name fields
                    $enriched = $this->guarantorRepository->find($g->getId());
                    $guarantorEmail = method_exists($enriched, 'toArray') ? ($enriched->toArray()['guarantor_email'] ?? '') : '';
                    $guarantorNameParts = [];
                    $arr = method_exists($enriched, 'toArray') ? $enriched->toArray() : [];
                    if (!empty($arr['guarantor_first_name'])) { $guarantorNameParts[] = $arr['guarantor_first_name']; }
                    if (!empty($arr['guarantor_last_name'])) { $guarantorNameParts[] = $arr['guarantor_last_name']; }
                    $guarantorName = implode(' ', $guarantorNameParts) ?: 'Guarantor';

                    // Create request and send email link
                    $req = create_signoff_request($conn, $createdLoan->getId(), 'member', $g->getId(), $arr['guarantor_member_id'] ?? null, $guarantorEmail ?: null, $guarantorName ?: null);
                    if (!empty($guarantorEmail)) {
                        @send_signoff_email($guarantorEmail, $guarantorName, $req['token'], $createdLoan->getId());
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal: log and continue
                error_log('Guarantor sign-off dispatch failed: ' . $e->getMessage());
            }
        }

        return $createdLoan;
    }
    
    /**
     * Update existing loan
     * 
     * @param int $id
     * @param array $data
     * @return Loan
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function updateLoan(int $id, array $data): Loan
    {
        // Find existing loan
        $loan = $this->loanRepository->find($id);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        // Sanitize input data
        $data = $this->securityService->sanitizeArray($data);
        
        // Update loan properties
        $loan->fromArray($data);
        
        // Validate updated loan
        $validation = $loan->validate();
        if (!$validation->isValid()) {
            throw new ValidationException($validation->getFirstError());
        }
        
        // Recalculate payment if relevant fields changed
        if (isset($data['amount']) || isset($data['interest_rate']) || isset($data['term_months'])) {
            $loan->autoCalculatePayment();
        }
        
        // Validate business rules
        $this->validateLoanBusinessRules($loan);
        
        // Save updated loan
        return $this->loanRepository->update($loan);
    }
    
    /**
     * Get loan by ID with member information
     * 
     * @param int $id
     * @return Loan|null
     * @throws DatabaseException
     */
    public function getLoan(int $id): ?Loan
    {
        return $this->loanRepository->find($id);
    }
    
    /**
     * Get loans with filtering and pagination
     * 
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @param array $orderBy
     * @return array
     * @throws DatabaseException
     */
    public function getLoans(array $filters = [], int $page = 1, int $limit = 10, array $orderBy = ['created_at' => 'DESC']): array
    {
        // Sanitize filters
        $filters = $this->securityService->sanitizeArray($filters);
        
        return $this->loanRepository->getPaginated($page, $limit, $filters, $orderBy);
    }
    
    /**
     * Get loans by member
     * 
     * @param int $memberId
     * @return array
     * @throws DatabaseException
     */
    public function getLoansByMember(int $memberId): array
    {
        return $this->loanRepository->findByMember($memberId);
    }
    
    /**
     * Get active loans
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getActiveLoans(): array
    {
        return $this->loanRepository->findActive();
    }
    
    /**
     * Get overdue loans
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getOverdueLoans(): array
    {
        return $this->loanRepository->findOverdue();
    }
    
    /**
     * Get loan statistics
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getLoanStatistics(): array
    {
        return $this->loanRepository->getStatistics();
    }
    
    /**
     * Approve loan
     * 
     * @param int $loanId
     * @param string $approvedBy
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function approveLoan(int $loanId, string $approvedBy): bool
    {
        $loan = $this->loanRepository->find($loanId);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        if ($loan->getStatus() !== 'Pending') {
            throw new ValidationException('Can only approve pending loans');
        }
        
        $result = $this->loanRepository->updateStatus($loanId, 'Approved', [
            'approved_by' => $this->securityService->sanitizeString($approvedBy),
            'approval_date' => date('Y-m-d')
        ]);
        if ($result) {
            $this->auditLogger->log('loan_approved', 'loan', $loanId, [
                'approved_by' => $approvedBy,
                'previous_status' => 'Pending',
                'new_status' => 'Approved',
                'approval_date' => date('Y-m-d')
            ]);
        }
        return $result;
    }
    
    /**
     * Reject loan
     * 
     * @param int $loanId
     * @param string $rejectedBy
     * @param string $rejectionReason
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function rejectLoan(int $loanId, string $rejectedBy, string $rejectionReason): bool
    {
        $loan = $this->loanRepository->find($loanId);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        if ($loan->getStatus() !== 'Pending') {
            throw new ValidationException('Can only reject pending loans');
        }
        
        $result = $this->loanRepository->updateStatus($loanId, 'Rejected', [
            'rejected_by' => $this->securityService->sanitizeString($rejectedBy),
            'rejection_reason' => $this->securityService->sanitizeString($rejectionReason),
            'rejection_date' => date('Y-m-d')
        ]);
        if ($result) {
            $this->auditLogger->log('loan_rejected', 'loan', $loanId, [
                'rejected_by' => $rejectedBy,
                'previous_status' => 'Pending',
                'new_status' => 'Rejected',
                'rejection_date' => date('Y-m-d'),
                'rejection_reason' => $rejectionReason
            ]);
        }
        return $result;
    }
    
    /**
     * Disburse loan
     * 
     * @param int $loanId
     * @param string $disbursedBy
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function disburseLoan(int $loanId, string $disbursedBy): bool
    {
        $loan = $this->loanRepository->find($loanId);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        if ($loan->getStatus() !== 'Approved') {
            throw new ValidationException('Can only disburse approved loans');
        }
        
        $nextPaymentDate = date('Y-m-d', strtotime('+1 month'));
        
        $result = $this->loanRepository->updateStatus($loanId, 'Disbursed', [
            'disbursed_by' => $this->securityService->sanitizeString($disbursedBy),
            'disbursement_date' => date('Y-m-d'),
            'next_payment_date' => $nextPaymentDate,
            'status' => 'Active' // Active means disbursed and ongoing
        ]);
        if ($result) {
            $this->auditLogger->log('loan_disbursed', 'loan', $loanId, [
                'disbursed_by' => $disbursedBy,
                'previous_status' => 'Approved',
                'new_status' => 'Active',
                'disbursement_date' => date('Y-m-d'),
                'next_payment_date' => $nextPaymentDate
            ]);
        }
        return $result;
    }
    
    /**
     * Process loan payment
     * 
     * @param int $loanId
     * @param float $paymentAmount
     * @param string $paymentMethod
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function processPayment(int $loanId, float $paymentAmount, string $paymentMethod): bool
    {
        $loan = $this->loanRepository->find($loanId);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        if (!in_array($loan->getStatus(), ['Active', 'Disbursed'])) {
            throw new ValidationException('Can only process payments for active loans');
        }
        
        if ($paymentAmount <= 0) {
            throw new ValidationException('Payment amount must be greater than zero');
        }
        
        // Calculate new balance (use remaining balance from model)
        $newBalance = max(0, $loan->getRemainingBalance() - $paymentAmount);
        
        // Update loan
        $updateData = [
            'remaining_balance' => $newBalance,
            'last_payment_date' => date('Y-m-d'),
            'last_payment_amount' => $paymentAmount
        ];
        
        // If loan is fully paid
        if ($newBalance <= 0) {
            $updateData['status'] = 'Paid';
            $updateData['next_payment_date'] = null;
        } else {
            // Calculate next payment date
            $updateData['next_payment_date'] = date('Y-m-d', strtotime($loan->getNextPaymentDate() . ' +1 month'));
        }
        
        $loan->fromArray($updateData);
        $result = $this->loanRepository->update($loan) !== null;
        if ($result) {
            $this->auditLogger->log('loan_payment_processed', 'loan', $loanId, [
                'payment_amount' => $paymentAmount,
                'payment_method' => $paymentMethod,
                'new_balance' => $newBalance,
                'status' => $updateData['status'] ?? $loan->getStatus(),
                'last_payment_date' => $updateData['last_payment_date']
            ]);
        }
        return $result;
    }
    
    /**
     * Delete loan (soft delete by marking as inactive)
     * 
     * @param int $id
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function deleteLoan(int $id): bool
    {
        $loan = $this->loanRepository->find($id);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        // Only allow deletion of pending or rejected loans
        if (!in_array($loan->getStatus(), ['Pending', 'Rejected'])) {
            throw new ValidationException('Can only delete pending or rejected loans');
        }
        
        return $this->loanRepository->delete($id);
    }
    
    /**
     * Calculate loan payment schedule
     * 
     * @param int $loanId
     * @return array
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function calculatePaymentSchedule(int $loanId): array
    {
        $loan = $this->loanRepository->find($loanId);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        $schedule = [];
        $currentBalance = $loan->getAmount();
        $monthlyPayment = $loan->getMonthlyPayment();
        $monthlyInterestRate = $loan->getInterestRate() / 100 / 12;
        $paymentDate = new \DateTime($loan->getDisbursementDate() ?? $loan->getApplicationDate());
        $paymentDate->modify('+1 month');
        
        for ($i = 1; $i <= $loan->getTermMonths(); $i++) {
            $interestPayment = $currentBalance * $monthlyInterestRate;
            $principalPayment = $monthlyPayment - $interestPayment;
            $currentBalance -= $principalPayment;
            
            // Ensure balance doesn't go negative
            if ($currentBalance < 0) {
                $principalPayment += $currentBalance;
                $currentBalance = 0;
            }
            
            $schedule[] = [
                'payment_number' => $i,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'payment_amount' => round($monthlyPayment, 2),
                'principal_amount' => round($principalPayment, 2),
                'interest_amount' => round($interestPayment, 2),
                'remaining_balance' => round($currentBalance, 2)
            ];
            
            $paymentDate->modify('+1 month');
            
            // Break if loan is fully paid
            if ($currentBalance <= 0) {
                break;
            }
        }
        
        return $schedule;
    }
    
    /**
     * Get loan summary for a member
     * 
     * @param int $memberId
     * @return array
     * @throws DatabaseException
     */
    public function getMemberLoanSummary(int $memberId): array
    {
        $loans = $this->loanRepository->findByMember($memberId);
        /** @var Loan[] $loans */
        
        $summary = [
            'total_loans' => count($loans),
            'active_loans' => 0,
            'total_borrowed' => 0,
            'total_outstanding' => 0,
            'total_paid' => 0,
            'loans' => $loans
        ];
        
        foreach ($loans as $loan) {
            $summary['total_borrowed'] += $loan->getAmount();
            
            if (in_array($loan->getStatus(), ['Active', 'Disbursed'])) {
                $summary['active_loans']++;
                $summary['total_outstanding'] += $loan->getRemainingBalance();
            }
            
            if ($loan->getStatus() === 'Paid') {
                $summary['total_paid'] += $loan->getAmount();
            }
        }
        
        return $summary;
    }
    
    /**
     * Validate loan business rules
     * 
     * @param Loan $loan
     * @throws ValidationException
     */
    private function validateLoanBusinessRules(Loan $loan): void
    {
        // Check minimum amount
        if ($loan->getAmount() < 100) {
            throw new ValidationException('Minimum loan amount is ₦100');
        }
        
        // Check maximum amount (business rule)
        if ($loan->getAmount() > 50000000) {
            throw new ValidationException('Maximum loan amount is ₦50,000,000');
        }
        
        // Check minimum term
        if ($loan->getTermMonths() < 1) {
            throw new ValidationException('Minimum loan term is 1 month');
        }
        
        // Check maximum term
        if ($loan->getTermMonths() > 360) {
            throw new ValidationException('Maximum loan term is 360 months (30 years)');
        }
        
        // Check interest rate range
        if ($loan->getInterestRate() < 0.1 || $loan->getInterestRate() > 50) {
            throw new ValidationException('Interest rate must be between 0.1% and 50%');
        }
        
        // Check if member has too many active loans
        $activeLoans = $this->loanRepository->findBy([
            'member_id' => $loan->getMemberId(),
            'status' => ['Active', 'Disbursed', 'Approved']
        ]);
        
        if (count($activeLoans) >= 5) { // Business rule: max 5 active loans per member
            throw new ValidationException('Member cannot have more than 5 active loans');
        }
        
        // Calculate total outstanding for member
        $totalOutstanding = 0;
        foreach ($activeLoans as $activeLoan) {
            $totalOutstanding += $activeLoan->calculateRemainingBalance();
        }
        
        // Check if total outstanding would exceed limit
        if ($totalOutstanding + $loan->getAmount() > 25000000) { // Business rule: max ₦25M outstanding per member
            throw new ValidationException('Total outstanding loans cannot exceed ₦25,000,000 per member');
        }
    }
    
    /**
     * Search loans by criteria
     * 
     * @param string $searchTerm
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     * @throws DatabaseException
     */
    public function searchLoans(string $searchTerm, array $filters = [], int $page = 1, int $limit = 10): array
    {
        $searchTerm = $this->securityService->sanitizeString($searchTerm);
        $filters = $this->securityService->sanitizeArray($filters);
        
        // Add search functionality to filters
        // This would require extending the QueryBuilder to support LIKE operations
        // For now, return regular filtered results
        return $this->getLoans($filters, $page, $limit);
    }
    
    /**
     * Add guarantors to a loan
     * 
     * @param int $loanId
     * @param array $guarantorsData
     * @return array
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function addGuarantorsToLoan(int $loanId, array $guarantorsData): array
    {
        if (!$this->guarantorRepository) {
            throw new DatabaseException('Guarantor repository not available');
        }
        
        $addedGuarantors = [];
        
        foreach ($guarantorsData as $guarantorData) {
            $guarantorData['loan_id'] = $loanId;
            $guarantorData = $this->securityService->sanitizeArray($guarantorData);
            
            $guarantor = new LoanGuarantor($guarantorData);
            
            $validation = $guarantor->validate();
            if (!$validation->isValid()) {
                throw new ValidationException('Invalid guarantor data: ' . implode(', ', $validation->getErrors()));
            }
            
            // Check if guarantor can provide this guarantee
            $eligibility = $this->guarantorRepository->canMemberGuarantee(
                $guarantor->getGuarantorMemberId(),
                $guarantor->getGuaranteeAmount()
            );
            
            if (!$eligibility['can_guarantee']) {
                throw new ValidationException($eligibility['reason'] ?? 'Guarantor not eligible');
            }
            
            $addedGuarantors[] = $this->guarantorRepository->create($guarantor);
        }
        
        return $addedGuarantors;
    }
    
    /**
     * Get loan with its guarantors
     * 
     * @param int $loanId
     * @return array
     * @throws DatabaseException
     */
    public function getLoanWithGuarantors(int $loanId): array
    {
        $loan = $this->loanRepository->find($loanId);
        /** @var Loan $loan */
        if (!$loan) {
            throw new ValidationException('Loan not found');
        }
        
        $guarantors = [];
        $totalGuaranteeAmount = 0;
        
        if ($this->guarantorRepository) {
            $guarantors = $this->guarantorRepository->findByLoan($loanId);
            $totalGuaranteeAmount = $this->guarantorRepository->getTotalGuaranteeAmount($loanId);
        }
        
        return [
            'loan' => $loan,
            'guarantors' => $guarantors,
            'total_guarantee_amount' => $totalGuaranteeAmount,
            'guarantee_coverage_percentage' => $loan->getAmount() > 0 ? 
                round(($totalGuaranteeAmount / $loan->getAmount()) * 100, 2) : 0
        ];
    }
    
    /**
     * Get loans guaranteed by a member
     * 
     * @param int $memberId
     * @return array
     * @throws DatabaseException
     */
    public function getLoansGuaranteedByMember(int $memberId): array
    {
        if (!$this->guarantorRepository) {
            return [];
        }
        
        return $this->guarantorRepository->findByGuarantor($memberId);
    }
    
    /**
     * Remove a guarantor from a loan
     * 
     * @param int $guarantorId
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function removeGuarantor(int $guarantorId): bool
    {
        if (!$this->guarantorRepository) {
            throw new DatabaseException('Guarantor repository not available');
        }
        
        return $this->guarantorRepository->delete($guarantorId);
    }
    
    /**
     * Update guarantor status
     * 
     * @param int $guarantorId
     * @param string $status
     * @return bool
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function updateGuarantorStatus(int $guarantorId, string $status): bool
    {
        if (!$this->guarantorRepository) {
            throw new DatabaseException('Guarantor repository not available');
        }
        
        $guarantor = $this->guarantorRepository->find($guarantorId);
        /** @var LoanGuarantor $guarantor */
        if (!$guarantor) {
            throw new ValidationException('Guarantor not found');
        }
        
        $status = $this->securityService->sanitizeString($status);
        $guarantor->setStatus($status);
        
        return $this->guarantorRepository->update($guarantor) !== null;
    }
    
    /**
     * Get enhanced loan statistics including guarantor information
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getEnhancedLoanStatistics(): array
    {
        $loanStats = $this->loanRepository->getStatistics();
        
        if ($this->guarantorRepository) {
            $guarantorStats = $this->guarantorRepository->getStatistics();
            $loanStats['guarantor_statistics'] = $guarantorStats;
        }
        
        return $loanStats;
    }
}
