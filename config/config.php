<?php
// Application Configuration

// Application Information
define('APP_NAME', 'Cooperative Society Information Management System');
define('APP_SHORT_NAME', 'CSIMS');
define('APP_VERSION', '1.0.0');

// URL Configuration
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/CSIMS');

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
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Date & Time Configuration
date_default_timezone_set('Africa/Lagos'); // Change according to your location

// Email Configuration
define('MAIL_FROM', 'noreply@csims.com');
define('MAIL_FROM_NAME', APP_NAME);

// Pagination Configuration
define('ITEMS_PER_PAGE', 10);

// Security Configuration
define('CSRF_TOKEN_SECRET', 'csims_secret_token'); // Change this to a random string in production
define('PASSWORD_RESET_EXPIRY', 24); // Password reset link expiry in hours

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Include required files
require_once ROOT_DIR . '/includes/db.php';
require_once ROOT_DIR . '/includes/session.php';
require_once ROOT_DIR . '/includes/utilities.php';

// Initialize session
$session = Session::getInstance();

// Check session timeout
$session->checkTimeout(SESSION_TIMEOUT);
?>