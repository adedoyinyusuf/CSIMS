<?php
/**
 * Enhanced Authentication Check for Admin Pages
 * Includes CSRF protection, session validation, and security logging
 */
require_once __DIR__ . '/config.php';

// Force HTTPS in production
if (defined('FORCE_HTTPS') && FORCE_HTTPS && !isset($_SERVER['HTTPS'])) {
    $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirectURL");
    exit;
}

// Check if user is logged in
if (!$session->isLoggedIn()) {
    SecurityLogger::logSuspiciousActivity('Unauthorized access attempt', [
        'url' => $_SERVER['REQUEST_URI'],
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Clear any existing session data
    $session->destroy();
    
    header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
    exit;
}

// Check session timeout
if ($session->checkTimeout(SESSION_TIMEOUT)) {
    header('Location: ' . BASE_URL . '/index.php?expired=1');
    exit;
}

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Skip CSRF check for AJAX requests with proper headers (optional)
    $skipCSRF = Utilities::isAjaxRequest() && 
                isset($_SERVER['HTTP_X_CSRF_TOKEN']) && 
                CSRFProtection::validateToken($_SERVER['HTTP_X_CSRF_TOKEN']);
    
    if (!$skipCSRF) {
        CSRFProtection::validateRequest();
    }
}

// Additional security checks
$currentIP = Utilities::getClientIP();
$sessionIP = $session->get('user_ip');

// Check for IP address changes (potential session hijacking)
if ($sessionIP && $sessionIP !== $currentIP) {
    SecurityLogger::logCriticalSecurity('Potential session hijacking detected', [
        'user_id' => $session->get('admin_id'),
        'username' => $session->get('username'),
        'original_ip' => $sessionIP,
        'current_ip' => $currentIP,
        'url' => $_SERVER['REQUEST_URI']
    ]);
    
    $session->logout();
    header('Location: ' . BASE_URL . '/index.php?error=security');
    exit;
}

// Rate limiting for sensitive operations
if (in_array($_SERVER['REQUEST_URI'], ['/admin/settings.php', '/admin/users.php'])) {
    $rateLimitKey = $session->get('admin_id') . '_sensitive_ops';
    if (!RateLimiter::checkLimit($rateLimitKey, 10, 300)) { // 10 requests per 5 minutes
        SecurityLogger::logSuspiciousActivity('Rate limit exceeded for sensitive operations', [
            'user_id' => $session->get('admin_id'),
            'url' => $_SERVER['REQUEST_URI']
        ]);
        
        http_response_code(429);
        die('Rate limit exceeded. Please try again later.');
    }
}

// Log access to sensitive pages
$sensitivePaths = ['/admin/settings.php', '/admin/users.php', '/admin/security.php'];
foreach ($sensitivePaths as $path) {
    if (strpos($_SERVER['REQUEST_URI'], $path) !== false) {
        SecurityLogger::logSecurityEvent('Sensitive page access', [
            'user_id' => $session->get('admin_id'),
            'username' => $session->get('username'),
            'page' => $_SERVER['REQUEST_URI'],
            'ip' => $currentIP
        ]);
        break;
    }
}

// Set security headers for admin pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Make current user info available globally
$current_user = [
    'id' => $session->get('admin_id'),
    'username' => $session->get('username'),
    'role' => $session->get('role'),
    'first_name' => $session->get('first_name'),
    'last_name' => $session->get('last_name')
];
?>