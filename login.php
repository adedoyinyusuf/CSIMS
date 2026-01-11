<?php
/**
 * Unified Login System
 * Single entry point for both Admin and Member authentication
 * Reduces attack surface and centralizes security
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/db.php';
require_once 'includes/session.php';

// Initialize session
try {
    $session = Session::getInstance();
    
    // Check if already logged in and redirect appropriately
    if ($session->isLoggedIn()) {
        if (isset($_SESSION['user_type'])) {
            if ($_SESSION['user_type'] === 'admin') {
                header('Location: views/admin/dashboard.php');
                exit();
            } elseif ($_SESSION['user_type'] === 'member') {
                header('Location: views/member_dashboard.php');
                exit();
            }
        }
    }
} catch (Exception $e) {
    error_log('Session initialization failed: ' . $e->getMessage());
}

// Import required classes
require_once 'controllers/auth_controller.php';
require_once 'src/autoload.php';

$error = '';
$success = '';
$username = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security verification failed. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Unified rate limiting - applies to both admin and member attempts
            $clientId = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '_' . $username;
            if (class_exists('RateLimiter')) {
                if (!RateLimiter::checkLimit($clientId, 5, 900)) {
                    if (class_exists('SecurityLogger')) {
                        SecurityLogger::init();
                        SecurityLogger::logSuspiciousActivity('Rate limit exceeded for login attempts', [
                            'username' => $username,
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]);
                    }
                    $error = 'Too many login attempts. Please try again after 15 minutes.';
                }
            }
            
            if (!$error) {
                $authenticated = false;
                $userType = null;
                $userData = null;
                
                // Try Admin authentication first
                try {
                    $authController = new AuthController();
                    $adminResult = $authController->login($username, $password, false); // Don't redirect yet
                    
                    if ($adminResult['success']) {
                        $authenticated = true;
                        $userType = 'admin';
                        header('Location: views/admin/dashboard.php');
                        exit();
                    }
                } catch (Exception $e) {
                    error_log('Admin auth attempt failed: ' . $e->getMessage());
                }
                
                // If admin auth failed, try Member authentication
                if (!$authenticated) {
                    try {
                        $container = container();
                        $authService = new \CSIMS\Services\AuthService(
                            $container->resolve(\CSIMS\Services\SecurityService::class),
                            $container->resolve(\CSIMS\Repositories\MemberRepository::class),
                            $container->resolve(\CSIMS\Services\ConfigurationManager::class)
                        );
                        
                        $memberResult = $authService->authenticate($username, $password);
                        
                        if (!empty($memberResult['success'])) {
                            $authenticated = true;
                            $userType = 'member';
                            
                            // Set member session data
                            session_regenerate_id(true);
                            $user = $memberResult['user'] ?? [];
                            $session->set('member_id', $user['id'] ?? ($_SESSION['user_id'] ?? null));
                            $session->set('member_username', $user['username'] ?? ($_SESSION['username'] ?? null));
                            $session->set('member_name', $user['full_name'] ?? ($_SESSION['full_name'] ?? null));
                            $session->set('member_email', $user['email'] ?? ($_SESSION['email'] ?? null));
                            $session->set('user_type', 'member');
                            
                            header('Location: views/member_dashboard.php');
                            exit();
                        } else {
                            if (!empty($memberResult['requires_2fa'])) {
                                $error = 'Two-factor authentication required.';
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Member auth attempt failed: ' . $e->getMessage());
                    }
                }
                
                // If both failed, log the attempt and show error
                if (!$authenticated) {
                    if (class_exists('SecurityLogger')) {
                        SecurityLogger::init();
                        SecurityLogger::logFailedLogin($username, $_SERVER['REMOTE_ADDR'] ?? 'unknown', 'Invalid credentials');
                    }
                    $error = 'Invalid username or password.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/csims-colors.css">
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: {
                                50: '#f8fafc',
                                100: '#f1f5f9',
                                200: '#e2e8f0',
                                300: '#cbd5e1',
                                400: '#94a3b8',
                                500: '#64748b',
                                600: '#475569',
                                700: '#334155',
                                800: '#1e293b',
                                900: '#0f172a'
                            },
                            secondary: {
                                100: '#fef3e2',
                                300: '#EA8C55',
                                500: '#C75146',
                                600: '#AD2E24',
                                700: '#81171B'
                            }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .banner-vignette { box-shadow: inset 0 0 120px rgba(0,0,0,0.22); }
        .pattern-overlay { position: relative; }
        .pattern-overlay::before {
            content: "";
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center font-sans" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 25%, #cbd5e1 50%, #94a3b8 75%, #64748b 100%);">
    <div class="w-full max-w-5xl mx-auto flex flex-col md:flex-row rounded-3xl shadow-2xl overflow-hidden border border-white/20">
        <!-- Left Panel - Branding -->
        <div class="md:w-1/2 w-full flex flex-col justify-center items-center p-10 banner-vignette pattern-overlay" style="background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);">
            <div class="w-24 h-24 md:w-28 md:h-28 rounded-full bg-white/95 shadow-lg flex items-center justify-center mb-4 border border-white/60">
                <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                    <img src="<?php echo APP_LOGO_URL; ?>" alt="Logo" class="w-16 h-16 md:w-20 md:h-20 object-contain" />
                <?php else: ?>
                    <i class="fas fa-shield-alt text-3xl text-primary-800"></i>
                <?php endif; ?>
            </div>
            <div class="mb-6 text-center">
                <h1 class="text-5xl md:text-6xl font-extrabold mb-3 text-white tracking-tight">
                    <?php echo APP_SHORT_NAME; ?>
                </h1>
                <p class="text-lg md:text-xl text-white/90">Welcome to <?php echo APP_NAME; ?></p>
            </div>
            <p class="text-base md:text-lg text-white/85 text-center max-w-xl">Secure access to your cooperative. One login for all services.</p>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="md:w-1/2 w-full flex flex-col justify-center p-10 bg-white">
            <div class="mb-8 text-center">
                <h2 class="text-gray-800 text-2xl font-bold mb-2">Sign In</h2>
                <p class="text-gray-600">Access your account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span class="text-red-700 ml-2"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span class="text-green-700 ml-2"><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken(); ?>">
                
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 font-medium mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-white p-2 rounded-l-lg" style="background: linear-gradient(135deg, #1A5599 0%, #336699 100%);"></i>
                        </div>
                        <input type="text" 
                               class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               required 
                               autofocus>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-white p-2 rounded-l-lg" style="background: linear-gradient(135deg, #1A5599 0%, #336699 100%);"></i>
                        </div>
                        <input type="password" 
                               class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" 
                               id="password" 
                               name="password" 
                               required>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full py-3 px-4 font-semibold text-white rounded-lg hover:-translate-y-1 transition-all duration-300 shadow-lg"
                        style="background: linear-gradient(135deg, #1A5599 0%, #336699 100%);">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>

            <div class="text-center mt-6">
                <a href="views/auth/forgot_password.php" class="text-primary-600 hover:text-primary-800 transition-colors font-medium">
                    Forgot Password?
                </a>
            </div>

            <hr class="my-6" style="border-color: rgba(180, 136, 235, 0.3);">

            <div class="text-center">
                <p class="text-gray-600 text-sm mb-3">Don't have an account?</p>
                <a href="views/member_register.php" class="inline-flex items-center py-3 px-6 text-center font-medium rounded-lg shadow-lg transition-all duration-300 hover:-translate-y-1" style="background: linear-gradient(135deg, #EA8C55 0%, #C75146 100%); color: white;">
                    <i class="fas fa-user-plus mr-2"></i> Join as Member
                </a>
            </div>
        </div>
    </div>
</body>
</html>
