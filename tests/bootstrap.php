<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the testing environment
 */

// Set testing environment
define('TESTING', true);
define('APP_ENV', 'testing');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DATABASE'] = 'csims_test';
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = '';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Timezone
date_default_timezone_set('UTC');

// Include application bootstrap (but don't start session)
// We'll create a test-specific bootstrap that skips session

echo "PHPUnit Test Bootstrap Loaded\n";
echo "Testing Database: csims_test\n";
?>
