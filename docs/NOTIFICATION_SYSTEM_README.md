# CSIMS Notification System - Setup and Troubleshooting

## Overview
The CSIMS notification system has been successfully implemented with fallback email functionality using PHP's built-in `mail()` function. All PHP syntax errors have been resolved.

## Fixed Issues

### 1. PHP Syntax Errors in notification_trigger_runner.php
- **Issue**: Cron syntax `*/5 * * * *` in comments was being parsed as PHP code
- **Fix**: Escaped the asterisks in the comment to prevent parsing errors

### 2. Undefined $emailService Variable
- **Issue**: Missing `global $emailService;` declarations in functions
- **Fix**: Added proper global declarations in `sendWeeklyReports()` and `sendMonthlyReports()` functions

### 3. PHPMailer Dependency Issues
- **Issue**: PHPMailer classes not found (no Composer installation)
- **Fix**: Implemented fallback email service using PHP's built-in `mail()` function

## Current Email Implementation

The system now uses PHP's built-in `mail()` function as a fallback. This works for basic email sending but has limitations:

### Limitations of Built-in mail() Function:
- No SMTP authentication
- Limited HTML email support
- No attachment support in current implementation
- May be blocked by some email providers
- Requires server mail configuration

## Upgrading to PHPMailer (Recommended)

For production use, it's recommended to upgrade to PHPMailer for better email delivery:

### Step 1: Install PHPMailer via Composer
```bash
cd C:\Apps\CSIMS
composer require phpmailer/phpmailer
```

### Step 2: Update email_service.php
Uncomment these lines in `includes/email_service.php`:
```php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
```

### Step 3: Restore PHPMailer Implementation
Replace the current `EmailService` class with the PHPMailer version (backup available in git history)

## Email Configuration

Update the email configuration in `config/notification_config.php`:

```php
'email' => [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@yoursite.com',
    'from_name' => 'CSIMS System'
]
```

## Testing the System

### 1. Test PHP Syntax
```bash
C:\xampp\php\php.exe -l C:\Apps\CSIMS\cron\notification_trigger_runner.php
C:\xampp\php\php.exe -l C:\Apps\CSIMS\cron\automated_notifications.php
C:\xampp\php\php.exe -l C:\Apps\CSIMS\includes\email_service.php
```

### 2. Test Email Functionality
Create a test script to verify email sending works:

```php
<?php
require_once 'includes/email_service.php';

$emailService = new EmailService();
$result = $emailService->send(
    'test@example.com',
    'Test Subject',
    '<h1>Test Email</h1><p>This is a test email.</p>',
    'Test User'
);

echo $result ? 'Email sent successfully!' : 'Email failed to send.';
?>
```

### 3. Test Notification Triggers
```bash
C:\xampp\php\php.exe C:\Apps\CSIMS\cron\notification_trigger_runner.php
```

## Cron Job Setup

### Windows Task Scheduler
Use the provided batch file:
```bash
C:\Apps\CSIMS\setup_cron_windows.bat
```

### Manual Task Creation
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily, repeat every 5 minutes
4. Action: Start a program
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `C:\Apps\CSIMS\cron\notification_trigger_runner.php`

## Troubleshooting

### Email Not Sending
1. Check server mail configuration
2. Verify email settings in config
3. Check logs in `logs/` directory
4. Consider upgrading to PHPMailer

### Permission Issues
Ensure the following directories are writable:
- `logs/`
- `temp/`
- `uploads/`

### Database Connection
Verify database settings in `config/database.php`

## File Structure
```
CSIMS/
├── cron/
│   ├── notification_trigger_runner.php (Fixed)
│   ├── automated_notifications.php (Fixed)
│   └── cron_runner.php
├── includes/
│   ├── email_service.php (Updated with fallback)
│   └── sms_service.php
├── controllers/
│   └── notification_trigger_controller.php
├── config/
│   └── notification_config.php
└── composer.json (Created)
```

## Next Steps

1. **Test the current implementation** with built-in mail()
2. **Configure server mail settings** if needed
3. **Upgrade to PHPMailer** for production use
4. **Set up cron jobs** for automated execution
5. **Monitor logs** for any issues

All PHP syntax errors have been resolved and the notification system is now functional with fallback email support.