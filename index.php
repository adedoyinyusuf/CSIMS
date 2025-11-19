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
            header('Location: views/admin/dashboard.php');
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
        header('Location: views/admin/dashboard.php');
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
                // Successful login - redirect to working dashboard
                header('Location: views/admin/dashboard.php');
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
                        header('Location: views/admin/dashboard.php');
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
    <style>
        /* Static banner helpers (no animation) */
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
    <div class="w-full max-w-5xl mx-auto flex flex-col md:flex-row rounded-3xl shadow-2xl overflow-hidden border border-white/20 glass">
        <div class="md:w-1/2 w-full flex flex-col justify-center items-center p-10 banner-vignette pattern-overlay" style="background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);">
            <div class="w-24 h-24 md:w-28 md:h-28 rounded-full bg-white/95 shadow-lg flex items-center justify-center mb-4 border border-white/60">
                <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                    <img src="<?php echo APP_LOGO_URL; ?>" alt="CTLStaff Logo" class="w-16 h-16 md:w-20 md:h-20 object-contain" />
                <?php else: ?>
                    <i class="fas fa-shield-alt text-3xl text-primary-800"></i>
                <?php endif; ?>
            </div>
            <div class="mb-6 text-center">
                <h1 class="text-5xl md:text-6xl font-extrabold mb-3 text-white tracking-tight">
                    <?php echo APP_SHORT_NAME; ?>
                </h1>
                <p class="text-lg md:text-xl text-white/90">Welcome to <?php echo APP_NAME; ?> Admin Portal</p>
            </div>
            <p class="text-base md:text-lg text-white/85 text-center max-w-xl">Secure, modern, and easy to use. Manage your cooperative with confidence.</p>
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
                    <a href="views/member_login.php" class="flex-1 glass-dark text-white py-3 px-4 rounded-lg text-center font-medium">
                        <i class="fas fa-user mr-1"></i> Member Login
                    </a>
                    <a href="views/member_register.php" class="flex-1 btn-secondary py-3 px-4 text-center font-medium">
                        <i class="fas fa-user-plus mr-1"></i> Join
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<!-- Removed animations and transitions for left panel to keep static banner -->