<?php
// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once 'config/database.php';
require_once 'includes/db.php';
require_once 'config/config.php';
require_once 'includes/session.php';

// Initialize session with error handling
try {
    $session = Session::getInstance();
    
    // Check if admin is logged in
    if (isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        // Prevent redirect loops by checking if we're in a redirect cycle
        if (!isset($_SESSION['redirect_check'])) {
            $_SESSION['redirect_check'] = true;
            header('Location: simple_dashboard.php');
            exit();
        }
    }
} catch (Exception $e) {
    // If session initialization fails, fall back to simple session
    error_log('Session initialization failed: ' . $e->getMessage());
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check for admin login with simple session
    if (isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        header('Location: simple_dashboard.php');
        exit();
    }
}

// Check if member is logged in
if (isset($_SESSION['member_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'member') {
    header('Location: views/member_dashboard.php');
    exit();
}
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Use AuthController for login
            require_once 'controllers/auth_controller.php';
            $authController = new AuthController();
            
            $loginResult = $authController->login($username, $password);
            
            if ($loginResult['success']) {
                // Successful login - redirect to simple dashboard
                header('Location: simple_dashboard.php');
                exit();
            } else {
                $error = $loginResult['message'];
            }
            
        } catch (Exception $e) {
            // Fall back to simple login if AuthController fails
            error_log('AuthController failed, using simple login: ' . $e->getMessage());
            
            try {
                $db = Database::getInstance();
                $conn = $db->getConnection();
                $stmt = $conn->prepare("SELECT admin_id, username, password, first_name, last_name, email, role, status FROM admins WHERE username = ? AND status = 'Active'");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    if (password_verify($password, $admin['password'])) {
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['username'] = $admin['username'];
                        $_SESSION['role'] = $admin['role'];
                        $_SESSION['first_name'] = $admin['first_name'];
                        $_SESSION['last_name'] = $admin['last_name'];
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['last_activity'] = time();
                        $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
                        $updateStmt->bind_param("i", $admin['admin_id']);
                        $updateStmt->execute();
                        header('Location: simple_dashboard.php');
                        exit();
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
                $stmt->close();
            } catch (Exception $e2) {
                $error = 'Login system temporarily unavailable. Please try again later.';
                error_log('Simple login also failed: ' . $e2->getMessage());
            }
        }
    }
}
$db_needs_init = false;
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $result = $conn->query("SHOW TABLES LIKE 'admins'");
    $db_needs_init = ($result->num_rows === 0);
} catch (Exception $e) {
    $db_needs_init = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Admin Login</title>
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
</head>
<body class="min-h-screen flex items-center justify-center font-sans" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 25%, #cbd5e1 50%, #94a3b8 75%, #64748b 100%);">
    <div class="w-full max-w-5xl mx-auto flex flex-col md:flex-row rounded-3xl shadow-2xl overflow-hidden border border-white/20 glass animate-slide-in">
        <div class="md:w-1/2 w-full flex flex-col justify-center items-center p-10 relative overflow-hidden" style="background: linear-gradient(135deg, #1A5599 0%, #336699 50%, #334155 100%);">
            <div id="curtain-left" class="absolute inset-0 z-20 transition-transform duration-1000" style="background: linear-gradient(135deg, #336699 0%, #475569 100%); transform:translateX(0);"></div>
            <div id="curtain-right" class="absolute inset-0 z-10 transition-transform duration-1000" style="background: linear-gradient(135deg, #475569 0%, #64748b 100%); transform:translateX(0);"></div>
            <div class="mb-8 text-center z-30 relative animate-text-fade-up">
                <h1 class="text-4xl font-bold mb-2">
                    <i class="fas fa-shield-alt"></i> <?php echo APP_SHORT_NAME; ?>
                </h1>
                <p class="text-lg opacity-80">Welcome to <?php echo APP_NAME; ?> Admin Portal</p>
            </div>
            <div class="w-3/4 max-w-xs mb-6 rounded-xl shadow-lg z-30 relative overflow-hidden">
                <img id="animated-image" src="assets/images/login-illustration.jpg" alt="Login Illustration" class="w-full h-auto rounded-xl shadow-lg animate-image-scale">
            </div>
            <p class="text-base opacity-80 z-30 relative animate-text-fade-up" style="animation-delay:0.6s;">Secure, modern, and easy to use. Manage your cooperative with confidence.</p>
        </div>
        <div class="md:w-1/2 w-full flex flex-col justify-center p-10">
            <div class="mb-8 text-center">
                <h2 class="text-gray-800 text-2xl font-bold mb-2">
                    Administrator Login
                </h2>
            </div>
            <?php if ($db_needs_init): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                    <h5 class="text-yellow-800 font-semibold mb-2">
                        <i class="fas fa-database"></i> Database Setup Required
                    </h5>
                    <p class="text-yellow-700 mb-3">The system database needs to be initialized.</p>
                    <a href="config/init_db.php" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition-colors">
                        Initialize Database
                    </a>
                </div>
            <?php else: ?>
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
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 font-medium mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-white p-2 rounded-l-lg" style="background: linear-gradient(135deg, #1A5599 0%, #336699 100%);"></i>
                            </div>
                            <input type="text" class="form-control w-full pl-12 pr-4 py-3" 
                                   id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-white p-2 rounded-l-lg" style="background: linear-gradient(135deg, #1A5599 0%, #336699 100%);"></i>
                            </div>
                            <input type="password" class="form-control w-full pl-12 pr-4 py-3" 
                                   id="password" name="password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 px-4 font-semibold hover:-translate-y-1 transition-all duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i> Login
                    </button>
                </form>
                <div class="text-center mt-6">
                    <a href="views/auth/forgot_password.php" class="text-primary-600 hover:text-primary-800 transition-colors font-medium">
                        Forgot Password?
                    </a>
                </div>
                <hr class="my-6" style="border-color: rgba(180, 136, 235, 0.3);">
                <div class="flex gap-3">
                    <a href="views/member_login.php" class="flex-1 glass-dark text-white py-3 px-4 rounded-lg text-center transition-all duration-300 hover:transform hover:scale-105 font-medium">
                        <i class="fas fa-user mr-1"></i> Member Login
                    </a>
                    <a href="views/member_register.php" class="flex-1 btn-secondary py-3 px-4 text-center transition-all duration-300 hover:transform hover:scale-105 font-medium">
                        <i class="fas fa-user-plus mr-1"></i> Join
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<style>
@keyframes textFadeUp {
  0% { opacity: 0; transform: translateY(30px); }
  50% { opacity: 1; transform: translateY(0); }
  100% { opacity: 0; transform: translateY(-30px); }
}
.animate-text-fade-up {
  animation: textFadeUp 2.5s cubic-bezier(0.4,0,0.2,1) infinite;
}
@keyframes imageScale {
  0% { transform: scale(1.1); }
  50% { transform: scale(1); }
  100% { transform: scale(1.1); }
}
.animate-image-scale {
  animation: imageScale 2.5s cubic-bezier(0.4,0,0.2,1) infinite;
}
</style>
<script>
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.getElementById('curtain-left').style.transform = 'translateX(-100%)';
        document.getElementById('curtain-right').style.transform = 'translateX(100%)';
    }, 500);
});
</script>