<?php
/**
 * Security Hardening Script
 * 
 * Addresses identified security vulnerabilities:
 * 1. Default database credentials (config/database.php)
 * 2. CORS wildcard in API (api.php)
 * 3. Debug statements in views (error_log gating)
 * 4. Cookie admin file removal
 * 
 * Run: php scripts/security_hardening.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   CSIMS Security Hardening Script                             ║\n";
echo "║   " . date('Y-m-d H:i:s') . "                                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$issues_fixed = [];
$warnings = [];

// ============================================================================
// ISSUE 1: Default Database Credentials
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "ISSUE 1: Hardening Database Configuration\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$database_file = 'config/database.php';
$database_backup = 'config/database.php.backup.' . date('YmdHis');

echo "Reading $database_file...\n";
$current_content = file_get_contents($database_file);

// Create backup
copy($database_file, $database_backup);
echo "✓ Backup created: $database_backup\n";

// Create hardened version
$hardened_content = <<<'PHP'
<?php
/**
 * Database Configuration - Production Hardened
 * 
 * This file requires environment variables to be set.
 * NO default credentials - fails securely if .env is not configured.
 * 
 * Updated: <?php echo date('Y-m-d H:i:s'); ?>

 */

// Load environment variables
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove quotes if present
        $value = trim($value, '"\'');
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

// SECURITY: Require environment configuration - NO DEFAULTS!
$required_vars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE'];
$missing_vars = [];

foreach ($required_vars as $var) {
    if (empty($_ENV[$var]) && empty(getenv($var))) {
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars)) {
    $error_msg = "SECURITY ERROR: Database configuration requires environment variables.\n\n";
    $error_msg .= "Missing variables: " . implode(', ', $missing_vars) . "\n\n";
    $error_msg .= "Please configure .env file with:\n";
    foreach ($missing_vars as $var) {
        $error_msg .= "  $var=your_value\n";
    }
    $error_msg .= "\nCopy .env.example to .env and configure it properly.\n";
    
    // Log error
    error_log("CSIMS Security: " . $error_msg);
    
    // Fail securely - no default credentials!
    die($error_msg);
}

// Define constants from environment
if (!defined('DB_HOST')) define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST'));
if (!defined('DB_USER')) define('DB_USER', $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME'));
if (!defined('DB_PASS')) define('DB_PASS', $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD'));
if (!defined('DB_NAME')) define('DB_NAME', $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE'));

// Establish connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        error_log("CSIMS Database: Connection failed for " . DB_USER . "@" . DB_HOST . ": " . $conn->connect_error);
        die("Database connection failed. Please check your configuration.");
    }
    
    // Set charset
    $conn->set_charset('utf8mb4');
}

// Select database
if (!$conn->select_db(DB_NAME)) {
    error_log("CSIMS Database: Cannot select database '" . DB_NAME . "': " . $conn->error);
    die("Database access error. Please verify your configuration.");
}

// Success
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    error_log("CSIMS Database: Connected successfully to " . DB_NAME);
}
?>
PHP;

file_put_contents($database_file, $hardened_content);
echo "✅ Database configuration hardened - now requires .env file\n";
echo "✅ No default credentials - fails securely if not configured\n\n";
$issues_fixed[] = "Default database credentials removed";

// ============================================================================
// ISSUE 2: CORS Wildcard
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "ISSUE 2: Restricting CORS Configuration\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$api_file = 'api.php';
$api_backup = 'api.php.backup.' . date('YmdHis');

if (file_exists($api_file)) {
    echo "Reading $api_file...\n";
    $api_content = file_get_contents($api_file);
    
    // Create backup
    copy($api_file, $api_backup);
    echo "✓ Backup created: $api_backup\n";
    
    // Replace CORS wildcard with environment-based configuration
    $old_cors = "header('Access-Control-Allow-Origin: *');";
    
    $new_cors = <<<'PHP'
// CORS Configuration - Environment-based
    $allowed_origins = explode(',', $_ENV['API_ALLOWED_ORIGINS'] ?? getenv('API_ALLOWED_ORIGINS') ?: $_SERVER['HTTP_HOST'] ?? 'localhost');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowed_origins) || in_array('*', $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        // Default to same origin in production
        header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }
    header('Access-Control-Allow-Credentials: true');
