<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/auth_controller.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $session->setFlash('error', 'Username and password are required');
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
    
    // Attempt login
    $auth = new AuthController();
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        // Redirect to dashboard
        header("Location: " . BASE_URL . "/admin/dashboard.php");
        exit();
    } else {
        // Set error message and redirect back to login
        $session->setFlash('error', $result['message']);
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: " . BASE_URL . "/index.php");
    exit();
}
?>