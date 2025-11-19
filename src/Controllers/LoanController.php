<?php

namespace CSIMS\Controllers;

use CSIMS\Controllers\BaseController;
use CSIMS\Services\SecurityService;
use CSIMS\Services\ConfigurationManager;
use CSIMS\Services\LoanService;
use CSIMS\Repositories\LoanRepository;
use CSIMS\Repositories\LoanGuarantorRepository;
use CSIMS\Models\Loan;
use CSIMS\Models\LoanGuarantor;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\DatabaseException;

/**
 * Enhanced Loan Controller
 * 
 * Modern implementation with advanced loan management including
 * guarantor support, payment scheduling, and workflow integration
 */
class LoanController extends BaseController
{
    private LoanService $loanService;
    private LoanRepository $loanRepository;
    private ?LoanGuarantorRepository $guarantorRepository;
    
    public function __construct(
        SecurityService $security,
        ConfigurationManager $config,
        LoanService $loanService,
        LoanRepository $loanRepository,
        ?LoanGuarantorRepository $guarantorRepository = null
    ) {
        parent::__construct($security, $config);
        $this->loanService = $loanService;
        $this->loanRepository = $loanRepository;
        $this->guarantorRepository = $guarantorRepository;
    }
    
    /**
     * Create new loan application
     * 
     * @param array $data Loan application data
     * @return array
     */
    public function createLoanApplication(array $data): array
    {
        try {
            // Require member authentication
            $this->requireAuthentication('member');
            $this->validateCSRF();
            
            // Define validation rules
            $rules = [
                'member_id' => 'required|int',
                'amount' => 'required|numeric|min:100',
                'purpose' => 'required|min:5|max:500',
                'term_months' => 'required|int|min:1|max:240',
                'interest_rate' => 'numeric|min:0|max:50'
            ];
            
            // Validate input
            $validatedData = $this->validateInput($data, $rules);
            
            // Check if user can apply for this member ID
            $currentUser = $this->getCurrentUser('member');
            if ($currentUser['member_id'] != $validatedData['member_id']) {
                return $this->errorResponse('You can only apply for loans on your own behalf');
            }
            
            // Set default interest rate if not provided
            if (!isset($validatedData['interest_rate'])) {
                $validatedData['interest_rate'] = $this->getDefaultInterestRate();
            }
            
            // Extract guarantor data if provided
            $guarantorsData = [];
            if (isset($data['guarantors']) && is_array($data['guarantors'])) {
                foreach ($data['guarantors'] as $guarantorData) {
                    if (!empty($guarantorData['guarantor_member_id'])) {
                        $guarantorsData[] = $this->validateInput($guarantorData, [
                            'guarantor_member_id' => 'required|int',
                            'guarantee_amount' => 'numeric|min:0',
                            'guarantee_percentage' => 'numeric|min:0|max:100',
                            'guarantor_type' => 'required'
                        ]);
                    }
                }
            }
            
            // Create loan with guarantors
            $loan = $this->loanService->createLoan($validatedData, $guarantorsData);
            
            // Log activity
            $this->logActivity('create', 'loan_application', $loan->getId(), [
                'amount' => $validatedData['amount'],
                'purpose' => $validatedData['purpose'],
                'guarantors_count' => count($guarantorsData)
            ]);
            
            return $this->successResponse(
                'Loan application submitted successfully',
                ['loan_id' => $loan->getId()]
            );
            
        } catch (ValidationException $e) {
            return $this->handleException($e);
        } catch (DatabaseException $e) {
            return $this->handleException($e);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Update loan application
     * 
     * @param int $loanId
     * @param array $data
     * @return array
     */
    public function updateLoanApplication(int $loanId, array $data): array
    {
        try {
            // Require authentication
            $this->requireAuthentication();
            $this->validateCSRF();
            
            // Find loan
            $loan = $this->loanRepository->find($loanId);
            if (!$loan) {
                return $this->errorResponse('Loan not found');
            }
            
            // Check permissions
            if (!$this->canUpdateLoan($loan)) {
                return $this->errorResponse('Permission denied');
            }
            
            // Define validation rules
            $rules = [
                'amount' => 'numeric|min:100',
                'purpose' => 'min:5|max:500',
                'term_months' => 'int|min:1|max:240',
                'interest_rate' => 'numeric|min:0|max:50',
                'status' => 'alpha'
            ];
            
            // Validate input
            $validatedData = $this->validateInput($data, $rules);
            
            // Update loan
            $updatedLoan = $this->loanService->updateLoan($loanId, $validatedData);
            
            // Log activity
            $this->logActivity('update', 'loan', $loanId, $validatedData);
            
            return $this->successResponse('Loan updated successfully');
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get loan by ID with guarantors
     * 
     * @param int $loanId
     * @return array
     */
    public function getLoanById(int $loanId): array
    {
        try {
            // Require authentication
            $this->requireAuthentication();
            
            $loanData = $this->loanService->getLoanWithGuarantors($loanId);
            
            // Check if user can view this loan
            if (!$this->canViewLoan($loanData['loan'])) {
                return $this->errorResponse('Permission denied');
            }
            
            return $this->successResponse('Loan retrieved successfully', $loanData);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get loans for a member
     * 
     * @param int $memberId
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getMemberLoans(int $memberId, int $page = 1, int $limit = 10): array
    {
        try {
            // Require authentication
            $this->requireAuthentication();
            
            // Check if user can view loans for this member
            $currentUser = $this->getCurrentUser('member');
            if ($currentUser && $currentUser['member_id'] != $memberId && !$this->hasPermission('view_all_loans', 'admin')) {
                return $this->errorResponse('Permission denied');
            }
            
            return $this->getPaginatedResults(
                fn($p, $l) => $this->loanRepository->getPaginated($p, $l, ['member_id' => $memberId]),
                $page,
                $limit
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get loans by status (admin only)
     * 
     * @param string $status
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getLoansByStatus(string $status, int $page = 1, int $limit = 10): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            
            $status = $this->security->sanitizeInput($status, 'string');
            
            return $this->getPaginatedResults(
                fn($p, $l) => $this->loanRepository->getPaginated($p, $l, ['status' => $status]),
                $page,
                $limit
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Approve loan application
     * 
     * @param int $loanId
     * @param array $data Additional approval data
     * @return array
     */
    public function approveLoan(int $loanId, array $data = []): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            $this->validateCSRF();
            
            // Default actor from current admin if not provided
            $currentAdmin = $this->getCurrentUser('admin') ?? [];
            if (!isset($data['approved_by']) || !$data['approved_by']) {
                $data['approved_by'] = $currentAdmin['username']
                    ?? trim(($currentAdmin['first_name'] ?? '') . ' ' . ($currentAdmin['last_name'] ?? ''))
                    ?: 'System';
            }

            // Validate additional data
            $validatedData = $this->validateInput($data, [
                'approved_by' => 'required|min:2',
                'approval_notes' => 'max:1000'
            ]);
            
            // Approve loan (service expects approved_by string)
            $success = $this->loanService->approveLoan($loanId, $validatedData['approved_by'] ?? '');
            
            if ($success) {
                // Log activity
                $this->logActivity('approve', 'loan', $loanId, $validatedData);
                
                return $this->successResponse('Loan approved successfully');
            } else {
                return $this->errorResponse('Failed to approve loan');
            }
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Reject loan application
     * 
     * @param int $loanId
     * @param array $data Rejection data
     * @return array
     */
    public function rejectLoan(int $loanId, array $data): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            $this->validateCSRF();
            
            // Default actor from current admin if not provided
            $currentAdmin = $this->getCurrentUser('admin') ?? [];
            if (!isset($data['rejected_by']) || !$data['rejected_by']) {
                $data['rejected_by'] = $currentAdmin['username']
                    ?? trim(($currentAdmin['first_name'] ?? '') . ' ' . ($currentAdmin['last_name'] ?? ''))
                    ?: 'System';
            }

            // Validate rejection data
            $validatedData = $this->validateInput($data, [
                'rejected_by' => 'required|min:2',
                'rejection_reason' => 'required|min:10|max:1000'
            ]);
            
            $success = $this->loanService->rejectLoan(
                $loanId,
                $validatedData['rejected_by'] ?? '',
                $validatedData['rejection_reason']
            );
            
            if ($success) {
                // Log activity
                $this->logActivity('reject', 'loan', $loanId, $validatedData);
                
                return $this->successResponse('Loan rejected');
            } else {
                return $this->errorResponse('Failed to reject loan');
            }
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Disburse approved loan
     * 
     * @param int $loanId
     * @param array $data Disbursement data
     * @return array
     */
    public function disburseLoan(int $loanId, array $data): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            $this->validateCSRF();
            
            // Default actor from current admin if not provided
            $currentAdmin = $this->getCurrentUser('admin') ?? [];
            if (!isset($data['disbursed_by']) || !$data['disbursed_by']) {
                $data['disbursed_by'] = $currentAdmin['username']
                    ?? trim(($currentAdmin['first_name'] ?? '') . ' ' . ($currentAdmin['last_name'] ?? ''))
                    ?: 'System';
            }

            // Validate disbursement data
            $validatedData = $this->validateInput($data, [
                'disbursed_by' => 'required|min:2',
                'disbursement_method' => 'required',
                'disbursement_notes' => 'max:1000'
            ]);
            
            // Disburse loan (service expects disbursed_by string)
            $success = $this->loanService->disburseLoan($loanId, $validatedData['disbursed_by'] ?? '');
            
            if ($success) {
                // Log activity
                $this->logActivity('disburse', 'loan', $loanId, $validatedData);
                
                return $this->successResponse('Loan disbursed successfully');
            } else {
                return $this->errorResponse('Failed to disburse loan');
            }
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Add guarantor to loan
     * 
     * @param int $loanId
     * @param array $data Guarantor data
     * @return array
     */
    public function addGuarantor(int $loanId, array $data): array
    {
        try {
            // Require authentication
            $this->requireAuthentication();
            $this->validateCSRF();
            
            // Find loan
            $loan = $this->loanRepository->find($loanId);
            if (!$loan) {
                return $this->errorResponse('Loan not found');
            }
            
            // Check permissions
            if (!$this->canManageGuarantors($loan)) {
                return $this->errorResponse('Permission denied');
            }
            
            // Validate guarantor data
            $guarantorData = $this->validateInput($data, [
                'guarantor_member_id' => 'required|int',
                'guarantee_amount' => 'numeric|min:0',
                'guarantee_percentage' => 'numeric|min:0|max:100',
                'guarantor_type' => 'required',
                'notes' => 'max:500'
            ]);
            
            // Add guarantor
            $guarantors = $this->loanService->addGuarantorsToLoan($loanId, [$guarantorData]);
            
            // Log activity
            $this->logActivity('add_guarantor', 'loan', $loanId, [
                'guarantor_member_id' => $guarantorData['guarantor_member_id'],
                'guarantee_amount' => $guarantorData['guarantee_amount'] ?? 0
            ]);
            
            return $this->successResponse(
                'Guarantor added successfully',
                ['guarantor' => $guarantors[0]->toArray()]
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Remove guarantor from loan
     * 
     * @param int $loanId
     * @param int $guarantorId
     * @return array
     */
    public function removeGuarantor(int $loanId, int $guarantorId): array
    {
        try {
            // Require authentication
            $this->requireAuthentication();
            $this->validateCSRF();
            
            // Find loan
            $loan = $this->loanRepository->find($loanId);
            if (!$loan) {
                return $this->errorResponse('Loan not found');
            }
            
            // Check permissions
            if (!$this->canManageGuarantors($loan)) {
                return $this->errorResponse('Permission denied');
            }
            
            $success = $this->loanService->removeGuarantor($guarantorId);
            
            if ($success) {
                // Log activity
                $this->logActivity('remove_guarantor', 'loan', $loanId, [
                    'guarantor_id' => $guarantorId
                ]);
                
                return $this->successResponse('Guarantor removed successfully');
            } else {
                return $this->errorResponse('Failed to remove guarantor');
            }
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get loan payment schedule
     * 
     * @param int $loanId
     * @return array
     */
    public function getPaymentSchedule(int $loanId): array
    {
        try {
            // Require authentication
            $this->requireAuthentication();
            
            $loan = $this->loanRepository->find($loanId);
            if (!$loan) {
                return $this->errorResponse('Loan not found');
            }
            
            // Check permissions
            if (!$this->canViewLoan($loan)) {
                return $this->errorResponse('Permission denied');
            }
            
            // Calculate payment schedule
            $schedule = $this->calculatePaymentSchedule($loan);
            
            return $this->successResponse(
                'Payment schedule generated',
                ['schedule' => $schedule]
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get loan statistics (admin only)
     * 
     * @return array
     */
    public function getLoanStatistics(): array
    {
        try {
            // Require admin authentication
            $this->requireAuthentication('admin');
            
            $stats = $this->loanService->getEnhancedLoanStatistics();
            
            return $this->successResponse('Statistics retrieved', $stats);
            
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    // ================== PRIVATE HELPER METHODS ==================
    
    /**
     * Check if user can update loan
     * 
     * @param Loan $loan
     * @return bool
     */
    private function canUpdateLoan(Loan $loan): bool
    {
        // Admin can update any loan
        if ($this->hasPermission('edit_all_loans', 'admin')) {
            return true;
        }
        
        // Member can only update their own pending loans
        $currentUser = $this->getCurrentUser('member');
        if ($currentUser && $currentUser['member_id'] == $loan->getMemberId()) {
            return in_array($loan->getStatus(), ['pending', 'Pending']);
        }
        
        return false;
    }
    
    /**
     * Check if user can view loan
     * 
     * @param Loan $loan
     * @return bool
     */
    private function canViewLoan(Loan $loan): bool
    {
        // Admin can view all loans
        if ($this->hasPermission('view_all_loans', 'admin')) {
            return true;
        }
        
        // Member can view their own loans
        $currentUser = $this->getCurrentUser('member');
        if ($currentUser && $currentUser['member_id'] == $loan->getMemberId()) {
            return true;
        }
        
        // Member can view loans they guarantee
        if ($this->guarantorRepository && $currentUser) {
            $guarantees = $this->guarantorRepository->findByGuarantor($currentUser['member_id']);
            foreach ($guarantees as $guarantee) {
                if ($guarantee->getLoanId() == $loan->getId()) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if user can manage guarantors for loan
     * 
     * @param Loan $loan
     * @return bool
     */
    private function canManageGuarantors(Loan $loan): bool
    {
        // Admin can manage guarantors for any loan
        if ($this->hasPermission('manage_guarantors', 'admin')) {
            return true;
        }
        
        // Member can manage guarantors for their own pending loans
        $currentUser = $this->getCurrentUser('member');
        if ($currentUser && $currentUser['member_id'] == $loan->getMemberId()) {
            return in_array($loan->getStatus(), ['pending', 'Pending']);
        }
        
        return false;
    }
    
    /**
     * Get default interest rate
     * 
     * @return float
     */
    private function getDefaultInterestRate(): float
    {
        // Try to retrieve from centralized SystemConfigService (database-backed)
        try {
            // Ensure legacy config services are available
            $baseDir = dirname(__DIR__, 2);
            $sysConfigPath = $baseDir . '/includes/config/SystemConfigService.php';
            $pdoDbPath = $baseDir . '/includes/config/database.php';
            if (file_exists($sysConfigPath) && file_exists($pdoDbPath)) {
                require_once $pdoDbPath;
                require_once $sysConfigPath;
                if (class_exists('\\PdoDatabase') && class_exists('\\SystemConfigService')) {
                    $db = new \PdoDatabase();
                    $pdo = $db->getConnection();
                    $config = \SystemConfigService::getInstance($pdo);
                    // Prefer a system_config key if present
                    $rate = $config->get('DEFAULT_INTEREST_RATE', null);
                    if (is_numeric($rate) && (float)$rate > 0) {
                        return (float)$rate;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall back silently; optionally log for diagnostics
            error_log('LoanController:getDefaultInterestRate SystemConfig error: ' . $e->getMessage());
        }

        // Skip legacy settings table fallbacks; use environment/default config
        $configDefault = $this->config->get('loan.default_interest_rate', 12.0);
        return is_numeric($configDefault) ? (float)$configDefault : 12.0;

        // Fallback: read from legacy settings table if available
        try {
            $baseDir = dirname(__DIR__, 2);
            $pdoDbPath = $baseDir . '/includes/config/database.php';
            if (file_exists($pdoDbPath)) {
                require_once $pdoDbPath;
                if (class_exists('\\PdoDatabase')) {
                    $db = new \PdoDatabase();
                    $pdo = $db->getConnection();
                    $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'default_interest_rate' LIMIT 1");
                    $stmt->execute();
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && isset($row['value']) && is_numeric($row['value'])) {
                        return (float)$row['value'];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('LoanController:getDefaultInterestRate settings fallback error: ' . $e->getMessage());
        }

        // Final fallback: static default or environment config
        $configDefault = $this->config->get('loan.default_interest_rate', 12.0);
        return is_numeric($configDefault) ? (float)$configDefault : 12.0;
    }
    
    /**
     * Calculate payment schedule for loan
     * 
     * @param Loan $loan
     * @return array
     */
    private function calculatePaymentSchedule(Loan $loan): array
    {
        $schedule = [];
        $balance = $loan->getAmount();
        $monthlyPayment = $loan->getMonthlyPayment() ?: $loan->calculateMonthlyPayment();
        $monthlyRate = $loan->getInterestRate() / 100 / 12;
        
        $paymentDate = new \DateTime($loan->getApplicationDate());
        $paymentDate->modify('+1 month');
        
        for ($i = 1; $i <= $loan->getTermMonths() && $balance > 0; $i++) {
            $interestPayment = $balance * $monthlyRate;
            $principalPayment = $monthlyPayment - $interestPayment;
            
            if ($principalPayment > $balance) {
                $principalPayment = $balance;
                $monthlyPayment = $principalPayment + $interestPayment;
            }
            
            $balance -= $principalPayment;
            
            $schedule[] = [
                'payment_number' => $i,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'monthly_payment' => round($monthlyPayment, 2),
                'principal_payment' => round($principalPayment, 2),
                'interest_payment' => round($interestPayment, 2),
                'remaining_balance' => round($balance, 2)
            ];
            
            $paymentDate->modify('+1 month');
        }
        
        return $schedule;
    }
}