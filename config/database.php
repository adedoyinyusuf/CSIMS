<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change in production
define('DB_PASS', ''); // Change in production
define('DB_NAME', 'csims_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    // Connect to the database
    $conn->select_db(DB_NAME);
} else {
    die("Error creating database: " . $conn->error);
}
?>