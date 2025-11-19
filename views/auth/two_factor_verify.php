<?php
require_once '../config/config.php';
require_once '../controllers/auth_controller.php';

// Check if user has temporary credentials stored
if (!$session->has('temp_username') || !$session->has('temp_password')) {
    $session->setFlash('error', 'Invalid access. Please login again.');
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

$username = $session->get('temp_username');
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $two_factor_code = $_POST['two_factor_code'] ?? '';
    
    if (empty($two_factor_code)) {
        $message = 'Please enter the verification code';
        $messageType = 'danger';
    } else {
        $auth = new AuthController();
        $password = $session->get('temp_password');
        
        $result = $auth->login($username, $password, $two_factor_code);
        
        if ($result['success']) {
            // Clear temporary credentials
            $session->remove('temp_username');
            $session->remove('temp_password');
            
            // Redirect to dashboard
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit();
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .auth-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .auth-body {
            padding: 40px;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-align: center;
            letter-spacing: 0.2em;
            font-weight: 600;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
        }
        
        .security-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .code-input {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .timer {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin-top: 15px;
            font-weight: 600;
            color: #495057;
        }
        
        .app-suggestions {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .app-suggestions h6 {
            color: #495057;
            margin-bottom: 15px;
        }
        
        .app-suggestions ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .app-suggestions li {
            color: #6c757d;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="auth-card">
                    <div class="auth-header">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h3 class="mb-0">Two-Factor Authentication</h3>
                        <p class="mb-0 mt-2 opacity-75">Enter verification code</p>
                    </div>
                    
                    <div class="auth-body">
                        <?php if ($session->hasFlash('info')): ?>
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo $session->getFlash('info'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="security-info">
                            <i class="fas fa-mobile-alt fa-2x mb-2"></i>
                            <h6>Security Verification Required</h6>
                            <p class="mb-0">Please open your authenticator app and enter the 6-digit verification code for <strong><?php echo htmlspecialchars($username); ?></strong></p>
                        </div>
                        
                        <form method="POST" id="twoFactorForm">
                            <div class="mb-4">
                                <label for="two_factor_code" class="form-label">Verification Code</label>
                                <input type="text" 
                                       class="form-control code-input" 
                                       id="two_factor_code" 
                                       name="two_factor_code" 
                                       placeholder="000000" 
                                       maxlength="6" 
                                       pattern="[0-9]{6}" 
                                       autocomplete="off" 
                                       required>
                                <div class="form-text text-center mt-2">
                                    <i class="fas fa-clock me-1"></i>
                                    Codes refresh every 30 seconds
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check me-2"></i>
                                    Verify & Login
                                </button>
                            </div>
                        </form>
                        
                        <div class="timer" id="timer">
                            <i class="fas fa-sync-alt me-1"></i>
                            Next code in: <span id="countdown">30</span>s
                        </div>
                        
                        <div class="app-suggestions">
                            <h6><i class="fas fa-mobile-alt me-2"></i>Supported Authenticator Apps</h6>
                            <ul>
                                <li>Google Authenticator</li>
                                <li>Microsoft Authenticator</li>
                                <li>Authy</li>
                                <li>Any TOTP-compatible app</li>
                            </ul>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-focus on the code input
        document.getElementById('two_factor_code').focus();
        
        // Auto-submit when 6 digits are entered
        document.getElementById('two_factor_code').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            e.target.value = value;
            
            if (value.length === 6) {
                // Auto-submit after a short delay
                setTimeout(() => {
                    document.getElementById('twoFactorForm').submit();
                }, 500);
            }
        });
        
        // Countdown timer for next code
        let countdown = 30;
        const countdownElement = document.getElementById('countdown');
        
        function updateCountdown() {
            const now = new Date();
            const seconds = now.getSeconds();
            countdown = 30 - (seconds % 30);
            
            countdownElement.textContent = countdown;
            
            if (countdown === 30) {
                // Flash the timer when it resets
                document.getElementById('timer').style.background = '#d4edda';
                setTimeout(() => {
                    document.getElementById('timer').style.background = '#f8f9fa';
                }, 1000);
            }
        }
        
        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
        
        // Clear temporary session data if user navigates away
        window.addEventListener('beforeunload', function() {
            if (!document.getElementById('twoFactorForm').submitted) {
                fetch('<?php echo BASE_URL; ?>/auth/clear_temp_session.php', {
                    method: 'POST',
                    keepalive: true
                });
            }
        });
        
        // Mark form as submitted
        document.getElementById('twoFactorForm').addEventListener('submit', function() {
            this.submitted = true;
        });
    </script>
</body>
</html>