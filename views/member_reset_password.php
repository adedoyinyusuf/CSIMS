<?php
session_start();
require_once '../config/database.php';

// Get token and member_id from query params
$token = isset($_GET['token']) ? $_GET['token'] : '';
$member_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($token) || empty($member_id)) {
    $_SESSION['forgot_error'] = 'Invalid password reset link.';
    header('Location: member_login.php');
    exit();
}
$error = isset($_SESSION['reset_error']) ? $_SESSION['reset_error'] : '';
$success = isset($_SESSION['reset_success']) ? $_SESSION['reset_success'] : '';
unset($_SESSION['reset_error'], $_SESSION['reset_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Member Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-container {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            width: 100%;
        }
        .btn-primary:hover {
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
    <div class="reset-container">
        <div class="logo">
            <h2><i class="fas fa-user-circle"></i> Reset Password</h2>
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
        <form method="POST" action="member_reset_password_process.php" id="resetPasswordForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member_id); ?>">
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </form>
        <div class="links">
            <p><a href="member_login.php">Back to Login</a></p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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