<?php
// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include required files
require_once 'config/database.php';
require_once 'includes/db.php';
require_once 'config/config.php';
require_once 'includes/session.php';

// Initialize session
$session = Session::getInstance();

// Check if user is already logged in
if (isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header('Location: views/admin/dashboard.php');
    exit();
}

// Handle login form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Get database connection
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Prepare and execute query
            $stmt = $conn->prepare("SELECT admin_id, username, password, first_name, last_name, email, role, status FROM admins WHERE username = ? AND status = 'Active'");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $admin['password'])) {
                    // Set session variables
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['role'] = $admin['role'];
                    $_SESSION['first_name'] = $admin['first_name'];
                    $_SESSION['last_name'] = $admin['last_name'];
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['last_activity'] = time();
                    
                    // Update last login
                    $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
                    $updateStmt->bind_param("i", $admin['admin_id']);
                    $updateStmt->execute();
                    
                    // Redirect to dashboard
                    header('Location: views/admin/dashboard.php');
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
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

// Check if database needs initialization
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
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind CSS configuration
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: {
                                50: '#eff6ff',
                                100: '#dbeafe',
                                500: '#667eea',
                                600: '#4f46e5',
                                700: '#4338ca',
                                800: '#3730a3',
                                900: '#312e81'
                            }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-primary-500 to-purple-600 flex items-center justify-center font-sans">
    <div class="bg-white/95 backdrop-blur-lg rounded-3xl shadow-2xl border border-white/20 w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-gray-800 text-2xl font-bold mb-2">
                <i class="fas fa-shield-alt text-primary-500"></i> <?php echo APP_SHORT_NAME; ?>
            </h1>
            <p class="text-gray-600">Administrator Login</p>
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
                            <i class="fas fa-user text-white bg-gradient-to-r from-primary-500 to-purple-600 p-2 rounded-l-lg"></i>
                        </div>
                        <input type="text" class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all" 
                               id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-white bg-gradient-to-r from-primary-500 to-purple-600 p-2 rounded-l-lg"></i>
                        </div>
                        <input type="password" class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all" 
                               id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-primary-500 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </button>
            </form>
            
            <div class="text-center mt-6">
                <a href="views/auth/forgot_password.php" class="text-primary-600 hover:text-primary-700 transition-colors">
                    Forgot Password?
                </a>
            </div>
            
            <hr class="my-6 border-gray-200">
            
            <div class="flex gap-3">
                <a href="views/member_login.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg text-center transition-colors">
                    <i class="fas fa-user mr-1"></i> Member Login
                </a>
                <a href="views/member_register.php" class="flex-1 bg-green-100 hover:bg-green-200 text-green-700 py-2 px-4 rounded-lg text-center transition-colors">
                    <i class="fas fa-user-plus mr-1"></i> Join
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
        });
    </script>
</body>
</html>