<?php
require_once 'config/config.php';

// Check if database needs to be initialized
$db_initialized = false;

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if the admins table exists
$check_table = "SHOW TABLES LIKE 'admins'";
$result = $conn->query($check_table);

if ($result->num_rows == 0) {
    // Database needs initialization
    $db_initialized = true;
}

// Check if user is logged in
if ($session->isLoggedIn()) {
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/views/admin/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h2><?php echo APP_SHORT_NAME; ?></h2>
                        <p class="mb-0"><?php echo APP_NAME; ?></p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($db_initialized): ?>
                            <div class="alert alert-info">
                                <p><strong>Database initialization required.</strong></p>
                                <p>Click the button below to set up the database.</p>
                                <a href="config/init_db.php" class="btn btn-info">Initialize Database</a>
                            </div>
                        <?php else: ?>
                            <?php if ($session->hasFlash('error')): ?>
                                <div class="alert alert-danger">
                                    <?php echo $session->getFlash('error'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($session->hasFlash('success')): ?>
                                <div class="alert alert-success">
                                    <?php echo $session->getFlash('success'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form action="<?php echo BASE_URL; ?>/views/auth/login_process.php" method="post" id="loginForm">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                
                                <!-- Two-Factor Authentication Code (Hidden by default) -->
                                <div class="mb-3" id="twoFactorGroup" style="display: none;">
                                    <label for="two_factor_code" class="form-label">
                                        <i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication Code
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                        <input type="text" 
                                               class="form-control text-center" 
                                               id="two_factor_code" 
                                               name="two_factor_code" 
                                               placeholder="000000" 
                                               maxlength="6" 
                                               pattern="[0-9]{6}"
                                               style="font-family: 'Courier New', monospace; font-size: 1.2rem; letter-spacing: 0.2em;">
                                    </div>
                                    <div class="form-text text-center">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Enter the 6-digit code from your authenticator app
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg" id="loginButton">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </button>
                                </div>
                                
                                <!-- Show 2FA toggle for users who have it enabled -->
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-link btn-sm" id="toggle2FA" style="display: none;">
                                        <i class="fas fa-shield-alt me-1"></i>I have two-factor authentication enabled
                                    </button>
                                </div>
                            </form>
                            <div class="text-center mt-3">
                                <a href="<?php echo BASE_URL; ?>/views/auth/forgot_password.php">Forgot Password?</a>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="text-center">
                                <p class="mb-2">Are you a member?</p>
                                <a href="<?php echo BASE_URL; ?>/views/member_login.php" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-user"></i> Member Login
                                </a>
                                <a href="<?php echo BASE_URL; ?>/views/member_register.php" class="btn btn-success">
                                    <i class="fas fa-user-plus"></i> Join as Member
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center py-3">
                        <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle2FA = document.getElementById('toggle2FA');
            const twoFactorGroup = document.getElementById('twoFactorGroup');
            const twoFactorCode = document.getElementById('two_factor_code');
            const loginButton = document.getElementById('loginButton');
            const usernameField = document.getElementById('username');
            
            // Show 2FA toggle after username is entered
            usernameField.addEventListener('blur', function() {
                if (this.value.trim()) {
                    toggle2FA.style.display = 'block';
                }
            });
            
            // Toggle 2FA field visibility
            toggle2FA.addEventListener('click', function() {
                if (twoFactorGroup.style.display === 'none') {
                    twoFactorGroup.style.display = 'block';
                    twoFactorCode.focus();
                    this.innerHTML = '<i class="fas fa-times me-1"></i>Hide two-factor authentication';
                    loginButton.innerHTML = '<i class="fas fa-shield-alt me-2"></i>Login with 2FA';
                } else {
                    twoFactorGroup.style.display = 'none';
                    twoFactorCode.value = '';
                    this.innerHTML = '<i class="fas fa-shield-alt me-1"></i>I have two-factor authentication enabled';
                    loginButton.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Login';
                }
            });
            
            // Auto-format 2FA code input (numbers only)
            twoFactorCode.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                e.target.value = value;
            });
            
            // Form validation
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                const twoFactorVisible = twoFactorGroup.style.display !== 'none';
                const twoFactorValue = twoFactorCode.value.trim();
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password.');
                    return;
                }
                
                if (twoFactorVisible && (!twoFactorValue || twoFactorValue.length !== 6)) {
                    e.preventDefault();
                    alert('Please enter a valid 6-digit authentication code.');
                    twoFactorCode.focus();
                    return;
                }
                
                // Show loading state
                loginButton.disabled = true;
                if (twoFactorVisible) {
                    loginButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
                } else {
                    loginButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';
                }
            });
        });
    </script>
</body>
</html>