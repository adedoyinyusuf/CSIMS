<?php
/**
 * Security Configuration Validator
 * 
 * This script validates the security configuration of the CSIMS system
 * and provides recommendations for improvements.
 * 
 * Usage: php security_validator.php [--fix] [--verbose]
 */

require_once __DIR__ . '/../config/config.php';

class SecurityValidator {
    private $verbose;
    private $autoFix;
    private $issues;
    private $recommendations;
    
    public function __construct($verbose = false, $autoFix = false) {
        $this->verbose = $verbose;
        $this->autoFix = $autoFix;
        $this->issues = [];
        $this->recommendations = [];
    }
    
    /**
     * Run all security validations
     */
    public function validateSecurity() {
        $this->log("Starting security configuration validation...");
        
        // Validate PHP configuration
        $this->validatePHPConfig();
        
        // Validate application configuration
        $this->validateAppConfig();
        
        // Validate file permissions
        $this->validateFilePermissions();
        
        // Validate database security
        $this->validateDatabaseSecurity();
        
        // Validate session security
        $this->validateSessionSecurity();
        
        // Validate HTTPS configuration
        $this->validateHTTPSConfig();
        
        // Validate security headers
        $this->validateSecurityHeaders();
        
        // Validate password policies
        $this->validatePasswordPolicies();
        
        // Generate report
        $this->generateReport();
        
        $this->log("Security validation completed.");
    }
    
    /**
     * Validate PHP configuration
     */
    private function validatePHPConfig() {
        $this->log("Validating PHP configuration...");
        
        // Check display_errors
        if (ini_get('display_errors') == '1') {
            $this->addIssue('HIGH', 'PHP display_errors is enabled', 
                'Disable display_errors in production to prevent information disclosure');
        }
        
        // Check expose_php
        if (ini_get('expose_php') == '1') {
            $this->addIssue('MEDIUM', 'PHP version exposed', 
                'Disable expose_php to hide PHP version information');
        }
        
        // Check session.cookie_httponly
        if (ini_get('session.cookie_httponly') != '1') {
            $this->addIssue('HIGH', 'HTTP-only cookies not enabled', 
                'Enable session.cookie_httponly to prevent XSS attacks');
        }
        
        // Check session.cookie_secure
        if (ini_get('session.cookie_secure') != '1') {
            $this->addIssue('HIGH', 'Secure cookies not enabled', 
                'Enable session.cookie_secure for HTTPS-only cookies');
        }
        
        // Check session.use_strict_mode
        if (ini_get('session.use_strict_mode') != '1') {
            $this->addIssue('MEDIUM', 'Strict session mode not enabled', 
                'Enable session.use_strict_mode to prevent session fixation');
        }
        
        // Check file_uploads
        if (ini_get('file_uploads') == '1') {
            $maxSize = ini_get('upload_max_filesize');
            $this->addRecommendation('File uploads enabled', 
                "Ensure upload restrictions are in place. Current max size: {$maxSize}");
        }
        
        // Check allow_url_fopen
        if (ini_get('allow_url_fopen') == '1') {
            $this->addIssue('MEDIUM', 'allow_url_fopen is enabled', 
                'Consider disabling allow_url_fopen to prevent remote file inclusion');
        }
        
        // Check allow_url_include
        if (ini_get('allow_url_include') == '1') {
            $this->addIssue('HIGH', 'allow_url_include is enabled', 
                'Disable allow_url_include to prevent remote code execution');
        }
    }
    
