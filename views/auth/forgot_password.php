<?php
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
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
                        <h2>Forgot Password</h2>
                        <p class="mb-0"><?php echo APP_NAME; ?></p>
                    </div>
                    <div class="card-body p-4">
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
                        
                        <p class="mb-3">Enter your email address below and we'll send you a link to reset your password.</p>
                        
                        <form action="forgot_password_process.php" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
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
</body>
</html>