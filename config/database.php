<?php
// Database configuration with idempotent guards and env fallbacks
if (!defined('DB_HOST')) define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'csims_db');

// Establish connection only once
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        die("Connection failed for user '" . DB_USER . "'@'" . DB_HOST . "': " . $conn->connect_error);
    }
}

// Ensure database exists; fall back to selecting if create fails
$sql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`";
if ($conn->query($sql) === TRUE) {
    $conn->select_db(DB_NAME);
} else {
    // Try selecting existing database; otherwise abort with helpful message
    if (!$conn->select_db(DB_NAME)) {
        die("Database access error for '" . DB_NAME . "': " . $conn->error . "\nPlease verify credentials in .env or config/database.php.");
    }
}
?>