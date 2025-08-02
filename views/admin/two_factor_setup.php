<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/security_controller.php';

// Check if user is logged in
if (!Session::getInstance()->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit();
}

$authController = new AuthController();
$securityController = new SecurityController();
$user = $authController->getCurrentUser();

// Handle form submissions
$message = '';
$messageType = '';
$qrCodeData = '';
$secret = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'enable_2fa':
                $result = $authController->enableTwoFactor($user['admin_id']);
                if ($result['success']) {
                    $secret = $result['secret'];
                    $qrCodeData = 'otpauth://totp/NPC%20CTLStaff%20Loan%20Society:' . urlencode($user['username']) . '?secret=' . $secret . '&issuer=NPC%20CTLStaff%20Loan%20Society';
                    $message = 'Two-factor authentication setup initiated. Please scan the QR code with your authenticator app.';
                    $messageType = 'info';
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
                break;
                
            case 'confirm_2fa':
                $code = $_POST['verification_code'];
                $result = $authController->confirmTwoFactor($user['admin_id'], $code);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                if ($result['success']) {
                    // Refresh user data
                    $user = $authController->getCurrentUser();
                }
                break;
                
            case 'disable_2fa':
                $password = $_POST['password'];
                $result = $authController->disableTwoFactor($user['admin_id'], $password);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                if ($result['success']) {
                    // Refresh user data
                    $user = $authController->getCurrentUser();
                }
                break;
        }
    }
}

// Check current 2FA status
$stmt = Database::getInstance()->getConnection()->prepare("SELECT two_factor_enabled, two_factor_secret FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $user['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$twoFactorData = $result->fetch_assoc();
$stmt->close();

$is2FAEnabled = $twoFactorData['two_factor_enabled'] ?? 0;
$hasSecret = !empty($twoFactorData['two_factor_secret']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - NPC CTLStaff Loan Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .status-enabled {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
            color: white;
        }
        
        .status-disabled {
            background: linear-gradient(135deg, #ddd 0%, #bbb 100%);
            color: #666;
        }
        
        .qr-container {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            margin: 0 5px;
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .step.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .step.completed {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
            color: white;
        }
        
        .security-info {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-shield-alt me-2"></i>
                                Two-Factor Authentication Setup
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($message): ?>
                                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="security-info">
                                <h5><i class="fas fa-info-circle me-2"></i>About Two-Factor Authentication</h5>
                                <p class="mb-0">Two-factor authentication (2FA) adds an extra layer of security to your account by requiring a verification code from your mobile device in addition to your password.</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Current Status</h5>
                                    <div class="mb-3">
                                        <span class="status-badge <?php echo $is2FAEnabled ? 'status-enabled' : 'status-disabled'; ?>">
                                            <i class="fas <?php echo $is2FAEnabled ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                            <?php echo $is2FAEnabled ? 'Enabled' : 'Disabled'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5>Account Security</h5>
                                    <p class="text-muted">User: <?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <?php if (!$is2FAEnabled): ?>
                                <?php if (!$hasSecret || $secret): ?>
                                    <!-- Step 1: Enable 2FA -->
                                    <div class="step-indicator">
                                        <div class="step <?php echo $secret ? 'completed' : 'active'; ?>">
                                            <i class="fas fa-mobile-alt"></i><br>
                                            <small>Setup</small>
                                        </div>
                                        <div class="step <?php echo $secret ? 'active' : ''; ?>">
                                            <i class="fas fa-qrcode"></i><br>
                                            <small>Scan QR</small>
                                        </div>
                                        <div class="step">
                                            <i class="fas fa-check"></i><br>
                                            <small>Verify</small>
                                        </div>
                                    </div>
                                    
                                    <?php if (!$secret): ?>
                                        <div class="text-center">
                                            <h5>Enable Two-Factor Authentication</h5>
                                            <p class="text-muted mb-4">Click the button below to start setting up 2FA for your account.</p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="enable_2fa">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-shield-alt me-2"></i>
                                                    Start 2FA Setup
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <!-- Step 2: Show QR Code -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5>Step 1: Install Authenticator App</h5>
                                                <p>Download and install an authenticator app on your mobile device:</p>
                                                <ul>
                                                    <li>Google Authenticator</li>
                                                    <li>Microsoft Authenticator</li>
                                                    <li>Authy</li>
                                                    <li>Any TOTP-compatible app</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h5>Step 2: Scan QR Code</h5>
                                                <div class="qr-container">
                                                    <canvas id="qrcode"></canvas>
                                                    <p class="mt-2 mb-0"><small>Or enter this code manually:</small></p>
                                                    <code><?php echo $secret; ?></code>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <h5>Step 3: Verify Setup</h5>
                                        <p>Enter the 6-digit code from your authenticator app to complete the setup:</p>
                                        <form method="POST" class="row g-3">
                                            <input type="hidden" name="action" value="confirm_2fa">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="verification_code" placeholder="Enter 6-digit code" maxlength="6" required>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-check me-2"></i>
                                                    Verify & Enable 2FA
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- 2FA is enabled -->
                                <div class="text-center mb-4">
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <h5>Two-Factor Authentication is Active</h5>
                                        <p class="mb-0">Your account is protected with two-factor authentication.</p>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Security Benefits</h5>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>Enhanced account security</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Protection against unauthorized access</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Compliance with security standards</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Disable 2FA</h5>
                                        <p class="text-muted">If you need to disable two-factor authentication, enter your password below:</p>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="disable_2fa">
                                            <div class="mb-3">
                                                <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                                            </div>
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.')">
                                                <i class="fas fa-times me-2"></i>
                                                Disable 2FA
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="text-center">
                                <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($qrCodeData): ?>
    <script>
        // Generate QR code
        QRCode.toCanvas(document.getElementById('qrcode'), '<?php echo $qrCodeData; ?>', {
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    </script>
    <?php endif; ?>
</body>
</html>
