<?php
/**
 * Notification Trigger Runner
 * 
 * This script runs automated notification triggers that are due for execution.
 * Should be run via cron job every few minutes (e.g., every 5 minutes)
 * 
 * Usage:
 * - Via cron: *\/5 * * * * php /path/to/notification_trigger_runner.php
 * - Via command line: php notification_trigger_runner.php
 * - Via web (for testing): http://yoursite.com/cron/notification_trigger_runner.php
 */

// Prevent direct web access in production (optional)
if (isset($_SERVER['HTTP_HOST']) && !isset($_GET['allow_web'])) {
    http_response_code(403);
    die('Direct web access not allowed. Use cron job or add ?allow_web=1 for testing.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/notification_trigger_controller.php';
require_once __DIR__ . '/../config/notification_config.php';

// Set time limit and memory limit for long-running processes
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Initialize services
$triggerController = new NotificationTriggerController();
$config = require __DIR__ . '/../config/notification_config.php';

// Log file for cron execution
$logFile = __DIR__ . '/../logs/notification_trigger_runner.log';

// Ensure log directory exists
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

/**
 * Log function
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

/**
 * Check if another instance is already running
 */
function checkLockFile() {
    $lockFile = __DIR__ . '/../logs/notification_trigger_runner.lock';
    
    if (file_exists($lockFile)) {
        $pid = file_get_contents($lockFile);
        
        // Check if process is still running (Unix/Linux)
        if (function_exists('posix_kill') && posix_kill($pid, 0)) {
            logMessage('Another instance is already running (PID: ' . $pid . ')', 'WARNING');
            return false;
        }
        
        // Remove stale lock file
        unlink($lockFile);
    }
    
    // Create new lock file
    file_put_contents($lockFile, getmypid());
    
    // Register shutdown function to remove lock file
    register_shutdown_function(function() use ($lockFile) {
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    });
    
    return true;
}

/**
 * Main execution function
 */
function runNotificationTriggers() {
    global $triggerController, $config;
    
    logMessage('Starting notification trigger runner');
    
    try {
        // Check if cron is enabled in configuration
        if (!$config['scheduling']['cron_enabled']) {
            logMessage('Notification triggers are disabled in configuration', 'WARNING');
            return;
        }
        
        // Get due triggers
        $dueTriggers = $triggerController->getDueTriggers();
        
        if (empty($dueTriggers)) {
            logMessage('No triggers due for execution');
            return;
        }
        
        logMessage('Found ' . count($dueTriggers) . ' triggers due for execution');
        
        $successCount = 0;
        $errorCount = 0;
        $batchSize = $config['scheduling']['batch_size'] ?? 10;
        $delayBetweenBatches = $config['scheduling']['delay_between_batches'] ?? 5;
        
        // Process triggers in batches to avoid overwhelming the system
        $batches = array_chunk($dueTriggers, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            logMessage('Processing batch ' . ($batchIndex + 1) . ' of ' . count($batches) . ' (' . count($batch) . ' triggers)');
            
            foreach ($batch as $trigger) {
                try {
                    $startTime = microtime(true);
                    
                    logMessage('Executing trigger: ' . $trigger['name'] . ' (ID: ' . $trigger['id'] . ')');
                    
                    $success = $triggerController->executeTrigger($trigger['id']);
                    
                    $executionTime = microtime(true) - $startTime;
                    
                    if ($success) {
                        $successCount++;
                        logMessage('Trigger executed successfully in ' . round($executionTime, 3) . ' seconds');
                    } else {
                        $errorCount++;
                        logMessage('Trigger execution failed', 'ERROR');
                    }
                    
                } catch (Exception $e) {
                    $errorCount++;
                    logMessage('Error executing trigger ' . $trigger['id'] . ': ' . $e->getMessage(), 'ERROR');
                }
                
                // Small delay between triggers to avoid overwhelming email/SMS services
                usleep(500000); // 0.5 seconds
            }
            
            // Delay between batches if not the last batch
            if ($batchIndex < count($batches) - 1) {
                logMessage('Waiting ' . $delayBetweenBatches . ' seconds before next batch');
                sleep($delayBetweenBatches);
            }
        }
        
        logMessage('Notification trigger runner completed. Success: ' . $successCount . ', Errors: ' . $errorCount);
        
        // Cleanup old logs if configured
        cleanupOldLogs();
        
    } catch (Exception $e) {
        logMessage('Fatal error in notification trigger runner: ' . $e->getMessage(), 'ERROR');
    }
}

/**
 * Cleanup old log files
 */
function cleanupOldLogs() {
    global $config;
    
    try {
        $cleanupDays = $config['scheduling']['cleanup_logs_after_days'] ?? 90;
        $logDir = __DIR__ . '/../logs/';
        
        if (!is_dir($logDir)) {
            return;
        }
        
        $files = glob($logDir . '*.log');
        $cutoffTime = time() - ($cleanupDays * 24 * 60 * 60);
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        if ($deletedCount > 0) {
            logMessage('Cleaned up ' . $deletedCount . ' old log files');
        }
        
    } catch (Exception $e) {
        logMessage('Error during log cleanup: ' . $e->getMessage(), 'WARNING');
    }
}

/**
 * Rate limiting check
 */
function checkRateLimit() {
    global $config;
    
    if (!$config['security']['rate_limiting']['enabled']) {
        return true;
    }
    
    $rateLimitFile = __DIR__ . '/../logs/rate_limit.json';
    $maxEmailsPerMinute = $config['security']['rate_limiting']['max_emails_per_minute'] ?? 10;
    $maxSmsPerMinute = $config['security']['rate_limiting']['max_sms_per_minute'] ?? 5;
    $cooldownPeriod = $config['security']['rate_limiting']['cooldown_period'] ?? 300;
    
    $currentTime = time();
    $rateData = [];
    
    if (file_exists($rateLimitFile)) {
        $rateData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    
    // Clean old entries
    $rateData = array_filter($rateData, function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < 60; // Keep last minute
    });
    
    // Check if we're within limits
    $emailCount = count(array_filter($rateData, function($entry) {
        return $entry['type'] === 'email';
    }));
    
    $smsCount = count(array_filter($rateData, function($entry) {
        return $entry['type'] === 'sms';
    }));
    
    if ($emailCount >= $maxEmailsPerMinute || $smsCount >= $maxSmsPerMinute) {
        logMessage('Rate limit exceeded. Emails: ' . $emailCount . '/' . $maxEmailsPerMinute . ', SMS: ' . $smsCount . '/' . $maxSmsPerMinute, 'WARNING');
        return false;
    }
    
    return true;
}

/**
 * Health check function
 */
function performHealthCheck() {
    global $triggerController;
    
    try {
        // Check database connection
        $stats = $triggerController->getTriggerStats();
        
        // Check if email service is configured
        $emailConfigured = defined('EMAIL_ENABLED') && EMAIL_ENABLED;
        
        // Check if SMS service is configured
        $smsConfigured = defined('SMS_ENABLED') && SMS_ENABLED;
        
        logMessage('Health check - DB: OK, Email: ' . ($emailConfigured ? 'OK' : 'NOT CONFIGURED') . ', SMS: ' . ($smsConfigured ? 'OK' : 'NOT CONFIGURED'));
        
        return true;
        
    } catch (Exception $e) {
        logMessage('Health check failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Send status report (optional)
 */
function sendStatusReport() {
    global $triggerController, $config;
    
    try {
        // Only send status report once per day
        $statusFile = __DIR__ . '/../logs/last_status_report.txt';
        $lastReport = file_exists($statusFile) ? file_get_contents($statusFile) : '0';
        
        if ((time() - intval($lastReport)) < 86400) { // 24 hours
            return;
        }
        
        $stats = $triggerController->getTriggerStats();
        
        // Create status report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_triggers' => $stats['total_triggers'] ?? 0,
            'active_triggers' => $stats['active_triggers'] ?? 0,
            'due_triggers' => $stats['due_triggers'] ?? 0,
            'executions_today' => $stats['executions_today'] ?? 0
        ];
        
        logMessage('Daily status report: ' . json_encode($report));
        
        // Update last report time
        file_put_contents($statusFile, time());
        
    } catch (Exception $e) {
        logMessage('Error generating status report: ' . $e->getMessage(), 'WARNING');
    }
}

// Main execution
if (!checkLockFile()) {
    exit(1);
}

try {
    // Perform health check
    if (!performHealthCheck()) {
        logMessage('Health check failed, aborting execution', 'ERROR');
        exit(1);
    }
    
    // Check rate limits
    if (!checkRateLimit()) {
        logMessage('Rate limit exceeded, skipping execution', 'WARNING');
        exit(0);
    }
    
    // Run notification triggers
    runNotificationTriggers();
    
    // Send daily status report
    sendStatusReport();
    
} catch (Exception $e) {
    logMessage('Unexpected error: ' . $e->getMessage(), 'ERROR');
    exit(1);
}

exit(0);

?>

<?php
/**
 * Additional utility functions for the notification trigger runner
 */

/**
 * Get system resource usage
 */
function getSystemUsage() {
    $usage = [
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
    ];
    
    return $usage;
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Log system resource usage
 */
function logSystemUsage() {
    $usage = getSystemUsage();
    $message = sprintf(
        'System usage - Memory: %s (Peak: %s), Execution time: %.3f seconds',
        formatBytes($usage['memory_usage']),
        formatBytes($usage['memory_peak']),
        $usage['execution_time']
    );
    
    logMessage($message, 'DEBUG');
}

/**
 * Emergency stop function
 */
function emergencyStop($reason = 'Unknown') {
    logMessage('EMERGENCY STOP: ' . $reason, 'CRITICAL');
    
    // Send alert to administrators if configured
    // This could be implemented to send immediate notifications
    
    exit(1);
}

/**
 * Signal handler for graceful shutdown
 */
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function($signal) {
        logMessage('Received SIGTERM, shutting down gracefully', 'INFO');
        exit(0);
    });
    
    pcntl_signal(SIGINT, function($signal) {
        logMessage('Received SIGINT, shutting down gracefully', 'INFO');
        exit(0);
    });
}

?>