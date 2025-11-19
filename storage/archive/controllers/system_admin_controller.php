<?php
require_once '../includes/config/database.php';

class SystemAdminController {
    private $pdo;
    
    public function __construct() {
        $database = new PdoDatabase();
        $this->pdo = $database->getConnection();
    }
    
    // ===================================================================
    // USER ROLE MANAGEMENT
    // ===================================================================
    
    /**
     * Get all admin users with roles
     */
    public function getAllAdminUsers($limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    admin_id,
                    first_name,
                    last_name,
                    email,
                    role,
                    status,
                    last_login,
                    created_at,
                    updated_at
                FROM admins 
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getAllAdminUsers: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new admin user
     */
    public function createAdminUser($admin_data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admins (
                    first_name, last_name, email, password, role, status, created_by
                ) VALUES (
                    :first_name, :last_name, :email, :password, :role, :status, :created_by
                )
            ");
            
            $hashed_password = password_hash($admin_data['password'], PASSWORD_DEFAULT);
            
            $stmt->bindParam(':first_name', $admin_data['first_name']);
            $stmt->bindParam(':last_name', $admin_data['last_name']);
            $stmt->bindParam(':email', $admin_data['email']);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':role', $admin_data['role']);
            $stmt->bindParam(':status', $admin_data['status']);
            $stmt->bindParam(':created_by', $admin_data['created_by']);
            
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in createAdminUser: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update admin user
     */
    public function updateAdminUser($admin_id, $admin_data) {
        try {
            $sql = "UPDATE admins SET 
                       first_name = :first_name,
                       last_name = :last_name,
                       email = :email,
                       role = :role,
                       status = :status,
                       updated_at = CURRENT_TIMESTAMP";
            
            if (!empty($admin_data['password'])) {
                $sql .= ", password = :password";
            }
            
            $sql .= " WHERE admin_id = :admin_id";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->bindParam(':first_name', $admin_data['first_name']);
            $stmt->bindParam(':last_name', $admin_data['last_name']);
            $stmt->bindParam(':email', $admin_data['email']);
            $stmt->bindParam(':role', $admin_data['role']);
            $stmt->bindParam(':status', $admin_data['status']);
            $stmt->bindParam(':admin_id', $admin_id);
            
            if (!empty($admin_data['password'])) {
                $hashed_password = password_hash($admin_data['password'], PASSWORD_DEFAULT);
                $stmt->bindParam(':password', $hashed_password);
            }
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateAdminUser: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete admin user
     */
    public function deleteAdminUser($admin_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM admins WHERE admin_id = :admin_id");
            $stmt->bindParam(':admin_id', $admin_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in deleteAdminUser: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get admin user permissions by role
     */
    public function getRolePermissions($role) {
        $permissions = [
            'super_admin' => [
                'users' => ['create', 'read', 'update', 'delete'],
                'members' => ['create', 'read', 'update', 'delete'],
                'loans' => ['create', 'read', 'update', 'delete', 'approve', 'disburse'],
                'contributions' => ['create', 'read', 'update', 'delete'],
                'reports' => ['read', 'export'],
                'system' => ['settings', 'backup', 'audit'],
                'communication' => ['create', 'read', 'update', 'delete', 'send']
            ],
            'admin' => [
                'members' => ['create', 'read', 'update'],
                'loans' => ['create', 'read', 'update', 'approve'],
                'contributions' => ['create', 'read', 'update'],
                'reports' => ['read', 'export'],
                'communication' => ['create', 'read', 'update', 'send']
            ],
            'manager' => [
                'members' => ['read', 'update'],
                'loans' => ['read', 'update', 'approve'],
                'contributions' => ['read', 'update'],
                'reports' => ['read']
            ],
            'staff' => [
                'members' => ['read'],
                'loans' => ['read'],
                'contributions' => ['read'],
                'reports' => ['read']
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    // ===================================================================
    // SYSTEM SETTINGS MANAGEMENT
    // ===================================================================
    
    /**
     * Get system settings
     */
    public function getSystemSettings() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM system_settings 
                ORDER BY category, setting_key
            ");
            $stmt->execute();
            
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by category
            $grouped_settings = [];
            foreach ($settings as $setting) {
                $grouped_settings[$setting['category']][] = $setting;
            }
            
            return $grouped_settings;
        } catch (Exception $e) {
            // Create settings table if it doesn't exist
            $this->createSystemSettingsTable();
            return $this->getDefaultSettings();
        }
    }
    
    /**
     * Create system settings table
     */
    private function createSystemSettingsTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS system_settings (
                    setting_id INT AUTO_INCREMENT PRIMARY KEY,
                    category VARCHAR(50) NOT NULL,
                    setting_key VARCHAR(100) NOT NULL,
                    setting_value TEXT,
                    setting_type ENUM('text', 'number', 'boolean', 'json', 'email', 'url') DEFAULT 'text',
                    description TEXT,
                    is_editable BOOLEAN DEFAULT TRUE,
                    updated_by INT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_setting (category, setting_key),
                    INDEX idx_category (category)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
            
            $this->pdo->exec($sql);
            $this->insertDefaultSettings();
        } catch (Exception $e) {
            error_log("Error creating system_settings table: " . $e->getMessage());
        }
    }
    
    /**
     * Insert default system settings
     */
    private function insertDefaultSettings() {
        $default_settings = [
            // General Settings
            ['general', 'organization_name', 'NPC CTLStaff Loan Society', 'text', 'Organization name', true],
            ['general', 'organization_email', 'info@npcctlstaff.com', 'email', 'Organization email address', true],
            ['general', 'organization_phone', '+234-XXX-XXX-XXXX', 'text', 'Organization phone number', true],
            ['general', 'organization_address', 'Lagos, Nigeria', 'text', 'Organization address', true],
            ['general', 'timezone', 'Africa/Lagos', 'text', 'System timezone', true],
            ['general', 'date_format', 'Y-m-d', 'text', 'Date display format', true],
            ['general', 'currency_symbol', '₦', 'text', 'Currency symbol', true],
            
            // Loan Settings
            ['loans', 'max_loan_amount', '5000000', 'number', 'Maximum loan amount', true],
            ['loans', 'min_loan_amount', '50000', 'number', 'Minimum loan amount', true],
            ['loans', 'default_interest_rate', '15', 'number', 'Default interest rate (%)', true],
            ['loans', 'max_loan_term', '36', 'number', 'Maximum loan term (months)', true],
            ['loans', 'min_loan_term', '3', 'number', 'Minimum loan term (months)', true],
            ['loans', 'grace_period_days', '7', 'number', 'Grace period for overdue loans (days)', true],
            ['loans', 'penalty_rate', '2.5', 'number', 'Penalty rate for overdue loans (%)', true],
            ['loans', 'require_guarantors', '1', 'boolean', 'Require guarantors for loans', true],
            ['loans', 'min_guarantors', '2', 'number', 'Minimum number of guarantors', true],
            
            // Membership Settings
            ['membership', 'membership_fee', '10000', 'number', 'Membership registration fee', true],
            ['membership', 'monthly_dues', '5000', 'number', 'Monthly membership dues', true],
            ['membership', 'auto_suspend_overdue', '1', 'boolean', 'Auto-suspend members with overdue payments', true],
            ['membership', 'suspension_grace_days', '30', 'number', 'Days before auto-suspension', true],
            ['membership', 'allow_self_registration', '0', 'boolean', 'Allow member self-registration', true],
            
            // Contribution Settings
            ['contributions', 'min_contribution', '1000', 'number', 'Minimum contribution amount', true],
            ['contributions', 'contribution_types', '["Membership Dues", "Investment", "Special Levy", "Share Capital"]', 'json', 'Available contribution types', true],
            ['contributions', 'withdrawal_fee_percentage', '2', 'number', 'Withdrawal fee percentage', true],
            ['contributions', 'max_withdrawal_fee', '50000', 'number', 'Maximum withdrawal fee', true],
            
            // Security Settings
            ['security', 'session_timeout', '7200', 'number', 'Session timeout (seconds)', true],
            ['security', 'password_min_length', '8', 'number', 'Minimum password length', true],
            ['security', 'require_password_complexity', '1', 'boolean', 'Require complex passwords', true],
            ['security', 'max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', true],
            ['security', 'lockout_duration', '1800', 'number', 'Account lockout duration (seconds)', true],
            ['security', 'enable_two_factor', '0', 'boolean', 'Enable two-factor authentication', true],
            
            // Email Settings
            ['email', 'smtp_host', '', 'text', 'SMTP server host', true],
            ['email', 'smtp_port', '587', 'number', 'SMTP server port', true],
            ['email', 'smtp_username', '', 'text', 'SMTP username', true],
            ['email', 'smtp_password', '', 'text', 'SMTP password (encrypted)', true],
            ['email', 'smtp_encryption', 'tls', 'text', 'SMTP encryption (tls/ssl)', true],
            ['email', 'from_email', 'noreply@npcctlstaff.com', 'email', 'Default from email', true],
            ['email', 'from_name', 'NPC CTLStaff Loan Society', 'text', 'Default from name', true],
            
            // Notification Settings
            ['notifications', 'enable_email_notifications', '1', 'boolean', 'Enable email notifications', true],
            ['notifications', 'enable_sms_notifications', '0', 'boolean', 'Enable SMS notifications', true],
            ['notifications', 'loan_due_reminder_days', '7', 'number', 'Days before loan due to send reminder', true],
            ['notifications', 'membership_expiry_reminder_days', '30', 'number', 'Days before membership expiry to send reminder', true]
        ];
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO system_settings (category, setting_key, setting_value, setting_type, description, is_editable)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($default_settings as $setting) {
                $stmt->execute($setting);
            }
        } catch (Exception $e) {
            error_log("Error inserting default settings: " . $e->getMessage());
        }
    }
    
    /**
     * Get default settings if table doesn't exist
     */
    private function getDefaultSettings() {
        return [
            'general' => [
                ['setting_key' => 'organization_name', 'setting_value' => 'NPC CTLStaff Loan Society', 'setting_type' => 'text', 'description' => 'Organization name'],
                ['setting_key' => 'organization_email', 'setting_value' => 'info@npcctlstaff.com', 'setting_type' => 'email', 'description' => 'Organization email'],
            ],
            'loans' => [
                ['setting_key' => 'max_loan_amount', 'setting_value' => '5000000', 'setting_type' => 'number', 'description' => 'Maximum loan amount'],
                ['setting_key' => 'default_interest_rate', 'setting_value' => '15', 'setting_type' => 'number', 'description' => 'Default interest rate (%)'],
            ]
        ];
    }
    
    /**
     * Update system setting
     */
    public function updateSystemSetting($category, $setting_key, $setting_value, $updated_by) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE system_settings 
                SET setting_value = :setting_value, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE category = :category AND setting_key = :setting_key
            ");
            
            $stmt->bindParam(':setting_value', $setting_value);
            $stmt->bindParam(':updated_by', $updated_by);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':setting_key', $setting_key);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateSystemSetting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get specific setting value
     */
    public function getSettingValue($category, $setting_key, $default_value = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT setting_value, setting_type 
                FROM system_settings 
                WHERE category = :category AND setting_key = :setting_key
            ");
            
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':setting_key', $setting_key);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $value = $result['setting_value'];
                // Convert based on type
                switch ($result['setting_type']) {
                    case 'boolean':
                        return (bool)$value;
                    case 'number':
                        return (float)$value;
                    case 'json':
                        return json_decode($value, true);
                    default:
                        return $value;
                }
            }
            
            return $default_value;
        } catch (Exception $e) {
            error_log("Error in getSettingValue: " . $e->getMessage());
            return $default_value;
        }
    }
    
