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

require_once __DIR__ . '/../../includes/db.php';

// Only include config if constants aren't already defined
if (!defined('EMAIL_ENABLED')) {
    require_once __DIR__ . '/../../config/notification_config.php';
}

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
            // Load config array if constants are defined
            if (defined('EMAIL_ENABLED')) {
                $this->loadConfig();
            }
        } catch (Exception $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Load configuration array
     */
    private function loadConfig() {
        $this->config = [
            'email' => [
                'smtp_host' => defined('EMAIL_SMTP_HOST') ? EMAIL_SMTP_HOST : '',
                'smtp_port' => defined('EMAIL_SMTP_PORT') ? EMAIL_SMTP_PORT : 587,
                'smtp_secure' => defined('EMAIL_SMTP_SECURE') ? EMAIL_SMTP_SECURE : 'tls',
                'username' => defined('EMAIL_USERNAME') ? EMAIL_USERNAME : '',
                'password' => defined('EMAIL_PASSWORD') ? EMAIL_PASSWORD : '',
                'from_address' => defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : '',
                'from_name' => defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : ''
            ],
            'sms' => [
                'provider' => defined('SMS_PROVIDER') ? SMS_PROVIDER : ''
            ],
            'templates' => [
                'membership_expiry' => [
                    'email' => [
                        'body' => 'Dear {member_name}, your membership will expire on {expiry_date} ({days_remaining} days remaining). Please renew to continue enjoying our services.'
                    ],
                    'sms' => [
                        'body' => 'Hi {member_name}, your membership expires on {expiry_date}. Please renew soon.'
                    ]
                ],
                'payment_overdue' => [
                    'email' => [
                        'body' => 'Dear {member_name}, your payment of {amount_due} was due on {due_date}. Please make payment as soon as possible.'
                    ]
                ],
                'welcome' => [
                    'email' => [
                        'body' => 'Welcome {member_name}! Your membership number is {membership_number}. We are excited to have you as part of our organization.'
                    ]
                ]
            ]
        ];
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
        $requiredExtensions = ['mysqli', 'json', 'curl', 'openssl'];
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
        
        // Check database connection - Fixed for MySQLi
        try {
            $result = $this->db->query('SELECT VERSION()');
            $version = $result->fetch_row()[0];
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
            // Use simplified schema file
            $schemaFile = __DIR__ . '/../../database/notification_triggers_schema_simple.sql';
            if (!file_exists($schemaFile)) {
                // Fallback to original schema but filter out problematic statements
                $schemaFile = __DIR__ . '/../../database/notification_triggers_schema.sql';
            }
            
            if (!file_exists($schemaFile)) {
                throw new Exception('Schema file not found: ' . $schemaFile);
            }
            
            $sql = file_get_contents($schemaFile);
            
            // Remove problematic DELIMITER statements and stored procedures
            $sql = preg_replace('/DELIMITER\s+\/\/.*?DELIMITER\s+;/s', '', $sql);
            $sql = preg_replace('/CREATE\s+EVENT.*?;/s', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }
                
                try {
                    $this->db->query($statement);
                } catch (Exception $e) {
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
            $stmt->bind_param('sssss', 
                $template['name'],
                $template['type'],
                $template['subject'],
                $template['content'],
                $template['variables']
            );
            $stmt->execute();
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
            $stmt->bind_param('sssssssiis',
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
            );
            $stmt->execute();
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
        
        echo "Configuration validation completed.\n\n";
    }
    
    /**
     * Setup required directories
     */
    private function setupDirectories() {
        echo "Setting up directories...\n";
        
        $directories = [
            __DIR__ . '/../../logs',
            __DIR__ . '/../../logs/notifications',
            __DIR__ . '/../../temp',
            __DIR__ . '/../../temp/notifications'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $this->success[] = "Created directory: $dir";
                } else {
                    $this->warnings[] = "Failed to create directory: $dir";
                }
            } else {
                $this->success[] = "Directory exists: $dir";
            }
        }
        
        echo "Directory setup completed.\n\n";
    }
    
    /**
     * Display cron job setup instructions
     */
    private function displayCronInstructions() {
        echo "=== CRON JOB SETUP INSTRUCTIONS ===\n\n";
        echo "To enable automated notifications, add these cron jobs:\n\n";
        echo "# Process notification queue every 5 minutes\n";
        echo "*/5 * * * * php " . __DIR__ . "/../../cron/process_notifications.php\n\n";
        echo "# Check for automated notifications every hour\n";
        echo "0 * * * * php " . __DIR__ . "/../../cron/automated_notifications.php\n\n";
        echo "# Run notification triggers daily at 9 AM\n";
        echo "0 9 * * * php " . __DIR__ . "/../../cron/notification_trigger_runner.php\n\n";
    }
    
    /**
     * Display setup summary
     */
    private function displaySummary() {
        echo "\n=== SETUP SUMMARY ===\n\n";
        
        if (!empty($this->success)) {
            echo "✓ SUCCESS:\n";
            foreach ($this->success as $message) {
                echo "  - $message\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠ WARNINGS:\n";
            foreach ($this->warnings as $message) {
                echo "  - $message\n";
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "✗ ERRORS:\n";
            foreach ($this->errors as $message) {
                echo "  - $message\n";
            }
            echo "\n";
            echo "Please fix the errors above before using the notification system.\n";
        } else {
            echo "🎉 Notification system setup completed successfully!\n";
            echo "\nNext steps:\n";
            echo "1. Configure email/SMS settings in config/notification_config.php\n";
            echo "2. Set up the cron jobs as shown above\n";
            echo "3. Test the notification system from the admin panel\n";
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