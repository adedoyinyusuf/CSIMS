<?php
// Clean login page without complex session initialization
session_start();

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';
$success = '';

// Check if user is already logged in
if (isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header('Location: simple_dashboard.php');
    exit();
}

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Simple database connection
            require_once 'config/database.php';
            require_once 'includes/db.php';
            
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Query for user
            $stmt = $conn->prepare("SELECT admin_id, username, password, first_name, last_name, email, role, status FROM admins WHERE username = ? AND status = 'Active'");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                if (password_verify($password, $admin['password'])) {
                    // Successful login - set session variables
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['role'] = $admin['role'];
                    $_SESSION['first_name'] = $admin['first_name'];
                    $_SESSION['last_name'] = $admin['last_name'];
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['last_activity'] = time();
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
                    $updateStmt->bind_param("i", $admin['admin_id']);
                    $updateStmt->execute();
                    
                    // Set success message
                    $_SESSION['success_message'] = 'Login successful!';
                    
                    // Redirect to dashboard
                    header('Location: simple_dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $error = 'Login system temporarily unavailable. Please try again later.';
            error_log('Clean login error: ' . $e->getMessage());
        }
    }
}

// Check if database needs initialization
$db_needs_init = false;
try {
    require_once 'config/database.php';
    require_once 'includes/db.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $result = $conn->query("SHOW TABLES LIKE 'admins'");
    $db_needs_init = ($result->num_rows === 0);
} catch (Exception $e) {
    $db_needs_init = true;
}

// Load config for app name
try {
    require_once 'config/config.php';
} catch (Exception $e) {
    define('APP_NAME', 'CSIMS');
    define('APP_SHORT_NAME', 'CSIMS');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'CSIMS'; ?> - Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 25%, #cbd5e1 50%, #94a3b8 75%, #64748b 100%);
        }
        .glass {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }
        .form-control {
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #1A5599;
            box-shadow: 0 0 0 3px rgba(26, 85, 153, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #1A5599 0%, #336699 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(26, 85, 153, 0.3);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center font-sans">
    <div class="w-full max-w-4xl mx-auto flex flex-col md:flex-row rounded-2xl shadow-2xl overflow-hidden bg-white">
        <!-- Left Side - Branding -->
        <div class="md:w-1/2 w-full flex flex-col justify-center items-center p-8" style="background: linear-gradient(135deg, #1A5599 0%, #336699 50%, #334155 100%);">
            <div class="text-center text-white">
                <h1 class="text-4xl font-bold mb-4">
                    <i class="fas fa-shield-alt"></i> <?php echo defined('APP_SHORT_NAME') ? APP_SHORT_NAME : 'CSIMS'; ?>
                </h1>
                <p class="text-lg opacity-90 mb-6">Welcome to <?php echo defined('APP_NAME') ? APP_NAME : 'Cooperative Society Information Management System'; ?></p>
                <div class="w-32 h-32 mx-auto mb-6 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-6xl text-white/90"></i>
                </div>
                <p class="opacity-80">Secure, modern, and easy to use admin portal</p>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="md:w-1/2 w-full flex flex-col justify-center p-8">
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold text-gray-800">Administrator Login</h2>
                <p class="text-gray-600 mt-2">Please sign in to your account</p>
            </div>
            
            <?php if ($db_needs_init): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <h5 class="text-yellow-800 font-semibold mb-2">
                        <i class="fas fa-database mr-2"></i>Database Setup Required
                    </h5>
                    <p class="text-yellow-700 mb-3">The system database needs to be initialized.</p>
                    <a href="config/init_db.php" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition-colors">
                        Initialize Database
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                            <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span class="text-green-700"><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="username" class="block text-gray-700 font-medium mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" class="form-control w-full pl-10 pr-4 py-3 rounded-lg" 
                                   id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                                   placeholder="Enter your username" required autofocus>
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" class="form-control w-full pl-10 pr-4 py-3 rounded-lg" 
                                   id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary w-full py-3 px-4 font-semibold rounded-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>
                
                <div class="text-center mt-6">
                    <a href="#" class="text-blue-600 hover:text-blue-800 transition-colors">Forgot Password?</a>
                </div>
                
                <hr class="my-6 border-gray-300">
                
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-4">Not an administrator?</p>
                    <div class="space-y-2">
                        <a href="views/member_login.php" class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-user mr-2"></i>Member Login
                        </a>
                        <a href="views/member_register.php" class="block w-full bg-blue-100 hover:bg-blue-200 text-blue-800 py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>Join as Member
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-8 text-center">
                <p class="text-xs text-gray-500">
                    Having issues? Try the <a href="debug_login.php" class="text-blue-600 hover:underline">debug login tool</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>