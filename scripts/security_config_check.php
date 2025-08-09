<?php
/**
 * CSIMS Security Configuration Checker
 * 
 * This script automatically verifies that all security configurations
 * are properly implemented and provides recommendations for improvements.
 * 
 * Usage: php security_config_check.php [--fix] [--verbose]
 * 
 * @author CSIMS Security Team
 * @version 1.0
 * @since 2024
 */

// Prevent direct web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

// Include required files
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/security.php';
require_once dirname(__DIR__) . '/config/database.php';

class SecurityConfigChecker {
    private $issues = [];
    private $warnings = [];
    private $passed = [];
    private $verbose = false;
    private $autoFix = false;
    private $score = 0;
    private $maxScore = 0;
    
    public function __construct($verbose = false, $autoFix = false) {
        $this->verbose = $verbose;
        $this->autoFix = $autoFix;
    }
    
    /**
     * Run all security configuration checks
     */
    public function runAllChecks() {
        $this->log("\n=== CSIMS Security Configuration Checker ===");
        $this->log("Starting comprehensive security configuration check...\n");
        
        // Core configuration checks
        $this->checkEnvironmentConfig();
        $this->checkSecurityConstants();
        $this->checkDatabaseSecurity();
        $this->checkSessionSecurity();
        $this->checkPasswordPolicies();
        $this->checkFilePermissions();
        $this->checkSSLConfiguration();
        $this->checkSecurityHeaders();
        $this->checkLoggingConfiguration();
        $this->checkAuthenticationSecurity();
        $this->checkInputValidation();
        $this->checkFileUploadSecurity();
        $this->checkRateLimiting();
        $this->checkSecurityFiles();
        
        // Generate final report
        $this->generateReport();
    }
    
    /**
     * Check environment configuration
     */
    private function checkEnvironmentConfig() {
        $this->log("Checking environment configuration...");
        
        // Check if environment is set to production
        $this->maxScore += 10;
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            $this->passed[] = "Environment set to production";
            $this->score += 10;
        } else {
            $this->issues[] = "Environment should be set to 'production' in production systems";
        }
        
        // Check error reporting
        $this->maxScore += 5;
        if (ini_get('display_errors') == '0') {
            $this->passed[] = "Error display disabled";
            $this->score += 5;
        } else {
            $this->issues[] = "display_errors should be Off in production";
        }
        
        // Check PHP expose
        $this->maxScore += 5;
        if (ini_get('expose_php') == '0') {
            $this->passed[] = "PHP version exposure disabled";
            $this->score += 5;
        } else {
            $this->warnings[] = "expose_php should be Off to hide PHP version";
        }
        
        // Check dangerous functions
        $this->maxScore += 10;
        $dangerousFunctions = ['eval', 'exec', 'system', 'shell_exec', 'passthru'];
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $enabledDangerous = array_diff($dangerousFunctions, $disabledFunctions);
        
