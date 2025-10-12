<?php
require_once 'DatabaseConnection.php';
require_once 'LogService.php';
require_once 'NotificationService.php';

class JobSchedulerService {
    private $db;
    private $logService;
    private $notificationService;
    
    public function __construct() {
        $this->db = DatabaseConnection::getInstance()->getConnection();
        $this->logService = new LogService();
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Run all pending scheduled jobs
     */
    public function runPendingJobs() {
        try {
            // Get all pending jobs that are due
            $sql = "SELECT * FROM system_jobs 
                    WHERE status = 'pending' 
                    AND scheduled_at <= NOW() 
                    ORDER BY priority DESC, scheduled_at ASC 
                    LIMIT 50"; // Process max 50 jobs per run
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            
            foreach ($jobs as $job) {
                $result = $this->executeJob($job);
                $results[] = array_merge($job, $result);
            }
            
            $this->logService->log("job_scheduler_run", [
                'jobs_processed' => count($results),
                'successful' => count(array_filter($results, function($r) { return $r['success']; }))
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            $this->logService->log("job_scheduler_error", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Execute a specific job
     */
    public function executeJob($job) {
        try {
            $this->db->beginTransaction();
            
            // Mark job as running
            $this->updateJobStatus($job['id'], 'running');
            
            $result = null;
            
            switch ($job['job_type']) {
                case 'monthly_interest':
                    $result = $this->processMonthlyInterest($job);
                    break;
                    
                case 'penalty_calculation':
                    $result = $this->processPenaltyCalculation($job);
                    break;
                    
                case 'workflow_timeout':
                    $result = $this->processWorkflowTimeout($job);
                    break;
                    
                case 'auto_disburse':
                    $result = $this->processAutoDisbursement($job);
                    break;
                    
                case 'account_maintenance':
                    $result = $this->processAccountMaintenance($job);
                    break;
                    
                case 'backup_database':
                    $result = $this->processDatabaseBackup($job);
                    break;
                    
                case 'send_notifications':
                    $result = $this->processSendNotifications($job);
                    break;
                    
                default:
                    throw new Exception("Unknown job type: {$job['job_type']}");
            }
            
            // Mark job as completed
            $this->updateJobStatus($job['id'], 'completed', $result['message'] ?? 'Job completed successfully');
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Job completed successfully',
                'data' => $result['data'] ?? null
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            // Mark job as failed
            $this->updateJobStatus($job['id'], 'failed', $e->getMessage());
            
            $this->logService->log("job_execution_failed", [
                'job_id' => $job['id'],
                'job_type' => $job['job_type'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process monthly interest calculation
     */
    private function processMonthlyInterest($job) {
        $parameters = json_decode($job['parameters'], true);
        $targetDate = $parameters['target_date'] ?? date('Y-m-01'); // First day of current month
        
        // Get all active loans that need interest calculation
        $sql = "SELECT l.*, lt.interest_rate, lt.type_name,
                       m.first_name, m.last_name, m.email
                FROM loans l
                JOIN loan_types lt ON l.loan_type_id = lt.id
                JOIN members m ON l.member_id = m.id
                WHERE l.status = 'active'
                AND l.disbursed_at IS NOT NULL
                AND l.disbursed_at <= ?
                AND NOT EXISTS (
                    SELECT 1 FROM loan_interest_postings lip 
                    WHERE lip.loan_id = l.id 
                    AND DATE_FORMAT(lip.posting_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$targetDate, $targetDate]);
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        $totalInterest = 0;
        $errors = [];
        
        foreach ($loans as $loan) {
            try {
                $interestAmount = $this->calculateMonthlyInterest($loan, $targetDate);
                
                if ($interestAmount > 0) {
                    // Post interest to loan account
                    $this->postLoanInterest($loan['id'], $interestAmount, $targetDate);
                    
                    // Create transaction record
                    $this->createInterestTransaction($loan, $interestAmount, $targetDate);
                    
                    $processedCount++;
                    $totalInterest += $interestAmount;
                }
                
            } catch (Exception $e) {
                $errors[] = "Loan {$loan['id']}: " . $e->getMessage();
            }
        }
        
        return [
            'message' => "Processed {$processedCount} loans, total interest: " . number_format($totalInterest, 2),
            'data' => [
                'processed_loans' => $processedCount,
                'total_interest' => $totalInterest,
                'errors' => $errors
            ]
        ];
    }
    
    /**
     * Process penalty calculation for overdue payments
     */
    private function processPenaltyCalculation($job) {
        $parameters = json_decode($job['parameters'], true);
        $targetDate = $parameters['target_date'] ?? date('Y-m-d');
        
        // Get overdue payment schedules
        $sql = "SELECT lps.*, l.*, lt.penalty_rate, lt.grace_period_days,
                       m.first_name, m.last_name, m.email
                FROM loan_payment_schedule lps
                JOIN loans l ON lps.loan_id = l.id
                JOIN loan_types lt ON l.loan_type_id = lt.id
                JOIN members m ON l.member_id = m.id
                WHERE lps.status = 'pending'
                AND lps.due_date < ?
                AND DATE_ADD(lps.due_date, INTERVAL lt.grace_period_days DAY) <= ?
                AND (lps.penalty_calculated_date IS NULL OR lps.penalty_calculated_date < ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$targetDate, $targetDate, $targetDate]);
        $overdueSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        $totalPenalty = 0;
        $errors = [];
        
        foreach ($overdueSchedules as $schedule) {
            try {
                $penaltyAmount = $this->calculatePenalty($schedule, $targetDate);
                
                if ($penaltyAmount > 0) {
                    // Update payment schedule with penalty
                    $this->updateSchedulePenalty($schedule['id'], $penaltyAmount, $targetDate);
                    
                    // Create penalty transaction
                    $this->createPenaltyTransaction($schedule, $penaltyAmount, $targetDate);
                    
                    // Send notification to member
                    $this->sendPenaltyNotification($schedule, $penaltyAmount);
                    
                    $processedCount++;
                    $totalPenalty += $penaltyAmount;
                }
                
            } catch (Exception $e) {
                $errors[] = "Schedule {$schedule['id']}: " . $e->getMessage();
            }
        }
        
        return [
            'message' => "Processed {$processedCount} overdue payments, total penalty: " . number_format($totalPenalty, 2),
            'data' => [
                'processed_schedules' => $processedCount,
                'total_penalty' => $totalPenalty,
                'errors' => $errors
            ]
        ];
    }
    
    /**
     * Process workflow timeout
     */
    private function processWorkflowTimeout($job) {
        require_once 'WorkflowService.php';
        
        $parameters = json_decode($job['parameters'], true);
        $workflowId = $parameters['workflow_id'];
        $level = $parameters['level'];
        
        $workflowService = new WorkflowService();
        $workflowService->processTimeout($workflowId, $level);
        
        return [
            'message' => "Workflow {$workflowId} timeout processed at level {$level}"
        ];
    }
    
    /**
     * Process auto-disbursement
     */
    private function processAutoDisbursement($job) {
        $parameters = json_decode($job['parameters'], true);
        $loanId = $parameters['loan_id'];
        
        // Get loan details
        $sql = "SELECT l.*, m.first_name, m.last_name, m.email, m.account_number
                FROM loans l
                JOIN members m ON l.member_id = m.id
                WHERE l.id = ? AND l.status = 'approved'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan) {
            throw new Exception("Loan not found or not approved for disbursement");
        }
        
        // Process disbursement
        $this->disburseLoan($loan);
        
        return [
            'message' => "Loan {$loanId} disbursed successfully to {$loan['first_name']} {$loan['last_name']}"
        ];
    }
    
    /**
     * Process account maintenance tasks
     */
    private function processAccountMaintenance($job) {
        $parameters = json_decode($job['parameters'], true);
        $tasks = $parameters['tasks'] ?? ['cleanup_logs', 'update_credit_scores', 'archive_old_data'];
        
        $results = [];
        
        foreach ($tasks as $task) {
            try {
                switch ($task) {
                    case 'cleanup_logs':
                        $result = $this->cleanupOldLogs();
                        break;
                    case 'update_credit_scores':
                        $result = $this->updateMemberCreditScores();
                        break;
                    case 'archive_old_data':
                        $result = $this->archiveOldData();
                        break;
                    default:
                        $result = "Unknown task: {$task}";
                }
                
                $results[$task] = $result;
                
            } catch (Exception $e) {
                $results[$task] = "Error: " . $e->getMessage();
            }
        }
        
        return [
            'message' => "Account maintenance completed: " . implode(', ', array_keys($results)),
            'data' => $results
        ];
    }
    
    /**
     * Calculate monthly interest for a loan
     */
    private function calculateMonthlyInterest($loan, $targetDate) {
        $principal = $loan['principal_amount'] - $loan['amount_paid'];
        $monthlyRate = ($loan['interest_rate'] / 100) / 12;
        
        // Calculate interest based on outstanding principal
        $interestAmount = $principal * $monthlyRate;
        
        return round($interestAmount, 2);
    }
    
    /**
     * Post interest to loan account
     */
    private function postLoanInterest($loanId, $amount, $date) {
        // Insert interest posting record
        $sql = "INSERT INTO loan_interest_postings (loan_id, amount, posting_date, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loanId, $amount, $date]);
        
        // Update loan balance
        $sql = "UPDATE loans 
                SET balance = balance + ?, 
                    interest_accrued = interest_accrued + ?,
                    updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$amount, $amount, $loanId]);
    }
    
    /**
     * Create interest transaction record
     */
    private function createInterestTransaction($loan, $amount, $date) {
        $sql = "INSERT INTO transactions (
                    member_id, transaction_type, amount, description,
                    reference_number, created_at
                ) VALUES (?, 'interest_charge', ?, ?, ?, NOW())";
        
        $description = "Monthly interest on loan #{$loan['id']}";
        $reference = "INT-" . $loan['id'] . "-" . date('Ym', strtotime($date));
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loan['member_id'], $amount, $description, $reference]);
    }
    
    /**
     * Calculate penalty for overdue payment
     */
    private function calculatePenalty($schedule, $targetDate) {
        $dueDate = new DateTime($schedule['due_date']);
        $currentDate = new DateTime($targetDate);
        $gracePeriod = $schedule['grace_period_days'];
        
        // Add grace period to due date
        $graceEndDate = clone $dueDate;
        $graceEndDate->add(new DateInterval("P{$gracePeriod}D"));
        
        if ($currentDate <= $graceEndDate) {
            return 0; // Still within grace period
        }
        
        $daysOverdue = $currentDate->diff($graceEndDate)->days;
        $penaltyRate = $schedule['penalty_rate'] / 100;
        $outstandingAmount = $schedule['amount'] - $schedule['amount_paid'];
        
        // Calculate penalty based on outstanding amount and days overdue
        $penaltyAmount = $outstandingAmount * $penaltyRate * ($daysOverdue / 30); // Monthly penalty rate
        
        return round($penaltyAmount, 2);
    }
    
    /**
     * Update payment schedule with penalty
     */
    private function updateSchedulePenalty($scheduleId, $penalty, $date) {
        $sql = "UPDATE loan_payment_schedule 
                SET penalty_amount = penalty_amount + ?,
                    penalty_calculated_date = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$penalty, $date, $scheduleId]);
    }
    
    /**
     * Create penalty transaction
     */
    private function createPenaltyTransaction($schedule, $amount, $date) {
        $sql = "INSERT INTO transactions (
                    member_id, transaction_type, amount, description,
                    reference_number, created_at
                ) VALUES (?, 'penalty_charge', ?, ?, ?, NOW())";
        
        $description = "Penalty on overdue payment - Schedule #{$schedule['id']}";
        $reference = "PEN-" . $schedule['loan_id'] . "-" . $schedule['id'];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$schedule['member_id'], $amount, $description, $reference]);
    }
    
    /**
     * Send penalty notification to member
     */
    private function sendPenaltyNotification($schedule, $penalty) {
        // This would integrate with your notification system
        $subject = "Payment Overdue - Penalty Applied";
        $message = "A penalty of " . number_format($penalty, 2) . " has been applied to your overdue payment.";
        
        // Log for now - implement actual notification sending
        $this->logService->log("penalty_notification", [
            'member_id' => $schedule['member_id'],
            'schedule_id' => $schedule['id'],
            'penalty_amount' => $penalty
        ]);
    }
    
    /**
     * Disburse approved loan
     */
    private function disburseLoan($loan) {
        // Update loan status to disbursed
        $sql = "UPDATE loans 
                SET status = 'disbursed', 
                    disbursed_at = NOW(), 
                    balance = principal_amount,
                    updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loan['id']]);
        
        // Create disbursement transaction
        $sql = "INSERT INTO transactions (
                    member_id, transaction_type, amount, description,
                    reference_number, created_at
                ) VALUES (?, 'loan_disbursement', ?, ?, ?, NOW())";
        
        $description = "Loan disbursement - Loan #{$loan['id']}";
        $reference = "DISB-" . $loan['id'] . "-" . date('YmdHis');
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$loan['member_id'], $loan['principal_amount'], $description, $reference]);
        
        // Send disbursement notification
        $this->notificationService->sendDisbursementNotification($loan['id']);
    }
    
    /**
     * Cleanup old log entries
     */
    private function cleanupOldLogs() {
        $cutoffDate = date('Y-m-d', strtotime('-90 days'));
        
        $sql = "DELETE FROM system_logs WHERE created_at < ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cutoffDate]);
        
        $deletedRows = $stmt->rowCount();
        
        return "Deleted {$deletedRows} old log entries";
    }
    
    /**
     * Update member credit scores
     */
    private function updateMemberCreditScores() {
        // This would implement credit score calculation logic
        $sql = "UPDATE members SET credit_score = 750 WHERE credit_score IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $updatedRows = $stmt->rowCount();
        
        return "Updated credit scores for {$updatedRows} members";
    }
    
    /**
     * Archive old completed data
     */
    private function archiveOldData() {
        $cutoffDate = date('Y-m-d', strtotime('-2 years'));
        
        // Archive old completed workflows
        $sql = "UPDATE workflow_approvals 
                SET archived = 1 
                WHERE status IN ('approved', 'rejected', 'timeout') 
                AND completed_at < ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cutoffDate]);
        
        $archivedRows = $stmt->rowCount();
        
        return "Archived {$archivedRows} old workflows";
    }
    
    /**
     * Update job status
     */
    private function updateJobStatus($jobId, $status, $message = null) {
        $sql = "UPDATE system_jobs 
                SET status = ?, 
                    executed_at = CASE WHEN status = 'running' THEN NOW() ELSE executed_at END,
                    completed_at = CASE WHEN status IN ('completed', 'failed') THEN NOW() ELSE completed_at END,
                    result_message = COALESCE(?, result_message),
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $message, $jobId]);
    }
    
    /**
     * Schedule a new job
     */
    public function scheduleJob($jobType, $entityId, $scheduledAt, $parameters = [], $priority = 5) {
        try {
            $sql = "INSERT INTO system_jobs (
                        job_type, entity_id, scheduled_at, parameters, priority, 
                        status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $jobType,
                $entityId,
                $scheduledAt,
                json_encode($parameters),
                $priority
            ]);
            
            $jobId = $this->db->lastInsertId();
            
            $this->logService->log("job_scheduled", [
                'job_id' => $jobId,
                'job_type' => $jobType,
                'scheduled_at' => $scheduledAt
            ]);
            
            return $jobId;
            
        } catch (Exception $e) {
            $this->logService->log("job_schedule_failed", [
                'job_type' => $jobType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get job statistics
     */
    public function getJobStatistics($days = 30) {
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "SELECT 
                    job_type,
                    status,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(SECOND, executed_at, completed_at)) as avg_duration
                FROM system_jobs 
                WHERE created_at >= ?
                GROUP BY job_type, status
                ORDER BY job_type, status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fromDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cancel pending job
     */
    public function cancelJob($jobId) {
        $sql = "UPDATE system_jobs 
                SET status = 'cancelled', 
                    completed_at = NOW(),
                    result_message = 'Job cancelled',
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        
        return $stmt->rowCount() > 0;
    }
}
?>