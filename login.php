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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 900: '#0c4a6e' },
                        slate: { 850: '#1e293b' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    backgroundImage: {
                        'hero-pattern': "url('assets/images/finance_hero_bg.png')",
                    },
                    animation: {
                        'shimmer': 'shimmer 1.5s infinite',
                        'fade-up': 'fadeUp 0.6s ease-out forwards',
                        'shake': 'shake 0.5s ease-in-out',
                    },
                    keyframes: {
                        shimmer: {
                            '100%': { transform: 'translateX(100%)' },
                        },
                        fadeUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        shake: {
                            '0%, 100%': { transform: 'translateX(0)' },
                            '10%, 30%, 50%, 70%, 90%': { transform: 'translateX(-4px)' },
                            '20%, 40%, 60%, 80%': { transform: 'translateX(4px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
        .input-group:focus-within label {
            color: #0284c7;
        }
        .input-group:focus-within i {
            color: #0284c7;
        }
    </style>
</head>
<body class="min-h-screen relative overflow-hidden flex items-center justify-center bg-slate-900">
    
    <!-- Unified Background (Matches Home) -->
    <div class="absolute inset-0 z-0">
        <div class="absolute inset-0 bg-slate-900/80 bg-gradient-to-br from-slate-900/95 via-slate-900/80 to-primary-900/40 z-10"></div>
        <img src="assets/images/finance_hero_bg.png" alt="Background" class="w-full h-full object-cover opacity-60">
    </div>

    <!-- Navigation / Back Link -->
    <div class="absolute top-0 left-0 w-full p-6 z-20">
        <div class="max-w-7xl mx-auto">
            <a href="index.php" class="inline-flex items-center text-white/70 hover:text-white transition-colors group text-sm font-medium">
                <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- Main Login Card -->
    <div class="relative z-20 w-full max-w-md px-4">
        <div class="glass-card rounded-3xl p-8 md:p-10 w-full animate-fade-up">
            
            <!-- Logo & Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg mb-4 transform hover:scale-105 transition-transform duration-300">
                    <i class="fas fa-shield-alt text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Welcome Back</h1>
                <p class="text-slate-500 mt-2 text-sm">Sign in to your CSIMS account</p>
            </div>

            <!-- Alerts -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg animate-shake">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-sm text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-sm text-green-700 font-medium"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" id="loginForm" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken(); ?>">
                
                <div class="input-group">
                    <label for="username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-user text-slate-400 transition-colors"></i>
                        </div>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               required 
                               autofocus
                               class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-medium sm:text-sm"
                               placeholder="Enter your username">
                    </div>
                </div>

                <div class="input-group">
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Password</label>
                        <a href="views/auth/forgot_password.php" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-slate-400 transition-colors"></i>
                        </div>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-medium sm:text-sm"
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" 
                        class="group relative w-full flex justify-center py-3.5 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 shadow-lg hover:shadow-primary-500/30 transition-all duration-300 transform hover:-translate-y-0.5 overflow-hidden mt-2">
                    <span class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shimmer"></span>
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-sign-in-alt text-primary-200 group-hover:text-white transition-colors"></i>
                    </span>
                    Sign In
                </button>
            </form>
            
             <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-sm text-slate-500">
                    Not a member yet? 
                    <a href="views/member_register.php" class="font-bold text-primary-600 hover:text-primary-700 transition-colors">Create an account</a>
                </p>
            </div>
        </div>
        
        <p class="text-center text-slate-400 text-xs mt-8 font-light tracking-wide opacity-60">
            &copy; <?php echo date('Y'); ?> NPC CTLStaff Loan Society.
        </p>
    </div>

</body>
</html>
