<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/utilities.php';

class SecurityController {
    private $db;
    private $session;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->session = Session::getInstance();
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event_type, $description, $user_id = null, $ip_address = null, $severity = 'medium') {
        try {
            $ip_address = $ip_address ?? $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (event_type, description, user_id, ip_address, user_agent, severity, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param('ssssss', $event_type, $description, $user_id, $ip_address, $user_agent, $severity);
            $stmt->execute();
            
            // Also log to file for critical events
            if ($severity === 'high' || $severity === 'critical') {
                error_log("SECURITY [{$severity}]: {$event_type} - {$description} (User: {$user_id}, IP: {$ip_address})");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for suspicious login attempts
     */
    public function checkSuspiciousActivity($username, $ip_address = null) {
        try {
            $ip_address = $ip_address ?? $this->getClientIP();
            
            // Check failed login attempts in last 15 minutes
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as failed_attempts 
                FROM security_logs 
                WHERE event_type = 'failed_login' 
                AND (description LIKE ? OR ip_address = ?) 
                AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            
            $username_pattern = "%{$username}%";
            $stmt->bind_param('ss', $username_pattern, $ip_address);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            $failed_attempts = $result['failed_attempts'];
            
            // Check for account lockout
            if ($failed_attempts >= 5) {
                $this->lockAccount($username, 'Multiple failed login attempts');
                $this->logSecurityEvent('account_locked', "Account {$username} locked due to {$failed_attempts} failed attempts", null, $ip_address, 'high');
                return ['status' => 'locked', 'attempts' => $failed_attempts];
            }
            
            // Check for suspicious patterns
            $suspicious_patterns = $this->detectSuspiciousPatterns($ip_address);
            if (!empty($suspicious_patterns)) {
                $this->logSecurityEvent('suspicious_activity', 'Suspicious patterns detected: ' . implode(', ', $suspicious_patterns), null, $ip_address, 'medium');
                return ['status' => 'suspicious', 'patterns' => $suspicious_patterns];
            }
            
            return ['status' => 'normal', 'attempts' => $failed_attempts];
            
        } catch (Exception $e) {
            error_log("Error checking suspicious activity: " . $e->getMessage());
            return ['status' => 'error'];
        }
    }
    
    /**
     * Detect suspicious patterns
     */
    private function detectSuspiciousPatterns($ip_address) {
        $patterns = [];
        
        try {
            // Check for rapid requests from same IP
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as request_count 
                FROM security_logs 
                WHERE ip_address = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            $stmt->bind_param('s', $ip_address);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['request_count'] > 20) {
                $patterns[] = 'rapid_requests';
            }
            
            // Check for multiple user attempts from same IP
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT description) as unique_users 
                FROM security_logs 
                WHERE ip_address = ? 
                AND event_type = 'failed_login' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->bind_param('s', $ip_address);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['unique_users'] > 3) {
                $patterns[] = 'multiple_user_attempts';
            }
            
            // Check for unusual time patterns
            $hour = date('H');
            if ($hour >= 2 && $hour <= 5) {
                $patterns[] = 'unusual_time';
            }
            
        } catch (Exception $e) {
            error_log("Error detecting suspicious patterns: " . $e->getMessage());
        }
        
        return $patterns;
    }
    
    /**
     * Lock user account
     */
    public function lockAccount($username, $reason) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_locked = 1, 
                    locked_at = NOW(), 
                    lock_reason = ? 
                WHERE username = ?
            ");
            $stmt->bind_param('ss', $reason, $username);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log("Error locking account: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unlock user account
     */
    public function unlockAccount($username, $admin_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_locked = 0, 
                    locked_at = NULL, 
                    lock_reason = NULL 
                WHERE username = ?
            ");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $this->logSecurityEvent('account_unlocked', "Account {$username} unlocked by admin", $admin_id, null, 'medium');
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error unlocking account: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate and store two-factor authentication secret
     */
    public function generateTwoFactorSecret($user_id) {
        try {
            // Generate a random 32-character secret
            $secret = $this->generateRandomString(32);
            
            $stmt = $this->db->prepare("
                UPDATE users 
                SET two_factor_secret = ?, 
                    two_factor_enabled = 0 
                WHERE id = ?
            ");
            $stmt->bind_param('si', $secret, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $this->logSecurityEvent('2fa_secret_generated', "Two-factor secret generated for user {$user_id}", $user_id, null, 'low');
                return $secret;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error generating 2FA secret: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor($user_id, $verification_code) {
        try {
            // Get user's secret
            $stmt = $this->db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!$result || !$result['two_factor_secret']) {
                return false;
            }
            
            // Verify the code (simplified - in production use proper TOTP library)
            if ($this->verifyTOTP($result['two_factor_secret'], $verification_code)) {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET two_factor_enabled = 1 
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $this->logSecurityEvent('2fa_enabled', "Two-factor authentication enabled for user {$user_id}", $user_id, null, 'medium');
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error enabling 2FA: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify TOTP code (simplified implementation)
     */
    private function verifyTOTP($secret, $code) {
        // This is a simplified implementation
        // In production, use a proper TOTP library like RobThree/TwoFactorAuth
        $time_slice = floor(time() / 30);
        $expected_code = substr(hash_hmac('sha1', pack('N*', 0) . pack('N*', $time_slice), base32_decode($secret)), -6);
        return hash_equals($expected_code, $code);
    }
    
    /**
     * Get security dashboard data
     */
    public function getSecurityDashboard($period = 'week') {
        try {
            $date_condition = $this->getDateCondition($period);
            
            // Security events summary
            $stmt = $this->db->prepare("
                SELECT 
                    event_type,
                    severity,
                    COUNT(*) as event_count
                FROM security_logs 
                WHERE created_at >= {$date_condition}
                GROUP BY event_type, severity
                ORDER BY event_count DESC
            ");
            $stmt->execute();
            $security_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Failed login attempts by IP
            $stmt = $this->db->prepare("
                SELECT 
                    ip_address,
                    COUNT(*) as failed_attempts,
                    MAX(created_at) as last_attempt
                FROM security_logs 
                WHERE event_type = 'failed_login' 
                AND created_at >= {$date_condition}
                GROUP BY ip_address
                ORDER BY failed_attempts DESC
                LIMIT 10
            ");
            $stmt->execute();
            $failed_logins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Locked accounts
            $stmt = $this->db->prepare("
                SELECT 
                    username,
                    locked_at,
                    lock_reason
                FROM users 
                WHERE account_locked = 1
                ORDER BY locked_at DESC
            ");
            $stmt->execute();
            $locked_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Two-factor authentication stats
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN two_factor_enabled = 1 THEN 1 ELSE 0 END) as users_with_2fa
                FROM users
            ");
            $stmt->execute();
            $tfa_stats = $stmt->get_result()->fetch_assoc();
            
            // Recent security events
            $stmt = $this->db->prepare("
                SELECT 
                    sl.*,
                    u.username
                FROM security_logs sl
                LEFT JOIN users u ON sl.user_id = u.id
                WHERE sl.created_at >= {$date_condition}
                ORDER BY sl.created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $recent_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            return [
                'security_events' => $security_events,
                'failed_logins' => $failed_logins,
                'locked_accounts' => $locked_accounts,
                'tfa_stats' => $tfa_stats,
                'recent_events' => $recent_events
            ];
            
        } catch (Exception $e) {
            error_log("Error getting security dashboard: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Perform security audit
     */
    public function performSecurityAudit() {
        $audit_results = [];
        
        try {
            // Check for weak passwords
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as weak_passwords
                FROM users 
                WHERE LENGTH(password) < 60 -- Assuming bcrypt hashes are longer
                OR password_updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
                OR password_updated_at IS NULL
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $audit_results['weak_passwords'] = $result['weak_passwords'];
            
            // Check for inactive admin accounts
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as inactive_admins
                FROM users 
                WHERE role = 'admin' 
                AND last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $audit_results['inactive_admins'] = $result['inactive_admins'];
            
            // Check for users without 2FA
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as users_without_2fa
                FROM users 
                WHERE (two_factor_enabled = 0 OR two_factor_enabled IS NULL)
                AND role IN ('admin', 'staff')
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $audit_results['users_without_2fa'] = $result['users_without_2fa'];
            
            // Check for suspicious login patterns
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as suspicious_logins
                FROM security_logs 
                WHERE event_type = 'suspicious_activity' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $audit_results['suspicious_logins'] = $result['suspicious_logins'];
            
            // Calculate security score
            $total_users = $this->getTotalUsers();
            $security_score = $this->calculateSecurityScore($audit_results, $total_users);
            $audit_results['security_score'] = $security_score;
            
            $this->logSecurityEvent('security_audit', 'Security audit performed', null, null, 'low');
            
            return $audit_results;
            
        } catch (Exception $e) {
            error_log("Error performing security audit: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate security score
     */
    private function calculateSecurityScore($audit_results, $total_users) {
        $score = 100;
        
        // Deduct points for security issues
        if ($total_users > 0) {
            $weak_password_ratio = $audit_results['weak_passwords'] / $total_users;
            $score -= ($weak_password_ratio * 30); // Up to 30 points for weak passwords
            
            $no_2fa_ratio = $audit_results['users_without_2fa'] / $total_users;
            $score -= ($no_2fa_ratio * 25); // Up to 25 points for no 2FA
        }
        
        $score -= min($audit_results['inactive_admins'] * 10, 20); // Up to 20 points for inactive admins
        $score -= min($audit_results['suspicious_logins'] * 2, 15); // Up to 15 points for suspicious activity
        
        return max(0, round($score));
    }
    
    /**
     * Get total users count
     */
    private function getTotalUsers() {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM users");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            return $result['total'];
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Export security report
     */
    public function exportSecurityReport($type = 'csv') {
        try {
            $dashboard_data = $this->getSecurityDashboard('month');
            
            if ($type === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="security_report_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // Security Events
                fputcsv($output, ['Security Events Summary']);
                fputcsv($output, ['Event Type', 'Severity', 'Count']);
                foreach ($dashboard_data['security_events'] as $event) {
                    fputcsv($output, [$event['event_type'], $event['severity'], $event['event_count']]);
                }
                
                fputcsv($output, []);
                
                // Failed Logins
                fputcsv($output, ['Failed Login Attempts']);
                fputcsv($output, ['IP Address', 'Failed Attempts', 'Last Attempt']);
                foreach ($dashboard_data['failed_logins'] as $login) {
                    fputcsv($output, [$login['ip_address'], $login['failed_attempts'], $login['last_attempt']]);
                }
                
                fclose($output);
            }
            
        } catch (Exception $e) {
            error_log("Error exporting security report: " . $e->getMessage());
        }
    }
    
    /**
     * Helper methods
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function generateRandomString($length) {
        return bin2hex(random_bytes($length / 2));
    }
    
    private function getDateCondition($period) {
        switch ($period) {
            case 'week':
                return "DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'quarter':
                return "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            case 'year':
                return "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        }
    }
}

// Base32 decode function for TOTP
if (!function_exists('base32_decode')) {
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
}
?>