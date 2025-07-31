<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/CSIMS/config/config.php';

class AuthController {
    private $db;
    private $conn;
    private $session;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->session = Session::getInstance();
    }
    
    // Login user
    public function login($username, $password) {
        // Sanitize inputs
        $username = Utilities::sanitizeInput($username);
        
        // Prepare statement
        $stmt = $this->conn->prepare("SELECT * FROM admins WHERE username = ? AND status = 'Active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $admin['password'])) {
                // Update last login
                $updateStmt = $this->conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
                $updateStmt->bind_param("i", $admin['admin_id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Set session
                $this->session->login($admin);
                
                return ['success' => true, 'message' => 'Login successful'];
            } else {
                return ['success' => false, 'message' => 'Invalid password'];
            }
        } else {
            return ['success' => false, 'message' => 'Invalid username or account is inactive'];
        }
    }
    
    // Logout user
    public function logout() {
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
    
    // Change password
    public function changePassword($admin_id, $current_password, $new_password, $confirm_password) {
        // Validate inputs
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'New passwords do not match'];
        }
        
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        // Get current admin
        $stmt = $this->conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Verify current password
            if (password_verify($current_password, $admin['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $updateStmt = $this->conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
                $updateStmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($updateStmt->execute()) {
                    return ['success' => true, 'message' => 'Password changed successfully'];
                } else {
                    return ['success' => false, 'message' => 'Failed to update password'];
                }
            } else {
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
}
?>