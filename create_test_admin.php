<?php
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Create direct PDO connection
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $username = 'test_admin';
    $password = 'test123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if test admin already exists
    $stmt = $db->prepare("SELECT admin_id FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Test admin already exists',
            'username' => $username,
            'password' => $password
        ]);
        exit;
    }
    
    // Create test admin
    $stmt = $db->prepare("
        INSERT INTO admins (username, password, first_name, last_name, email, role, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $username,
        $hashedPassword,
        'Test',
        'Admin',
        'test@admin.com',
        'admin',
        'Active'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Test admin created successfully',
        'username' => $username,
        'password' => $password
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error creating test admin: ' . $e->getMessage()
    ]);
}
?>