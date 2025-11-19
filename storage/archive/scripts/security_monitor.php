<?php
/**
 * Security Monitoring Script
 * 
 * This script performs automated security checks and sends alerts
 * Run this script via cron job or task scheduler for continuous monitoring
 * 
 * Usage: php security_monitor.php [--email] [--verbose]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/security_controller.php';
require_once __DIR__ . '/../controllers/notification_controller.php';

class SecurityMonitor {
    private $db;
    private $securityController;
    private $notificationController;
    private $verbose;
    private $sendEmail;
    private $alerts;
    
    public function __construct($verbose = false, $sendEmail = false) {
        $this->db = Database::getInstance()->getConnection();
        $this->securityController = new SecurityController();
        $this->notificationController = new NotificationController();
        $this->verbose = $verbose;
        $this->sendEmail = $sendEmail;
        $this->alerts = [];
    }
    
    /**
     * Run all security checks
     */
    public function runSecurityChecks() {
        $this->log("Starting security monitoring checks...");
        
        // Check for suspicious activities
        $this->checkSuspiciousActivities();
        
        // Check for failed login attempts
        $this->checkFailedLogins();
        
        // Check for locked accounts
        $this->checkLockedAccounts();
        
        // Check for inactive admin accounts
        $this->checkInactiveAdmins();
        
        // Check for users without 2FA
        $this->checkUsers2FA();
        
        // Check for weak passwords
        $this->checkWeakPasswords();
        
        // Check system security settings
        $this->checkSystemSecurity();
        
        // Check for unusual login patterns
        $this->checkUnusualLoginPatterns();
        
        // Generate summary report
        $this->generateReport();
        
        $this->log("Security monitoring checks completed.");
    }
    
    /**
     * Check for suspicious activities in the last hour
     */
    private function checkSuspiciousActivities() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as suspicious_count,
                       GROUP_CONCAT(DISTINCT ip_address) as suspicious_ips
                FROM security_logs 
                WHERE event_type = 'suspicious_activity' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['suspicious_count'] > 10) {
                $this->addAlert('HIGH', 'Suspicious Activity Spike', 
                    "Detected {$result['suspicious_count']} suspicious activities in the last hour from IPs: {$result['suspicious_ips']}");
            }
            
            $this->log("Suspicious activities check: {$result['suspicious_count']} events found");
            
        } catch (Exception $e) {
            $this->log("Error checking suspicious activities: " . $e->getMessage());
        }
    }
    
    /**
     * Check for excessive failed login attempts
     */
    private function checkFailedLogins() {
        try {
            $stmt = $this->db->prepare("
                SELECT ip_address, COUNT(*) as failed_count
                FROM security_logs 
                WHERE event_type = 'failed_login' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY ip_address
                HAVING failed_count > 20
                ORDER BY failed_count DESC
            ");
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            foreach ($results as $result) {
                $this->addAlert('MEDIUM', 'Brute Force Attack Detected', 
                    "IP {$result['ip_address']} has {$result['failed_count']} failed login attempts in the last hour");
            }
            
            $this->log("Failed logins check: " . count($results) . " suspicious IPs found");
            
        } catch (Exception $e) {
            $this->log("Error checking failed logins: " . $e->getMessage());
        }
    }
    
    /**
     * Check for locked accounts that need attention
     */
    private function checkLockedAccounts() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as locked_count
                FROM users 
                WHERE account_locked = 1
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['locked_count'] > 5) {
                $this->addAlert('MEDIUM', 'Multiple Locked Accounts', 
                    "There are {$result['locked_count']} locked accounts that may need review");
            }
            
            $this->log("Locked accounts check: {$result['locked_count']} accounts locked");
            
        } catch (Exception $e) {
            $this->log("Error checking locked accounts: " . $e->getMessage());
        }
    }
    
    /**
     * Check for inactive admin accounts
     */
    private function checkInactiveAdmins() {
        try {
            $stmt = $this->db->prepare("
                SELECT username, last_login
                FROM users 
                WHERE role IN ('admin', 'super_admin') 
                AND (last_login < DATE_SUB(NOW(), INTERVAL 30 DAY) OR last_login IS NULL)
            ");
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            if (!empty($results)) {
                $usernames = array_column($results, 'username');
                $this->addAlert('LOW', 'Inactive Admin Accounts', 
                    "Admin accounts inactive for 30+ days: " . implode(', ', $usernames));
            }
            
            $this->log("Inactive admins check: " . count($results) . " inactive admin accounts found");
            
        } catch (Exception $e) {
            $this->log("Error checking inactive admins: " . $e->getMessage());
        }
    }
    
    /**
     * Check for users without 2FA
     */
    private function checkUsers2FA() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as no_2fa_count
                FROM users 
                WHERE role IN ('admin', 'staff') 
                AND (two_factor_enabled = 0 OR two_factor_enabled IS NULL)
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['no_2fa_count'] > 0) {
                $this->addAlert('LOW', 'Users Without 2FA', 
                    "{$result['no_2fa_count']} admin/staff users don't have 2FA enabled");
            }
            
            $this->log("2FA check: {$result['no_2fa_count']} users without 2FA");
            
        } catch (Exception $e) {
            $this->log("Error checking 2FA status: " . $e->getMessage());
        }
    }
    
    /**
     * Check for weak passwords
     */
    private function checkWeakPasswords() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as weak_password_count
                FROM users 
                WHERE password_updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
                OR password_updated_at IS NULL
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['weak_password_count'] > 0) {
                $this->addAlert('MEDIUM', 'Outdated Passwords', 
                    "{$result['weak_password_count']} users have passwords older than 90 days");
            }
            
            $this->log("Weak passwords check: {$result['weak_password_count']} outdated passwords found");
            
        } catch (Exception $e) {
            $this->log("Error checking weak passwords: " . $e->getMessage());
        }
    }
    
    /**
     * Check system security settings
     */
    private function checkSystemSecurity() {
        $issues = [];
        
        // Check if HTTPS is enforced
        if (!defined('FORCE_HTTPS') || !FORCE_HTTPS) {
            $issues[] = 'HTTPS not enforced';
        }
        
        // Check if secure cookies are enabled
        if (!defined('SECURE_COOKIES') || !SECURE_COOKIES) {
            $issues[] = 'Secure cookies not enabled';
        }
        
        // Check session settings
        if (ini_get('session.cookie_httponly') != '1') {
            $issues[] = 'HTTP-only cookies not enabled';
        }
        
        if (!empty($issues)) {
            $this->addAlert('MEDIUM', 'System Security Issues', 
                'Security configuration issues: ' . implode(', ', $issues));
        }
        
        $this->log("System security check: " . count($issues) . " issues found");
    }
    
    /**
     * Check for unusual login patterns
     */
    private function checkUnusualLoginPatterns() {
        try {
            // Check for logins from new countries/IPs
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ip_address) as new_ips
                FROM security_logs 
                WHERE event_type = 'user_login' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND ip_address NOT IN (
                    SELECT DISTINCT ip_address 
                    FROM security_logs 
                    WHERE event_type = 'user_login' 
                    AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['new_ips'] > 5) {
                $this->addAlert('LOW', 'New Login Locations', 
                    "Detected {$result['new_ips']} new IP addresses in login attempts in the last 24 hours");
            }
            
            $this->log("Unusual login patterns check: {$result['new_ips']} new IPs found");
            
        } catch (Exception $e) {
            $this->log("Error checking unusual login patterns: " . $e->getMessage());
        }
    }
    
    /**
     * Add security alert
     */
    private function addAlert($severity, $title, $message) {
        $this->alerts[] = [
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Log to security logs
        $this->securityController->logSecurityEvent(
            'security_monitor_alert', 
            "[{$severity}] {$title}: {$message}", 
            null, 
            null, 
            strtolower($severity)
        );
        
        $this->log("ALERT [{$severity}] {$title}: {$message}");
    }
    
    /**
     * Generate and send security report
     */
    private function generateReport() {
        if (empty($this->alerts)) {
            $this->log("No security alerts to report.");
            return;
        }
        
        $report = "Security Monitoring Report - " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat('=', 50) . "\n\n";
        
        $severityCounts = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        
        foreach ($this->alerts as $alert) {
            $severityCounts[$alert['severity']]++;
            $report .= "[{$alert['severity']}] {$alert['title']}\n";
            $report .= "Time: {$alert['timestamp']}\n";
            $report .= "Details: {$alert['message']}\n\n";
        }
        
        $summary = "Summary: {$severityCounts['HIGH']} High, {$severityCounts['MEDIUM']} Medium, {$severityCounts['LOW']} Low priority alerts\n\n";
        $report = $summary . $report;
        
        // Save report to file
        $reportFile = __DIR__ . '/../logs/security_reports/security_report_' . date('Y-m-d_H-i-s') . '.txt';
        $reportDir = dirname($reportFile);
        
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        file_put_contents($reportFile, $report);
        $this->log("Security report saved to: {$reportFile}");
        
        // Send email if requested and there are high/medium severity alerts
        if ($this->sendEmail && ($severityCounts['HIGH'] > 0 || $severityCounts['MEDIUM'] > 0)) {
            $this->sendEmailAlert($report, $severityCounts);
        }
    }
    
    /**
     * Send email alert
     */
    private function sendEmailAlert($report, $severityCounts) {
        try {
            $subject = "Security Alert - CSIMS System";
            if ($severityCounts['HIGH'] > 0) {
                $subject .= " [HIGH PRIORITY]";
            }
            
            $adminEmails = $this->getAdminEmails();
            
            foreach ($adminEmails as $email) {
                $this->notificationController->sendEmail(
                    $email,
                    $subject,
                    $report,
                    'Security Monitor'
                );
            }
            
            $this->log("Email alerts sent to " . count($adminEmails) . " administrators");
            
        } catch (Exception $e) {
            $this->log("Error sending email alerts: " . $e->getMessage());
        }
    }
    
    /**
     * Get admin email addresses
     */
    private function getAdminEmails() {
        try {
            $stmt = $this->db->prepare("
                SELECT email 
                FROM users 
                WHERE role IN ('admin', 'super_admin') 
                AND email IS NOT NULL 
                AND email != ''
            ");
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            return array_column($results, 'email');
            
        } catch (Exception $e) {
            $this->log("Error getting admin emails: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        
        if ($this->verbose) {
            echo $logMessage . "\n";
        }
        
        // Log to file
        $logFile = __DIR__ . '/../logs/security_monitor.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);
    }
}

// Command line execution
if (php_sapi_name() === 'cli') {
    $verbose = in_array('--verbose', $argv) || in_array('-v', $argv);
    $sendEmail = in_array('--email', $argv) || in_array('-e', $argv);
    
    $monitor = new SecurityMonitor($verbose, $sendEmail);
    $monitor->runSecurityChecks();
    
    echo "Security monitoring completed. Check logs for details.\n";
} else {
    // Web execution (for testing)
    if (isset($_GET['run']) && $_GET['run'] === 'security_monitor') {
        $monitor = new SecurityMonitor(true, false);
        echo "<pre>";
        $monitor->runSecurityChecks();
        echo "</pre>";
    } else {
        echo "Security Monitor Script - Use ?run=security_monitor to execute";
    }
}
?>