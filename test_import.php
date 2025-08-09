<?php
// Simple test script to test CSV import functionality
echo "<h2>Testing CSV Import</h2>";

// Set up environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost:8001';
$_SERVER['REQUEST_URI'] = '/test_import.php';

// Start session and simulate admin login
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'csims_db');

// Test database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>Database connection successful!</p>";
    
    // Check if membership_types table exists and has data
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM membership_types");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "<p>Membership types found: {$count}</p>";
    
    if ($count == 0) {
        // Insert a default membership type
        $stmt = $pdo->prepare("INSERT INTO membership_types (type_name, duration, description) VALUES (?, ?, ?)");
        $stmt->execute(['Regular', 12, 'Regular membership']);
        echo "<p style='color: blue;'>Default membership type created.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Simulate file upload
$csvContent = "first_name,last_name,email,phone,gender,date_of_birth,address,membership_type_id\nJohn,Doe,john.doe@example.com,08012345678,Male,1990-01-15,123 Main St,1\nJane,Smith,jane.smith@example.com,08087654321,Female,1985-05-20,456 Oak Ave,1";

$tempFile = tempnam(sys_get_temp_dir(), 'csv_test');
file_put_contents($tempFile, $csvContent);

// Simulate $_FILES and $_POST
$_FILES['import_file'] = [
    'name' => 'test.csv',
    'type' => 'text/csv',
    'tmp_name' => $tempFile,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($csvContent)
];

$_POST['import_mode'] = 'insert_only';
$_POST['create_accounts'] = 'true';
$_POST['send_credentials'] = 'false';

echo "<h3>Testing Import Process...</h3>";

// Capture output from import controller
ob_start();

// Include the import controller
include 'controllers/member_import_controller.php';

$output = ob_get_clean();

echo "<h3>Controller Output:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Parse JSON response
$response = json_decode($output, true);
if ($response) {
    echo "<h3>Parsed Response:</h3>";
    echo "<ul>";
    echo "<li>Success: " . ($response['success'] ? 'Yes' : 'No') . "</li>";
    echo "<li>Message: " . htmlspecialchars($response['message']) . "</li>";
    if (isset($response['imported'])) echo "<li>Imported: " . $response['imported'] . "</li>";
    if (isset($response['updated'])) echo "<li>Updated: " . $response['updated'] . "</li>";
    if (isset($response['errors'])) echo "<li>Errors: " . $response['errors'] . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>Failed to parse JSON response</p>";
}

// Check if members were actually inserted
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE email IN (?, ?)");
    $stmt->execute(['john.doe@example.com', 'jane.smith@example.com']);
    $memberCount = $stmt->fetchColumn();
    echo "<h3>Verification:</h3>";
    echo "<p>Members found in database: {$memberCount}</p>";
    
    if ($memberCount > 0) {
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM members WHERE email IN (?, ?)");
        $stmt->execute(['john.doe@example.com', 'jane.smith@example.com']);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<ul>";
        foreach ($members as $member) {
            echo "<li>" . htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['email'] . ')') . "</li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking members: " . $e->getMessage() . "</p>";
}

// Clean up
unlink($tempFile);
?>