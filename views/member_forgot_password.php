<?php
require_once '../config/config.php';
$error = isset($_SESSION['forgot_error']) ? $_SESSION['forgot_error'] : '';
$success = isset($_SESSION['forgot_success']) ? $_SESSION['forgot_success'] : '';
unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Member Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/csims-colors.css">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .forgot-container {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background: var(--surface-primary);
            border-radius: 16px;
            box-shadow: 0 6px 24px var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        .btn-outline {
            width: 100%;
        }
        .btn-outline:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h2 {
            color: #333;
            font-weight: 700;
        }
        .links {
            text-align: center;
            margin-top: 1rem;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <h2><i class="fas fa-user-circle"></i> Forgot Password</h2>
            <p class="text-muted">Member Portal</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="member_forgot_password_process.php">
            <div class="mb-3">
                <label for="email" class="form-label">Enter your registered email address</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
            </div>
            <button type="submit" class="btn btn-outline">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>
        <div class="links">
            <p><a href="member_login.php">Back to Login</a></p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>