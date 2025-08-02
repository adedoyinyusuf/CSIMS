<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $two_factor_code = isset($_POST['two_factor_code']) ? $_POST['two_factor_code'] : null;
    
    // Validate input
    if (empty($username) || empty($password)) {
        $session->setFlash('error', 'Username and password are required');
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
    
    // Attempt login with 2FA support
    $auth = new AuthController();
    $result = $auth->login($username, $password, $two_factor_code);
    
    if ($result['success']) {
        // Redirect to dashboard
        header("Location: " . BASE_URL . "/views/admin/dashboard.php");
        exit();
    } else {
        // Check if 2FA is required
        if (isset($result['requires_2fa']) && $result['requires_2fa']) {
            // Store username and password temporarily for 2FA verification
            $session->set('temp_username', $username);
            $session->set('temp_password', $password);
            $session->setFlash('info', $result['message']);
            header("Location: " . BASE_URL . "/views/auth/two_factor_verify.php");
            exit();
        } else {
            // Set error message and redirect back to login
            $session->setFlash('error', $result['message']);
            header("Location: " . BASE_URL . "/index.php");
            exit();
        }
    }
} else {
    // If not POST request, redirect to login page
    header("Location: " . BASE_URL . "/index.php");
    exit();
}
?>