        if (empty($enabledDangerous)) {
            $this->passed[] = "Dangerous PHP functions disabled";
            $this->score += 10;
        } else {
            $this->warnings[] = "Consider disabling dangerous functions: " . implode(', ', $enabledDangerous);
        }
    }
    
    /**
     * Check security constants
     */
    private function checkSecurityConstants() {
        $this->log("Checking security constants...");
        
        $requiredConstants = [
            'CSRF_TOKEN_SECRET' => 'CSRF token secret',
            'MAX_LOGIN_ATTEMPTS' => 'Maximum login attempts',
            'LOGIN_LOCKOUT_TIME' => 'Login lockout time',
            'SESSION_TIMEOUT' => 'Session timeout',
            'SESSION_REGENERATE_INTERVAL' => 'Session regeneration interval',
            'PASSWORD_MIN_LENGTH' => 'Minimum password length',
            'FORCE_HTTPS' => 'HTTPS enforcement',
            'SECURE_COOKIES' => 'Secure cookies'
        ];
        
        foreach ($requiredConstants as $constant => $description) {
            $this->maxScore += 5;
            if (defined($constant)) {
                $this->passed[] = "$description configured";
                $this->score += 5;
            } else {
                $this->issues[] = "Missing security constant: $constant ($description)";
            }
        }
        
        // Check CSRF token strength
        if (defined('CSRF_TOKEN_SECRET')) {
            $this->maxScore += 5;
            if (strlen(CSRF_TOKEN_SECRET) >= 32) {
                $this->passed[] = "CSRF token secret has adequate length";
                $this->score += 5;
            } else {
                $this->issues[] = "CSRF token secret should be at least 32 characters";
            }
        }
        
        // Check password policy strength
        if (defined('PASSWORD_MIN_LENGTH')) {
            $this->maxScore += 5;
            if (PASSWORD_MIN_LENGTH >= 12) {
                $this->passed[] = "Password minimum length is adequate";
                $this->score += 5;
            } else {
                $this->warnings[] = "Consider increasing minimum password length to 12+ characters";
            }
        }
    }
    
    /**
     * Check database security
     */
    private function checkDatabaseSecurity() {
        $this->log("Checking database security...");
        
        try {
            global $conn;
            
            // Check if using default credentials
            $this->maxScore += 10;
            if (DB_USER !== 'root' && DB_USER !== 'admin') {
                $this->passed[] = "Database user is not default (root/admin)";
                $this->score += 10;
            } else {
                $this->issues[] = "Database should not use default user accounts (root/admin)";
            }
            
            // Check password strength (basic check)
            $this->maxScore += 5;
            if (strlen(DB_PASS) >= 12) {
                $this->passed[] = "Database password has adequate length";
                $this->score += 5;
            } else {
                $this->issues[] = "Database password should be at least 12 characters";
            }
            
            // Check if database exists and is accessible
            $this->maxScore += 5;
            if ($conn) {
                $this->passed[] = "Database connection successful";
                $this->score += 5;
            } else {
                $this->issues[] = "Cannot connect to database";
            }
            
        } catch (Exception $e) {
            $this->issues[] = "Database connection error: " . $e->getMessage();
        }
    }
    
    /**
     * Check session security
     */
    private function checkSessionSecurity() {
        $this->log("Checking session security...");
        
        $sessionChecks = [
            'session.cookie_httponly' => '1',
            'session.cookie_secure' => '1',
            'session.use_strict_mode' => '1',
            'session.cookie_samesite' => 'Strict'
        ];
        
        foreach ($sessionChecks as $setting => $expected) {
            $this->maxScore += 5;
            $actual = ini_get($setting);
            if ($actual == $expected) {
                $this->passed[] = "Session setting $setting properly configured";
                $this->score += 5;
            } else {
                $this->issues[] = "Session setting $setting should be '$expected', currently '$actual'";
            }
        }
        
        // Check session name
        $this->maxScore += 5;
        if (session_name() !== 'PHPSESSID') {
            $this->passed[] = "Custom session name configured";
            $this->score += 5;
        } else {
            $this->warnings[] = "Consider using custom session name instead of default PHPSESSID";
        }
    }
    
    /**
     * Check password policies
     */
    private function checkPasswordPolicies() {
        $this->log("Checking password policies...");
        
        $passwordConstants = [
            'PASSWORD_REQUIRE_UPPERCASE' => 'uppercase requirement',
            'PASSWORD_REQUIRE_LOWERCASE' => 'lowercase requirement',
            'PASSWORD_REQUIRE_NUMBERS' => 'numbers requirement',
            'PASSWORD_REQUIRE_SYMBOLS' => 'symbols requirement'
        ];
        
        foreach ($passwordConstants as $constant => $description) {
            $this->maxScore += 5;
            if (defined($constant) && constant($constant)) {
                $this->passed[] = "Password $description enabled";
                $this->score += 5;
            } else {
                $this->warnings[] = "Consider enabling password $description";
            }
        }
    }
    
    /**
     * Check file permissions
     */
    private function checkFilePermissions() {
        $this->log("Checking file permissions...");
        
        $criticalFiles = [
            dirname(__DIR__) . '/config/config.php',
            dirname(__DIR__) . '/config/database.php',
            dirname(__DIR__) . '/includes/security.php'
        ];
        
        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                $this->maxScore += 5;
                $perms = fileperms($file) & 0777;
                if ($perms <= 0644) {
                    $this->passed[] = "File permissions secure for " . basename($file);
                    $this->score += 5;
                } else {
                    $this->issues[] = "File permissions too permissive for $file (" . decoct($perms) . ")";
                }
            }
        }
        
        // Check upload directory
        $uploadDir = dirname(__DIR__) . '/uploads';
        if (is_dir($uploadDir)) {
            $this->maxScore += 5;
            if (!is_executable($uploadDir . '/index.php')) {
                $this->passed[] = "Upload directory properly secured";
                $this->score += 5;
            } else {
                $this->warnings[] = "Upload directory may allow script execution";
            }
        }
    }
    
    /**
     * Check SSL configuration
     */
    private function checkSSLConfiguration() {
        $this->log("Checking SSL configuration...");
        
        // Check FORCE_HTTPS setting
        $this->maxScore += 10;
        if (defined('FORCE_HTTPS') && FORCE_HTTPS) {
            $this->passed[] = "HTTPS enforcement enabled";
            $this->score += 10;
        } else {
            $this->issues[] = "HTTPS should be enforced (FORCE_HTTPS = true)";
        }
        
        // Check SECURE_COOKIES setting
        $this->maxScore += 5;
        if (defined('SECURE_COOKIES') && SECURE_COOKIES) {
            $this->passed[] = "Secure cookies enabled";
            $this->score += 5;
        } else {
            $this->issues[] = "Secure cookies should be enabled (SECURE_COOKIES = true)";
        }
    }
    
    /**
     * Check security headers configuration
     */
    private function checkSecurityHeaders() {
        $this->log("Checking security headers configuration...");
        
        // This is a basic check - in production, you'd want to test actual HTTP responses
        $securityHeadersFile = dirname(__DIR__) . '/includes/security_headers.php';
        $this->maxScore += 10;
        if (file_exists($securityHeadersFile)) {
            $this->passed[] = "Security headers file exists";
            $this->score += 10;
        } else {
            $this->warnings[] = "Consider implementing security headers";
        }
    }
    
    /**
     * Check logging configuration
     */
    private function checkLoggingConfiguration() {
        $this->log("Checking logging configuration...");
        
        // Check if security log directory exists
        $logDir = dirname(__DIR__) . '/logs';
        $this->maxScore += 5;
        if (is_dir($logDir) && is_writable($logDir)) {
            $this->passed[] = "Log directory exists and is writable";
            $this->score += 5;
        } else {
            $this->issues[] = "Log directory missing or not writable: $logDir";
        }
        
        // Check if SecurityLogger class exists
        $this->maxScore += 5;
        if (class_exists('SecurityLogger')) {
            $this->passed[] = "SecurityLogger class available";
            $this->score += 5;
        } else {
            $this->issues[] = "SecurityLogger class not found";
        }
    }
    
    /**
     * Check authentication security
     */
    private function checkAuthenticationSecurity() {
        $this->log("Checking authentication security...");
        
        // Check if 2FA tables exist
        try {
            global $conn;
            
            $this->maxScore += 5;
            $result = $conn->query("SHOW TABLES LIKE 'user_2fa'");
            if ($result && $result->num_rows > 0) {
                $this->passed[] = "Two-factor authentication table exists";
                $this->score += 5;
            } else {
                $this->warnings[] = "Two-factor authentication table not found";
            }
            
            // Check if security_logs table exists
            $this->maxScore += 5;
            $result = $conn->query("SHOW TABLES LIKE 'security_logs'");
            if ($result && $result->num_rows > 0) {
                $this->passed[] = "Security logs table exists";
                $this->score += 5;
            } else {
                $this->issues[] = "Security logs table not found";
            }
            
        } catch (Exception $e) {
            $this->issues[] = "Cannot check database tables: " . $e->getMessage();
        }
    }
    
    /**
     * Check input validation
     */
    private function checkInputValidation() {
        $this->log("Checking input validation...");
        
        // Check if SecurityValidator class exists
        $this->maxScore += 10;
        if (class_exists('SecurityValidator')) {
            $this->passed[] = "SecurityValidator class available";
            $this->score += 10;
        } else {
            $this->issues[] = "SecurityValidator class not found";
        }
        
        // Check if CSRF protection is implemented
        $authCheckFile = dirname(__DIR__) . '/includes/auth_check.php';
        if (file_exists($authCheckFile)) {
            $content = file_get_contents($authCheckFile);
            $this->maxScore += 5;
            if (strpos($content, 'csrf') !== false || strpos($content, 'CSRF') !== false) {
                $this->passed[] = "CSRF protection implemented";
                $this->score += 5;
            } else {
                $this->warnings[] = "CSRF protection may not be implemented";
            }
        }
    }
    
    /**
     * Check file upload security
     */
    private function checkFileUploadSecurity() {
        $this->log("Checking file upload security...");
        
        // Check upload directory configuration
        $uploadDir = dirname(__DIR__) . '/uploads';
        if (is_dir($uploadDir)) {
            // Check for .htaccess file
            $this->maxScore += 5;
            $htaccessFile = $uploadDir . '/.htaccess';
            if (file_exists($htaccessFile)) {
                $this->passed[] = "Upload directory has .htaccess protection";
                $this->score += 5;
            } else {
                $this->warnings[] = "Upload directory should have .htaccess file to prevent script execution";
            }
            
            // Check for index.php file
            $this->maxScore += 5;
            $indexFile = $uploadDir . '/index.php';
            if (file_exists($indexFile)) {
                $this->passed[] = "Upload directory has index.php protection";
                $this->score += 5;
            } else {
                $this->warnings[] = "Upload directory should have index.php to prevent directory listing";
            }
        }
    }
    
    /**
     * Check rate limiting
     */
    private function checkRateLimiting() {
        $this->log("Checking rate limiting...");
        
        // Check if rate limiting constants are defined
        $rateLimitConstants = [
            'MAX_LOGIN_ATTEMPTS',
            'LOGIN_LOCKOUT_TIME'
        ];
        
        foreach ($rateLimitConstants as $constant) {
            $this->maxScore += 5;
            if (defined($constant)) {
                $this->passed[] = "Rate limiting constant $constant defined";
                $this->score += 5;
            } else {
                $this->issues[] = "Rate limiting constant $constant not defined";
            }
        }
    }
    
    /**
     * Check security files
     */
    private function checkSecurityFiles() {
        $this->log("Checking security files...");
        
        $requiredFiles = [
            '/includes/security.php' => 'Security functions',
            '/includes/security_validator.php' => 'Security validator',
            '/controllers/security_controller.php' => 'Security controller',
            '/scripts/security_monitor.php' => 'Security monitor',
            '/docs/SECURITY.md' => 'Security documentation'
        ];
        
        foreach ($requiredFiles as $file => $description) {
            $this->maxScore += 5;
            $fullPath = dirname(__DIR__) . $file;
            if (file_exists($fullPath)) {
                $this->passed[] = "$description file exists";
                $this->score += 5;
            } else {
                $this->warnings[] = "$description file not found: $file";
            }
        }
    }
    
    /**
     * Generate final security report
     */
    private function generateReport() {
        $this->log("\n=== SECURITY CONFIGURATION REPORT ===");
        
        // Calculate security score
        $percentage = $this->maxScore > 0 ? round(($this->score / $this->maxScore) * 100, 1) : 0;
        
        $this->log("Security Score: {$this->score}/{$this->maxScore} ({$percentage}%)");
        
        // Determine security level
        if ($percentage >= 90) {
            $level = "EXCELLENT";
            $color = "\033[32m"; // Green
        } elseif ($percentage >= 80) {
            $level = "GOOD";
            $color = "\033[33m"; // Yellow
        } elseif ($percentage >= 70) {
            $level = "FAIR";
            $color = "\033[33m"; // Yellow
        } else {
            $level = "POOR";
            $color = "\033[31m"; // Red
        }
        
        $this->log($color . "Security Level: $level" . "\033[0m");
        
        // Show passed checks
        if (!empty($this->passed)) {
            $this->log("\n\033[32m✓ PASSED CHECKS (" . count($this->passed) . "):\033[0m");
            foreach ($this->passed as $pass) {
                $this->log("  ✓ $pass");
            }
        }
        
        // Show warnings
        if (!empty($this->warnings)) {
            $this->log("\n\033[33m⚠ WARNINGS (" . count($this->warnings) . "):\033[0m");
            foreach ($this->warnings as $warning) {
                $this->log("  ⚠ $warning");
            }
        }
        
        // Show critical issues
        if (!empty($this->issues)) {
            $this->log("\n\033[31m✗ CRITICAL ISSUES (" . count($this->issues) . "):\033[0m");
            foreach ($this->issues as $issue) {
                $this->log("  ✗ $issue");
            }
        }
        
        // Recommendations
        $this->log("\n=== RECOMMENDATIONS ===");
        
        if ($percentage < 80) {
            $this->log("\033[31m• Address critical issues immediately\033[0m");
            $this->log("\033[31m• Review security configuration\033[0m");
            $this->log("\033[31m• Consider professional security audit\033[0m");
        }
        
        if (!empty($this->warnings)) {
            $this->log("\033[33m• Review and address warnings\033[0m");
            $this->log("\033[33m• Implement additional security measures\033[0m");
        }
        
        $this->log("\033[32m• Run security checks regularly\033[0m");
        $this->log("\033[32m• Keep system and dependencies updated\033[0m");
        $this->log("\033[32m• Monitor security logs regularly\033[0m");
        
        // Save report to file
        $this->saveReport($percentage, $level);
        
        $this->log("\n=== CHECK COMPLETE ===");
        $this->log("Report saved to: logs/security_config_report_" . date('Y-m-d_H-i-s') . ".txt");
    }
    
    /**
     * Save report to file
     */
    private function saveReport($percentage, $level) {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $reportFile = $logDir . '/security_config_report_' . date('Y-m-d_H-i-s') . '.txt';
        
        $report = "CSIMS Security Configuration Report\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $report .= "Security Score: {$this->score}/{$this->maxScore} ({$percentage}%)\n";
        $report .= "Security Level: $level\n\n";
        
        if (!empty($this->passed)) {
            $report .= "PASSED CHECKS (" . count($this->passed) . "):\n";
            foreach ($this->passed as $pass) {
                $report .= "✓ $pass\n";
            }
            $report .= "\n";
        }
        
        if (!empty($this->warnings)) {
            $report .= "WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                $report .= "⚠ $warning\n";
            }
            $report .= "\n";
        }
        
        if (!empty($this->issues)) {
            $report .= "CRITICAL ISSUES (" . count($this->issues) . "):\n";
            foreach ($this->issues as $issue) {
                $report .= "✗ $issue\n";
            }
            $report .= "\n";
        }
        
        file_put_contents($reportFile, $report);
    }
    
    /**
     * Log message with optional verbose mode
     */
    private function log($message) {
        if ($this->verbose || strpos($message, '===') !== false || strpos($message, '✓') !== false || 
            strpos($message, '⚠') !== false || strpos($message, '✗') !== false) {
            echo $message . "\n";
        }
    }
}

// Parse command line arguments
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);
$autoFix = in_array('--fix', $argv) || in_array('-f', $argv);
$help = in_array('--help', $argv) || in_array('-h', $argv);

if ($help) {
    echo "CSIMS Security Configuration Checker\n";
    echo "\nUsage: php security_config_check.php [options]\n";
    echo "\nOptions:\n";
    echo "  --verbose, -v    Show detailed output\n";
    echo "  --fix, -f        Attempt to fix issues automatically (future feature)\n";
    echo "  --help, -h       Show this help message\n";
    echo "\nThis script checks the security configuration of your CSIMS installation\n";
    echo "and provides recommendations for improvements.\n";
    exit(0);
}

// Run security configuration check
try {
    $checker = new SecurityConfigChecker($verbose, $autoFix);
    $checker->runAllChecks();
} catch (Exception $e) {
    echo "\033[31mError running security check: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

exit(0);
?>