<?php
require_once '../config/config.php';

// Check if token and id are provided
$token = isset($_GET['token']) ? $_GET['token'] : '';
$admin_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($token) || empty($admin_id)) {
    $session->setFlash('error', 'Invalid password reset link');
    header("Location: ../../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h2>Reset Password</h2>
                        <p class="mb-0"><?php echo APP_NAME; ?></p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($session->hasFlash('error')): ?>
                            <div class="alert alert-danger">
                                <?php echo $session->getFlash('error'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="reset_password_process.php" method="post" id="resetPasswordForm">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin_id); ?>">
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                </div>
                                <div class="form-text">Password must be at least 8 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="../../index.php">Back to Login</a>
                        </div>
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
    <script src="../../assets/js/script.js"></script>
    
    <script>
        // Client-side validation
        document.getElementById('resetPasswordForm').addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>