<?php
// Application Configuration

// Application Information
define('APP_NAME', 'NPC CTLStaff Loan Society');
define('APP_SHORT_NAME', 'NPC CTLStaff');
define('APP_VERSION', '1.0.0');

// Environment Configuration
define('ENVIRONMENT', 'development'); // Change to 'production' for live environment

// URL Configuration
// Detect if running from CSIMS directory or subdirectory
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;
// Remove /views/admin or similar paths to get the base
if (strpos($base_path, '/views') !== false) {
    $base_path = substr($base_path, 0, strpos($base_path, '/views'));
}
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . $base_path);

// Directory Configuration
define('ROOT_DIR', dirname(__DIR__));
define('ASSETS_DIR', ROOT_DIR . '/assets');
define('UPLOADS_DIR', ASSETS_DIR . '/uploads/');
define('IMAGES_DIR', ASSETS_DIR . '/images/');

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

// Session Configuration
define('SESSION_TIMEOUT', 1800);

// Error Reporting (Disable in production)
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_DIR . '/logs/php_errors.log');
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Date & Time Configuration
date_default_timezone_set('Africa/Lagos');

// Email Configuration
define('MAIL_FROM', 'noreply@csims.com');
define('MAIL_FROM_NAME', APP_NAME);

// Pagination Configuration
define('ITEMS_PER_PAGE', 10);

// Security Configuration
define('CSRF_TOKEN_SECRET', bin2hex(random_bytes(32))); // Generate random secret
define('PASSWORD_RESET_EXPIRY', 24);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_REGENERATE_INTERVAL', 1800); // 30 minutes
define('FORCE_HTTPS', false); // Set to true in production
define('SECURE_COOKIES', false); // Set to true when using HTTPS
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);

// Security Headers Configuration
define('SECURITY_HEADERS', [
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.datatables.net https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self';"
]);

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Include required files
require_once ROOT_DIR . '/includes/db.php';
require_once ROOT_DIR . '/includes/session.php';
require_once ROOT_DIR . '/includes/utilities.php';
require_once ROOT_DIR . '/config/security.php';

// Initialize session
$session = Session::getInstance();

// Check session timeout
$session->checkTimeout(SESSION_TIMEOUT);
?>