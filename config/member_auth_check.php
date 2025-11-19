<?php
/**
 * Member Authentication Check
 * - Centralizes session validation and security checks for member-facing pages
 * - Enforces CSRF on POST, session timeout, IP consistency, and security headers
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/session.php';

// Initialize secure session instance
$session = Session::getInstance();

// Normalize member session state for legacy and service-auth flows
$memberId = $_SESSION['member_id'] ?? $_SESSION['user_id'] ?? null;
if ($memberId && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member')) {
    // Auto-correct user_type for member-facing pages when a member ID exists
    $_SESSION['user_type'] = 'member';
    // Optional: log normalization for traceability
    try {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'member_session_normalized',
            'member_id' => $memberId,
            'url' => $_SERVER['REQUEST_URI'] ?? ''
        ];
        @file_put_contents($logDir . '/member_auth_debug.log', json_encode($debug) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $t) { /* ignore logging errors */ }
}

// Force HTTPS in production
if (defined('FORCE_HTTPS') && FORCE_HTTPS && !isset($_SERVER['HTTPS'])) {
    $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirectURL");
    exit;
}

// Determine if member is authenticated
$memberLoggedIn = (
    ($session->get('user_type') === 'member') && 
    ($session->exists('member_id') || $session->exists('user_id'))
);

if (!$memberLoggedIn) {
    // Debug: log state before redirecting unauthenticated member
    try {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'member_auth_failed',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'env' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
            'session' => [
                'user_type' => $_SESSION['user_type'] ?? null,
                'member_id' => $_SESSION['member_id'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null,
                'last_activity' => $_SESSION['last_activity'] ?? null,
                'user_ip' => $_SESSION['user_ip'] ?? null,
                'user_agent' => $_SESSION['user_agent'] ?? null,
                'keys' => array_keys($_SESSION ?? [])
            ],
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        @file_put_contents($logDir . '/member_auth_debug.log', json_encode($debug) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $t) { /* ignore logging errors */ }
    if (class_exists('SecurityLogger')) {
        SecurityLogger::logSuspiciousActivity('Unauthorized member access attempt', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    // Clear any existing session data for safety
    session_unset();
    // Redirect to member login
    header('Location: ' . BASE_URL . '/views/member_login.php?error=unauthorized');
    exit;
}

// Member session timeout handling (custom, because Session::checkTimeout focuses on admin)
$lastActivity = $_SESSION['last_activity'] ?? null;
if ($lastActivity && (time() - $lastActivity > SESSION_TIMEOUT)) {
    // Debug: log timeout event
    try {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'member_session_timeout',
            'member_id' => $_SESSION['member_id'] ?? null,
            'duration' => time() - $lastActivity,
            'url' => $_SERVER['REQUEST_URI'] ?? ''
        ];
        @file_put_contents($logDir . '/member_auth_debug.log', json_encode($debug) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $t) { /* ignore logging errors */ }
    if (class_exists('SecurityLogger')) {
        SecurityLogger::logSecurityEvent('Member session timeout', [
            'member_id' => $_SESSION['member_id'] ?? null,
            'duration' => time() - $lastActivity
        ]);
    }
    $session->logout();
    header('Location: ' . BASE_URL . '/views/member_login.php?error=session_timeout');
    exit;
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Allow AJAX with header token if present
    $skipCSRF = (class_exists('Utilities') && Utilities::isAjaxRequest()) &&
                isset($_SERVER['HTTP_X_CSRF_TOKEN']) &&
                class_exists('CSRFProtection') && CSRFProtection::validateToken($_SERVER['HTTP_X_CSRF_TOKEN']);
    if (!$skipCSRF && class_exists('CSRFProtection')) {
        CSRFProtection::validateRequest();
    }
}

// IP consistency check to reduce hijacking risk (strict in production, lenient in development)
$currentIP = class_exists('Utilities') ? Utilities::getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '');
$sessionIP = $_SESSION['user_ip'] ?? '';
$env = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';
if (!empty($sessionIP) && $sessionIP !== $currentIP) {
    if ($env === 'production') {
        // Debug: log IP mismatch event
        try {
            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
            $debug = [
                'timestamp' => date('Y-m-d H:i:s'),
                'event' => 'member_ip_mismatch',
                'user_id' => $_SESSION['member_id'] ?? $_SESSION['user_id'] ?? null,
                'original_ip' => $sessionIP,
                'current_ip' => $currentIP,
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ];
            @file_put_contents($logDir . '/member_auth_debug.log', json_encode($debug) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $t) { /* ignore logging errors */ }
        if (class_exists('SecurityLogger')) {
            SecurityLogger::logCriticalSecurity('Potential member session hijacking detected', [
                'user_id' => $_SESSION['member_id'] ?? $_SESSION['user_id'] ?? null,
                'original_ip' => $sessionIP,
                'current_ip' => $currentIP,
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
        }
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/views/member_login.php?error=security');
        exit;
    } else {
        // In development, update stored IP to avoid unnecessary logouts
        $_SESSION['user_ip'] = $currentIP;
        if (class_exists('SecurityLogger')) {
            SecurityLogger::logSecurityEvent('Member IP updated in development', [
                'original_ip' => $sessionIP,
                'current_ip' => $currentIP,
            ]);
        }
    }
}

// Security headers for member pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Make current member info available globally (array form)
$current_member = [
    'id' => $_SESSION['member_id'] ?? $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['member_username'] ?? $_SESSION['username'] ?? null,
    'full_name' => $_SESSION['member_name'] ?? $_SESSION['full_name'] ?? null,
    'email' => $_SESSION['member_email'] ?? $_SESSION['email'] ?? null,
];