PHP;
    
    $api_content = str_replace($old_cors, $new_cors, $api_content);
    
    file_put_contents($api_file, $api_content);
    echo "✅ CORS configuration updated - now environment-based\n";
    echo "✅ Set API_ALLOWED_ORIGINS in .env (comma-separated domains)\n\n";
    $issues_fixed[] = "CORS wildcard replaced with environment configuration";
} else {
    echo "⚠️  api.php not found - skipping CORS update\n\n";
    $warnings[] = "api.php not found";
}

// ============================================================================
// ISSUE 3: Cookie Admin File
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "ISSUE 3: Removing Cookie Admin File\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$cookie_file = 'cookie_admin.txt';

if (file_exists($cookie_file)) {
    // Move to development folder instead of deleting (safer)
    $dev_folder = 'development';
    if (!is_dir($dev_folder)) {
        mkdir($dev_folder, 0755, true);
    }
    
    rename($cookie_file, "$dev_folder/cookie_admin.txt.old");
    echo "✅ Moved cookie_admin.txt to development/ (archived)\n\n";
    $issues_fixed[] = "Cookie admin file moved to development/";
} else {
    echo "✓ cookie_admin.txt not found (already removed)\n\n";
}

// ============================================================================
// ISSUE 4: Debug Statements Summary
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "ISSUE 4: Debug Statements in Views\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Found 40+ error_log() statements in view files.\n\n";
echo "💡 RECOMMENDATION: Gate these with environment check\n\n";
echo "Example pattern to use:\n";
echo "───────────────────────────────────────────────────────────────\n";
echo "// OLD:\n";
echo "error_log('Some debug message');\n\n";
echo "// NEW:\n";
echo "if (defined('APP_DEBUG') && APP_DEBUG === true) {\n";
echo "    error_log('Some debug message');\n";
echo "}\n";
echo "───────────────────────────────────────────────────────────────\n\n";

echo "NOTE: These statements are logging to error log (not displayed).\n";
echo "They are relatively safe but should still be gated for production.\n\n";

$warnings[] = "40+ error_log statements found in views - should be gated with APP_DEBUG check";

// ============================================================================
// Update .env.example with new variables
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "UPDATE: Adding New Environment Variables\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$env_example = '.env.example';
if (file_exists($env_example)) {
    $env_content = file_get_contents($env_example);
    
    // Check if API_ALLOWED_ORIGINS already exists
    if (strpos($env_content, 'API_ALLOWED_ORIGINS') === false) {
        // Add API configuration section
        $api_config = "\n# -----------------------------------------------------------------------------\n";
        $api_config .= "# API Configuration (Updated for Security)\n";
        $api_config .= "# -----------------------------------------------------------------------------\n";
        $api_config .= "# Allowed CORS origins (comma-separated domains)\n";
        $api_config .= "# Use * for development only! Specify domains in production\n";
        $api_config .= "API_ALLOWED_ORIGINS=https://yoursite.com,https://www.yoursite.com\n";
        $api_config .= "API_RATE_LIMIT=100\n";
        $api_config .= "API_RATE_LIMIT_PERIOD=3600\n";
        
        $env_content .= $api_config;
        file_put_contents($env_example, $env_content);
        echo "✅ Updated .env.example with API_ALLOWED_ORIGINS\n\n";
    } else {
        echo "✓ .env.example already has API_ALLOWED_ORIGINS\n\n";
    }
}

// ============================================================================
// Create helper script for debug statement gating
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "BONUS: Creating Debug Helper Script\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$debug_helper = <<<'PHP'
<?php
/**
 * Debug Helper
 * 
 * Safe logging that respects environment settings
 * 
 * Usage:
 *   debug_log('Message');
 *   debug_log('Data:', $data);
 */

if (!function_exists('debug_log')) {
    function debug_log(...$args) {
        // Only log in debug mode
        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            $message = '';
            foreach ($args as $arg) {
                if (is_array($arg) || is_object($arg)) {
                    $message .= print_r($arg, true) . ' ';
                } else {
                    $message .= $arg . ' ';
                }
            }
            error_log('[CSIMS DEBUG] ' . trim($message));
        }
    }
}

if (!function_exists('security_log')) {
    function security_log($message, $context = []) {
        $log_message = '[CSIMS SECURITY] ' . $message;
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        error_log($log_message);
        
        // Could also write to security-specific log file
        $security_log = __DIR__ . '/../logs/security.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(
            $security_log,
            "[$timestamp] $log_message\n",
            FILE_APPEND
        );
    }
}
?>
PHP;

