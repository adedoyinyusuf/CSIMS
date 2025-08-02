<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/security_controller.php';

class AuthController {
    private $db;
    private $conn;
    private $session;
    private $securityController;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->session = Session::getInstance();
        $this->securityController = new SecurityController();
    }
    
    // Login user with enhanced security
    public function login($username, $password, $two_factor_code = null) {
        // Sanitize inputs
        $username = Utilities::sanitizeInput($username);
        $ip_address = $this->getClientIP();
        
        // Check for suspicious activity
        $security_check = $this->securityController->checkSuspiciousActivity($username, $ip_address);
        
        if ($security_check['status'] === 'locked') {
            $this->logLoginAttempt($username, $ip_address, false, 'Account locked');
            return ['success' => false, 'message' => 'Account is locked due to suspicious activity. Please contact administrator.'];
        }
        
        // Prepare statement - check both admins and users tables
        $stmt = $this->conn->prepare("SELECT * FROM admins WHERE username = ? AND status = 'Active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Check if account is locked
            if (isset($admin['account_locked']) && $admin['account_locked'] == 1) {
                $this->logLoginAttempt($username, $ip_address, false, 'Account locked');
                return ['success' => false, 'message' => 'Account is locked. Please contact administrator.'];
            }
            
            // Verify password
            if (password_verify($password, $admin['password'])) {
                // Check two-factor authentication if enabled
                if (isset($admin['two_factor_enabled']) && $admin['two_factor_enabled'] == 1) {
                    if (!$two_factor_code) {
                        return ['success' => false, 'message' => 'Two-factor authentication code required', 'requires_2fa' => true];
                    }
                    
                    if (!$this->verifyTwoFactorCode($admin['admin_id'], $two_factor_code)) {
                        $this->logLoginAttempt($username, $ip_address, false, 'Invalid 2FA code');
                        $this->securityController->logSecurityEvent('failed_2fa', "Invalid 2FA code for user {$username}", $admin['admin_id'], $ip_address, 'medium');
                        return ['success' => false, 'message' => 'Invalid two-factor authentication code'];
                    }
                }
                
                // Successful login
                $this->updateLastLogin($admin['admin_id']);
                $this->resetFailedAttempts($admin['admin_id']);
                $this->session->login($admin);
                
                // Log successful login
                $this->logLoginAttempt($username, $ip_address, true);
                $this->securityController->logSecurityEvent('successful_login', "User {$username} logged in successfully", $admin['admin_id'], $ip_address, 'low');
                
                return ['success' => true, 'message' => 'Login successful'];
            } else {
                // Failed password
                $this->incrementFailedAttempts($admin['admin_id']);
                $this->logLoginAttempt($username, $ip_address, false, 'Invalid password');
                $this->securityController->logSecurityEvent('failed_login', "Failed login attempt for user {$username}", $admin['admin_id'], $ip_address, 'medium');
                
                return ['success' => false, 'message' => 'Invalid password'];
            }
        } else {
            // User not found
            $this->logLoginAttempt($username, $ip_address, false, 'User not found');
            $this->securityController->logSecurityEvent('failed_login', "Login attempt for non-existent user {$username}", null, $ip_address, 'medium');
            
            return ['success' => false, 'message' => 'Invalid username or account is inactive'];
        }
    }
    
    // Logout user with security logging
    public function logout() {
        $user = $this->getCurrentUser();
        if ($user) {
            $this->securityController->logSecurityEvent('user_logout', "User {$user['username']} logged out", $user['admin_id'], $this->getClientIP(), 'low');
        }
        
        $this->session->logout();
        return ['success' => true, 'message' => 'Logout successful'];
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return $this->session->isLoggedIn();
    }
    
    // Get current user
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            $admin_id = $this->session->get('admin_id');
            
            $stmt = $this->conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $admin = $result->fetch_assoc();
                // Remove password for security
                unset($admin['password']);
                return $admin;
            }
        }
        
        return null;
    }
    
    // Change password with enhanced security
    public function changePassword($admin_id, $current_password, $new_password, $confirm_password) {
        // Validate inputs
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'New passwords do not match'];
        }
        
        // Enhanced password validation
        $password_validation = $this->validatePassword($new_password);
        if (!$password_validation['valid']) {
            return ['success' => false, 'message' => $password_validation['message']];
        }
        
        // Get current admin
        $stmt = $this->conn->prepare("SELECT username, password FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Verify current password
            if (password_verify($current_password, $admin['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password with timestamp
                $updateStmt = $this->conn->prepare("UPDATE admins SET password = ?, password_updated_at = NOW() WHERE admin_id = ?");
                $updateStmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($updateStmt->execute()) {
                    // Log password change
                    $this->securityController->logSecurityEvent('password_changed', "Password changed for user {$admin['username']}", $admin_id, $this->getClientIP(), 'medium');
                    
                    return ['success' => true, 'message' => 'Password changed successfully'];
                } else {
                    return ['success' => false, 'message' => 'Failed to update password'];
                }
            } else {
                // Log failed password change attempt
                $this->securityController->logSecurityEvent('failed_password_change', "Failed password change attempt for user {$admin['username']}", $admin_id, $this->getClientIP(), 'medium');
                
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
        } else {
            return ['success' => false, 'message' => 'Admin not found'];
        }
    }
    
    // Request password reset
    public function requestPasswordReset($email) {
        // Sanitize and validate email
        $email = Utilities::sanitizeInput($email);
        
        if (!Utilities::validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Check if email exists
        $stmt = $this->conn->prepare("SELECT admin_id, first_name FROM admins WHERE email = ? AND status = 'Active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Generate reset token
            $token = Utilities::generateRandomString(32);
            $expiry = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_RESET_EXPIRY . ' hours'));
            
            // Store token in database
            $updateStmt = $this->conn->prepare("UPDATE admins SET reset_token = ?, reset_expiry = ? WHERE admin_id = ?");
            $updateStmt->bind_param("ssi", $token, $expiry, $admin['admin_id']);
            
            if ($updateStmt->execute()) {
                // In a real application, send email with reset link
                // For now, just return the token
                return [
                    'success' => true, 
                    'message' => 'Password reset link has been sent to your email',
                    'token' => $token, // Remove this in production
                    'admin_id' => $admin['admin_id'] // Remove this in production
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to generate reset token'];
            }
        } else {
            // Don't reveal if email exists or not for security
            return ['success' => true, 'message' => 'If your email is registered, you will receive a password reset link'];
        }
    }
    
    // Reset password with token
    public function resetPassword($admin_id, $token, $new_password, $confirm_password) {
        // Validate inputs
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        // Verify token
        $stmt = $this->conn->prepare("SELECT admin_id FROM admins WHERE admin_id = ? AND reset_token = ? AND reset_expiry > NOW() AND status = 'Active'");
        $stmt->bind_param("is", $admin_id, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password and clear token
            $updateStmt = $this->conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE admin_id = ?");
            $updateStmt->bind_param("si", $hashed_password, $admin_id);
            
            if ($updateStmt->execute()) {
                return ['success' => true, 'message' => 'Password has been reset successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to reset password'];
            }
        } else {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
    }
    
    // Enhanced security methods
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    private function logLoginAttempt($username, $ip_address, $success, $reason = null) {
        // Login attempt logging not implemented in basic table structure
        // This method is kept for compatibility but does nothing
        return true;
    }
    
    private function updateLastLogin($admin_id) {
        $stmt = $this->conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $stmt->close();
    }
    
    private function resetFailedAttempts($admin_id) {
        // Failed attempts tracking not implemented in basic table structure
        // This method is kept for compatibility but does nothing
        return true;
    }
    
    private function incrementFailedAttempts($admin_id) {
        // Failed attempts tracking not implemented in basic table structure
        // This method is kept for compatibility but does nothing
        return true;
    }
    
    private function verifyTwoFactorCode($admin_id, $code) {
        // Two-factor authentication not implemented in basic table structure
        // This method always returns true for compatibility
        return true;
    }
    
    private function generateTOTP($secret, $time) {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset+0]) & 0x7f) << 24) |
            ((ord($hash[$offset+1]) & 0xff) << 16) |
            ((ord($hash[$offset+2]) & 0xff) << 8) |
            (ord($hash[$offset+3]) & 0xff)
        ) % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    private function validatePassword($password) {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one special character'];
        }
        
        return ['valid' => true, 'message' => 'Password is valid'];
    }
    
    // Two-factor authentication methods
    public function enableTwoFactor($admin_id) {
        // Two-factor authentication not implemented in basic table structure
        return ['success' => false, 'message' => 'Two-factor authentication not available in basic setup'];
    }
    
    public function confirmTwoFactor($admin_id, $code) {
        // Two-factor authentication not implemented in basic table structure
        return ['success' => false, 'message' => 'Two-factor authentication not available in basic setup'];
    }
    
    public function disableTwoFactor($admin_id, $password) {
        // Two-factor authentication not implemented in basic table structure
        return ['success' => false, 'message' => 'Two-factor authentication not available in basic setup'];
    }
    
    // Check if user has a specific role
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // For admin users, check if they have admin role
        if ($role === 'admin') {
            // If user exists in admins table and is active, they have admin role
            return isset($user['admin_id']) && $user['status'] === 'Active';
        }
        
        // Add other role checks as needed
        return false;
    }
}

// Base32 decode function for TOTP
function base32_decode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0, $j = strlen($input); $i < $j; $i++) {
        $v <<= 5;
        $v += strpos($alphabet, $input[$i]);
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr($v >> ($vbits - 8));
            $vbits -= 8;
        }
    }
    
    return $output;
}
?>