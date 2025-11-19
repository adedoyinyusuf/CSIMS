<?php
// Admin Login Page (renders UI instead of redirecting)
require_once __DIR__ . '/../../config/config.php';

// If already logged in as admin, go to dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

$error = '';
$success = '';

// Check if database needs initialization
$db_needs_init = false;
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $result = $conn->query("SHOW TABLES LIKE 'admins'");
    $db_needs_init = ($result && $result->num_rows === 0);
} catch (Exception $e) {
    $db_needs_init = true;
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (class_exists('CSRFProtection')) {
        CSRFProtection::validateRequest();
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            require_once __DIR__ . '/../../controllers/auth_controller.php';
            $authController = new AuthController();
            $loginResult = $authController->login($username, $password);

            if (!empty($loginResult['success'])) {
                header('Location: ../admin/dashboard.php');
                exit();
            } else {
                $error = $loginResult['message'] ?? 'Invalid username or password.';
            }
        } catch (Exception $e) {
            // Fallback: simple login
            try {
                $stmt = $conn->prepare("SELECT admin_id, username, password, first_name, last_name, email, role, status FROM admins WHERE username = ? AND status = 'Active'");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows === 1) {
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
                        header('Location: ../admin/dashboard.php');
                        exit();
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (Exception $e2) {
                $error = 'Login system temporarily unavailable. Please try again later.';
                error_log('Auth fallback error: ' . $e2->getMessage());
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
    <title><?php echo APP_NAME; ?> - Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <style>
        /* Subtle brand-side motion with reduced-motion respect */
        @keyframes brandGradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .brand-animated { animation: brandGradientShift 28s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) {
            .brand-animated { animation: none !important; }
        }
        /* Subtle vignette to focus content */
        .vignette { box-shadow: inset 0 0 120px rgba(0,0,0,0.25); }
        /* Glass effect for the form panel */
        .glass { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); }
        /* Input icon placement */
        .input-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280; }
        .input-with-icon { padding-left: 2.5rem; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center font-sans" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 25%, #cbd5e1 50%, #94a3b8 75%, #64748b 100%);">
    <div class="w-full max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-0 rounded-3xl shadow-2xl overflow-hidden bg-white">
        <!-- Brand / Welcome Panel -->
        <div class="hidden md:flex flex-col items-start justify-center p-12 text-white brand-animated vignette" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%); background-size: 240% 240%;">
            <div class="w-16 h-16 rounded-xl bg-white/10 border border-white/40 flex items-center justify-center mb-6 overflow-hidden">
                <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                    <img src="<?php echo APP_LOGO_URL; ?>" alt="<?php echo APP_SHORT_NAME; ?> Logo" class="w-12 h-12 object-contain" />
                <?php else: ?>
                    <i class="fas fa-shield-alt text-2xl text-white"></i>
                <?php endif; ?>
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight mb-3 drop-shadow-[0_2px_8px_rgba(0,0,0,0.45)]"><?php echo APP_SHORT_NAME; ?></h1>
            <p class="text-white/95 drop-shadow-[0_1px_5px_rgba(0,0,0,0.35)] mb-8">Secure, modern, and easy to use. Manage your cooperative with confidence.</p>

            <?php
            $heroPath = __DIR__ . '/../../assets/images/login-hero.jpg';
            $heroUrl = BASE_URL . '/assets/images/login-hero.jpg';
            if (file_exists($heroPath)):
            ?>
                <img src="<?php echo $heroUrl; ?>" alt="Cooperative activity" class="rounded-xl shadow-2xl border border-white/20 w-11/12 max-w-md mb-8" />
            <?php endif; ?>
        </div>

        <!-- Login Form Panel -->
        <div class="p-8 md:p-12 glass">
            <div class="mb-6">
                <h2 class="text-gray-800 text-2xl font-bold mb-1">Administrator Login</h2>
                <p class="text-gray-600">Welcome to <?php echo APP_SHORT_NAME; ?></p>
            </div>

            <?php if ($db_needs_init): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                    <h5 class="text-yellow-800 font-semibold mb-2">
                        <i class="fas fa-database"></i> Database Setup Required
                    </h5>
                    <p class="text-yellow-700 mb-3">The system database needs to be initialized.</p>
                    <a href="<?php echo BASE_URL; ?>/config/init_db.php" class="bg-yellow-500 text-white px-4 py-2 rounded-lg">
                        Initialize Database
                    </a>
                    <p class="text-xs text-yellow-700 mt-2">Default admin: username "admin", password "admin123" (change after login).</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span class="text-red-700 ml-2"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <?php if (class_exists('CSRFProtection')) { echo CSRFProtection::getTokenField(); } ?>
                <div class="relative">
                    <label for="username" class="block text-gray-700 font-medium mb-2">Username</label>
                    <i class="fas fa-user input-icon" aria-hidden="true"></i>
                    <input type="text" class="form-control input-with-icon w-full px-4 py-3 border rounded-lg" id="username" name="username" placeholder="Enter your username" required autofocus>
                </div>
                <div class="relative">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                    <input type="password" class="form-control input-with-icon w-full px-4 py-3 border rounded-lg" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn-primary w-full py-3 px-4 font-semibold rounded-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>

            <div class="text-center mt-6">
                <a href="<?php echo BASE_URL; ?>/views/auth/forgot_password.php" class="text-primary-600 font-medium">
                    Forgot Password?
                </a>
            </div>

            <hr class="my-6">

            <div class="flex gap-3">
                <a href="<?php echo BASE_URL; ?>/views/member_login.php" class="flex-1 bg-gray-800 text-white py-3 px-4 rounded-lg text-center font-medium">
                    <i class="fas fa-user mr-1"></i> Member Login
                </a>
                <a href="<?php echo BASE_URL; ?>/views/member_register.php" class="flex-1 btn-secondary py-3 px-4 text-center font-medium">
                    <i class="fas fa-user-plus mr-1"></i> Join
                </a>
            </div>
        </div>
    </div>
</body>
</html>