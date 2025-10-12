<?php
class Session {
    private static $instance = null;
    
    private function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            $this->configureSecureSession();
            session_start();
        }
        $this->validateSession();
    }
    
    private function configureSecureSession() {
        // Only configure if session hasn't started yet
        if (session_status() == PHP_SESSION_NONE) {
            // Set secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            if (defined('SECURE_COOKIES') && SECURE_COOKIES) {
                ini_set('session.cookie_secure', 1);
            }
            
            // Prevent session fixation
            ini_set('session.use_strict_mode', 1);
            
            // Set session name
            session_name('CSIMS_SESSION');
            
            // Set session cookie parameters
            $cookieParams = [
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => defined('SECURE_COOKIES') && SECURE_COOKIES,
                'httponly' => true,
                'samesite' => 'Strict'
            ];
            session_set_cookie_params($cookieParams);
        }
    }
    
    private function validateSession() {
        // More flexible session validation for development/local environments
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';
        
        // In development, be more lenient with IP changes (localhost vs 127.0.0.1)
        if ($environment === 'development') {
            $this->validateSessionDevelopment();
        } else {
            $this->validateSessionProduction();
        }
        
        // Set initial security markers if not set
        if (!$this->exists('user_ip')) {
            $this->set('user_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        }
        
        if (!$this->exists('user_agent')) {
            $this->set('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        }
    }
    
    private function validateSessionDevelopment() {
        // More lenient validation for development
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stored_ip = $this->get('user_ip');
        
        // Allow localhost variations (127.0.0.1, ::1, localhost)
        $localhost_ips = ['127.0.0.1', '::1', 'localhost'];
        
        if ($stored_ip && $stored_ip !== $current_ip) {
            // If both are localhost variations, allow it
            $stored_is_local = in_array($stored_ip, $localhost_ips);
            $current_is_local = in_array($current_ip, $localhost_ips);
            
            if (!($stored_is_local && $current_is_local)) {
                // Log but don't destroy session in development
                if (class_exists('SecurityLogger')) {
                    SecurityLogger::logSecurityEvent('IP change detected in development', [
                        'stored_ip' => $stored_ip,
                        'current_ip' => $current_ip,
                        'session_id' => session_id()
                    ]);
                }
                // Update IP instead of destroying session
                $this->set('user_ip', $current_ip);
            }
        }
        
        // Skip user agent validation in development (browsers update frequently)
    }
    
    private function validateSessionProduction() {
        // Strict validation for production
        if ($this->exists('user_ip') && $this->get('user_ip') !== $_SERVER['REMOTE_ADDR']) {
            if (class_exists('SecurityLogger')) {
                SecurityLogger::logCriticalSecurity('Session hijacking attempt detected', [
                    'stored_ip' => $this->get('user_ip'),
                    'current_ip' => $_SERVER['REMOTE_ADDR'],
                    'session_id' => session_id()
                ]);
            }
            $this->destroy();
            return;
        }
        
        // Check for session fixation
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($this->exists('user_agent') && $this->get('user_agent') !== $currentUserAgent) {
            if (class_exists('SecurityLogger')) {
                SecurityLogger::logCriticalSecurity('Session fixation attempt detected', [
                    'stored_agent' => $this->get('user_agent'),
                    'current_agent' => $currentUserAgent,
                    'session_id' => session_id()
                ]);
            }
            $this->destroy();
            return;
        }
    }
    
    // Singleton pattern
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Session();
        }
        return self::$instance;
    }
    
    // Set session variable
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    // Get session variable
    public function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    // Check if session variable exists
    public function exists($key) {
        return isset($_SESSION[$key]);
    }
    
    // Remove session variable
    public function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    // Destroy session
    public function destroy() {
        session_unset();
        session_destroy();
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return $this->exists('admin_id');
    }
    
    // Set user as logged in
    public function login($admin) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $this->set('admin_id', $admin['admin_id']);
        $this->set('username', $admin['username']);
        $this->set('role', $admin['role']);
        $this->set('first_name', $admin['first_name']);
        $this->set('last_name', $admin['last_name']);
        $this->set('last_activity', time());
        $this->set('login_time', time());
        $this->set('session_created', time());
        
        // Set security markers
        $this->set('user_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        $this->set('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Log successful login
        SecurityLogger::logSecurityEvent('User login successful', [
            'user_id' => $admin['admin_id'],
            'username' => $admin['username'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    // Log user out
    public function logout() {
        // Log logout event
        if ($this->isLoggedIn()) {
            SecurityLogger::logSecurityEvent('User logout', [
                'user_id' => $this->get('admin_id'),
                'username' => $this->get('username')
            ]);
        }
        
        $this->destroy();
    }
    
    // Check session timeout and regenerate session ID periodically
    public function checkTimeout($timeout = 1800) {
        if ($this->isLoggedIn()) {
            $last_activity = $this->get('last_activity');
            $session_created = $this->get('session_created');
            
            // Check for session timeout
            if (time() - $last_activity > $timeout) {
                SecurityLogger::logSecurityEvent('Session timeout', [
                    'user_id' => $this->get('admin_id'),
                    'last_activity' => date('Y-m-d H:i:s', $last_activity)
                ]);
                $this->logout();
                return true;
            }
            
            // Regenerate session ID periodically for security
            $regenerateInterval = defined('SESSION_REGENERATE_INTERVAL') ? SESSION_REGENERATE_INTERVAL : 1800;
            if ($session_created && (time() - $session_created > $regenerateInterval)) {
                session_regenerate_id(true);
                $this->set('session_created', time());
            }
            
            // Update last activity time
            $this->set('last_activity', time());
        }
        return false;
    }
    
    // Set flash message
    public function setFlash($type, $message) {
        $this->set('flash_' . $type, $message);
    }
    
    // Get flash message and remove it
    public function getFlash($type) {
        $message = $this->get('flash_' . $type);
        $this->remove('flash_' . $type);
        return $message;
    }
    
    // Check if flash message exists
    public function hasFlash($type) {
        return $this->exists('flash_' . $type);
    }
}
?>