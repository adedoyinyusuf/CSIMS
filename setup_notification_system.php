<?php
/**
 * Notification System Setup Script
 * 
 * This script sets up the complete notification system including:
 * - Database tables and schema
 * - Default templates and triggers
 * - Configuration validation
 * - Cron job setup instructions
 */

require_once 'config/database.php';
require_once 'config/notification_config.php';

// Prevent running from web if not in development
if (isset($_SERVER['HTTP_HOST']) && !defined('ALLOW_WEB_SETUP')) {
    die('This setup script should be run from command line for security reasons.');
}

/**
 * Setup class for notification system
 */
class NotificationSystemSetup {
    private $db;
    private $config;
    private $errors = [];
    private $warnings = [];
    private $success = [];
    
    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->config = require 'config/notification_config.php';
        } catch (Exception $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Run the complete setup
     */
    public function run() {
        echo "\n=== CSIMS Notification System Setup ===\n\n";
        
        $this->checkPrerequisites();
        $this->createTables();
        $this->insertDefaultData();
        $this->validateConfiguration();
        $this->setupDirectories();
        $this->displayCronInstructions();
        $this->displaySummary();
    }
    
    /**
     * Check system prerequisites
     */
    private function checkPrerequisites() {
        echo "Checking prerequisites...\n";
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->errors[] = 'PHP 7.4.0 or higher is required. Current version: ' . PHP_VERSION;
        } else {
            $this->success[] = 'PHP version check passed: ' . PHP_VERSION;
        }
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->errors[] = "Required PHP extension '{$ext}' is not loaded";
            } else {
                $this->success[] = "Extension '{$ext}' is available";
            }
        }
        
        // Check optional extensions
        $optionalExtensions = ['mbstring', 'iconv', 'pcntl'];
        foreach ($optionalExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->warnings[] = "Optional PHP extension '{$ext}' is not loaded";
            }
        }
        
        // Check database connection
        try {
            $stmt = $this->db->query('SELECT VERSION()');
            $version = $stmt->fetchColumn();
            $this->success[] = 'Database connection successful: MySQL ' . $version;
        } catch (Exception $e) {
            $this->errors[] = 'Database connection failed: ' . $e->getMessage();
        }
        
        echo "Prerequisites check completed.\n\n";
    }
    
    /**
     * Create database tables
     */
    private function createTables() {
        echo "Creating database tables...\n";
        
        try {
            // Read and execute the schema file
            $schemaFile = 'database/notification_triggers_schema.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception('Schema file not found: ' . $schemaFile);
            }
            
            $sql = file_get_contents($schemaFile);
            
            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }
                
                try {
                    $this->db->exec($statement);
                } catch (PDOException $e) {
                    // Ignore "table already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
            
            $this->success[] = 'Database tables created successfully';
            
        } catch (Exception $e) {
            $this->errors[] = 'Failed to create database tables: ' . $e->getMessage();
        }
        
        echo "Database tables creation completed.\n\n";
    }
    
    /**
     * Insert default data
     */
    private function insertDefaultData() {
        echo "Inserting default data...\n";
        
        try {
            // Insert default notification templates
            $this->insertDefaultTemplates();
            
            // Insert default notification triggers
            $this->insertDefaultTriggers();
            
            $this->success[] = 'Default data inserted successfully';
            
        } catch (Exception $e) {
            $this->errors[] = 'Failed to insert default data: ' . $e->getMessage();
        }
        
        echo "Default data insertion completed.\n\n";
    }
    
    /**
     * Insert default notification templates
     */
    private function insertDefaultTemplates() {
        $templates = [
            [
                'name' => 'membership_expiry_reminder',
                'type' => 'email',
                'subject' => 'Membership Expiry Reminder',
                'content' => $this->config['templates']['membership_expiry']['email']['body'],
                'variables' => json_encode(['member_name', 'expiry_date', 'days_remaining'])
            ],
            [
                'name' => 'membership_expiry_reminder_sms',
                'type' => 'sms',
                'subject' => '',
                'content' => $this->config['templates']['membership_expiry']['sms']['body'],
                'variables' => json_encode(['member_name', 'expiry_date', 'days_remaining'])
            ],
            [
                'name' => 'payment_overdue_reminder',
                'type' => 'email',
                'subject' => 'Payment Overdue Reminder',
                'content' => $this->config['templates']['payment_overdue']['email']['body'],
                'variables' => json_encode(['member_name', 'amount_due', 'due_date'])
            ],
            [
                'name' => 'welcome_message',
                'type' => 'email',
                'subject' => 'Welcome to Our Organization',
                'content' => $this->config['templates']['welcome']['email']['body'],
                'variables' => json_encode(['member_name', 'membership_number'])
            ]
        ];
        
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO notification_templates 
            (name, type, subject, content, variables, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($templates as $template) {
            $stmt->execute([
                $template['name'],
                $template['type'],
                $template['subject'],
                $template['content'],
                $template['variables']
            ]);
        }
    }
    
    /**
     * Insert default notification triggers
     */
    private function insertDefaultTriggers() {
        $triggers = [
            [
                'name' => 'Membership Expiry - 30 Days',
                'description' => 'Send reminder 30 days before membership expires',
                'trigger_type' => 'membership_expiry',
                'trigger_condition' => json_encode(['days_before' => 30]),
                'recipient_group' => 'expiring_members',
                'notification_template' => 'membership_expiry_reminder',
                'schedule_pattern' => json_encode(['type' => 'daily', 'time' => '09:00']),
                'email_enabled' => 1,
                'sms_enabled' => 0,
                'status' => 'active'
            ],
            [
                'name' => 'Membership Expiry - 7 Days',
                'description' => 'Send reminder 7 days before membership expires',
                'trigger_type' => 'membership_expiry',
                'trigger_condition' => json_encode(['days_before' => 7]),
                'recipient_group' => 'expiring_members',
                'notification_template' => 'membership_expiry_reminder',
                'schedule_pattern' => json_encode(['type' => 'daily', 'time' => '09:00']),
                'email_enabled' => 1,
                'sms_enabled' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Payment Overdue Reminder',
                'description' => 'Send reminder for overdue payments',
                'trigger_type' => 'payment_overdue',
                'trigger_condition' => json_encode(['days_overdue' => 7]),
                'recipient_group' => 'overdue_members',
                'notification_template' => 'payment_overdue_reminder',
                'schedule_pattern' => json_encode(['type' => 'weekly', 'day_of_week' => 1, 'time' => '10:00']),
                'email_enabled' => 1,
                'sms_enabled' => 0,
                'status' => 'active'
            ],
            [
                'name' => 'Welcome New Members',
                'description' => 'Send welcome message to new members',
                'trigger_type' => 'welcome',
                'trigger_condition' => json_encode(['trigger_on' => 'registration']),
                'recipient_group' => 'new_members',
                'notification_template' => 'welcome_message',
                'schedule_pattern' => json_encode(['type' => 'immediate']),
                'email_enabled' => 1,
                'sms_enabled' => 0,
                'status' => 'active'
            ]
        ];
        
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO notification_triggers 
            (name, description, trigger_type, trigger_condition, recipient_group, 
             notification_template, schedule_pattern, email_enabled, sms_enabled, 
             status, next_run, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        foreach ($triggers as $trigger) {
            $stmt->execute([
                $trigger['name'],
                $trigger['description'],
                $trigger['trigger_type'],
                $trigger['trigger_condition'],
                $trigger['recipient_group'],
                $trigger['notification_template'],
                $trigger['schedule_pattern'],
                $trigger['email_enabled'],
                $trigger['sms_enabled'],
                $trigger['status']
            ]);
        }
    }
    
    /**
     * Validate configuration
     */
    private function validateConfiguration() {
        echo "Validating configuration...\n";
        
        // Check email configuration
        if (empty($this->config['email']['smtp_host'])) {
            $this->warnings[] = 'Email SMTP host not configured';
        } else {
            $this->success[] = 'Email configuration found';
        }
        
        // Check SMS configuration
        if (empty($this->config['sms']['provider'])) {
            $this->warnings[] = 'SMS provider not configured';
        } else {
            $this->success[] = 'SMS configuration found';
        }
        
        // Check required directories
        $requiredDirs = ['logs', 'temp'];
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                $this->warnings[] = "Directory '{$dir}' does not exist";
            } else {
                $this->success[] = "Directory '{$dir}' exists";
            }
        }
        
        echo "Configuration validation completed.\n\n";
    }
    
    /**
     * Setup required directories
     */
    private function setupDirectories() {
        echo "Setting up directories...\n";
        
        $directories = [
            'logs' => 0755,
            'temp' => 0755,
            'uploads' => 0755
        ];
        
        foreach ($directories as $dir => $permissions) {
            if (!is_dir($dir)) {
                if (mkdir($dir, $permissions, true)) {
                    $this->success[] = "Created directory: {$dir}";
                } else {
                    $this->errors[] = "Failed to create directory: {$dir}";
                }
            } else {
                $this->success[] = "Directory already exists: {$dir}";
            }
        }
        
        echo "Directory setup completed.\n\n";
    }
    
    /**
     * Display cron job setup instructions
     */
    private function displayCronInstructions() {
        echo "=== CRON JOB SETUP INSTRUCTIONS ===\n\n";
        
        $phpPath = PHP_BINARY;
        $scriptPath = realpath('cron/notification_trigger_runner.php');
        
        echo "To enable automated notifications, add the following cron job:\n\n";
        echo "# Run notification triggers every minute\n";
        echo "* * * * * {$phpPath} {$scriptPath}\n\n";
        
        echo "Alternative: Run every 5 minutes\n";
        echo "*/5 * * * * {$phpPath} {$scriptPath}\n\n";
        
        echo "For Windows Task Scheduler:\n";
        echo "Program: {$phpPath}\n";
        echo "Arguments: {$scriptPath}\n";
        echo "Trigger: Every 1 or 5 minutes\n\n";
        
        echo "Log files will be created in: " . realpath('logs') . "\n\n";
    }
    
    /**
     * Display setup summary
     */
    private function displaySummary() {
        echo "=== SETUP SUMMARY ===\n\n";
        
        if (!empty($this->success)) {
            echo "✓ SUCCESS:\n";
            foreach ($this->success as $message) {
                echo "  - {$message}\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠ WARNINGS:\n";
            foreach ($this->warnings as $message) {
                echo "  - {$message}\n";
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "✗ ERRORS:\n";
            foreach ($this->errors as $message) {
                echo "  - {$message}\n";
            }
            echo "\n";
        }
        
        if (empty($this->errors)) {
            echo "🎉 Notification system setup completed successfully!\n\n";
            echo "Next steps:\n";
            echo "1. Configure email and SMS settings in config/notification_config.php\n";
            echo "2. Set up the cron job as shown above\n";
            echo "3. Test the system by visiting the admin panel\n";
            echo "4. Create custom notification triggers as needed\n\n";
        } else {
            echo "❌ Setup completed with errors. Please fix the errors above and run again.\n\n";
        }
    }
}

// Run the setup
try {
    $setup = new NotificationSystemSetup();
    $setup->run();
} catch (Exception $e) {
    echo "Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>