<?php

require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/services/BusinessRulesService.php';
require_once __DIR__ . '/../includes/config/SystemConfigService.php';
require_once __DIR__ . '/../classes/WorkflowService.php';

/**
 * Enhanced Loan Controller with Business Rules Integration
 * 
 * Extends the existing loan controller with comprehensive business rule validation
 * and system configuration management.
 * 
 * @package CSIMS\Controllers
 * @version 1.0.0
 */

class EnhancedLoanController 
{
    private $pdo;
    private $businessRules;
    private $config;
    private $workflowService;

    public function __construct() 
    {
        $database = new Database();
        $this->pdo = $database->getConnection();
        $this->businessRules = new BusinessRulesService($this->pdo);
        $this->config = SystemConfigService::getInstance($this->pdo);
        $this->workflowService = new WorkflowService();
    }

    /**
     * Enhanced loan application with full business rules validation
     */
    public function applyForLoan() 
    {
        try {
            // Get form data
            $memberId = (int)($_POST['member_id'] ?? 0);
            $loanTypeId = (int)($_POST['loan_type_id'] ?? 0);
            $requestedAmount = (float)($_POST['amount'] ?? 0);
            $purpose = trim($_POST['purpose'] ?? '');
            $termMonths = (int)($_POST['term_months'] ?? 0);

            // Basic validation
            if ($memberId <= 0 || $loanTypeId <= 0 || $requestedAmount <= 0) {
                throw new InvalidArgumentException('All fields are required');
            }

            // Business rules validation
            $validationErrors = $this->businessRules->validateLoanEligibility($memberId, $requestedAmount, $loanTypeId);
            
            if (!empty($validationErrors)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Loan application does not meet eligibility criteria',
                    'errors' => $validationErrors
                ]);
            }

            // Calculate loan details using business rules
            $loanDetails = $this->calculateLoanDetails($requestedAmount, $loanTypeId, $termMonths);
            
            // Get approval workflow level based on amount
            $approvalLevel = $this->determineApprovalLevel($requestedAmount);
            
            // Create loan application
            $loanId = $this->createLoanApplication([
                'member_id' => $memberId,
                'loan_type_id' => $loanTypeId,
                'amount' => $requestedAmount,
                'purpose' => $purpose,
                'term_months' => $termMonths,
                'interest_rate' => $loanDetails['interest_rate'],
                'monthly_payment' => $loanDetails['monthly_payment'],
                'total_amount' => $loanDetails['total_amount'],
                'processing_fee' => $loanDetails['processing_fee'],
                'approval_level_required' => $approvalLevel,
                'status' => $approvalLevel > 0 ? 'pending_approval' : 'approved'
            ]);

            // Create payment schedule
            $this->createPaymentSchedule($loanId, $loanDetails);

