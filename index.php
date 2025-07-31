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
    header("Location: " . BASE_URL . "/admin/dashboard.php");
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
                            
                            <form action="<?php echo BASE_URL; ?>/auth/login_process.php" method="post">
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
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Login</button>
                                </div>
                            </form>
                            <div class="text-center mt-3">
                                <a href="<?php echo BASE_URL; ?>/auth/forgot_password.php">Forgot Password?</a>
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
</body>
</html>