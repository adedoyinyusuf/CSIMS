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
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue; // Skip comments and empty lines
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            // Set in $_ENV even if empty (empty password is valid!)
            $_ENV[$key] = $value;
        }
    }
}

// SECURITY: Require environment configuration - NO DEFAULTS!
$required_vars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE'];
$missing_vars = [];

foreach ($required_vars as $var) {
    // Check if variable is SET (not just if it's empty - empty password is valid for XAMPP)
    if (!isset($_ENV[$var]) && getenv($var) === false) {
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
if (!defined('DB_PORT')) define('DB_PORT', $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 3306);
if (!defined('DB_NAME')) define('DB_NAME', $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE'));

// Establish connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    // Add Port to connection
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
    
    if ($conn->connect_error) {
        error_log("CSIMS Database: Connection failed for " . DB_USER . "@" . DB_HOST . ":" . DB_PORT . " - " . $conn->connect_error);
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