            // Start approval workflow if required
            $workflowId = null;
            if ($approvalLevel > 0) {
                // Update loan status to pending workflow
                $stmt = $this->pdo->prepare("UPDATE loans SET status = 'pending_approval' WHERE id = ?");
                $stmt->execute([$loanId]);
                
                // Start workflow using new WorkflowService
                $workflowId = $this->workflowService->startWorkflow(
                    'loan',
                    $loanId,
                    $requestedAmount,
                    $_SESSION['user_id'] ?? null
                );
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Loan application submitted successfully',
                'loan_id' => $loanId,
                'workflow_id' => $workflowId,
                'requires_approval' => $approvalLevel > 0,
                'approval_level' => $approvalLevel,
                'loan_details' => $loanDetails
            ]);

        } catch (Exception $e) {
            error_log("Enhanced Loan Application Error: " . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check loan eligibility without creating application
     */
    public function checkLoanEligibility() 
    {
        try {
            $memberId = (int)($_GET['member_id'] ?? 0);
            $amount = (float)($_GET['amount'] ?? 0);
            $loanTypeId = (int)($_GET['loan_type_id'] ?? 1);

            if ($memberId <= 0 || $amount <= 0) {
                throw new InvalidArgumentException('Member ID and amount are required');
            }

            $errors = $this->businessRules->validateLoanEligibility($memberId, $amount, $loanTypeId);
            
            $response = [
                'eligible' => empty($errors),
                'errors' => $errors,
                'member_id' => $memberId,
                'requested_amount' => $amount
            ];

            if (empty($errors)) {
                // Add loan calculation preview
                $loanDetails = $this->calculateLoanDetails($amount, $loanTypeId, 12); // Default 12 months
                $response['loan_preview'] = $loanDetails;
                
                // Add member credit score
                $creditScore = $this->businessRules->getMemberCreditScore($memberId);
                $response['credit_score'] = $creditScore;
            }

            return $this->jsonResponse($response);

        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get member's loan dashboard with business rules context
     */
    public function getMemberLoanDashboard() 
    {
        try {
            $memberId = (int)($_GET['member_id'] ?? 0);
            
            if ($memberId <= 0) {
                throw new InvalidArgumentException('Member ID is required');
            }

            // Get active loans
            $activeLoans = $this->getActiveLoansByMember($memberId);
            
            // Calculate total borrowed, total paid, outstanding
            $loanSummary = $this->calculateLoanSummary($memberId);
            
            // Check for overdue payments
            $overdueLoans = $this->getOverdueLoans($memberId);
            
            // Get credit score
            $creditScore = $this->businessRules->getMemberCreditScore($memberId);
            
            // Get loan limits based on current savings
            $loanLimits = $this->calculateLoanLimits($memberId);
            
            // Recent loan applications
            $recentApplications = $this->getRecentLoanApplications($memberId);
            
            // Get workflow status for pending loans
            $pendingWorkflows = $this->getPendingWorkflowsForMember($memberId);

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'member_id' => $memberId,
                    'active_loans' => $activeLoans,
                    'loan_summary' => $loanSummary,
                    'overdue_loans' => $overdueLoans,
                    'credit_score' => $creditScore,
                    'loan_limits' => $loanLimits,
                    'recent_applications' => $recentApplications,
                    'pending_workflows' => $pendingWorkflows,
                    'has_overdue' => $this->businessRules->hasOverdueLoans($memberId)
                ]
            ]);

        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate penalty for overdue loan payments
     */
    public function calculateLoanPenalty() 
    {
        try {
            $loanId = (int)($_GET['loan_id'] ?? 0);
            $dueDate = $_GET['due_date'] ?? '';

            if ($loanId <= 0 || empty($dueDate)) {
                throw new InvalidArgumentException('Loan ID and due date are required');
            }

            $dueDateObj = new DateTime($dueDate);
            $penalty = $this->businessRules->calculateLoanPenalty($loanId, $dueDateObj);
            
            return $this->jsonResponse([
                'success' => true,
                'loan_id' => $loanId,
                'due_date' => $dueDate,
                'penalty_amount' => $penalty,
                'penalty_formatted' => '₦' . number_format($penalty, 2),
                'grace_period_days' => $this->config->getDefaultGracePeriod(),
                'penalty_rate' => $this->config->getLoanPenaltyRate() . '%'
            ]);

        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process loan payment with penalty calculation
     */
    public function processLoanPayment() 
    {
        try {
            $scheduleId = (int)($_POST['schedule_id'] ?? 0);
            $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
            $paymentMethod = trim($_POST['payment_method'] ?? '');
            $reference = trim($_POST['reference'] ?? '');

            if ($scheduleId <= 0 || $paymentAmount <= 0) {
                throw new InvalidArgumentException('Schedule ID and payment amount are required');
            }

            // Get payment schedule details
            $schedule = $this->getPaymentScheduleDetails($scheduleId);
            if (!$schedule) {
                throw new InvalidArgumentException('Payment schedule not found');
            }

            // Calculate penalty if overdue
            $penalty = 0;
            $dueDate = new DateTime($schedule['due_date']);
            if (new DateTime() > $dueDate) {
                $penalty = $this->businessRules->calculateLoanPenalty($schedule['loan_id'], $dueDate);
            }

            $totalDue = $schedule['amount'] + $penalty;
            
            // Process payment
            $this->pdo->beginTransaction();

            // Update payment schedule
            $stmt = $this->pdo->prepare("
                UPDATE loan_payment_schedule 
                SET 
                    amount_paid = amount_paid + ?,
                    penalty_amount = ?,
                    payment_date = NOW(),
                    status = CASE 
                        WHEN amount_paid + ? >= amount + ? THEN 'paid' 
                        ELSE 'partial' 
                    END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$paymentAmount, $penalty, $paymentAmount, $penalty, $scheduleId]);

            // Record transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (
                    member_id, transaction_type, amount, description, 
                    reference_number, payment_method, created_at
                ) VALUES (?, 'loan_payment', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $schedule['member_id'],
                $paymentAmount,
                "Loan payment for schedule ID {$scheduleId}",
                $reference,
                $paymentMethod
            ]);

            // Update loan balance
            $stmt = $this->pdo->prepare("
                UPDATE loans 
                SET 
                    amount_paid = amount_paid + ?,
                    balance = principal_amount + interest_amount + processing_fee - (amount_paid + ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$paymentAmount, $paymentAmount, $schedule['loan_id']]);

            $this->pdo->commit();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment_amount' => $paymentAmount,
                'penalty_amount' => $penalty,
                'total_paid' => $paymentAmount,
                'remaining_balance' => max(0, $totalDue - $paymentAmount)
            ]);

        } catch (Exception $e) {
            $this->pdo->rollback();
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // ============ PRIVATE HELPER METHODS ============

    /**
     * Calculate loan details using business rules and loan type configuration
     */
    private function calculateLoanDetails(float $amount, int $loanTypeId, int $termMonths): array 
    {
        // Get loan type details
        $stmt = $this->pdo->prepare("SELECT * FROM loan_types WHERE id = ?");
        $stmt->execute([$loanTypeId]);
        $loanType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loanType) {
            throw new InvalidArgumentException('Invalid loan type');
        }

        $interestRate = (float)$loanType['interest_rate'];
        $processingFeeRate = $this->config->get('DEFAULT_PROCESSING_FEE_RATE', 1.0);
        
        // Calculate using reducing balance method
        $monthlyInterestRate = $interestRate / 100 / 12;
        $processingFee = $amount * ($processingFeeRate / 100);
        
        if ($monthlyInterestRate > 0) {
            $monthlyPayment = $amount * (
                $monthlyInterestRate * pow(1 + $monthlyInterestRate, $termMonths)
            ) / (pow(1 + $monthlyInterestRate, $termMonths) - 1);
        } else {
            $monthlyPayment = $amount / $termMonths;
        }

        $totalAmount = $monthlyPayment * $termMonths;
        $totalInterest = $totalAmount - $amount;

        return [
            'principal_amount' => $amount,
            'interest_rate' => $interestRate,
            'term_months' => $termMonths,
            'monthly_payment' => round($monthlyPayment, 2),
            'total_amount' => round($totalAmount + $processingFee, 2),
            'total_interest' => round($totalInterest, 2),
            'processing_fee' => round($processingFee, 2),
            'monthly_interest_rate' => $monthlyInterestRate,
            'loan_type' => $loanType['name']
        ];
    }

    /**
     * Determine approval level based on loan amount
     */
    private function determineApprovalLevel(float $amount): int 
    {
        $autoApprovalLimit = $this->config->getAutoApprovalLimit();
        $maxApprovalLevels = $this->config->getApprovalLevels();
        
        if ($amount <= $autoApprovalLimit) {
            return 0; // Auto-approved
        }
        
        // Determine approval level based on amount tiers
        if ($amount <= 500000) {
            return 1;
        } elseif ($amount <= 1000000) {
            return 2;
        } else {
            return $maxApprovalLevels;
        }
    }

    /**
     * Create loan application record
     */
    private function createLoanApplication(array $data): int 
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO loans (
                member_id, loan_type_id, principal_amount, interest_rate,
                term_months, monthly_payment, total_amount, processing_fee,
                purpose, status, approval_level_required, application_date,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
        ");
        
        $stmt->execute([
            $data['member_id'],
            $data['loan_type_id'],
            $data['amount'],
            $data['interest_rate'],
            $data['term_months'],
            $data['monthly_payment'],
            $data['total_amount'],
            $data['processing_fee'],
            $data['purpose'],
            $data['status'],
            $data['approval_level_required']
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Create payment schedule for loan
     */
    private function createPaymentSchedule(int $loanId, array $loanDetails): void 
    {
        $monthlyPayment = $loanDetails['monthly_payment'];
        $termMonths = $loanDetails['term_months'];
        
        for ($i = 1; $i <= $termMonths; $i++) {
            $dueDate = date('Y-m-d', strtotime("+{$i} month"));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO loan_payment_schedule (
                    loan_id, payment_number, due_date, amount, 
                    status, created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$loanId, $i, $dueDate, $monthlyPayment]);
        }
    }

    /**
     * Get pending workflows for a member
     */
    private function getPendingWorkflowsForMember(int $memberId): array 
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT wa.*, wt.template_name, l.principal_amount, l.purpose,
                       CONCAT('Level ', wa.current_level, ' of ', wa.total_levels) as status_display,
                       DATEDIFF(NOW(), wa.created_at) as days_pending
                FROM workflow_approvals wa
                JOIN workflow_templates wt ON wa.template_id = wt.id
                JOIN loans l ON wa.entity_id = l.id
                WHERE wa.entity_type = 'loan'
                AND l.member_id = ?
                AND wa.status = 'pending'
                ORDER BY wa.created_at DESC
            ");
            $stmt->execute([$memberId]);
            
            $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add approval history for each workflow
            foreach ($workflows as &$workflow) {
                $workflow['approval_history'] = $this->workflowService->getApprovalHistory($workflow['id']);
            }
            
            return $workflows;
            
        } catch (Exception $e) {
            error_log("Error fetching pending workflows: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get workflow status for a specific loan
     */
    public function getLoanWorkflowStatus() 
    {
        try {
            $loanId = (int)($_GET['loan_id'] ?? 0);
            
            if ($loanId <= 0) {
                throw new InvalidArgumentException('Loan ID is required');
            }
            
            // Get workflows for this loan
            $workflows = $this->workflowService->getWorkflowsForEntity('loan', $loanId);
            
            // Get current pending workflow if any
            $currentWorkflow = null;
            foreach ($workflows as $workflow) {
                if ($workflow['status'] === 'pending') {
                    $currentWorkflow = $workflow;
                    $currentWorkflow['approval_history'] = $this->workflowService->getApprovalHistory($workflow['id']);
                    break;
                }
            }
            
            return $this->jsonResponse([
                'success' => true,
                'loan_id' => $loanId,
                'current_workflow' => $currentWorkflow,
                'workflow_history' => $workflows
            ]);
            
        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

    /**
     * Get various helper methods for loan dashboard
     */
    private function getActiveLoansByMember(int $memberId): array 
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, lt.name as loan_type_name,
                   (l.principal_amount - l.amount_paid) as balance
            FROM loans l
            LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
            WHERE l.member_id = ? 
            AND l.status IN ('active', 'disbursed')
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateLoanSummary(int $memberId): array 
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_loans,
                SUM(principal_amount) as total_borrowed,
                SUM(amount_paid) as total_paid,
                SUM(principal_amount - amount_paid) as total_outstanding
            FROM loans 
            WHERE member_id = ?
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getOverdueLoans(int $memberId): array 
    {
        $stmt = $this->pdo->prepare("
            SELECT lps.*, l.principal_amount, l.monthly_payment
            FROM loan_payment_schedule lps
            INNER JOIN loans l ON lps.loan_id = l.id
            WHERE l.member_id = ?
            AND lps.due_date < CURDATE()
            AND lps.status = 'pending'
            ORDER BY lps.due_date ASC
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculateLoanLimits(int $memberId): array 
    {
        $totalSavings = $this->getMemberTotalSavings($memberId);
        $multiplier = $this->config->getLoanToSavingsMultiplier();
        $maxSystemLoan = $this->config->getMaxLoanAmount();
        
        return [
            'total_savings' => $totalSavings,
            'max_based_on_savings' => $totalSavings * $multiplier,
            'max_system_limit' => $maxSystemLoan,
            'effective_limit' => min($totalSavings * $multiplier, $maxSystemLoan)
        ];
    }

    private function getMemberTotalSavings(int $memberId): float 
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_savings
            FROM contributions 
            WHERE member_id = ? AND status = 'completed'
        ");
        $stmt->execute([$memberId]);
        return (float)$stmt->fetchColumn();
    }

    private function getRecentLoanApplications(int $memberId): array 
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, lt.name as loan_type_name
            FROM loans l
            LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
            WHERE l.member_id = ?
            ORDER BY l.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPaymentScheduleDetails(int $scheduleId): ?array 
    {
        $stmt = $this->pdo->prepare("
            SELECT lps.*, l.member_id, l.principal_amount
            FROM loan_payment_schedule lps
            INNER JOIN loans l ON lps.loan_id = l.id
            WHERE lps.id = ?
        ");
        $stmt->execute([$scheduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * JSON response helper
     */
    private function jsonResponse(array $data): string 
    {
        header('Content-Type: application/json');
        return json_encode($data);
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new EnhancedLoanController();
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'apply_loan':
            echo $controller->applyForLoan();
            break;
        case 'check_eligibility':
            echo $controller->checkLoanEligibility();
            break;
        case 'loan_dashboard':
            echo $controller->getMemberLoanDashboard();
            break;
        case 'calculate_penalty':
            echo $controller->calculateLoanPenalty();
            break;
        case 'process_payment':
            echo $controller->processLoanPayment();
            break;
        case 'loan_workflow_status':
            echo $controller->getLoanWorkflowStatus();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}