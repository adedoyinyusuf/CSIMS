<?php
require_once __DIR__ . '/../includes/config/database.php';
require_once 'NotificationService.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/sms_service.php';

class JobSchedulerService {
    private $db;
    private $notificationService;
    
    public function __construct() {
        $this->db = (new PdoDatabase())->getConnection();
        $this->notificationService = new NotificationService();
    }

    private function log(string $event, array $data = []): void {
        try {
            error_log('[CSIMS] ' . json_encode(['event' => $event, 'data' => $data, 'ts' => date('c')]));
        } catch (\Throwable $e) {}
    }
    
    // Helper to check if a table has a specific column (schema-aware logic)
    private function hasColumn(string $table, string $column): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false; // Conservative fallback: assume missing if metadata query fails
        }
    }

    // Helper to check if a table exists (schema-aware logic)
    private function hasTable(string $table): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false; // Conservative fallback: assume missing if metadata query fails
        }
    }

    // Determine primary key column for system_jobs
    private function getJobsPrimaryKey(): string {
        if ($this->hasColumn('system_jobs', 'id')) return 'id';
        if ($this->hasColumn('system_jobs', 'job_id')) return 'job_id';
        return 'id';
    }

    // Determine primary key column for loans
    private function getLoansPrimaryKey(): string {
        if ($this->hasColumn('loans', 'id')) return 'id';
        if ($this->hasColumn('loans', 'loan_id')) return 'loan_id';
        return 'id';
    }

    /**
     * Run all pending scheduled jobs
     */
    public function runPendingJobs() {
        try {
            // Get all pending jobs that are due
            $hasStatus = $this->hasColumn('system_jobs', 'status');
            $hasScheduledAt = $this->hasColumn('system_jobs', 'scheduled_at');
            $hasCreatedAt = $this->hasColumn('system_jobs', 'created_at');
            $hasExecutedAt = $this->hasColumn('system_jobs', 'executed_at');
            $hasCompletedAt = $this->hasColumn('system_jobs', 'completed_at');
            
            $whereParts = [];
            if ($hasStatus) {
                $whereParts[] = "status = 'pending'";
            } elseif ($hasExecutedAt && $hasCompletedAt) {
                $whereParts[] = "(executed_at IS NULL AND completed_at IS NULL)";
            }
            
            if ($hasScheduledAt) {
                $whereParts[] = "scheduled_at <= NOW()";
            }
            
            if ($this->hasColumn('system_jobs', 'job_type')) {
                $whereParts[] = "job_type IS NOT NULL AND job_type <> ''";
            }
            
            $whereClause = count($whereParts) ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

            $orderParts = [];
            if ($this->hasColumn('system_jobs', 'priority')) {
                $orderParts[] = 'priority DESC';
            }
            if ($hasScheduledAt) {
                $orderParts[] = 'scheduled_at ASC';
            } elseif ($hasCreatedAt) {
                $orderParts[] = 'created_at ASC';
            } else {
                $orderParts[] = 'id ASC';
            }
            $orderClause = 'ORDER BY ' . implode(', ', $orderParts);

            $sql = "SELECT * FROM system_jobs {$whereClause} {$orderClause} LIMIT 50";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            $pkColumn = $this->getJobsPrimaryKey();
            
            foreach ($jobs as $job) {
                $result = $this->executeJob($job);
                $displayId = $this->resolveJobId($job);
                $results[] = array_merge(['id' => $displayId, 'job_type' => $job['job_type'] ?? null], $job, $result);
            }
            
            $this->log("job_scheduler_run", [
                'jobs_processed' => count($results),
                'successful' => count(array_filter($results, function($r) { return $r['success']; }))
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            $this->log("job_scheduler_error", [
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
            
            // Resolve job identifier robustly
            $jobId = $this->resolveJobId($job);
            
            // Mark job as running only if we can identify it
            if ($jobId !== null) {
                $this->updateJobStatus($jobId, 'running');
            }
            
            $result = null;
            
            switch ($job['job_type']) {
                case 'monthly_interest':
                    $result = $this->processMonthlyInterest($job);
                    break;
                case 'interest_calculation': // legacy alias
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
                
                case 'statement_generation':
                    $result = $this->processStatementGeneration($job);
                    break;
                
                case 'notification_cleanup':
                    $result = $this->processNotificationCleanup($job);
                    break;
                
                case 'monthly_savings_deposit':
                    $result = $this->processMonthlySavingsDeposit($job);
                    break;
                
                default:
                    throw new Exception("Unknown job type: {$job['job_type']}");
            }
            
            // Mark job as completed
            if ($jobId !== null) {
                $this->updateJobStatus($jobId, 'completed', $result['message'] ?? 'Job completed successfully');
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Job completed successfully',
                'data' => $result['data'] ?? null,
                'job_id' => $jobId
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            $jobId = $this->resolveJobId($job);
            
            if ($jobId !== null) {
                $this->updateJobStatus($jobId, 'failed', $e->getMessage());
            }
            
            $this->log("job_execution_failed", [
                'job_id' => $jobId,
                'job_type' => $job['job_type'] ?? null,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => $jobId
            ];
        }
    }
    
    /**
     * Process monthly interest calculation
     */
    private function processMonthlyInterest($job) {
        $parameters = $this->getParameters($job);
        $targetDate = $parameters['target_date'] ?? date('Y-m-01');
        $defaultRate = isset($parameters['default_interest_rate']) ? (float)$parameters['default_interest_rate'] : 12.0;
        
        $hasStatusCol = $this->hasColumn('loans', 'status');
        $hasDisbursedAtCol = $this->hasColumn('loans', 'disbursed_at');
        
        // Get all active loans that need interest calculation
        if ($this->hasTable('loan_types')) {
            $sql = "SELECT l.*, lt.interest_rate, lt.type_name
                FROM loans l
                JOIN loan_types lt ON l.loan_type_id = lt.id
                WHERE 1=1";
            $params = [];
            if ($hasStatusCol) { $sql .= " AND l.status = 'active'"; }
            if ($hasDisbursedAtCol) { $sql .= " AND l.disbursed_at IS NOT NULL AND l.disbursed_at <= ?"; $params[] = $targetDate; }
            if ($this->hasTable('loan_interest_postings')) {
                $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM loan_interest_postings lip 
                    WHERE lip.loan_id = l." . $this->getLoansPrimaryKey() . " 
                    AND DATE_FORMAT(lip.posting_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
                )";
                $params[] = $targetDate;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "SELECT l.*, COALESCE(l.interest_rate, ?) AS interest_rate
                FROM loans l
                WHERE 1=1";
            $params = [$defaultRate];
            if ($hasStatusCol) { $sql .= " AND l.status = 'active'"; }
            if ($hasDisbursedAtCol) { $sql .= " AND l.disbursed_at IS NOT NULL AND l.disbursed_at <= ?"; $params[] = $targetDate; }
            if ($this->hasTable('loan_interest_postings')) {
                $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM loan_interest_postings lip 
                    WHERE lip.loan_id = l." . $this->getLoansPrimaryKey() . " 
                    AND DATE_FORMAT(lip.posting_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
                )";
                $params[] = $targetDate;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
        $activeLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        $totalInterest = 0;
        $errors = [];
        
        foreach ($activeLoans as $loan) {
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
        $parameters = $this->getParameters($job);
        $targetDate = $parameters['target_date'] ?? date('Y-m-d');
        $defaultPenaltyRate = isset($parameters['default_penalty_rate']) ? (float)$parameters['default_penalty_rate'] : 2.0;
        $defaultGraceDays = isset($parameters['default_grace_days']) ? (int)$parameters['default_grace_days'] : 5;
        
        $hasStatusCol = $this->hasColumn('loan_payment_schedule', 'status');
        $hasPenaltyCalcDateCol = $this->hasColumn('loan_payment_schedule', 'penalty_calculated_date');
        
        // Get overdue payment schedules
        if ($this->hasTable('loan_types')) {
            $sql = "SELECT lps.*, l.*, lt.penalty_rate, lt.grace_period_days,
                       m.first_name, m.last_name, m.email
                FROM loan_payment_schedule lps
                JOIN loans l ON lps.loan_id = l.loan_id
                JOIN loan_types lt ON l.loan_type_id = lt.id
                JOIN members m ON l.member_id = m.member_id
                WHERE lps.due_date < ?
                AND DATE_ADD(lps.due_date, INTERVAL lt.grace_period_days DAY) <= ?";
            $params = [$targetDate, $targetDate];
            if ($hasStatusCol) { $sql .= " AND lps.status = 'pending'"; }
            if ($hasPenaltyCalcDateCol) { $sql .= " AND (lps.penalty_calculated_date IS NULL OR lps.penalty_calculated_date < ?)"; $params[] = $targetDate; }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "SELECT lps.*, l.*, 
                       ? AS penalty_rate, 
                       ? AS grace_period_days,
                       m.first_name, m.last_name, m.email
                FROM loan_payment_schedule lps
                JOIN loans l ON lps.loan_id = l.loan_id
                JOIN members m ON l.member_id = m.member_id
                WHERE lps.due_date < ?";
            $params = [$defaultPenaltyRate, $defaultGraceDays, $targetDate];
            if ($hasStatusCol) { $sql .= " AND lps.status = 'pending'"; }
            if ($hasPenaltyCalcDateCol) { $sql .= " AND (lps.penalty_calculated_date IS NULL OR lps.penalty_calculated_date < ?)"; $params[] = $targetDate; }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
        
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
        
        $parameters = $this->getParameters($job);
        $workflowId = $parameters['workflow_id'] ?? null;
        $level = $parameters['level'] ?? null;
        if ($workflowId === null || $level === null) {
            throw new Exception('Missing workflow parameters');
        }
        
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
        $parameters = $this->getParameters($job);
        $loanId = $parameters['loan_id'] ?? null;
        if ($loanId === null) { throw new Exception('Missing loan_id parameter'); }
        
        // Get loan details
        $sql = "SELECT l.*, m.first_name, m.last_name, m.email, m.account_number
                FROM loans l
                JOIN members m ON l.member_id = m.member_id
                WHERE l.loan_id = ? AND l.status = 'approved'";
        
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
        $parameters = $this->getParameters($job);
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
     * Create a database backup using mysqldump
     */
    private function processDatabaseBackup($job) {
        $parameters = $this->getParameters($job);
        $backupName = $parameters['backup_name'] ?? ('csims_backup_' . date('Y-m-d_H-i-s'));
        $backupDir = __DIR__ . '/../backups/';
        
        // Ensure backups directory exists
        if (!file_exists($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . $backupName . '.sql';
        
        // Read DB config constants (loaded via includes/config/database.php)
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dbName = defined('DB_NAME') ? DB_NAME : 'csims_db';
        
        // Build mysqldump command with proper escaping
        $command = sprintf(
            'mysqldump --host=%s --user=%s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($user),
            $pass !== '' ? ('--password=' . escapeshellarg($pass)) : '',
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
        
        $output = [];
        $returnVar = 0;
        @exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($backupFile)) {
            $size = @filesize($backupFile) ?: 0;
            $this->log('database_backup_created', [
                'backup_file' => $backupFile,
                'size' => $size
            ]);
            
            return [
                'message' => 'Database backup created: ' . ($backupName . '.sql'),
                'data' => [
                    'backup_file' => $backupName . '.sql',
                    'size' => $size
                ]
            ];
        }
        
        throw new Exception('Database backup failed');
    }
    
    /**
     * Process queued email and SMS notifications
     */
    private function processSendNotifications($job) {
        $parameters = $this->getParameters($job);
        $limit = isset($parameters['limit']) ? (int)$parameters['limit'] : 50;
        
        $emailService = new EmailService();
        $smsService = new SMSService();
        
        $processedEmail = 0;
        $failedEmail = 0;
        $processedSMS = 0;
        $failedSMS = 0;
        
        // Process email queue
        $sqlEmails = "SELECT * FROM email_queue WHERE status = 'pending' AND scheduled_at <= NOW() ORDER BY priority DESC, scheduled_at ASC LIMIT " . (int)$limit;
        $stmtEmails = $this->db->prepare($sqlEmails);
        $stmtEmails->execute();
        $emails = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($emails as $email) {
            // Mark as sending
            $stmtUpdate = $this->db->prepare("UPDATE email_queue SET status = ?, sent_at = NULL, error_message = NULL WHERE id = ?");
            $stmtUpdate->execute(['sending', $email['id']]);
            
            // Attempt to send
            $sent = false;
            try {
                $sent = $emailService->send($email['to_email'], $email['to_name'], $email['subject'], $email['body']);
            } catch (Exception $e) {
                $sent = false;
                $this->log('email_send_error', ['id' => $email['id'], 'error' => $e->getMessage()]);
            }
            
            if ($sent) {
                $stmtSent = $this->db->prepare("UPDATE email_queue SET status = ?, sent_at = NOW() WHERE id = ?");
                $stmtSent->execute(['sent', $email['id']]);
                $processedEmail++;
                $this->log('email_sent', ['queue_id' => $email['id'], 'to' => $email['to_email'], 'subject' => $email['subject']]);
            } else {
                $attempts = ((int)$email['attempts']) + 1;
                $maxAttempts = (int)$email['max_attempts'];
                if ($attempts >= $maxAttempts) {
                    $stmtFail = $this->db->prepare("UPDATE email_queue SET status = ?, error_message = ?, attempts = ? WHERE id = ?");
                    $stmtFail->execute(['failed', 'Max attempts reached', $attempts, $email['id']]);
                    $failedEmail++;
                    $this->log('email_failed_permanent', ['queue_id' => $email['id'], 'to' => $email['to_email']]);
                } else {
                    $stmtRetry = $this->db->prepare("UPDATE email_queue SET status = ?, error_message = ?, attempts = ? WHERE id = ?");
                    $stmtRetry->execute(['pending', 'Retry attempt ' . $attempts, $attempts, $email['id']]);
                    $this->log('email_retry_scheduled', ['queue_id' => $email['id'], 'to' => $email['to_email'], 'attempt' => $attempts]);
                }
            }
        }
        
        // Process SMS queue
        $sqlSMS = "SELECT * FROM sms_queue WHERE status = 'pending' AND scheduled_at <= NOW() ORDER BY priority DESC, scheduled_at ASC LIMIT " . (int)$limit;
        $stmtSMS = $this->db->prepare($sqlSMS);
        $stmtSMS->execute();
        $smsList = $stmtSMS->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($smsList as $sms) {
            // Mark as sending
            $stmtUpdate = $this->db->prepare("UPDATE sms_queue SET status = ?, sent_at = NULL, error_message = NULL WHERE id = ?");
            $stmtUpdate->execute(['sending', $sms['id']]);
            
            // Attempt to send
            $sent = false;
            try {
                $sent = $smsService->send($sms['to_phone'], $sms['message']);
            } catch (Exception $e) {
                $sent = false;
                $this->log('sms_send_error', ['id' => $sms['id'], 'error' => $e->getMessage()]);
            }
            
            if ($sent) {
                $stmtSent = $this->db->prepare("UPDATE sms_queue SET status = ?, sent_at = NOW() WHERE id = ?");
                $stmtSent->execute(['sent', $sms['id']]);
                $processedSMS++;
                $this->log('sms_sent', ['queue_id' => $sms['id'], 'to' => $sms['to_phone']]);
            } else {
                $attempts = ((int)$sms['attempts']) + 1;
                $maxAttempts = (int)$sms['max_attempts'];
                if ($attempts >= $maxAttempts) {
                    $stmtFail = $this->db->prepare("UPDATE sms_queue SET status = ?, error_message = ?, attempts = ? WHERE id = ?");
                    $stmtFail->execute(['failed', 'Max attempts reached', $attempts, $sms['id']]);
                    $failedSMS++;
                    $this->log('sms_failed_permanent', ['queue_id' => $sms['id'], 'to' => $sms['to_phone']]);
                } else {
                    $stmtRetry = $this->db->prepare("UPDATE sms_queue SET status = ?, error_message = ?, attempts = ? WHERE id = ?");
                    $stmtRetry->execute(['pending', 'Retry attempt ' . $attempts, $attempts, $sms['id']]);
                    $this->log('sms_retry_scheduled', ['queue_id' => $sms['id'], 'to' => $sms['to_phone'], 'attempt' => $attempts]);
                }
            }
        }
        
        return [
            'message' => "Notifications processed: {$processedEmail} emails sent, {$failedEmail} email failures; {$processedSMS} SMS sent, {$failedSMS} SMS failures",
            'data' => [
                'emails_sent' => $processedEmail,
                'emails_failed' => $failedEmail,
                'sms_sent' => $processedSMS,
                'sms_failed' => $failedSMS
            ]
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
        if ($this->hasTable('loan_interest_postings')) {
            $sql = "INSERT INTO loan_interest_postings (loan_id, amount, posting_date, created_at) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$loanId, $amount, $date]);
        } else {
            $this->log('interest_postings_table_missing', ['loan_id' => $loanId, 'amount' => $amount, 'date' => $date]);
        }
        
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
        if (!$this->hasTable('transactions')) {
            $this->log('transactions_table_missing', [
                'context' => 'penalty_charge',
                'loan_id' => $schedule['loan_id'],
                'member_id' => $schedule['member_id'],
                'amount' => $amount,
                'date' => $date
            ]);
            return;
        }
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
        $this->log("penalty_notification", [
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
        $hasStatus = $this->hasColumn('system_jobs', 'status');
        $hasUpdatedAt = $this->hasColumn('system_jobs', 'updated_at');
        $hasExecutedAt = $this->hasColumn('system_jobs', 'executed_at');
        $hasCompletedAt = $this->hasColumn('system_jobs', 'completed_at');
        $hasResultMessage = $this->hasColumn('system_jobs', 'result_message');
        $pkColumn = $this->getJobsPrimaryKey();

        if ($hasStatus) {
            $sets = ["status = ?"];
            $params = [$status];
            if ($hasResultMessage) { $sets[] = "result_message = COALESCE(?, result_message)"; $params[] = $message; }
            if ($hasExecutedAt) { $sets[] = "executed_at = CASE WHEN ? = 'running' THEN NOW() ELSE executed_at END"; $params[] = $status; }
            if ($hasCompletedAt) { $sets[] = "completed_at = CASE WHEN ? IN ('completed', 'failed', 'cancelled') THEN NOW() ELSE completed_at END"; $params[] = $status; }
            if ($hasUpdatedAt) { $sets[] = "updated_at = NOW()"; }
            $sql = "UPDATE system_jobs SET " . implode(', ', $sets) . " WHERE {$pkColumn} = ?";
            $params[] = $jobId;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $fields = [];
            $params = [];
            if ($status === 'running' && $hasExecutedAt) {
                $fields[] = 'executed_at = NOW()';
            } elseif ($status !== 'running' && $hasCompletedAt) {
                $fields[] = 'completed_at = NOW()';
            }
            if ($message !== null && $hasResultMessage) {
                $fields[] = 'result_message = ?';
                $params[] = $message;
            }
            if ($hasUpdatedAt) {
                $fields[] = 'updated_at = NOW()';
            }
            if (empty($fields)) {
                $fields[] = 'id = id'; // no-op to avoid invalid SQL
            }
            $sql = 'UPDATE system_jobs SET ' . implode(', ', $fields) . ' WHERE ' . $pkColumn . ' = ?';
            $params[] = $jobId;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }
    
    /**
     * Schedule a new job
     */
    public function scheduleJob($jobType, $entityId, $scheduledAt, $parameters = [], $priority = 5) {
        try {
            $hasStatus = $this->hasColumn('system_jobs', 'status');
            $hasUpdatedAt = $this->hasColumn('system_jobs', 'updated_at');
            $hasScheduledAt = $this->hasColumn('system_jobs', 'scheduled_at');
            $hasPriority = $this->hasColumn('system_jobs', 'priority');
            $hasCreatedAt = $this->hasColumn('system_jobs', 'created_at');
            $hasEntityId = $this->hasColumn('system_jobs', 'entity_id');
            $hasParameters = $this->hasColumn('system_jobs', 'parameters');
            $hasJobName = $this->hasColumn('system_jobs', 'job_name');
            
            // Compute job_name if column exists
            $jobName = null;
            if ($hasJobName) {
                if ($jobType === 'monthly_interest') {
                    $target = $parameters['target_date'] ?? date('Y-m-01', strtotime($scheduledAt));
                    $jobName = 'monthly_interest_' . date('Ym', strtotime($target));
                } elseif ($jobType === 'penalty_calculation') {
                    $target = $parameters['target_date'] ?? date('Y-m-d', strtotime($scheduledAt));
                    $jobName = 'penalty_calculation_' . date('Ymd', strtotime($target));
                } elseif ($jobType === 'account_maintenance') {
                    $target = date('Y-m-d', strtotime($scheduledAt));
                    $jobName = 'account_maintenance_' . date('Ymd', strtotime($target));
                } else {
                    // Generic naming includes type and timestamp
                    $jobName = $jobType . '_' . date('YmdHis', strtotime($scheduledAt ?? 'now'));
                }
            }

            // Avoid duplicate job_name if already scheduled
            if ($hasJobName && $jobName) {
                $pk = $this->getJobsPrimaryKey();
                $stmtCheck = $this->db->prepare("SELECT {$pk} FROM system_jobs WHERE job_name = ? LIMIT 1");
                $stmtCheck->execute([$jobName]);
                $existingId = $stmtCheck->fetchColumn();
                if ($existingId) {
                    $this->log("job_already_scheduled", [
                        'job_id' => $existingId,
                        'job_type' => $jobType,
                        'job_name' => $jobName,
                        'scheduled_at' => $scheduledAt
                    ]);
                    return $existingId;
                }
            }
            
            // Build insert parts based on existing columns
            $columns = ['job_type'];
            $placeholders = ['?'];
            $values = [$jobType];
            
            if ($hasJobName && $jobName) { $columns[] = 'job_name'; $placeholders[] = '?'; $values[] = $jobName; }
            if ($hasEntityId) { $columns[] = 'entity_id'; $placeholders[] = '?'; $values[] = $entityId; }
            if ($hasParameters) { $columns[] = 'parameters'; $placeholders[] = '?'; $values[] = json_encode($parameters); }
            if ($hasScheduledAt) { $columns[] = 'scheduled_at'; $placeholders[] = '?'; $values[] = $scheduledAt; }
            if ($hasPriority)    { $columns[] = 'priority';     $placeholders[] = '?'; $values[] = $priority; }
            if ($hasStatus)      { $columns[] = 'status';       $placeholders[] = "'pending'"; }
            if ($hasCreatedAt)   { $columns[] = 'created_at';   $placeholders[] = 'NOW()'; }
            if ($hasUpdatedAt)   { $columns[] = 'updated_at';   $placeholders[] = 'NOW()'; }
            
            $sql = 'INSERT INTO system_jobs (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $jobId = $this->db->lastInsertId();
            
            $this->log("job_scheduled", [
                'job_id' => $jobId,
                'job_type' => $jobType,
                'scheduled_at' => $scheduledAt,
                'job_name' => $jobName
            ]);
            
            return $jobId;
            
        } catch (Exception $e) {
            $this->log("job_schedule_failed", [
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
        
        $hasStatus = $this->hasColumn('system_jobs', 'status');
        $hasExecutedAt = $this->hasColumn('system_jobs', 'executed_at');
        $hasCompletedAt = $this->hasColumn('system_jobs', 'completed_at');
        $hasCreatedAt = $this->hasColumn('system_jobs', 'created_at');
        
        $avgSelect = ($hasExecutedAt && $hasCompletedAt)
            ? 'AVG(TIMESTAMPDIFF(SECOND, executed_at, completed_at)) as avg_duration'
            : 'NULL as avg_duration';
        
        if ($hasStatus) {
            $sql = "SELECT 
                        job_type,
                        status,
                        COUNT(*) as count,
                        {$avgSelect}
                    FROM system_jobs " . ($hasCreatedAt ? "WHERE created_at >= ?" : "") .
                    " GROUP BY job_type, status
                    ORDER BY job_type, status";
            $params = $hasCreatedAt ? [$fromDate] : [];
        } else {
            // Derive status if timing columns exist, else mark as 'unknown'
            $statusCase = ($hasExecutedAt || $hasCompletedAt)
                ? "CASE 
                        WHEN completed_at IS NOT NULL THEN 'completed'
                        WHEN executed_at IS NOT NULL THEN 'running'
                        ELSE 'pending'
                   END"
                : "'unknown'";
            $sql = "SELECT 
                        job_type,
                        {$statusCase} AS status,
                        COUNT(*) as count,
                        {$avgSelect}
                    FROM system_jobs " . ($hasCreatedAt ? "WHERE created_at >= ?" : "") .
                    " GROUP BY job_type, " . ($hasExecutedAt || $hasCompletedAt ? $statusCase : "'unknown'") .
                    " ORDER BY job_type, status";
            $params = $hasCreatedAt ? [$fromDate] : [];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cancel pending job
     */
    public function cancelJob($jobId) {
        $hasStatus = $this->hasColumn('system_jobs', 'status');
        $hasUpdatedAt = $this->hasColumn('system_jobs', 'updated_at');
        $hasExecutedAt = $this->hasColumn('system_jobs', 'executed_at');
        $hasCompletedAt = $this->hasColumn('system_jobs', 'completed_at');
        $hasResultMessage = $this->hasColumn('system_jobs', 'result_message');
        $pkColumn = $this->getJobsPrimaryKey();
        
        if ($hasStatus) {
            $sets = ["status = 'cancelled'", "completed_at = NOW()"];
            if ($hasResultMessage) { $sets[] = "result_message = 'Job cancelled'"; }
            if ($hasUpdatedAt) { $sets[] = "updated_at = NOW()"; }
            $sql = "UPDATE system_jobs SET " . implode(', ', $sets) . " WHERE {$pkColumn} = ? AND status = 'pending'";
        } else {
            $sets = ["completed_at = NOW()"]; 
            if ($hasResultMessage) { $sets[] = "result_message = 'Job cancelled'"; }
            if ($hasUpdatedAt) { $sets[] = "updated_at = NOW()"; }
            $sql = "UPDATE system_jobs SET " . implode(', ', $sets) . " WHERE {$pkColumn} = ?";
            if ($hasExecutedAt && $hasCompletedAt) { $sql .= " AND executed_at IS NULL AND completed_at IS NULL"; }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        
        return $stmt->rowCount() > 0;
    }

    private function getParameters(array $job): array {
        try {
            if (!isset($job['parameters']) || $job['parameters'] === null || $job['parameters'] === '') {
                return [];
            }
            $decoded = json_decode($job['parameters'], true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    private function resolveJobId(array $job) {
        // Try detected primary key first
        $pk = $this->getJobsPrimaryKey();
        if (isset($job[$pk])) return $job[$pk];
        // Fallback: any key that looks like an id
        foreach ($job as $k => $v) {
            if (preg_match('/(^id$|_id$)/i', $k)) {
                return $v;
            }
        }
        return null;
    }


    private function processStatementGeneration($job) {
        $parameters = $this->getParameters($job);
        $fromDate = $parameters['from_date'] ?? date('Y-m-01');
        $toDate = $parameters['to_date'] ?? date('Y-m-t');
        $memberId = $parameters['member_id'] ?? null;
        $limit = isset($parameters['limit']) ? (int)$parameters['limit'] : 1000;
        
        if (!$this->hasTable('transactions')) {
            return [
                'message' => 'Transactions table missing. Skipping statement generation.',
                'data' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'count' => 0
                ]
            ];
        }
        
        $where = ['created_at BETWEEN ? AND ?'];
        $params = [$fromDate, $toDate];
        if ($memberId !== null) { $where[] = 'member_id = ?'; $params[] = $memberId; }
        
        // Aggregate transactions for the period
        $sql = "SELECT member_id, COUNT(*) AS txn_count, SUM(amount) AS total_amount
                FROM transactions
                WHERE " . implode(' AND ', $where) . "
                GROUP BY member_id
                ORDER BY member_id ASC
                LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert into account_statements if table exists, else just return data
        if ($this->hasTable('account_statements')) {
            $insert = $this->db->prepare("INSERT INTO account_statements (member_id, from_date, to_date, txn_count, total_amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            foreach ($rows as $r) {
                $insert->execute([$r['member_id'], $fromDate, $toDate, (int)$r['txn_count'], (float)$r['total_amount']]);
            }
        }
        
        return [
            'message' => 'Statements generated for ' . count($rows) . ' member(s) between ' . $fromDate . ' and ' . $toDate,
            'data' => [
                'from' => $fromDate,
                'to' => $toDate,
                'count' => count($rows)
            ]
        ];
    }

    private function processNotificationCleanup($job) {
        $parameters = $this->getParameters($job);
        $cutoffDays = isset($parameters['cutoff_days']) ? (int)$parameters['cutoff_days'] : 30;
        $cutoffDate = date('Y-m-d', strtotime('-' . $cutoffDays . ' days'));
        
        $totalDeleted = 0;
        // Cleanup email_queue
        if ($this->hasTable('email_queue')) {
            $stmt = $this->db->prepare("DELETE FROM email_queue WHERE status IN ('sent','failed') AND created_at < ?");
            $stmt->execute([$cutoffDate]);
            $totalDeleted += $stmt->rowCount();
        }
        // Cleanup sms_queue
        if ($this->hasTable('sms_queue')) {
            $stmt = $this->db->prepare("DELETE FROM sms_queue WHERE status IN ('sent','failed') AND created_at < ?");
            $stmt->execute([$cutoffDate]);
            $totalDeleted += $stmt->rowCount();
        }
        
        return [
            'message' => 'Notification cleanup completed. Deleted ' . $totalDeleted . ' old records prior to ' . $cutoffDate,
            'data' => [
                'deleted' => $totalDeleted,
                'cutoff_date' => $cutoffDate
            ]
        ];
    }

    private function getSavingsSchemaPDO(): array {
        $txTable = 'savings_transactions';
        $acctTable = 'savings_accounts';
        
        $txPk = $this->hasColumn($txTable, 'id') ? 'id' : ($this->hasColumn($txTable, 'transaction_id') ? 'transaction_id' : 'id');
        $txStatus = $this->hasColumn($txTable, 'transaction_status') ? 'transaction_status' : ($this->hasColumn($txTable, 'status') ? 'status' : 'transaction_status');
        $txType = $this->hasColumn($txTable, 'transaction_type') ? 'transaction_type' : ($this->hasColumn($txTable, 'type') ? 'type' : 'transaction_type');
        $txDate = $this->hasColumn($txTable, 'transaction_date') ? 'transaction_date' : ($this->hasColumn($txTable, 'date') ? 'date' : ($this->hasColumn($txTable, 'created_at') ? 'created_at' : 'transaction_date'));
        $txProcessed = $this->hasColumn($txTable, 'processed_at') ? 'processed_at' : ($this->hasColumn($txTable, 'updated_at') ? 'updated_at' : null);
        $txAccountFk = $this->hasColumn($txTable, 'savings_account_id') ? 'savings_account_id' : ($this->hasColumn($txTable, 'account_id') ? 'account_id' : null);
        $txAmount = $this->hasColumn($txTable, 'amount') ? 'amount' : 'amount';
        $txDesc = $this->hasColumn($txTable, 'description') ? 'description' : null;
        
        $acctPk = $this->hasColumn($acctTable, 'id') ? 'id' : ($this->hasColumn($acctTable, 'account_id') ? 'account_id' : 'id');
        $acctBalance = $this->hasColumn($acctTable, 'account_balance') ? 'account_balance' : ($this->hasColumn($acctTable, 'balance') ? 'balance' : 'account_balance');
        
        return [
            'transactions' => [
                'table' => $txTable,
                'pk' => $txPk,
                'status' => $txStatus,
                'type' => $txType,
                'date' => $txDate,
                'processed' => $txProcessed,
                'account_fk' => $txAccountFk,
                'amount' => $txAmount,
                'description' => $txDesc
            ],
            'accounts' => [
                'table' => $acctTable,
                'pk' => $acctPk,
                'balance' => $acctBalance
            ]
        ];
    }

    private function processMonthlySavingsDeposit($job) {
        $parameters = $this->getParameters($job);
        $targetMonth = $parameters['target_month'] ?? date('Y-m'); // e.g., 2025-10
        $autoTag = $parameters['auto_tag'] ?? 'Monthly Deposit';
        $dryRun = isset($parameters['dry_run']) ? (bool)$parameters['dry_run'] : false;
        
        if (!$this->hasTable('savings_transactions')) {
            return [
                'message' => 'Savings transactions table missing. Skipping monthly deposits.',
                'data' => [ 'processed' => 0, 'updated_accounts' => 0 ]
            ];
        }
        
        $schema = $this->getSavingsSchemaPDO();
        $tx = $schema['transactions'];
        $acct = $schema['accounts'];
        
        $statusCol = $tx['status'];
        $typeCol = $tx['type'];
        $dateCol = $tx['date'];
        $processedCol = $tx['processed'];
        $acctFkCol = $tx['account_fk'];
        $amountCol = $tx['amount'];
        $descCol = $tx['description'];
        
        // Build selection criteria for pending monthly deposits in target month
        $where = [
            "{$statusCol} = 'Pending'",
            "{$typeCol} = 'Deposit'",
            "DATE_FORMAT({$dateCol}, '%Y-%m') = ?"
        ];
        $params = [$targetMonth];
        if ($processedCol !== null) { $where[] = "({$processedCol} IS NULL)"; }
        if ($descCol !== null) { $where[] = "({$descCol} IS NULL OR {$descCol} LIKE ? )"; $params[] = "%" . $autoTag . "%"; }
        $whereClause = implode(' AND ', $where);
        
        $selectSql = "SELECT {$tx['pk']} AS tx_id, "
                   . ($acctFkCol ? "{$acctFkCol} AS account_id, " : "NULL AS account_id, ")
                   . "{$amountCol} AS amount FROM {$tx['table']} WHERE {$whereClause} ORDER BY {$dateCol} ASC";
        $stmt = $this->db->prepare($selectSql);
        $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processed = 0;
        $updatedAccounts = 0;
        $errors = [];
        
        foreach ($rows as $r) {
            try {
                if ($dryRun) { $processed++; continue; }
                
                // Mark transaction as Completed
                $updates = ["{$statusCol} = 'Completed'"];
                if ($processedCol !== null) { $updates[] = "{$processedCol} = NOW()"; }
                $updateSql = "UPDATE {$tx['table']} SET " . implode(', ', $updates) . " WHERE {$tx['pk']} = ?";
                $uStmt = $this->db->prepare($updateSql);
                $uStmt->execute([$r['tx_id']]);
                $processed++;
                
                // Update account balance if possible
                if ($acctFkCol && $r['account_id'] !== null && $this->hasTable($acct['table'])) {
                    $balanceCol = $acct['balance'];
                    $pkCol = $acct['pk'];
                    $updAcctSql = "UPDATE {$acct['table']} SET {$balanceCol} = COALESCE({$balanceCol}, 0) + ? WHERE {$pkCol} = ?";
                    $aStmt = $this->db->prepare($updAcctSql);
                    $aStmt->execute([ (float)$r['amount'], $r['account_id'] ]);
                    $updatedAccounts += $aStmt->rowCount() > 0 ? 1 : 0;
                }
            } catch (Exception $e) {
                $errors[] = 'Tx ' . $r['tx_id'] . ': ' . $e->getMessage();
            }
        }
        
        return [
            'message' => "Monthly savings deposits processed: {$processed}, accounts updated: {$updatedAccounts}",
            'data' => [
                'processed' => $processed,
                'updated_accounts' => $updatedAccounts,
                'errors' => $errors,
                'target_month' => $targetMonth,
                'dry_run' => $dryRun
            ]
        ];
    }
}
