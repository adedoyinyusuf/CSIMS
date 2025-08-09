<?php
/**
 * Main Cron Runner
 * 
 * Orchestrates all automated tasks including:

 * - Automated notifications
 * - System maintenance tasks
 */

require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/automated_notifications.php';

class CronRunner {
    private $logFile;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/logs/cron_runner_' . date('Y-m-d') . '.log';
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Run all scheduled tasks
     */
    public function runAllTasks() {
        $this->log('=== CRON RUNNER STARTED ===');
        $startTime = microtime(true);
        
        try {

            // 2. Process automated notifications
            $this->log('Running automated notifications...');
            $this->runAutomatedNotifications();
            
            // 3. Run system maintenance
            $this->log('Running system maintenance...');
            $this->runSystemMaintenance();
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            $this->log('=== CRON RUNNER COMPLETED in ' . $duration . ' seconds ===');
            
        } catch (Exception $e) {
            $this->log('CRON RUNNER ERROR: ' . $e->getMessage());
        }
    }
    

    
    /**
     * Run automated notifications
     */
    private function runAutomatedNotifications() {
        try {
            // Include and run the automated notifications script
            // The script contains a runAutomatedNotifications() function that will be executed
            include_once __DIR__ . '/automated_notifications.php';
            $this->log('Automated notifications completed successfully');
        } catch (Exception $e) {
            $this->log('Automated notifications error: ' . $e->getMessage());
        }
    }
    
    /**
     * Run system maintenance tasks
     */
    private function runSystemMaintenance() {
        try {
            // Clean up old log files (older than 30 days)
            $this->cleanupOldLogs();
            
            // Clean up temporary files
            $this->cleanupTempFiles();
            
            // Update system statistics
            $this->updateSystemStats();
            
            $this->log('System maintenance completed successfully');
        } catch (Exception $e) {
            $this->log('System maintenance error: ' . $e->getMessage());
        }
    }
    
    /**
     * Clean up old log files
     */
    private function cleanupOldLogs() {
        $logDir = __DIR__ . '/logs/';
        
        if (!file_exists($logDir)) {
            return;
        }
        
        $files = glob($logDir . '*.log');
        $cutoffTime = time() - (30 * 24 * 60 * 60); // 30 days ago
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        if ($deletedCount > 0) {
            $this->log('Cleaned up ' . $deletedCount . ' old log files');
        }
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles() {
        $tempDirs = [
            __DIR__ . '/../temp/uploads/',
            __DIR__ . '/../temp/exports/',
            __DIR__ . '/../temp/cache/'
        ];
        
        $cutoffTime = time() - (7 * 24 * 60 * 60); // 7 days ago
        $deletedCount = 0;
        
        foreach ($tempDirs as $dir) {
            if (!file_exists($dir)) {
                continue;
            }
            
            $files = glob($dir . '*');
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
        }
        
        if ($deletedCount > 0) {
            $this->log('Cleaned up ' . $deletedCount . ' temporary files');
        }
    }
    
    /**
     * Update system statistics
     */
    private function updateSystemStats() {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Update member statistics
            $stmt = $db->query("SELECT COUNT(*) as total_members FROM members WHERE status = 'active'");
            $totalMembers = $stmt->fetch_assoc()['total_members'];
            
            // Update contribution statistics
            $stmt = $db->query("SELECT COUNT(*) as total_contributions, SUM(amount) as total_amount FROM contributions");
            $contributionStats = $stmt->fetch_assoc();
            
            // Update loan statistics
            $stmt = $db->query("SELECT COUNT(*) as total_loans, SUM(amount) as total_amount FROM loans");
            $loanStats = $stmt->fetch_assoc();
            
            // Store or update system statistics
            $statsData = [
                'total_members' => $totalMembers,
                'total_contributions' => $contributionStats['total_contributions'],
                'total_contribution_amount' => $contributionStats['total_amount'] ?? 0,
                'total_loans' => $loanStats['total_loans'],
                'total_loan_amount' => $loanStats['total_amount'] ?? 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
            // Check if system_stats table exists, if not create it
            $this->ensureSystemStatsTable($db);
            
            // Insert or update statistics
            $stmt = $db->prepare(
                "INSERT INTO system_stats (stat_date, total_members, total_contributions, total_contribution_amount, total_loans, total_loan_amount, created_at) 
                 VALUES (CURDATE(), ?, ?, ?, ?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                 total_members = VALUES(total_members),
                 total_contributions = VALUES(total_contributions),
                 total_contribution_amount = VALUES(total_contribution_amount),
                 total_loans = VALUES(total_loans),
                 total_loan_amount = VALUES(total_loan_amount),
                 updated_at = NOW()"
            );
            
            $stmt->bind_param(
                'iidid',
                $statsData['total_members'],
                $statsData['total_contributions'],
                $statsData['total_contribution_amount'],
                $statsData['total_loans'],
                $statsData['total_loan_amount']
            );
            
            $stmt->execute();
            
            $this->log('System statistics updated successfully');
            
        } catch (Exception $e) {
            $this->log('Error updating system statistics: ' . $e->getMessage());
        }
    }
    
    /**
     * Ensure system_stats table exists
     */
    public function ensureSystemStatsTable($db) {
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS system_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stat_date DATE UNIQUE,
                total_members INT DEFAULT 0,
                total_contributions INT DEFAULT 0,
                total_contribution_amount DECIMAL(15,2) DEFAULT 0.00,
                total_loans INT DEFAULT 0,
                total_loan_amount DECIMAL(15,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        
        $db->query($createTableSQL);
    }
    
    /**
     * Run specific task by name
     */
    public function runTask($taskName) {
        $this->log('Running specific task: ' . $taskName);
        
        switch ($taskName) {

            case 'notifications':
                $this->runAutomatedNotifications();
                break;
            case 'maintenance':
                $this->runSystemMaintenance();
                break;
            default:
                $this->log('Unknown task: ' . $taskName);
                break;
        }
    }
    
    /**
     * Get cron status and statistics
     */
    public function getStatus() {
        $status = [
            'last_run' => null,
            'next_run' => null,
            'pending_imports' => 0,
            'recent_logs' => []
        ];
        
        // Check for pending imports
        $importDir = __DIR__ . '/../temp/imports/';
        if (file_exists($importDir)) {
            $pendingFiles = glob($importDir . 'import_*.json');
            $status['pending_imports'] = count($pendingFiles);
        }
        
        // Get recent log entries
        if (file_exists($this->logFile)) {
            $logs = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $status['recent_logs'] = array_slice($logs, -10); // Last 10 entries
        }
        
        return $status;
    }
    
    /**
     * Log message to file
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running from command line
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
}

// If running directly from command line
if (php_sapi_name() === 'cli') {
    $runner = new CronRunner();
    
    // Check for specific task argument
    if (isset($argv[1])) {
        $runner->runTask($argv[1]);
    } else {
        $runner->runAllTasks();
    }
}

?>