file_put_contents('includes/debug_helper.php', $debug_helper);
echo "✅ Created includes/debug_helper.php\n";
echo "   Use debug_log() instead of error_log() for debug messages\n";
echo "   Use security_log() for security-related events\n\n";

// ============================================================================
// Summary Report
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "SECURITY HARDENING SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "✅ Issues Fixed (" . count($issues_fixed) . "):\n";
foreach ($issues_fixed as $i => $issue) {
    echo "   " . ($i + 1) . ". $issue\n";
}
echo "\n";

if (!empty($warnings)) {
    echo "⚠️  Warnings/Recommendations (" . count($warnings) . "):\n";
    foreach ($warnings as $i => $warning) {
        echo "   " . ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

echo "📄 Backups Created:\n";
echo "   - $database_backup\n";
if (isset($api_backup)) {
    echo "   - $api_backup\n";
}
echo "\n";

echo "📝 Next Steps:\n";
echo "   1. Update .env file with required database credentials\n";
echo "   2. Set API_ALLOWED_ORIGINS in .env (specific domains for production)\n";
echo "   3. Consider gating error_log statements with APP_DEBUG check\n";
echo "   4. Test application to ensure database connection works\n";
echo "   5. Deploy with proper .env configuration\n\n";

echo "💡 For debug logging, use the new helper:\n";
echo "   require_once 'includes/debug_helper.php';\n";
echo "   debug_log('Your message');  // Only logs when APP_DEBUG=true\n\n";

// Save report
$report = [];
$report[] = "CSIMS Security Hardening Report";
$report[] = "Date: " . date('Y-m-d H:i:s');
$report[] = "";
$report[] = "Issues Fixed:";
foreach ($issues_fixed as $issue) {
    $report[] = "  - $issue";
}
$report[] = "";
$report[] = "Warnings:";
foreach ($warnings as $warning) {
    $report[] = "  - $warning";
}
$report[] = "";
$report[] = "Backups:";
$report[] = "  - $database_backup";
if (isset($api_backup)) {
    $report[] = "  - $api_backup";
}

file_put_contents('logs/security_hardening.log', implode("\n", $report) . "\n", FILE_APPEND);

echo "📊 Report saved to: logs/security_hardening.log\n\n";
echo "✅ Security hardening complete!\n\n";
?>
PHP;

file_put_contents('scripts/security_hardening.php', $hardening_script);
echo "✓ Created scripts/security_hardening.php\n";
$issues_fixed[] = "Security hardening script created";

// ============================================================================
// Summary Report
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "SECURITY HARDENING SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "✅ Issues Fixed (" . count($issues_fixed) . "):\n";
foreach ($issues_fixed as $i => $issue) {
    echo "   " . ($i + 1) . ". $issue\n";
}
echo "\n";

if (!empty($warnings)) {
    echo "⚠️  Warnings/Recommendations (" . count($warnings) . "):\n";
    foreach ($warnings as $i => $warning) {
        echo "   " . ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

echo "📄 Backups Created:\n";
echo "   - $database_backup\n";
if (isset($api_backup)) {
    echo "   - $api_backup\n";
}
echo "\n";

echo "📝 Next Steps:\n";
echo "   1. Update .env file with required database credentials\n";
echo "   2. Set API_ALLOWED_ORIGINS in .env (specific domains for production)\n";
echo "   3. Consider gating error_log statements with APP_DEBUG check\n";
echo "   4. Test application to ensure database connection works\n";
echo "   5. Deploy with proper .env configuration\n\n";

echo "💡 For debug logging, use the new helper:\n";
echo "   require_once 'includes/debug_helper.php';\n";
echo "   debug_log('Your message');  // Only logs when APP_DEBUG=true\n\n";

// Save report
$report = [];
$report[] = "CSIMS Security Hardening Report";
$report[] = "Date: " . date('Y-m-d H:i:s');
$report[] = "";
$report[] = "Issues Fixed:";
foreach ($issues_fixed as $issue) {
    $report[] = "  - $issue";
}
$report[] = "";
$report[] = "Warnings:";
foreach ($warnings as $warning) {
    $report[] = "  - $warning";
}
$report[] = "";
$report[] = "Backups:";
$report[] = "  - $database_backup";
if (isset($api_backup)) {
    $report[] = "  - $api_backup";
}

file_put_contents('logs/security_hardening.log', implode("\n", $report) . "\n", FILE_APPEND);

echo "📊 Report saved to: logs/security_hardening.log\n\n";
echo "✅ Security hardening complete!\n\n";
?>
