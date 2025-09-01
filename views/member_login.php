<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/member_controller.php';

$memberController = new MemberController($conn);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $member = $memberController->authenticateMember($username, $password);
        
        if ($member && isset($member['member_id'])) {
            // Active member - allow login
            $_SESSION['member_id'] = $member['member_id'];
            $_SESSION['member_username'] = $member['username'];
            $_SESSION['member_name'] = $member['first_name'] . ' ' . $member['last_name'];
            $_SESSION['member_email'] = $member['email'];
            $_SESSION['user_type'] = 'member';
            
            // Redirect to member dashboard
            header('Location: member_dashboard.php');
            exit();
        } elseif ($member && isset($member['status'])) {
            // Member exists but not active - provide status-specific feedback
            switch ($member['status']) {
                case 'Pending':
                    $error = 'Your account is pending admin approval. You will be notified once your registration is approved.';
                    break;
                case 'Rejected':
                    $error = 'Your registration was not approved. Please contact the administrator for more information.';
                    break;
                case 'Inactive':
                    $error = 'Your account is currently inactive. Please contact the administrator to reactivate your account.';
                    break;
                case 'Suspended':
                    $error = 'Your account has been suspended. Please contact the administrator for assistance.';
                    break;
                case 'Expired':
                    $error = 'Your membership has expired. Please contact the administrator to renew your membership.';
                    break;
                default:
                    $error = 'Your account status does not allow login. Please contact the administrator.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login - NPC CTLStaff Loan Society</title>
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
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .form-control {
            border-left: none;
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
    <div class="login-container">
        <div class="logo">
            <h2><i class="fas fa-user-circle"></i> Member Portal</h2>
            <p class="text-muted">NPC CTLStaff Loan Society</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Username" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        <div class="links">
            <p class="mb-2">Don't have an account? <a href="member_register.php">Register here</a></p>
            <p><a href="member_forgot_password.php">Forgot Password?</a></p>
            <p><a href="../index.php">Admin Login</a></p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>