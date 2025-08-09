<?php
require_once 'config/config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get username from POST data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';

if (empty($username)) {
    echo json_encode(['has_2fa' => false]);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if user exists and has 2FA enabled
    $stmt = $conn->prepare("SELECT two_factor_enabled FROM admins WHERE username = ? AND status = 'Active'");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $has_2fa = (bool) $user['two_factor_enabled'];
    } else {
        $has_2fa = false;
    }
    
    echo json_encode(['has_2fa' => $has_2fa]);
    
} catch (Exception $e) {
    error_log('Error checking 2FA status: ' . $e->getMessage());
    echo json_encode(['has_2fa' => false]);
}
?>