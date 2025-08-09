# CSIMS Troubleshooting Guide

## Table of Contents

1. [Common Issues](#common-issues)
2. [Database Problems](#database-problems)
3. [Authentication Issues](#authentication-issues)
4. [File Upload Problems](#file-upload-problems)
5. [Performance Issues](#performance-issues)
6. [Email and Notification Problems](#email-and-notification-problems)
7. [Report Generation Issues](#report-generation-issues)
8. [Browser Compatibility](#browser-compatibility)
9. [Server Configuration Issues](#server-configuration-issues)
10. [Error Messages Reference](#error-messages-reference)
11. [Diagnostic Tools](#diagnostic-tools)
12. [Getting Help](#getting-help)

## Common Issues

### Issue: "Page Not Found" or 404 Errors

**Symptoms:**
- Accessing CSIMS shows 404 error
- Internal pages return "Not Found"

**Possible Causes:**
- Incorrect installation path
- Web server configuration issues
- Missing .htaccess file

**Solutions:**

1. **Verify Installation Path**
   ```bash
   # Check if files exist
   ls -la /var/www/html/CSIMS/  # Linux
   dir C:\xampp\htdocs\CSIMS\   # Windows
   ```

2. **Check Web Server Configuration**
   ```apache
   # Apache - Ensure mod_rewrite is enabled
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

3. **Verify .htaccess File**
   ```apache
   # Create/check .htaccess in CSIMS root
   RewriteEngine On
   DirectoryIndex index.php
   ```

### Issue: White Screen or Blank Page

**Symptoms:**
- Page loads but shows nothing
- No error message displayed

**Possible Causes:**
- PHP fatal errors
- Memory limit exceeded
- Missing configuration

**Solutions:**

1. **Enable Error Reporting**
   ```php
   // Add to top of index.php temporarily
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

2. **Check PHP Error Log**
   ```bash
   tail -f /var/log/php_errors.log
   ```

3. **Increase Memory Limit**
   ```ini
   ; In php.ini
   memory_limit = 256M
   ```

### Issue: "Internal Server Error" (500)

**Symptoms:**
- HTTP 500 error displayed
- Server error in browser

**Possible Causes:**
- PHP syntax errors
- Incorrect file permissions
- Server configuration issues

**Solutions:**

1. **Check Error Logs**
   ```bash
   # Apache error log
   tail -f /var/log/apache2/error.log
   
   # Nginx error log
   tail -f /var/log/nginx/error.log
   ```

2. **Fix File Permissions**
   ```bash
   # Set correct permissions
   sudo chown -R www-data:www-data /var/www/html/CSIMS
   sudo chmod -R 755 /var/www/html/CSIMS
   sudo chmod 644 config/config.php
   ```

3. **Check PHP Syntax**
   ```bash
   php -l config/config.php
   php -l index.php
   ```

## Database Problems

### Issue: "Database Connection Failed"

**Error Message:**
```
Fatal error: Uncaught mysqli_sql_exception: Access denied for user 'csims_user'@'localhost'
```

**Solutions:**

1. **Verify Database Credentials**
   ```php
   // Check config/config.php
   define('DB_HOST', 'localhost');
   define('DB_USERNAME', 'csims_user');
   define('DB_PASSWORD', 'your_password');
   define('DB_NAME', 'csims_db');
   ```

2. **Test Database Connection**
   ```bash
   mysql -u csims_user -p csims_db
   ```

3. **Reset Database User**
   ```sql
   DROP USER 'csims_user'@'localhost';
   CREATE USER 'csims_user'@'localhost' IDENTIFIED BY 'new_password';
   GRANT ALL PRIVILEGES ON csims_db.* TO 'csims_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Issue: "Table Doesn't Exist"

**Error Message:**
```
Table 'csims_db.members' doesn't exist
```

**Solutions:**

1. **Check Database Tables**
   ```sql
   USE csims_db;
   SHOW TABLES;
   ```

2. **Reinitialize Database**
   ```bash
   php config/init_db.php
   ```

3. **Import Database Schema**
   ```bash
   mysql -u csims_user -p csims_db < database/schema.sql
   ```

### Issue: "Unknown Column" Error

**Error Message:**
```
Unknown column 'date_of_birth' in 'field list'
```

**Solutions:**

1. **Check Table Structure**
   ```sql
   DESCRIBE members;
   ```

2. **Update Column References**
   ```php
   // Change from 'date_of_birth' to 'dob'
   $sql = "SELECT * FROM members WHERE dob = ?";
   ```

3. **Add Missing Columns**
   ```sql
   ALTER TABLE members ADD COLUMN date_of_birth DATE AFTER dob;
   ```

## Authentication Issues

### Issue: Cannot Login

**Symptoms:**
- Valid credentials rejected
- "Invalid username or password" message

**Solutions:**

1. **Reset Admin Password**
   ```sql
   UPDATE admins SET password = ? WHERE username = 'admin';
   -- Use password_hash('new_password', PASSWORD_DEFAULT) for the value
   ```

2. **Check Password Hashing**
   ```php
   // Verify password hashing method
   $hashed = password_hash('admin123', PASSWORD_DEFAULT);
   echo $hashed;
   ```

3. **Clear Sessions**
   ```bash
   # Clear PHP sessions
   sudo rm -rf /var/lib/php/sessions/*
   ```

### Issue: Session Timeout Issues

**Symptoms:**
- Frequent logouts
- Session expires too quickly

**Solutions:**

1. **Increase Session Timeout**
   ```php
   // In config/config.php
   define('SESSION_TIMEOUT', 7200); // 2 hours
   ```

2. **Check PHP Session Settings**
   ```ini
   ; In php.ini
   session.gc_maxlifetime = 7200
   session.cookie_lifetime = 7200
   ```

### Issue: "Access Denied" for Valid Users

**Symptoms:**
- User can login but cannot access certain pages
- Permission denied errors

**Solutions:**

1. **Check User Role**
   ```sql
   SELECT username, role, status FROM admins WHERE username = 'your_username';
   ```

2. **Update User Status**
   ```sql
   UPDATE admins SET status = 'Active' WHERE username = 'your_username';
   ```

## File Upload Problems

### Issue: "File Too Large" Error

**Error Message:**
```
The uploaded file exceeds the upload_max_filesize directive in php.ini
```

**Solutions:**

1. **Increase PHP Upload Limits**
   ```ini
   ; In php.ini
   upload_max_filesize = 10M
   post_max_size = 10M
   max_execution_time = 300
   max_input_time = 300
   ```

2. **Restart Web Server**
   ```bash
   sudo systemctl restart apache2  # or nginx
   ```

### Issue: Upload Directory Not Writable

**Error Message:**
```
Failed to move uploaded file
```

**Solutions:**

1. **Set Directory Permissions**
   ```bash
   sudo chmod 755 uploads/
   sudo chmod 755 uploads/photos/
   sudo chmod 755 uploads/documents/
   sudo chown -R www-data:www-data uploads/
   ```

2. **Create Missing Directories**
   ```bash
   mkdir -p uploads/photos
   mkdir -p uploads/documents
   ```

## Performance Issues

### Issue: Slow Page Loading

**Symptoms:**
- Pages take long time to load
- Database queries are slow

**Solutions:**

1. **Optimize Database**
   ```sql
   -- Add indexes for frequently queried columns
   CREATE INDEX idx_member_status ON members(status);
   CREATE INDEX idx_contribution_date ON contributions(contribution_date);
   CREATE INDEX idx_loan_status ON loans(status);
   ```

2. **Enable Query Caching**
   ```ini
   ; In MySQL configuration
   query_cache_type = 1
   query_cache_size = 64M
   ```

3. **Optimize PHP**
   ```ini
   ; In php.ini
   opcache.enable = 1
   opcache.memory_consumption = 128
   opcache.max_accelerated_files = 4000
   ```

### Issue: High Memory Usage

**Symptoms:**
- "Fatal error: Allowed memory size exhausted"
- Server becomes unresponsive

**Solutions:**

1. **Increase Memory Limit**
   ```ini
   ; In php.ini
   memory_limit = 512M
   ```

2. **Optimize Queries**
   ```php
   // Use LIMIT in queries
   $sql = "SELECT * FROM members LIMIT 50";
   
   // Free result sets
   $result->free();
   ```

## Email and Notification Problems

### Issue: Emails Not Sending

**Symptoms:**
- Notification emails not received
- SMTP errors in logs

**Solutions:**

1. **Check SMTP Configuration**
   ```php
   // In config/config.php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USERNAME', 'your-email@gmail.com');
   define('SMTP_PASSWORD', 'your-app-password');
   ```

2. **Test Email Function**
   ```php
   // Create test email script
   $to = 'test@example.com';
   $subject = 'Test Email';
   $message = 'This is a test email.';
   $headers = 'From: noreply@yoursite.com';
   
   if (mail($to, $subject, $message, $headers)) {
       echo 'Email sent successfully';
   } else {
       echo 'Email failed to send';
   }
   ```

3. **Check Firewall Settings**
   ```bash
   # Allow SMTP ports
   sudo ufw allow 587
   sudo ufw allow 465
   ```

### Issue: Notifications Not Displaying

**Symptoms:**
- New notifications don't appear
- Notification count incorrect

**Solutions:**

1. **Check Database Records**
   ```sql
   SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10;
   ```

2. **Clear Browser Cache**
   - Clear browser cache and cookies
   - Try incognito/private browsing mode

3. **Check JavaScript Errors**
   - Open browser developer tools
   - Look for JavaScript errors in console

## Report Generation Issues

### Issue: Reports Not Generating

**Symptoms:**
- Blank report pages
- "No data found" errors
- PDF generation fails

**Solutions:**

1. **Check Data Availability**
   ```sql
   SELECT COUNT(*) FROM members;
   SELECT COUNT(*) FROM contributions;
   SELECT COUNT(*) FROM loans;
   ```

2. **Verify Date Ranges**
   ```php
   // Ensure date format is correct
   $start_date = date('Y-m-d', strtotime($input_date));
   ```

3. **Check PDF Library**
   ```bash
   # Install required libraries
   composer require dompdf/dompdf
   ```

### Issue: "Column Not Found" in Reports

**Error Message:**
```
Unknown column 'date_of_birth' in 'field list'
```

**Solutions:**

1. **Update Column Names**
   ```php
   // In report_controller.php
   // Change 'date_of_birth' to 'dob'
   $sql = "SELECT YEAR(CURDATE()) - YEAR(dob) as age FROM members";
   ```

2. **Check Table Schema**
   ```sql
   SHOW COLUMNS FROM members;
   ```

## Browser Compatibility

### Issue: Layout Problems in Older Browsers

**Symptoms:**
- Broken layout in Internet Explorer
- Missing styles or functionality

**Solutions:**

1. **Add Browser Compatibility Meta Tags**
   ```html
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   ```

2. **Include Polyfills**
   ```html
   <!--[if lt IE 9]>
   <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
   <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
   <![endif]-->
   ```

### Issue: JavaScript Errors

**Symptoms:**
- Interactive features not working
- Console shows JavaScript errors

**Solutions:**

1. **Check jQuery Loading**
   ```html
   <!-- Ensure jQuery loads before other scripts -->
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   ```

2. **Fix JavaScript Syntax**
   ```javascript
   // Use proper error handling
   try {
       // Your code here
   } catch (error) {
       console.error('Error:', error);
   }
   ```

## Server Configuration Issues

### Issue: Apache/Nginx Not Starting

**Symptoms:**
- Web server fails to start
- Port already in use errors

**Solutions:**

1. **Check Port Usage**
   ```bash
   sudo netstat -tulpn | grep :80
   sudo netstat -tulpn | grep :443
   ```

2. **Check Configuration Syntax**
   ```bash
   # Apache
   sudo apache2ctl configtest
   
   # Nginx
   sudo nginx -t
   ```

3. **View Service Status**
   ```bash
   sudo systemctl status apache2
   sudo systemctl status nginx
   ```

### Issue: PHP Not Working

**Symptoms:**
- PHP files download instead of executing
- "File not found" for PHP files

**Solutions:**

1. **Install PHP Module**
   ```bash
   # Apache
   sudo apt install libapache2-mod-php8.2
   sudo a2enmod php8.2
   
   # Nginx
   sudo apt install php8.2-fpm
   sudo systemctl start php8.2-fpm
   ```

2. **Check PHP Configuration**
   ```apache
   # Apache - ensure this is in configuration
   <FilesMatch \.php$>
       SetHandler application/x-httpd-php
   </FilesMatch>
   ```

## Error Messages Reference

### Database Errors

| Error Code | Message | Solution |
|------------|---------|----------|
| 1045 | Access denied for user | Check database credentials |
| 1146 | Table doesn't exist | Run database initialization |
| 1054 | Unknown column | Update column names in queries |
| 2002 | Can't connect to MySQL server | Start MySQL service |

### PHP Errors

| Error Type | Common Causes | Solution |
|------------|---------------|----------|
| Fatal Error | Syntax errors, missing files | Check PHP syntax, verify file paths |
| Parse Error | Syntax mistakes | Review code syntax |
| Warning | Deprecated functions | Update code to use current functions |
| Notice | Undefined variables | Initialize variables properly |

### HTTP Errors

| Status Code | Meaning | Common Causes |
|-------------|---------|---------------|
| 403 | Forbidden | File permissions, .htaccess issues |
| 404 | Not Found | Missing files, incorrect URLs |
| 500 | Internal Server Error | PHP errors, server misconfiguration |
| 503 | Service Unavailable | Server overload, maintenance mode |

## Diagnostic Tools

### System Information Script

Create `diagnostic.php` in your CSIMS root:

```php
<?php
echo "<h2>CSIMS Diagnostic Information</h2>";

// PHP Information
echo "<h3>PHP Version: " . PHP_VERSION . "</h3>";

// Required Extensions
$required_extensions = ['mysqli', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'gd'];
echo "<h3>PHP Extensions:</h3><ul>";
foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? '✅' : '❌';
    echo "<li>{$status} {$ext}</li>";
}
echo "</ul>";

// Database Connection
echo "<h3>Database Connection:</h3>";
try {
    include 'config/config.php';
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        echo "❌ Connection failed: " . $conn->connect_error;
    } else {
        echo "✅ Database connected successfully";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

// File Permissions
echo "<h3>File Permissions:</h3><ul>";
$check_dirs = ['uploads/', 'logs/', 'config/'];
foreach ($check_dirs as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        echo "<li>{$dir}: {$perms}</li>";
    } else {
        echo "<li>❌ {$dir}: Directory not found</li>";
    }
}
echo "</ul>";

// Memory and Limits
echo "<h3>PHP Configuration:</h3><ul>";
echo "<li>Memory Limit: " . ini_get('memory_limit') . "</li>";
echo "<li>Upload Max Size: " . ini_get('upload_max_filesize') . "</li>";
echo "<li>Post Max Size: " . ini_get('post_max_size') . "</li>";
echo "<li>Max Execution Time: " . ini_get('max_execution_time') . "</li>";
echo "</ul>";
?>
```

### Log Monitoring Script

Create `log_monitor.php`:

```php
<?php
// Display recent error logs
echo "<h2>Recent Error Logs</h2>";

$log_files = [
    'Application Log' => 'logs/error.log',
    'PHP Error Log' => ini_get('error_log'),
    'Apache Error Log' => '/var/log/apache2/error.log'
];

foreach ($log_files as $name => $file) {
    echo "<h3>{$name}</h3>";
    if (file_exists($file) && is_readable($file)) {
        $lines = file($file);
        $recent_lines = array_slice($lines, -10); // Last 10 lines
        echo "<pre>" . implode('', $recent_lines) . "</pre>";
    } else {
        echo "<p>Log file not accessible: {$file}</p>";
    }
}
?>
```

## Getting Help

### Before Seeking Help

1. **Check Error Logs**
   - Application logs in `logs/` directory
   - Web server error logs
   - PHP error logs

2. **Run Diagnostic Script**
   - Use the diagnostic tools provided above
   - Document any error messages

3. **Try Basic Solutions**
   - Restart web server and database
   - Clear browser cache
   - Check file permissions

### Information to Provide

When seeking help, include:

1. **System Information**
   - Operating system and version
   - PHP version
   - Web server (Apache/Nginx) version
   - MySQL/MariaDB version

2. **Error Details**
   - Exact error message
   - When the error occurs
   - Steps to reproduce

3. **Configuration**
   - Relevant configuration files (remove sensitive data)
   - Recent changes made to the system

### Support Channels

1. **Documentation**
   - User Manual
   - Technical Documentation
   - API Documentation

2. **Community Support**
   - GitHub Issues (if applicable)
   - Community forums
   - Stack Overflow (tag: csims)

3. **Professional Support**
   - Contact system administrator
   - Hire PHP/MySQL developer
   - Consult with web hosting provider

---

*This troubleshooting guide is regularly updated based on common issues reported by users. If you encounter an issue not covered here, please document it for future reference.*