    /**
     * Validate application configuration
     */
    private function validateAppConfig() {
        $this->log("Validating application configuration...");
        
        // Check FORCE_HTTPS
        if (!defined('FORCE_HTTPS') || !FORCE_HTTPS) {
            $this->addIssue('HIGH', 'HTTPS not enforced', 
                'Enable FORCE_HTTPS to ensure all traffic uses HTTPS');
        }
        
        // Check SECURE_COOKIES
        if (!defined('SECURE_COOKIES') || !SECURE_COOKIES) {
            $this->addIssue('HIGH', 'Secure cookies not configured', 
                'Enable SECURE_COOKIES for enhanced cookie security');
        }
        
        // Check CSRF protection
        if (!defined('CSRF_TOKEN_SECRET') || empty(CSRF_TOKEN_SECRET)) {
            $this->addIssue('HIGH', 'CSRF protection not configured', 
                'Configure CSRF_TOKEN_SECRET for CSRF protection');
        }
        
        // Check password policy
        if (!defined('PASSWORD_MIN_LENGTH') || PASSWORD_MIN_LENGTH < 8) {
            $this->addIssue('MEDIUM', 'Weak password policy', 
                'Set PASSWORD_MIN_LENGTH to at least 8 characters');
        }
        
        // Check session timeout
        if (!defined('SESSION_TIMEOUT') || SESSION_TIMEOUT > 3600) {
            $this->addIssue('MEDIUM', 'Long session timeout', 
                'Consider reducing SESSION_TIMEOUT for better security');
        }
        
        // Check error reporting
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            if (error_reporting() !== 0) {
                $this->addIssue('HIGH', 'Error reporting enabled in production', 
                    'Disable error reporting in production environment');
            }
        }
    }
    
    /**
     * Validate file permissions
     */
    private function validateFilePermissions() {
        $this->log("Validating file permissions...");
        
        $criticalFiles = [
            __DIR__ . '/../config/config.php',
            __DIR__ . '/../config/database.php',
            __DIR__ . '/../config/security.php'
        ];
        
        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                $perms = fileperms($file) & 0777;
                if ($perms > 0644) {
                    $this->addIssue('MEDIUM', "Overly permissive file permissions: {$file}", 
                        sprintf('File has permissions %o, should be 644 or less', $perms));
                }
            }
        }
        
        // Check upload directory permissions
        $uploadDir = __DIR__ . '/../uploads';
        if (is_dir($uploadDir)) {
            $perms = fileperms($uploadDir) & 0777;
            if ($perms > 0755) {
                $this->addIssue('MEDIUM', "Overly permissive upload directory permissions", 
                    sprintf('Upload directory has permissions %o, should be 755 or less', $perms));
            }
        }
    }
    
    /**
     * Validate database security
     */
    private function validateDatabaseSecurity() {
        $this->log("Validating database security...");
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if using default credentials
            if (DB_USER === 'root' && (empty(DB_PASS) || DB_PASS === 'password')) {
                $this->addIssue('HIGH', 'Default database credentials', 
                    'Change default database username and password');
            }
            
            // Check database connection encryption
            $result = $db->query("SHOW STATUS LIKE 'Ssl_cipher'");
            if ($result) {
                $row = $result->fetch_assoc();
                if (empty($row['Value'])) {
                    $this->addRecommendation('Database connection not encrypted', 
                        'Consider enabling SSL for database connections');
                }
            }
            
            // Check for security tables
            $tables = ['security_logs', 'login_attempts', 'session_tokens'];
            foreach ($tables as $table) {
                $result = $db->query("SHOW TABLES LIKE '{$table}'");
                if ($result->num_rows === 0) {
                    $this->addIssue('MEDIUM', "Missing security table: {$table}", 
                        'Run security table migrations to create required tables');
                }
            }
            
        } catch (Exception $e) {
            $this->addIssue('HIGH', 'Database connection error', 
                'Unable to validate database security: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate session security
     */
    private function validateSessionSecurity() {
        $this->log("Validating session security...");
        
        // Check session name
        if (session_name() === 'PHPSESSID') {
            $this->addIssue('LOW', 'Default session name', 
                'Change session name from default PHPSESSID');
        }
        
        // Check session save path
        $savePath = session_save_path();
        if (empty($savePath) || $savePath === '/tmp') {
            $this->addRecommendation('Default session save path', 
                'Consider using a custom session save path for better security');
        }
        
        // Check session regeneration
        if (!defined('SESSION_REGENERATE_INTERVAL') || SESSION_REGENERATE_INTERVAL > 1800) {
            $this->addIssue('MEDIUM', 'Infrequent session regeneration', 
                'Set SESSION_REGENERATE_INTERVAL to 30 minutes or less');
        }
    }
    
    /**
     * Validate HTTPS configuration
     */
    private function validateHTTPSConfig() {
        $this->log("Validating HTTPS configuration...");
        
        // Check if running on HTTPS
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            if (php_sapi_name() !== 'cli') {
                $this->addIssue('HIGH', 'Not running on HTTPS', 
                    'Configure web server to use HTTPS');
            }
        }
        
        // Check HSTS header configuration
        if (!defined('SECURITY_HEADERS') || !isset(SECURITY_HEADERS['Strict-Transport-Security'])) {
            $this->addIssue('MEDIUM', 'HSTS header not configured', 
                'Configure Strict-Transport-Security header');
        }
    }
    
    /**
     * Validate security headers
     */
    private function validateSecurityHeaders() {
        $this->log("Validating security headers...");
        
        $requiredHeaders = [
            'X-Frame-Options' => 'Clickjacking protection',
            'X-Content-Type-Options' => 'MIME type sniffing protection',
            'X-XSS-Protection' => 'XSS protection',
            'Content-Security-Policy' => 'Content Security Policy',
            'Referrer-Policy' => 'Referrer policy'
        ];
        
        foreach ($requiredHeaders as $header => $description) {
            if (!defined('SECURITY_HEADERS') || !isset(SECURITY_HEADERS[$header])) {
                $this->addIssue('MEDIUM', "Missing security header: {$header}", 
                    "Configure {$header} header for {$description}");
            }
        }
    }
    
    /**
     * Validate password policies
     */
    private function validatePasswordPolicies() {
        $this->log("Validating password policies...");
        
        $policies = [
            'PASSWORD_MIN_LENGTH' => [8, 'Minimum password length should be at least 8'],
            'PASSWORD_REQUIRE_UPPERCASE' => [true, 'Require uppercase letters in passwords'],
            'PASSWORD_REQUIRE_LOWERCASE' => [true, 'Require lowercase letters in passwords'],
            'PASSWORD_REQUIRE_NUMBERS' => [true, 'Require numbers in passwords'],
            'PASSWORD_REQUIRE_SPECIAL' => [true, 'Require special characters in passwords']
        ];
        
        foreach ($policies as $policy => $config) {
            list($expected, $description) = $config;
            
            if (!defined($policy)) {
                $this->addIssue('MEDIUM', "Password policy not defined: {$policy}", $description);
            } elseif (constant($policy) !== $expected && $policy !== 'PASSWORD_MIN_LENGTH') {
                $this->addIssue('LOW', "Weak password policy: {$policy}", $description);
            } elseif ($policy === 'PASSWORD_MIN_LENGTH' && constant($policy) < $expected) {
                $this->addIssue('MEDIUM', "Weak password length requirement", $description);
            }
        }
    }
    
    /**
     * Add security issue
     */
    private function addIssue($severity, $title, $description) {
        $this->issues[] = [
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->log("ISSUE [{$severity}] {$title}: {$description}");
    }
    
    /**
     * Add recommendation
     */
    private function addRecommendation($title, $description) {
        $this->recommendations[] = [
            'title' => $title,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->log("RECOMMENDATION {$title}: {$description}");
    }
    
    /**
     * Generate validation report
     */
    private function generateReport() {
        $report = "Security Configuration Validation Report\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat('=', 60) . "\n\n";
        
        // Summary
        $severityCounts = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        foreach ($this->issues as $issue) {
            $severityCounts[$issue['severity']]++;
        }
        
        $report .= "SUMMARY\n";
        $report .= "-------\n";
        $report .= "Total Issues: " . count($this->issues) . "\n";
        $report .= "High Priority: {$severityCounts['HIGH']}\n";
        $report .= "Medium Priority: {$severityCounts['MEDIUM']}\n";
        $report .= "Low Priority: {$severityCounts['LOW']}\n";
        $report .= "Recommendations: " . count($this->recommendations) . "\n\n";
        
        // Security Issues
        if (!empty($this->issues)) {
            $report .= "SECURITY ISSUES\n";
            $report .= "---------------\n";
            
            foreach (['HIGH', 'MEDIUM', 'LOW'] as $severity) {
                $severityIssues = array_filter($this->issues, function($issue) use ($severity) {
                    return $issue['severity'] === $severity;
                });
                
                if (!empty($severityIssues)) {
                    $report .= "\n{$severity} PRIORITY:\n";
                    foreach ($severityIssues as $issue) {
                        $report .= "• {$issue['title']}\n";
                        $report .= "  {$issue['description']}\n\n";
                    }
                }
            }
        }
        
        // Recommendations
        if (!empty($this->recommendations)) {
            $report .= "\nRECOMMENDATIONS\n";
            $report .= "---------------\n";
            
            foreach ($this->recommendations as $rec) {
                $report .= "• {$rec['title']}\n";
                $report .= "  {$rec['description']}\n\n";
            }
        }
        
        // Security Score
        $score = $this->calculateSecurityScore($severityCounts);
        $report .= "\nSECURITY SCORE: {$score}/100\n";
        $report .= $this->getScoreDescription($score) . "\n";
        
        // Save report
        $reportFile = __DIR__ . '/../logs/security_validation_' . date('Y-m-d_H-i-s') . '.txt';
        $reportDir = dirname($reportFile);
        
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        file_put_contents($reportFile, $report);
        $this->log("Validation report saved to: {$reportFile}");
        
        // Display summary
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "SECURITY VALIDATION SUMMARY\n";
        echo str_repeat('=', 60) . "\n";
        echo "Issues Found: " . count($this->issues) . " (High: {$severityCounts['HIGH']}, Medium: {$severityCounts['MEDIUM']}, Low: {$severityCounts['LOW']})\n";
        echo "Recommendations: " . count($this->recommendations) . "\n";
        echo "Security Score: {$score}/100 - " . $this->getScoreDescription($score) . "\n";
        echo "Full report: {$reportFile}\n";
        echo str_repeat('=', 60) . "\n";
    }
    
    /**
     * Calculate security score
     */
    private function calculateSecurityScore($severityCounts) {
        $score = 100;
        $score -= $severityCounts['HIGH'] * 15;
        $score -= $severityCounts['MEDIUM'] * 8;
        $score -= $severityCounts['LOW'] * 3;
        
        return max(0, $score);
    }
    
    /**
     * Get score description
     */
    private function getScoreDescription($score) {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= 60) return 'Fair';
        if ($score >= 40) return 'Poor';
        return 'Critical';
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
        $logFile = __DIR__ . '/../logs/security_validator.log';
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
    $autoFix = in_array('--fix', $argv) || in_array('-f', $argv);
    
    $validator = new SecurityValidator($verbose, $autoFix);
    $validator->validateSecurity();
} else {
    // Web execution (for testing)
    if (isset($_GET['run']) && $_GET['run'] === 'security_validator') {
        $validator = new SecurityValidator(true, false);
        echo "<pre>";
        $validator->validateSecurity();
        echo "</pre>";
    } else {
        echo "Security Validator Script - Use ?run=security_validator to execute";
    }
}
?>