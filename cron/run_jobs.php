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
 * - Every 5 minutes for regular jobs: */5 * * * *
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
require_once __DIR__ . '/../classes/LogService.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting job scheduler...\n";
    
    $jobScheduler = new JobSchedulerService();
    $logService = new LogService();
    
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
        $db = DatabaseConnection::getInstance()->getConnection();
        
        // Check if monthly interest job needs to be scheduled
        $currentMonth = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('+1 month'));
        
        $sql = "SELECT COUNT(*) FROM system_jobs 
                WHERE job_type = 'monthly_interest' 
                AND status = 'pending' 
                AND JSON_EXTRACT(parameters, '$.target_date') = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$nextMonth]);
        
        if ($stmt->fetchColumn() == 0) {
            // Schedule next month's interest calculation
            $scheduledAt = $nextMonth . ' 02:00:00'; // 2 AM on first day of month
            
            $jobScheduler->scheduleJob(
                'monthly_interest',
                null,
                $scheduledAt,
                ['target_date' => $nextMonth],
                8 // High priority
            );
            
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled monthly interest calculation for {$nextMonth}\n";
        }
        
        // Check if penalty calculation job needs to be scheduled (daily)
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $sql = "SELECT COUNT(*) FROM system_jobs 
                WHERE job_type = 'penalty_calculation' 
                AND status = 'pending' 
                AND DATE(scheduled_at) = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$tomorrow]);
        
        if ($stmt->fetchColumn() == 0) {
            // Schedule tomorrow's penalty calculation
            $scheduledAt = $tomorrow . ' 03:00:00'; // 3 AM daily
            
            $jobScheduler->scheduleJob(
                'penalty_calculation',
                null,
                $scheduledAt,
                ['target_date' => $tomorrow],
                7 // High priority
            );
            
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled penalty calculation for {$tomorrow}\n";
        }
        
        // Check if account maintenance job needs to be scheduled (weekly)
        $nextSunday = date('Y-m-d', strtotime('next sunday'));
        
        $sql = "SELECT COUNT(*) FROM system_jobs 
                WHERE job_type = 'account_maintenance' 
                AND status = 'pending' 
                AND DATE(scheduled_at) = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$nextSunday]);
        
        if ($stmt->fetchColumn() == 0) {
            // Schedule weekly maintenance
            $scheduledAt = $nextSunday . ' 01:00:00'; // 1 AM on Sunday
            
            $jobScheduler->scheduleJob(
                'account_maintenance',
                null,
                $scheduledAt,
                [
                    'tasks' => [
                        'cleanup_logs',
                        'update_credit_scores', 
                        'archive_old_data'
                    ]
                ],
                3 // Lower priority
            );
            
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