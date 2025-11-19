<?php
/**
 * CSIMS Job Scheduler Cron Script
 * 
 * This script should be run via cron/task scheduler to process scheduled jobs
 * 
 * Usage:
 * - Add to crontab: * * * * * /usr/bin/php /path/to/CSIMS/cron/run_jobs.php
 * - Or run manually: php run_jobs.php
 * 
 * Recommended schedule:
 * - Every minute for critical jobs: * * * * *
 * - Every 5 minutes for regular jobs: * /5 * * * *
 * - Daily for maintenance: 0 2 * * *
 */

// Prevent web access
if (isset($_SERVER['REQUEST_METHOD'])) {
    die('This script can only be run from command line.');
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron_errors.log');

// Include required files
require_once __DIR__ . '/../classes/JobSchedulerService.php';

require_once __DIR__ . '/../includes/config/database.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting job scheduler...\n";
    
    $jobScheduler = new JobSchedulerService();

    
    // Run pending jobs
    $results = $jobScheduler->runPendingJobs();
    
    if (!empty($results)) {
        echo "[" . date('Y-m-d H:i:s') . "] Processed " . count($results) . " jobs:\n";
        
        foreach ($results as $result) {
            $status = $result['success'] ? 'SUCCESS' : 'FAILED';
            echo "  - Job #{$result['id']} ({$result['job_type']}): {$status} - {$result['message']}\n";
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] No jobs to process\n";
    }
    
    // Schedule recurring jobs if needed
    scheduleRecurringJobs($jobScheduler);
    
    echo "[" . date('Y-m-d H:i:s') . "] Job scheduler completed\n\n";
    
} catch (Exception $e) {
    $error = "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $e->getMessage() . "\n";
    echo $error;
    error_log($error);
    exit(1);
}

/**
 * Schedule recurring jobs
 */
