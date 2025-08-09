<?php
/**
 * Notification Trigger Runner
 * 
 * This script runs automated notification triggers that are due for execution.
 * Should be run via cron job every few minutes (e.g., every 5 minutes)
 * 
 * Usage:
 * - Via cron: 0/5 * * * * php /path/to/notification_trigger_runner.php
 * - Via command line: php notification_trigger_runner.php
 * - Via web (for testing): http://yoursite.com/cron/notification_trigger_runner.php
 */

// Prevent direct web access in production (optional)
if (isset($_SERVER['HTTP_HOST']) && !isset($_GET['allow_web'])) {
    http_response_code(403);
    die('Direct web access not allowed. Use cron job or add ?allow_web=1 for testing.');
}

// Set up environment for CLI execution
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '/cron/notification_trigger_runner.php';
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Create PDO connection for NotificationTriggerController
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Load notification config only if constants aren't already defined
if (!defined('EMAIL_ENABLED')) {
    require_once __DIR__ . '/../config/notification_config.php';
}

// Include required controller
require_once __DIR__ . '/../controllers/notification_trigger_controller.php';

// Set time limit and memory limit for long-running processes
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Initialize services
$triggerController = new NotificationTriggerController();

// Load configuration from constants
$config = [
    'scheduling' => [
        'cron_enabled' => defined('CRON_ENABLED') ? CRON_ENABLED : true,
        'batch_size' => defined('CRON_BATCH_SIZE') ? CRON_BATCH_SIZE : 10,
        'delay_between_batches' => defined('CRON_DELAY_BETWEEN_BATCHES') ? CRON_DELAY_BETWEEN_BATCHES : 5,
        'cleanup_logs_after_days' => defined('CLEANUP_LOGS_AFTER_DAYS') ? CLEANUP_LOGS_AFTER_DAYS : 90
    ],
    'security' => [
        'rate_limiting' => [
            'enabled' => defined('RATE_LIMITING_ENABLED') ? RATE_LIMITING_ENABLED : true,
            'max_emails_per_minute' => defined('MAX_EMAILS_PER_MINUTE') ? MAX_EMAILS_PER_MINUTE : 10,
            'max_sms_per_minute' => defined('MAX_SMS_PER_MINUTE') ? MAX_SMS_PER_MINUTE : 5,
            'cooldown_period' => defined('COOLDOWN_PERIOD') ? COOLDOWN_PERIOD : 300
        ]
    ]
];

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
    
    $cleanupDays = $config['scheduling']['cleanup_logs_after_days'] ?? 90;
    $logDir = __DIR__ . '/../logs';
    
    if (!is_dir($logDir)) {
        return;
    }
    
    $cutoffTime = time() - ($cleanupDays * 24 * 60 * 60);
    
    $files = glob($logDir . '/*.log');
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
            logMessage('Cleaned up old log file: ' . basename($file));
        }
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
    $currentTime = time();
    $rateData = [];
    
    if (file_exists($rateLimitFile)) {
        $rateData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    
    // Clean old entries
    $rateData = array_filter($rateData, function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < 60; // Keep last minute
    });
    
    $emailCount = count(array_filter($rateData, function($entry) {
        return isset($entry['type']) && $entry['type'] === 'email';
    }));
    
    $smsCount = count(array_filter($rateData, function($entry) {
        return isset($entry['type']) && $entry['type'] === 'sms';
    }));
    
    $maxEmails = $config['security']['rate_limiting']['max_emails_per_minute'];
    $maxSms = $config['security']['rate_limiting']['max_sms_per_minute'];
    
    if ($emailCount >= $maxEmails || $smsCount >= $maxSms) {
        logMessage('Rate limit exceeded. Emails: ' . $emailCount . '/' . $maxEmails . ', SMS: ' . $smsCount . '/' . $maxSms, 'WARNING');
        return false;
    }
    
    return true;
}

/**
 * Health check function
 */
