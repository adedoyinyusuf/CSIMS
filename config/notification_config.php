<?php
/**
 * Notification Configuration
 * 
 * Central configuration for all notification settings including:
 * - Email/SMS service settings
 * - Automated notification rules
 * - Template configurations
 * - Scheduling settings
 */

// Email Configuration
define('EMAIL_ENABLED', true);
define('EMAIL_SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP server
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('EMAIL_USERNAME', 'your-email@gmail.com'); // Your email
define('EMAIL_PASSWORD', 'your-app-password'); // Your email password or app password
define('EMAIL_FROM_ADDRESS', 'noreply@csims.com');
define('EMAIL_FROM_NAME', 'CSIMS Notification System');
define('EMAIL_REPLY_TO', 'support@csims.com');

// SMS Configuration
define('SMS_ENABLED', true);
define('SMS_PROVIDER', 'twilio'); // 'twilio', 'nexmo', 'textlocal'
define('SMS_TWILIO_SID', 'your-twilio-sid');
define('SMS_TWILIO_TOKEN', 'your-twilio-token');
define('SMS_TWILIO_FROM', '+1234567890');
define('SMS_NEXMO_KEY', 'your-nexmo-key');
define('SMS_NEXMO_SECRET', 'your-nexmo-secret');
define('SMS_NEXMO_FROM', 'CSIMS');
define('SMS_TEXTLOCAL_USERNAME', 'your-textlocal-username');
define('SMS_TEXTLOCAL_HASH', 'your-textlocal-hash');
define('SMS_TEXTLOCAL_SENDER', 'CSIMS');
define('SMS_MAX_LENGTH', 160);

// Automated Notification Settings
$notificationSettings = [
    'membership_expiry' => [
        'enabled' => true,
        'reminder_days' => [30, 15, 7], // Days before expiry to send reminders
        'send_email' => true,
        'send_sms' => true,
        'template_email' => 'membership_expiry_email',
        'template_sms' => 'membership_expiry_sms'
    ],
    
    'payment_overdue' => [
        'enabled' => true,
        'reminder_frequency' => 7, // Days between reminders
        'max_reminders' => 3, // Maximum number of reminders
        'send_email' => true,
        'send_sms' => true,
        'template_email' => 'payment_overdue_email',
        'template_sms' => 'payment_overdue_sms'
    ],
    
    'welcome_message' => [
        'enabled' => true,
        'delay_hours' => 1, // Hours after registration to send welcome
        'send_email' => true,
        'send_sms' => true,
        'template_email' => 'welcome_email',
        'template_sms' => 'welcome_sms'
    ],
    
    'birthday_wishes' => [
        'enabled' => false,
        'send_email' => true,
        'send_sms' => false,
        'template_email' => 'birthday_email',
        'template_sms' => 'birthday_sms'
    ],
    
    'weekly_reports' => [
        'enabled' => true,
        'day_of_week' => 1, // Monday = 1, Sunday = 7
        'time' => '09:00',
        'recipients' => ['admin'], // 'admin', 'manager', 'all'
        'template' => 'weekly_report'
    ],
    
    'monthly_reports' => [
        'enabled' => true,
        'day_of_month' => 1, // 1st day of month
        'time' => '08:00',
        'recipients' => ['admin'],
        'template' => 'monthly_report'
    ]
];

// Default Message Templates
$defaultTemplates = [
    'membership_expiry_email' => [
        'subject' => 'Membership Expiry Reminder - {days} Days Remaining',
        'content' => '
            <h2>Dear {name},</h2>
            <p>Your membership will expire in <strong>{days} days</strong> on <strong>{expiry_date}</strong>.</p>
            <p>Please renew your membership to continue enjoying our services.</p>
            <h3>Membership Details:</h3>
            <ul>
                <li>Member ID: {member_id}</li>
                <li>Current Status: {status}</li>
                <li>Expiry Date: {expiry_date}</li>
            </ul>
            <p>Contact us for renewal assistance.</p>
            <p>Thank you!</p>
        ',
        'type' => 'email'
    ],
    
    'membership_expiry_sms' => [
        'content' => 'CSIMS Alert: Hi {first_name}, your membership expires in {days} days ({expiry_date}). Please renew to continue services.',
        'type' => 'sms'
    ],
    
    'payment_overdue_email' => [
        'subject' => 'Payment Overdue - Immediate Action Required',
        'content' => '
            <h2>Dear {name},</h2>
            <p><strong>URGENT:</strong> Your membership payment is overdue.</p>
            <p>Your membership expired on <strong>{expiry_date}</strong>.</p>
            <p>Please make your payment immediately to restore your membership.</p>
            <h3>Account Details:</h3>
            <ul>
                <li>Member ID: {member_id}</li>
                <li>Current Status: {status}</li>
                <li>Expired Date: {expiry_date}</li>
            </ul>
            <p>Contact us immediately to resolve this issue.</p>
        ',
        'type' => 'email'
    ],
    
    'payment_overdue_sms' => [
        'content' => 'CSIMS: Hi {first_name}, your membership payment is overdue. Please contact us immediately to avoid service interruption.',
        'type' => 'sms'
    ],
    
    'welcome_email' => [
        'subject' => 'Welcome to CSIMS - Your Membership is Active!',
        'content' => '
            <h2>Dear {name},</h2>
            <p><strong>Congratulations!</strong> Your membership application has been approved.</p>
            <p>We\'re thrilled to welcome you to our community!</p>
            <h3>Your Membership Details:</h3>
            <ul>
                <li>Member ID: {member_id}</li>
                <li>Status: Active</li>
                <li>Join Date: {current_date}</li>
            </ul>
            <p>If you have any questions, please don\'t hesitate to contact us.</p>
            <p>Welcome aboard!</p>
        ',
        'type' => 'email'
    ],
    
    'welcome_sms' => [
        'content' => 'Welcome to CSIMS, {first_name}! Your membership is now active. We\'re excited to have you as part of our community!',
        'type' => 'sms'
    ],
    
    'birthday_email' => [
        'subject' => 'Happy Birthday from CSIMS!',
        'content' => '
            <h2>Happy Birthday, {first_name}!</h2>
            <p>On behalf of everyone at CSIMS, we wish you a wonderful birthday!</p>
            <p>Thank you for being a valued member of our community.</p>
            <p>We hope you have a fantastic day!</p>
            <p>Best wishes,<br>The CSIMS Team</p>
        ',
        'type' => 'email'
    ],
    
    'birthday_sms' => [
        'content' => 'Happy Birthday, {first_name}! Wishing you a wonderful day from all of us at CSIMS!',
        'type' => 'sms'
    ]
];

// Notification Scheduling
$schedulingSettings = [
    'cron_enabled' => true,
    'max_emails_per_hour' => 100,
    'max_sms_per_hour' => 50,
    'retry_failed_after_hours' => 24,
    'max_retry_attempts' => 3,
    'cleanup_logs_after_days' => 90,
    'batch_size' => 50, // Number of notifications to process at once
    'delay_between_batches' => 5 // Seconds to wait between batches
];

// Recipient Groups
$recipientGroups = [
    'all_members' => [
        'name' => 'All Members',
        'description' => 'All registered members',
        'query' => "SELECT * FROM members WHERE email IS NOT NULL"
    ],
    
    'active_members' => [
        'name' => 'Active Members',
        'description' => 'Members with active status',
        'query' => "SELECT * FROM members WHERE status = 'Active' AND email IS NOT NULL"
    ],
    
    'expired_members' => [
        'name' => 'Expired Members',
        'description' => 'Members with expired status',
        'query' => "SELECT * FROM members WHERE status = 'Expired' AND email IS NOT NULL"
    ],
    
    'expiring_soon' => [
        'name' => 'Expiring Soon',
        'description' => 'Members expiring within 30 days',
        'query' => "
            SELECT m.* FROM members m 
            JOIN memberships ms ON m.id = ms.member_id 
            WHERE ms.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND m.status = 'Active' AND m.email IS NOT NULL
        "
    ],
    
    'new_members' => [
        'name' => 'New Members',
        'description' => 'Members who joined in the last 7 days',
        'query' => "
            SELECT * FROM members 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND email IS NOT NULL
        "
    ],
    
    'admins' => [
        'name' => 'Administrators',
        'description' => 'System administrators',
        'query' => "
            SELECT email, CONCAT(first_name, ' ', last_name) as name, 'admin' as role
            FROM users 
            WHERE role = 'admin' AND email IS NOT NULL AND status = 'active'
        "
    ]
];

// Placeholder Variables
$availablePlaceholders = [
    '{name}' => 'Full name (first_name + last_name)',
    '{first_name}' => 'First name',
    '{last_name}' => 'Last name',
    '{member_id}' => 'Member ID',
    '{email}' => 'Email address',
    '{phone}' => 'Phone number',
    '{status}' => 'Membership status',
    '{membership_type}' => 'Type of membership',
    '{expiry_date}' => 'Membership expiry date',
    '{join_date}' => 'Date member joined',
    '{current_date}' => 'Current date',
    '{current_time}' => 'Current time',
    '{current_year}' => 'Current year',
    '{days}' => 'Number of days (for expiry reminders)',
    '{organization_name}' => 'Organization name',
    '{contact_email}' => 'Contact email',
    '{contact_phone}' => 'Contact phone',
    '{website_url}' => 'Website URL'
];

// Organization Information
$organizationInfo = [
    'name' => 'CSIMS',
    'full_name' => 'Cooperative Society Information Management System',
    'contact_email' => 'info@csims.com',
    'contact_phone' => '+1-234-567-8900',
    'website_url' => 'https://www.csims.com',
    'address' => '123 Main Street, City, State 12345',
    'business_hours' => 'Monday - Friday: 9:00 AM - 5:00 PM'
];

// Logging Configuration
$loggingSettings = [
    'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
    'log_file_path' => __DIR__ . '/../logs/',
    'max_log_file_size' => 10485760, // 10MB
    'log_rotation' => true,
    'log_email_content' => false, // For privacy
    'log_sms_content' => false, // For privacy
    'log_recipient_details' => true
];

// Security Settings
$securitySettings = [
    'rate_limiting' => [
        'enabled' => true,
        'max_emails_per_minute' => 10,
        'max_sms_per_minute' => 5,
        'cooldown_period' => 300 // 5 minutes
    ],
    
    'content_filtering' => [
        'enabled' => true,
        'blocked_words' => ['spam', 'scam', 'phishing'],
        'max_message_length' => 5000
    ],
    
    'authentication' => [
        'require_admin_approval' => true,
        'log_all_activities' => true
    ]
];

// Export settings for use in other files
return [
    'email' => [
        'enabled' => EMAIL_ENABLED,
        'smtp_host' => EMAIL_SMTP_HOST,
        'smtp_port' => EMAIL_SMTP_PORT,
        'smtp_secure' => EMAIL_SMTP_SECURE,
        'username' => EMAIL_USERNAME,
        'password' => EMAIL_PASSWORD,
        'from_address' => EMAIL_FROM_ADDRESS,
        'from_name' => EMAIL_FROM_NAME,
        'reply_to' => EMAIL_REPLY_TO
    ],
    
    'sms' => [
        'enabled' => SMS_ENABLED,
        'provider' => SMS_PROVIDER,
        'twilio' => [
            'sid' => SMS_TWILIO_SID,
            'token' => SMS_TWILIO_TOKEN,
            'from' => SMS_TWILIO_FROM
        ],
        'nexmo' => [
            'key' => SMS_NEXMO_KEY,
            'secret' => SMS_NEXMO_SECRET,
            'from' => SMS_NEXMO_FROM
        ],
        'textlocal' => [
            'username' => SMS_TEXTLOCAL_USERNAME,
            'hash' => SMS_TEXTLOCAL_HASH,
            'sender' => SMS_TEXTLOCAL_SENDER
        ],
        'max_length' => SMS_MAX_LENGTH
    ],
    
    'notifications' => $notificationSettings,
    'templates' => $defaultTemplates,
    'scheduling' => $schedulingSettings,
    'recipients' => $recipientGroups,
    'placeholders' => $availablePlaceholders,
    'organization' => $organizationInfo,
    'logging' => $loggingSettings,
    'security' => $securitySettings
];

?>