function scheduleRecurringJobs($jobScheduler) {
    try {
        $db = (new PdoDatabase())->getConnection();

        $hasColumn = function($table, $column) use ($db) {
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $stmt->execute([$table, $column]);
                return (int)$stmt->fetchColumn() > 0;
            } catch (\Throwable $e) {
                return true; // assume present to avoid breaking scheduling
            }
        };
        
        $hasStatus = $hasColumn('system_jobs', 'status');
        $hasScheduledAt = $hasColumn('system_jobs', 'scheduled_at');
        $hasCreatedAt = $hasColumn('system_jobs', 'created_at');
        $hasExecutedAt = $hasColumn('system_jobs', 'executed_at');
        $hasCompletedAt = $hasColumn('system_jobs', 'completed_at');
        $hasParameters = $hasColumn('system_jobs', 'parameters');
        $hasJobName = $hasColumn('system_jobs', 'job_name');

        // Check if monthly interest job needs to be scheduled
        $currentMonth = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('+1 month'));
        $monthlyJobName = 'monthly_interest_' . date('Ym', strtotime($nextMonth));
        
        if ($hasJobName) {
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE job_type = 'monthly_interest' AND job_name = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$monthlyJobName]);
        } else {
            $where = "job_type = 'monthly_interest'";
            if ($hasStatus) {
                $where .= " AND status = 'pending'";
            } elseif ($hasExecutedAt && $hasCompletedAt) {
                $where .= " AND (executed_at IS NULL AND completed_at IS NULL)";
            }
            if ($hasParameters) {
                $where .= " AND JSON_EXTRACT(parameters, '$.target_date') = ?";
                $dateFilterParam = $nextMonth;
            } elseif ($hasScheduledAt) {
                $where .= " AND DATE_FORMAT(scheduled_at, '%Y-%m-01') = ?";
                $dateFilterParam = $nextMonth;
            } elseif ($hasCreatedAt) {
                $where .= " AND DATE_FORMAT(created_at, '%Y-%m-01') = ?";
                $dateFilterParam = $nextMonth;
            } else {
                $dateFilterParam = null;
            }
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE {$where}";
            $stmt = $db->prepare($sql);
            $stmt->execute($dateFilterParam !== null ? [$dateFilterParam] : []);
        }
        
        if ($stmt->fetchColumn() == 0) {
            $scheduledAt = $nextMonth . ' 02:00:00';
            $params = ['target_date' => $nextMonth];
            $jobScheduler->scheduleJob('monthly_interest', null, $scheduledAt, $params, 8);
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled monthly interest calculation for {$nextMonth}\n";
        }
        
        // Schedule monthly savings auto-deposits (runs after interest)
        $savingsDepJobName = 'monthly_savings_deposit_' . date('Ym', strtotime($nextMonth));
        if ($hasJobName) {
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE job_type = 'monthly_savings_deposit' AND job_name = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$savingsDepJobName]);
        } else {
            $where = "job_type = 'monthly_savings_deposit'";
            if ($hasStatus) {
                $where .= " AND status = 'pending'";
            } elseif ($hasExecutedAt && $hasCompletedAt) {
                $where .= " AND (executed_at IS NULL AND completed_at IS NULL)";
            }
            if ($hasParameters) {
                $where .= " AND JSON_EXTRACT(parameters, '$.target_date') = ?";
                $dateFilterParam = $nextMonth;
            } elseif ($hasScheduledAt) {
                $where .= " AND DATE_FORMAT(scheduled_at, '%Y-%m-01') = ?";
                $dateFilterParam = $nextMonth;
            } elseif ($hasCreatedAt) {
                $where .= " AND DATE_FORMAT(created_at, '%Y-%m-01') = ?";
                $dateFilterParam = $nextMonth;
            } else {
                $dateFilterParam = null;
            }
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE {$where}";
            $stmt = $db->prepare($sql);
            $stmt->execute($dateFilterParam !== null ? [$dateFilterParam] : []);
        }
        
        if ($stmt->fetchColumn() == 0) {
            $scheduledAt = $nextMonth . ' 02:10:00';
            $params = ['target_date' => $nextMonth, 'require_approval' => true];
            $jobScheduler->scheduleJob('monthly_savings_deposit', $savingsDepJobName, $scheduledAt, $params, 8);
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled monthly savings auto-deposit for {$nextMonth}\n";
        }
        
        // Penalty calculation (daily)
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $penaltyJobName = 'penalty_calculation_' . date('Ymd', strtotime($tomorrow));
        if ($hasJobName) {
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE job_type = 'penalty_calculation' AND job_name = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$penaltyJobName]);
        } else {
            $where = "job_type = 'penalty_calculation'";
            if ($hasStatus) {
                $where .= " AND status = 'pending'";
            } elseif ($hasExecutedAt && $hasCompletedAt) {
                $where .= " AND (executed_at IS NULL AND completed_at IS NULL)";
            }
            if ($hasScheduledAt) {
                $where .= " AND DATE(scheduled_at) = ?";
            } elseif ($hasCreatedAt) {
                $where .= " AND DATE(created_at) = ?";
            } else {
                $where .= " AND 1 = 1"; // no date filter available
            }
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE {$where}";
            $stmt = $db->prepare($sql);
            $stmt->execute([$tomorrow]);
        }
        if ($stmt->fetchColumn() == 0) {
            $scheduledAt = $tomorrow . ' 03:00:00';
            $jobScheduler->scheduleJob('penalty_calculation', null, $scheduledAt, ['target_date' => $tomorrow], 7);
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled penalty calculation for {$tomorrow}\n";
        }
        
        // Account maintenance (weekly)
        $nextSunday = date('Y-m-d', strtotime('next sunday'));
        $acctMaintJobName = 'account_maintenance_' . date('Ymd', strtotime($nextSunday));
        if ($hasJobName) {
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE job_type = 'account_maintenance' AND job_name = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$acctMaintJobName]);
        } else {
            $where = "job_type = 'account_maintenance'";
            if ($hasStatus) {
                $where .= " AND status = 'pending'";
            } elseif ($hasExecutedAt && $hasCompletedAt) {
                $where .= " AND (executed_at IS NULL AND completed_at IS NULL)";
            }
            if ($hasScheduledAt) {
                $where .= " AND DATE(scheduled_at) = ?";
            } elseif ($hasCreatedAt) {
                $where .= " AND DATE(created_at) = ?";
            } else {
                $where .= " AND 1 = 1";
            }
            $sql = "SELECT COUNT(*) FROM system_jobs WHERE {$where}";
            $stmt = $db->prepare($sql);
            $stmt->execute([$nextSunday]);
        }
        if ($stmt->fetchColumn() == 0) {
            $scheduledAt = $nextSunday . ' 01:00:00';
            $jobScheduler->scheduleJob('account_maintenance', null, $scheduledAt, [
                'tasks' => ['cleanup_logs','update_credit_scores','archive_old_data']
            ], 3);
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled account maintenance for {$nextSunday}\n";
        }
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error scheduling recurring jobs: " . $e->getMessage() . "\n";
    }
}

/**
 * Send completion email to admin (optional)
 */
function sendCompletionReport($results) {
    if (empty($results)) return;
    
    try {
        require_once __DIR__ . '/../classes/NotificationService.php';
        
        $notificationService = new NotificationService();
        
        $successful = array_filter($results, function($r) { return $r['success']; });
        $failed = array_filter($results, function($r) { return !$r['success']; });
        
        $subject = "CSIMS Job Scheduler Report - " . date('Y-m-d H:i:s');
        
        $body = "
        <h3>Job Scheduler Execution Report</h3>
        <p><strong>Execution Time:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Total Jobs:</strong> " . count($results) . "</p>
        <p><strong>Successful:</strong> " . count($successful) . "</p>
        <p><strong>Failed:</strong> " . count($failed) . "</p>
        ";
        
        if (!empty($failed)) {
            $body .= "<h4>Failed Jobs:</h4><ul>";
            foreach ($failed as $job) {
                $body .= "<li>Job #{$job['id']} ({$job['job_type']}): {$job['message']}</li>";
            }
            $body .= "</ul>";
        }
        
        // Send to admin email (configure as needed)
        $adminEmail = 'admin@csims.local';
        
        // Uncomment to enable email reports
        // $notificationService->sendEmail($adminEmail, $subject, $body);
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error sending completion report: " . $e->getMessage() . "\n";
    }
}
?>