function performHealthCheck() {
    global $triggerController, $pdo;
    
    logMessage('Performing health check');
    
    try {
        // Database connectivity check
        $pdo->query('SELECT 1');
        logMessage('Database connection: OK');
        
        // Get trigger statistics
        $stats = $triggerController->getTriggerStats();
        logMessage('Trigger stats - Total: ' . ($stats['total_triggers'] ?? 0) . 
                  ', Active: ' . ($stats['active_triggers'] ?? 0) . 
                  ', Due: ' . ($stats['due_triggers'] ?? 0));
        
        // Check email configuration
        if (defined('EMAIL_ENABLED') && EMAIL_ENABLED) {
            logMessage('Email service: Enabled');
        } else {
            logMessage('Email service: Disabled');
        }
        
        // Check SMS configuration
        if (defined('SMS_ENABLED') && SMS_ENABLED) {
            logMessage('SMS service: Enabled');
        } else {
            logMessage('SMS service: Disabled');
        }
        
        return true;
        
    } catch (Exception $e) {
        logMessage('Health check failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Send daily status report
 */
function sendDailyStatusReport() {
    global $triggerController;
    
    // Only send report once per day
    $lastReportFile = __DIR__ . '/../logs/last_daily_report.txt';
    $today = date('Y-m-d');
    
    if (file_exists($lastReportFile) && file_get_contents($lastReportFile) === $today) {
        return;
    }
    
    try {
        $stats = $triggerController->getTriggerStats();
        
        $report = "Daily Notification System Status Report - " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "Total Triggers: " . ($stats['total_triggers'] ?? 0) . "\n";
        $report .= "Active Triggers: " . ($stats['active_triggers'] ?? 0) . "\n";
        $report .= "Due Triggers: " . ($stats['due_triggers'] ?? 0) . "\n";
        $report .= "Executions Today: " . ($stats['executions_today'] ?? 0) . "\n";
        
        logMessage('Daily status report generated');
        
        // Mark report as sent
        file_put_contents($lastReportFile, $today);
        
    } catch (Exception $e) {
        logMessage('Error generating daily status report: ' . $e->getMessage(), 'ERROR');
    }
}

/**
 * Log system resource usage
 */
function logResourceUsage() {
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    logMessage('Resource usage - Memory: ' . formatBytes($memoryUsage) . 
              ' (Peak: ' . formatBytes($memoryPeak) . 
              ', Limit: ' . $memoryLimit . ')');
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Emergency stop function
 */
function emergencyStop($reason = 'Unknown') {
    logMessage('EMERGENCY STOP: ' . $reason, 'CRITICAL');
    
    // Remove lock file
    $lockFile = __DIR__ . '/../logs/notification_trigger_runner.lock';
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
    
    exit(1);
}

// Signal handlers for graceful shutdown (if available)
if (extension_loaded('pcntl')) {
    pcntl_signal(SIGTERM, function() {
        logMessage('Received SIGTERM, shutting down gracefully');
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() {
        logMessage('Received SIGINT, shutting down gracefully');
        exit(0);
    });
}

// Main execution
try {
    // Check if another instance is running
    if (!checkLockFile()) {
        exit(1);
    }
    
    // Perform health check
    if (!performHealthCheck()) {
        emergencyStop('Health check failed');
    }
    
    // Check rate limiting
    if (!checkRateLimit()) {
        logMessage('Rate limit exceeded, skipping this run', 'WARNING');
        exit(0);
    }
    
    // Run notification triggers
    runNotificationTriggers();
    
    // Send daily status report
    sendDailyStatusReport();
    
    // Log resource usage
    logResourceUsage();
    
    logMessage('Notification trigger runner completed successfully');
    
} catch (Exception $e) {
    logMessage('Fatal error in main execution: ' . $e->getMessage(), 'CRITICAL');
    emergencyStop('Fatal error: ' . $e->getMessage());
} catch (Error $e) {
    logMessage('Fatal PHP error: ' . $e->getMessage(), 'CRITICAL');
    emergencyStop('Fatal PHP error: ' . $e->getMessage());
}

?>