    // ===================================================================
    // SECURITY AUDIT LOGS
    // ===================================================================
    
    /**
     * Log security event
     */
    public function logSecurityEvent($event_data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_audit_log (
                    event_type, user_id, user_type, ip_address, user_agent, 
                    event_description, event_data, severity, created_at
                ) VALUES (
                    :event_type, :user_id, :user_type, :ip_address, :user_agent,
                    :event_description, :event_data, :severity, NOW()
                )
            ");
            
            $stmt->bindParam(':event_type', $event_data['event_type']);
            $stmt->bindParam(':user_id', $event_data['user_id']);
            $stmt->bindParam(':user_type', $event_data['user_type']);
            $stmt->bindParam(':ip_address', $event_data['ip_address']);
            $stmt->bindParam(':user_agent', $event_data['user_agent']);
            $stmt->bindParam(':event_description', $event_data['event_description']);
            $stmt->bindParam(':event_data', $event_data['event_data']);
            $stmt->bindParam(':severity', $event_data['severity']);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Create audit log table if it doesn't exist
            $this->createSecurityAuditTable();
            return $this->logSecurityEvent($event_data);
        }
    }
    
    /**
     * Create security audit log table
     */
    private function createSecurityAuditTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS security_audit_log (
                    log_id INT AUTO_INCREMENT PRIMARY KEY,
                    event_type ENUM('login', 'logout', 'login_failed', 'password_change', 'account_locked', 'permission_denied', 'data_access', 'data_modification', 'system_change', 'suspicious_activity') NOT NULL,
                    user_id INT,
                    user_type ENUM('admin', 'member', 'system') DEFAULT 'admin',
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    event_description TEXT NOT NULL,
                    event_data JSON,
                    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_event_type (event_type),
                    INDEX idx_user (user_id, user_type),
                    INDEX idx_severity (severity),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
            
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Error creating security_audit_log table: " . $e->getMessage());
        }
    }
    
    /**
     * Get security audit logs
     */
    public function getSecurityAuditLogs($filters = [], $limit = 100, $offset = 0) {
        try {
            $sql = "
                SELECT sal.*, 
                       CASE 
                           WHEN sal.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                           WHEN sal.user_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
                           ELSE 'System'
                       END as user_name,
                       CASE
                           WHEN sal.user_type = 'admin' THEN a.email
                           WHEN sal.user_type = 'member' THEN m.email
                           ELSE NULL
                       END as user_email
                FROM security_audit_log sal
                LEFT JOIN admins a ON sal.user_type = 'admin' AND sal.user_id = a.admin_id
                LEFT JOIN members m ON sal.user_type = 'member' AND sal.user_id = m.member_id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filters['event_type'])) {
                $sql .= " AND sal.event_type = :event_type";
                $params[':event_type'] = $filters['event_type'];
            }
            
            if (!empty($filters['severity'])) {
                $sql .= " AND sal.severity = :severity";
                $params[':severity'] = $filters['severity'];
            }
            
            if (!empty($filters['user_type'])) {
                $sql .= " AND sal.user_type = :user_type";
                $params[':user_type'] = $filters['user_type'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND sal.created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND sal.created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $sql .= " ORDER BY sal.created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getSecurityAuditLogs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get audit statistics
     */
    public function getAuditStatistics($period = '30_days') {
        try {
            $date_condition = match($period) {
                '7_days' => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                '30_days' => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                '90_days' => 'created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
                '1_year' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)',
                default => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
            };
            
            // Event type statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    event_type,
                    COUNT(*) as count,
                    COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_count,
                    COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_count
                FROM security_audit_log 
                WHERE $date_condition
                GROUP BY event_type
                ORDER BY count DESC
            ");
            $stmt->execute();
            $event_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Severity statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    severity,
                    COUNT(*) as count
                FROM security_audit_log 
                WHERE $date_condition
                GROUP BY severity
                ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')
            ");
            $stmt->execute();
            $severity_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Daily activity
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_events,
                    COUNT(CASE WHEN severity IN ('critical', 'high') THEN 1 END) as high_severity_events
                FROM security_audit_log 
                WHERE $date_condition
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stmt->execute();
            $daily_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'period' => $period,
                'event_types' => $event_stats,
                'severity_levels' => $severity_stats,
                'daily_activity' => $daily_activity,
                'total_events' => array_sum(array_column($event_stats, 'count'))
            ];
        } catch (Exception $e) {
            error_log("Error in getAuditStatistics: " . $e->getMessage());
            return [
                'period' => $period,
                'event_types' => [],
                'severity_levels' => [],
                'daily_activity' => [],
                'total_events' => 0
            ];
        }
    }
    
    // ===================================================================
    // SYSTEM BACKUP & MAINTENANCE
    // ===================================================================
    
    /**
     * Create database backup
     */
    public function createDatabaseBackup($backup_name = null) {
        try {
            $backup_name = $backup_name ?: 'csims_backup_' . date('Y-m-d_H-i-s');
            $backup_dir = '../backups/';
            
            // Create backups directory if it doesn't exist
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $backup_file = $backup_dir . $backup_name . '.sql';
            
            // Get database configuration
            $config = [
                'host' => 'localhost',
                'username' => 'root', // This should come from config
                'password' => '',     // This should come from config  
                'database' => 'csims_db' // This should come from config
            ];
            
            // Use mysqldump command
            $command = sprintf(
                'mysqldump -h%s -u%s %s %s > %s',
                $config['host'],
                $config['username'],
                !empty($config['password']) ? '-p' . $config['password'] : '',
                $config['database'],
                $backup_file
            );
            
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($backup_file)) {
                // Log backup creation
                $this->logSecurityEvent([
                    'event_type' => 'system_change',
                    'user_id' => $_SESSION['admin_id'] ?? 0,
                    'user_type' => 'admin',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'event_description' => 'Database backup created: ' . $backup_name,
                    'event_data' => json_encode(['backup_file' => $backup_file, 'size' => filesize($backup_file)]),
                    'severity' => 'medium'
                ]);
                
                return [
                    'success' => true,
                    'backup_file' => $backup_name . '.sql',
                    'size' => filesize($backup_file)
                ];
            } else {
                return ['success' => false, 'error' => 'Backup creation failed'];
            }
        } catch (Exception $e) {
            error_log("Error in createDatabaseBackup: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get system information
     */
    public function getSystemInfo() {
        try {
            return [
                'php_version' => phpversion(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'database_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'disk_free_space' => disk_free_space('.'),
                'disk_total_space' => disk_total_space('.'),
                'server_time' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ];
        } catch (Exception $e) {
            error_log("Error in getSystemInfo: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get database statistics
     */
    public function getDatabaseStats() {
        try {
            $stats = [];
            
            // Get table sizes and row counts
            $tables = ['members', 'loans', 'contributions', 'admins', 'messages', 'announcements'];
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM `$table`");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['tables'][$table] = $result['count'];
            }
            
            // Get database size
            $stmt = $this->pdo->prepare("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size_mb'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['database_size_mb'] = $result['db_size_mb'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getDatabaseStats: " . $e->getMessage());
            return ['tables' => [], 'database_size_mb' => 0];
        }
    }
    
    /**
     * Clean up system logs
     */
    public function cleanupSystemLogs($days_to_keep = 90) {
        try {
            $this->pdo->beginTransaction();
            
            // Clean up security audit logs
            $stmt = $this->pdo->prepare("
                DELETE FROM security_audit_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND severity NOT IN ('critical', 'high')
            ");
            $stmt->bindParam(':days', $days_to_keep);
            $stmt->execute();
            $audit_deleted = $stmt->rowCount();
            
            // Clean up old messages
            $stmt = $this->pdo->prepare("
                DELETE FROM messages 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND is_read = 1
            ");
            $stmt->bindParam(':days', $days_to_keep);
            $stmt->execute();
            $messages_deleted = $stmt->rowCount();
            
            $this->pdo->commit();
            
            // Log cleanup activity
            $this->logSecurityEvent([
                'event_type' => 'system_change',
                'user_id' => $_SESSION['admin_id'] ?? 0,
                'user_type' => 'admin',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'event_description' => 'System logs cleanup performed',
                'event_data' => json_encode([
                    'days_kept' => $days_to_keep,
                    'audit_logs_deleted' => $audit_deleted,
                    'messages_deleted' => $messages_deleted
                ]),
                'severity' => 'low'
            ]);
            
            return [
                'success' => true,
                'audit_logs_deleted' => $audit_deleted,
                'messages_deleted' => $messages_deleted
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error in cleanupSystemLogs: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
