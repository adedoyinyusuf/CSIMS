<?php
class Session {
    private static $instance = null;
    
    private function __construct() {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
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
        $this->set('admin_id', $admin['admin_id']);
        $this->set('username', $admin['username']);
        $this->set('role', $admin['role']);
        $this->set('first_name', $admin['first_name']);
        $this->set('last_name', $admin['last_name']);
        $this->set('last_activity', time());
    }
    
    // Log user out
    public function logout() {
        $this->destroy();
    }
    
    // Check session timeout (30 minutes)
    public function checkTimeout($timeout = 1800) {
        if ($this->isLoggedIn()) {
            $last_activity = $this->get('last_activity');
            if (time() - $last_activity > $timeout) {
                $this->logout();
                return true;
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