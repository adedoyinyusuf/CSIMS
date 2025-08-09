<?php
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'controllers/auth_controller.php';

echo "<h2>CSIMS Login Debug</h2>";

// Test 1: Database Connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "✅ Database connection successful<br>";
    
    // Test 2: Check if admins table exists
    echo "<h3>2. Admins Table Check</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'admins'");
    if ($result->num_rows > 0) {
        echo "✅ Admins table exists<br>";
        
        // Test 3: Check admin users
        echo "<h3>3. Admin Users Check</h3>";
        $adminResult = $conn->query("SELECT admin_id, username, first_name, last_name, email, role, status FROM admins");
        if ($adminResult->num_rows > 0) {
            echo "✅ Found " . $adminResult->num_rows . " admin user(s):<br>";
            while ($admin = $adminResult->fetch_assoc()) {
                echo "- ID: {$admin['admin_id']}, Username: {$admin['username']}, Name: {$admin['first_name']} {$admin['last_name']}, Status: {$admin['status']}<br>";
            }
        } else {
            echo "❌ No admin users found<br>";
            echo "<a href='config/init_db.php'>Initialize Database</a><br>";
        }
    } else {
        echo "❌ Admins table does not exist<br>";
        echo "<a href='config/init_db.php'>Initialize Database</a><br>";
    }
    
    // Test 4: Test Authentication
    echo "<h3>4. Authentication Test</h3>";
    $auth = new AuthController();
    $testResult = $auth->login('admin', 'admin123');
    if ($testResult['success']) {
        echo "✅ Authentication test successful<br>";
    } else {
        echo "❌ Authentication test failed: " . $testResult['message'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 5: Configuration Check
echo "<h3>5. Configuration Check</h3>";
echo "BASE_URL: " . BASE_URL . "<br>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";

echo "<h3>6. Quick Actions</h3>";
echo "<a href='index.php'>Go to Login Page</a> | ";
echo "<a href='config/init_db.php'>Initialize Database</a>";
?>