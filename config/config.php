<?php
// Application Configuration

// Application Information
if (!defined('APP_NAME')) define('APP_NAME', 'NPC CTLStaff Loan Society');
if (!defined('APP_SHORT_NAME')) define('APP_SHORT_NAME', 'NPC CTLStaff');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

// Environment Configuration
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'development'); // Change to 'production' for live environment

// URL Configuration
// Detect if running from CSIMS directory or subdirectory
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;
// Normalize base path by removing known subdirectories (views, admin, api, src)
$remove_dirs = ['/views', '/admin', '/api', '/src'];
foreach ($remove_dirs as $dir) {
    $pos = strpos($base_path, $dir);
    if ($pos !== false) {
        $base_path = substr($base_path, 0, $pos);
        break;
    }
}
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}
if (!defined('BASE_URL')) define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . $base_path);

// Directory Configuration
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
if (!defined('ASSETS_DIR')) define('ASSETS_DIR', ROOT_DIR . '/assets');
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', ASSETS_DIR . '/uploads/');
if (!defined('IMAGES_DIR')) define('IMAGES_DIR', ASSETS_DIR . '/images/');

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

// Session Configuration
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 1800);

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
if (!defined('MAIL_FROM')) define('MAIL_FROM', 'noreply@csims.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', APP_NAME);

// Pagination Configuration
if (!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 10);

// Security Configuration
if (!defined('CSRF_TOKEN_SECRET')) define('CSRF_TOKEN_SECRET', bin2hex(random_bytes(32))); // Generate random secret
if (!defined('PASSWORD_RESET_EXPIRY')) define('PASSWORD_RESET_EXPIRY', 24);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
if (!defined('SESSION_REGENERATE_INTERVAL')) define('SESSION_REGENERATE_INTERVAL', 1800); // 30 minutes
if (!defined('FORCE_HTTPS')) define('FORCE_HTTPS', false); // Set to true in production
if (!defined('SECURE_COOKIES')) define('SECURE_COOKIES', false); // Set to true when using HTTPS
if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', 8);
if (!defined('PASSWORD_REQUIRE_SPECIAL')) define('PASSWORD_REQUIRE_SPECIAL', true);
if (!defined('PASSWORD_REQUIRE_NUMBERS')) define('PASSWORD_REQUIRE_NUMBERS', true);
if (!defined('PASSWORD_REQUIRE_UPPERCASE')) define('PASSWORD_REQUIRE_UPPERCASE', true);
if (!defined('PASSWORD_REQUIRE_LOWERCASE')) define('PASSWORD_REQUIRE_LOWERCASE', true);

// Security Headers Configuration
if (!defined('SECURITY_HEADERS')) define('SECURITY_HEADERS', [
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.datatables.net https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.datatables.net https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self';"
]);

// File Upload Configuration
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Include required files
require_once ROOT_DIR . '/includes/db.php';
require_once ROOT_DIR . '/includes/session.php';
require_once ROOT_DIR . '/includes/utilities.php';
require_once ROOT_DIR . '/config/security.php';

// Initialize session
$session = Session::getInstance();

// Check session timeout
$session->checkTimeout(SESSION_TIMEOUT);
// Application Logo URL detection (centralized)
// Finds a suitable logo in assets/images and exposes APP_LOGO_URL for all views
if (!defined('APP_LOGO_URL')) {
    $logoUrl = '';

    // Preferred explicit candidates first
    $explicitCandidates = [
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'ctlstaff-logo.png',
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'CTLStaff-Logo.png',
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'logo_ctlstaff.png',
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'logo.png',
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'logo.webp',
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'logo.jpg',
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'logo.jpeg',
        IMAGES_DIR . DIRECTORY_SEPARATOR . 'logo.svg'
    ];
    $found = null;
    foreach ($explicitCandidates as $candidate) {
        if (file_exists($candidate)) { $found = $candidate; break; }
    }

    // If not found, scan images directory for files containing "logo" or "ctlstaff"
    if (!$found && is_dir(IMAGES_DIR)) {
        $entries = @scandir(IMAGES_DIR);
        if ($entries) {
            // Rank by extension preference
            $extWeight = ['png' => 10, 'svg' => 9, 'webp' => 8, 'jpg' => 7, 'jpeg' => 7, 'gif' => 5];
            $candidates = [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $lower = strtolower($entry);
                if (strpos($lower, 'logo') !== false || strpos($lower, 'ctlstaff') !== false) {
                    $ext = pathinfo($entry, PATHINFO_EXTENSION);
                    $weight = $extWeight[strtolower($ext)] ?? 1;
                    $candidates[] = ['file' => IMAGES_DIR . DIRECTORY_SEPARATOR . $entry, 'weight' => $weight];
                }
            }
            if (!empty($candidates)) {
                usort($candidates, function($a, $b) { return $b['weight'] <=> $a['weight']; });
                $found = $candidates[0]['file'];
            }
        }
    }

    if ($found) {
        // Convert to web URL
        $logoUrl = BASE_URL . '/assets/images/' . basename($found);
    }

    define('APP_LOGO_URL', $logoUrl);
}