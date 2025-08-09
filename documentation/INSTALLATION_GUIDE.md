# CSIMS Installation Guide

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Pre-Installation Setup](#pre-installation-setup)
3. [Installation Methods](#installation-methods)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [Post-Installation Setup](#post-installation-setup)
7. [Verification](#verification)
8. [Troubleshooting](#troubleshooting)
9. [Security Hardening](#security-hardening)
10. [Backup and Maintenance](#backup-and-maintenance)

## System Requirements

### Minimum Requirements
- **Operating System**: Windows 10/11, Ubuntu 18.04+, CentOS 7+, macOS 10.15+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: Version 8.2 or higher
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Memory**: 2GB RAM minimum, 4GB recommended
- **Storage**: 5GB free disk space
- **Network**: Internet connection for initial setup

### Recommended Requirements
- **Memory**: 8GB RAM
- **Storage**: 20GB SSD storage
- **CPU**: Multi-core processor
- **SSL Certificate**: For production deployment

### PHP Extensions Required
- mysqli
- pdo_mysql
- json
- mbstring
- openssl
- curl
- gd
- zip
- xml
- session
- filter

## Pre-Installation Setup

### Windows (XAMPP)

1. **Download and Install XAMPP**
   ```
   Download from: https://www.apachefriends.org/
   Version: 8.2.x or higher
   ```

2. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services
   - Verify services are running (green status)

3. **Configure PHP**
   - Edit `C:\xampp\php\php.ini`
   - Ensure required extensions are enabled:
   ```ini
   extension=mysqli
   extension=pdo_mysql
   extension=mbstring
   extension=openssl
   extension=curl
   extension=gd
   extension=zip
   ```

### Linux (Ubuntu/Debian)

1. **Update System**
   ```bash
   sudo apt update
   sudo apt upgrade -y
   ```

2. **Install Apache**
   ```bash
   sudo apt install apache2 -y
   sudo systemctl enable apache2
   sudo systemctl start apache2
   ```

3. **Install PHP**
   ```bash
   sudo apt install php8.2 php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-gd php8.2-zip -y
   ```

4. **Install MySQL**
   ```bash
   sudo apt install mysql-server -y
   sudo systemctl enable mysql
   sudo systemctl start mysql
   sudo mysql_secure_installation
   ```

### CentOS/RHEL

1. **Install EPEL Repository**
   ```bash
   sudo yum install epel-release -y
   ```

2. **Install Apache**
   ```bash
   sudo yum install httpd -y
   sudo systemctl enable httpd
   sudo systemctl start httpd
   ```

3. **Install PHP**
   ```bash
   sudo yum install php php-mysql php-mbstring php-xml php-curl php-gd php-zip -y
   ```

4. **Install MySQL**
   ```bash
   sudo yum install mysql-server -y
   sudo systemctl enable mysqld
   sudo systemctl start mysqld
   ```

## Installation Methods

### Method 1: Direct Download

1. **Download CSIMS**
   - Download the latest release from the repository
   - Extract to web server directory:
     - Windows (XAMPP): `C:\xampp\htdocs\CSIMS`
     - Linux: `/var/www/html/CSIMS`

2. **Set Permissions (Linux)**
   ```bash
   sudo chown -R www-data:www-data /var/www/html/CSIMS
   sudo chmod -R 755 /var/www/html/CSIMS
   sudo chmod -R 777 /var/www/html/CSIMS/logs
   sudo chmod -R 777 /var/www/html/CSIMS/uploads
   ```

### Method 2: Git Clone

1. **Clone Repository**
   ```bash
   # Navigate to web directory
   cd /var/www/html  # Linux
   cd C:\xampp\htdocs  # Windows
   
   # Clone repository
   git clone https://github.com/your-repo/CSIMS.git
   cd CSIMS
   ```

2. **Install Dependencies (if using Composer)**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

## Database Setup

### Create Database

1. **Access MySQL**
   ```bash
   mysql -u root -p
   ```

2. **Create Database and User**
   ```sql
   CREATE DATABASE csims_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'csims_user'@'localhost' IDENTIFIED BY 'secure_password_here';
   GRANT ALL PRIVILEGES ON csims_db.* TO 'csims_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

### Initialize Database Schema

1. **Run Database Initialization**
   - Navigate to your CSIMS installation
   - Access: `http://localhost/CSIMS/config/init_db.php`
   - Or run via command line:
   ```bash
   php config/init_db.php
   ```

2. **Verify Tables Created**
   ```sql
   USE csims_db;
   SHOW TABLES;
   ```
   
   Expected tables:
   - admins
   - members
   - membership_types
   - contributions
   - loans
   - investments
   - notifications
   - messages

## Configuration

### Database Configuration

1. **Edit Configuration File**
   ```php
   // config/config.php
   <?php
   // Database Configuration
   define('DB_HOST', 'localhost');
   define('DB_USERNAME', 'csims_user');
   define('DB_PASSWORD', 'secure_password_here');
   define('DB_NAME', 'csims_db');
   
   // Application Configuration
   define('APP_NAME', 'CSIMS');
   define('APP_VERSION', '1.0.0');
   define('BASE_URL', 'http://localhost/CSIMS');
   
   // Security Configuration
   define('SESSION_TIMEOUT', 3600); // 1 hour
   define('PASSWORD_MIN_LENGTH', 8);
   define('MAX_LOGIN_ATTEMPTS', 5);
   
   // File Upload Configuration
   define('UPLOAD_MAX_SIZE', 5242880); // 5MB
   define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
   
   // Email Configuration (if using email features)
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USERNAME', 'your-email@gmail.com');
   define('SMTP_PASSWORD', 'your-app-password');
   
   // Environment
   define('ENVIRONMENT', 'development'); // development, production
   define('DEBUG_MODE', true);
   ?>
   ```

### Web Server Configuration

#### Apache (.htaccess)

1. **Create .htaccess file in CSIMS root**
   ```apache
   RewriteEngine On
   
   # Security Headers
   Header always set X-Content-Type-Options nosniff
   Header always set X-Frame-Options DENY
   Header always set X-XSS-Protection "1; mode=block"
   Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
   
   # Hide sensitive files
   <Files "config.php">
       Order allow,deny
       Deny from all
   </Files>
   
   <Files "*.log">
       Order allow,deny
       Deny from all
   </Files>
   
   # URL Rewriting (if needed)
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^api/(.*)$ api/index.php [QSA,L]
   
   # Prevent access to sensitive directories
   RedirectMatch 404 /\.git
   RedirectMatch 404 /config/
   RedirectMatch 404 /logs/
   ```

#### Nginx Configuration

1. **Create Nginx site configuration**
   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /var/www/html/CSIMS;
       index index.php index.html;
       
       # Security headers
       add_header X-Content-Type-Options nosniff;
       add_header X-Frame-Options DENY;
       add_header X-XSS-Protection "1; mode=block";
       
       # PHP handling
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
       
       # Deny access to sensitive files
       location ~ /\.(ht|git) {
           deny all;
       }
       
       location ~ /(config|logs)/ {
           deny all;
       }
       
       # API routing
       location /api/ {
           try_files $uri $uri/ /api/index.php?$query_string;
       }
   }
   ```

## Post-Installation Setup

### Create Default Admin Account

1. **Access the system**
   - Navigate to: `http://localhost/CSIMS`
   - Default admin credentials:
     - Username: `admin`
     - Password: `admin123`

2. **Change Default Password**
   - Login with default credentials
   - Navigate to Admin Profile
   - Change password immediately

### Configure System Settings

1. **Update System Information**
   - Organization name
   - Contact information
   - System preferences

2. **Set Up Membership Types**
   - Define membership categories
   - Set membership fees
   - Configure membership benefits

3. **Configure Email Settings**
   - SMTP configuration
   - Email templates
   - Notification preferences

### Create Directory Structure

1. **Create Required Directories**
   ```bash
   mkdir -p uploads/photos
   mkdir -p uploads/documents
   mkdir -p logs
   mkdir -p backups
   ```

2. **Set Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/photos/
   chmod 755 uploads/documents/
   chmod 777 logs/
   chmod 755 backups/
   ```

## Verification

### System Health Check

1. **Access Health Check Page**
   ```
   http://localhost/CSIMS/config/health_check.php
   ```

2. **Verify Components**
   - ✅ Database connection
   - ✅ PHP version and extensions
   - ✅ File permissions
   - ✅ Directory structure
   - ✅ Configuration files

### Functional Testing

1. **Test Admin Functions**
   - Login/logout
   - Member management
   - Financial operations
   - Report generation

2. **Test Member Functions**
   - Member registration
   - Profile management
   - Contribution tracking
   - Messaging system

## Troubleshooting

### Common Issues

#### Database Connection Error
```
Error: Could not connect to database
```
**Solution:**
1. Verify MySQL service is running
2. Check database credentials in config.php
3. Ensure database exists
4. Test connection manually:
   ```bash
   mysql -u csims_user -p csims_db
   ```

#### Permission Denied Errors
```
Error: Permission denied
```
**Solution:**
1. Check file permissions:
   ```bash
   ls -la /var/www/html/CSIMS
   ```
2. Set correct ownership:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/CSIMS
   ```

#### PHP Extension Missing
```
Error: Call to undefined function mysqli_connect()
```
**Solution:**
1. Install missing extension:
   ```bash
   sudo apt install php8.2-mysql
   ```
2. Restart web server:
   ```bash
   sudo systemctl restart apache2
   ```

#### Session Issues
```
Error: Session could not be started
```
**Solution:**
1. Check session directory permissions:
   ```bash
   ls -la /var/lib/php/sessions
   ```
2. Verify session configuration in php.ini

### Log Files

1. **Application Logs**
   ```
   logs/application.log
   logs/error.log
   logs/access.log
   ```

2. **Web Server Logs**
   ```
   # Apache
   /var/log/apache2/error.log
   /var/log/apache2/access.log
   
   # Nginx
   /var/log/nginx/error.log
   /var/log/nginx/access.log
   ```

3. **PHP Logs**
   ```
   /var/log/php_errors.log
   ```

## Security Hardening

### File Permissions
```bash
# Set secure permissions
find /var/www/html/CSIMS -type f -exec chmod 644 {} \;
find /var/www/html/CSIMS -type d -exec chmod 755 {} \;
chmod 600 config/config.php
chmod 777 logs/
chmod 755 uploads/
```

### Database Security
```sql
-- Remove test databases
DROP DATABASE IF EXISTS test;

-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Disable remote root login
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Reload privileges
FLUSH PRIVILEGES;
```

### SSL Configuration
1. Obtain SSL certificate
2. Configure HTTPS in web server
3. Update BASE_URL in config.php
4. Force HTTPS redirects

## Backup and Maintenance

### Database Backup
```bash
#!/bin/bash
# backup_database.sh
DATE=$(date +"%Y%m%d_%H%M%S")
mysqldump -u csims_user -p csims_db > backups/csims_backup_$DATE.sql
gzip backups/csims_backup_$DATE.sql
```

### File Backup
```bash
#!/bin/bash
# backup_files.sh
DATE=$(date +"%Y%m%d_%H%M%S")
tar -czf backups/csims_files_$DATE.tar.gz \
    --exclude='logs/*' \
    --exclude='backups/*' \
    /var/www/html/CSIMS
```

### Automated Backup (Cron)
```bash
# Add to crontab
crontab -e

# Daily database backup at 2 AM
0 2 * * * /path/to/backup_database.sh

# Weekly file backup on Sunday at 3 AM
0 3 * * 0 /path/to/backup_files.sh
```

### Maintenance Tasks
1. **Regular Updates**
   - Update PHP and extensions
   - Update web server
   - Update database

2. **Log Rotation**
   ```bash
   # Configure logrotate
   sudo nano /etc/logrotate.d/csims
   ```

3. **Performance Monitoring**
   - Monitor disk usage
   - Check memory usage
   - Review error logs

---

**Installation Complete!**

Your CSIMS installation should now be ready for use. For additional support, refer to the User Manual and Technical Documentation.

*Last updated: January 2024*