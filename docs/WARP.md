# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

CSIMS is a Cooperative Society Information Management System built with PHP 8.2+ and MySQL. It follows an MVC architecture pattern with comprehensive security features including 2FA, CSRF protection, rate limiting, and audit logging.

## Development Commands

### Environment Setup
```bash
# Start XAMPP/development server
# Access application at: http://localhost:8000/

# Install PHP dependencies
composer install

# Install Node.js dependencies (for Tailwind CSS)
npm install
```

### CSS Development
```bash
# Build CSS with watch mode for development
npm run build-css

# Build minified CSS for production
npm run build-css-prod
```

### Database Operations
```bash
# Initialize database (creates csims_db if not exists)
php config/init_db.php

# Create admin user for testing
php create_test_admin.php

# Run database migrations
php database/migrations/run_extended_member_fields_migration.php
```

### Development Testing
```bash
# Test database connection
php test_db_class.php

# Test login functionality
php test_login.php

# Test import functionality
php test_import.php

# Debug login issues
php debug_login.php
```

### Cron Jobs & Background Tasks
```bash
# Run notification system manually
php cron/process_notifications.php

# Run automated notifications
php cron/automated_notifications.php

# Execute notification triggers
php cron/notification_trigger_runner.php

# Run all cron jobs
php cron/cron_runner.php
```

## Architecture Overview

### Core Directory Structure
- **`/config`** - Application configuration, database settings, security configs
- **`/controllers`** - Business logic layer (MVC Controllers)
- **`/views`** - Presentation layer organized by user roles (admin/, member/)
- **`/includes`** - Core utilities (database, session, email services)
- **`/assets`** - Frontend resources (CSS, JS, images)
- **`/cron`** - Background job processing and scheduled tasks
- **`/documentation`** - Comprehensive project documentation

### Key Architectural Patterns

#### MVC Architecture
- **Models**: Data access through Database singleton class and prepared statements
- **Views**: PHP templates in `/views/admin/` and `/views/member/` directories
- **Controllers**: Business logic in `/controllers/` with dedicated controllers for each domain

#### Security Architecture
- **Authentication**: Multi-factor authentication with TOTP support
- **Session Management**: Custom Session class with timeout and regeneration
- **Security Controllers**: Centralized security logging and threat detection
- **Rate Limiting**: Built-in rate limiting for login attempts and API calls
- **Input Validation**: SecurityValidator class for sanitization and validation

#### Database Architecture
- **Connection**: Singleton Database class with connection pooling
- **Queries**: Prepared statements throughout for SQL injection prevention
- **Transactions**: Support for database transactions in critical operations
- **Migrations**: SQL migration files in `/database/migrations/`

### Core Classes & Components

#### Database Layer
- **`Database`** - Singleton database connection manager with transaction support
- **`SecurityValidator`** - Input sanitization and validation utilities
- **`RateLimiter`** - Request rate limiting implementation

#### Authentication & Security
- **`AuthController`** - Login, logout, password management, 2FA verification
- **`SecurityController`** - Security event logging, threat detection
- **`Session`** - Custom session management with security features
- **`SecurityLogger`** - Comprehensive security event logging

#### Business Logic Controllers
- **`MemberController`** - Member CRUD operations and management
- **`ContributionController`** - Financial contribution tracking
- **`LoanController`** - Loan processing and repayment management
- **`NotificationController`** - Internal messaging and notifications
- **`ReportController`** - Report generation and analytics

#### Communication System
- **Email Service**: PHPMailer integration with SMTP support and fallback
- **SMS Service**: SMS gateway integration for notifications
- **Message System**: Internal messaging between admins and members
- **Notification Triggers**: Automated notification system with cron job support

### Configuration Management

#### Environment Configuration
- **`config/config.php`** - Main application configuration
- **`config/database.php`** - Database connection settings  
- **`config/security.php`** - Security headers and CSRF protection
- **`.env`** - Environment-specific variables (use `.env.example` as template)

#### Security Settings
- CSRF protection enabled on all forms
- Content Security Policy headers configured
- Rate limiting: 5 login attempts with 15-minute lockout
- Password requirements: 8+ chars, uppercase, lowercase, numbers, special chars
- Session timeout: 30 minutes with automatic regeneration

### Import/Export System
- **CSV Import**: Member, contribution, and loan data import with validation
- **Excel Export**: Report generation in multiple formats (PDF, CSV, Excel)
- **Bulk Operations**: Mass member operations with progress tracking
- **Data Validation**: Header mapping and data integrity checks

### Notification & Cron System
- **Automated Notifications**: Daily, weekly, monthly notification triggers
- **Email Fallback**: Automatic fallback email service for reliability
- **Cron Management**: Centralized cron job runner with logging
- **Notification Types**: System alerts, payment reminders, announcements

### Frontend Technology
- **Tailwind CSS**: Utility-first CSS framework with custom configuration
- **JavaScript**: Vanilla JS for interactive components and form validation
- **Bootstrap**: Additional UI components and responsive grid system
- **Chart.js**: Data visualization for financial analytics and reports

## Development Guidelines

### Database Queries
- Always use prepared statements via `Database::getInstance()->prepare()`
- Use transactions for multi-table operations
- Follow the existing pattern for CRUD operations in controllers

### Security Implementation
- Validate all inputs using `SecurityValidator::sanitizeInput()`
- Log security events using `SecurityLogger::logSecurityEvent()`
- Implement CSRF tokens on forms using the existing security framework
- Use rate limiting for sensitive operations

### File Organization
- Controllers handle business logic only
- Views contain presentation logic
- Database operations centralized in Database class
- Utility functions in `/includes/utilities.php`

### Error Handling
- Production: Errors logged to `/logs/php_errors.log`
- Development: Full error reporting enabled
- Security events logged to `/logs/security.log`
- Email service logs to `/logs/email.log`

### Authentication Flow
1. Login attempts validated through `AuthController::login()`
2. 2FA verification if enabled for user
3. Session creation with security headers
4. Activity logging for audit trail
5. Automatic session timeout and regeneration

### Notification System Architecture
1. **Triggers**: Database-driven notification triggers with scheduling
2. **Processing**: Cron jobs process queued notifications
3. **Delivery**: Email/SMS delivery with fallback mechanisms
4. **Logging**: Comprehensive delivery logging and error tracking

## Testing & Development

### Local Development Setup
- Default admin credentials: admin/admin123 (change immediately)
- Database auto-creates as `csims_db` on first run
- XAMPP recommended for local development environment
- Application runs on http://localhost:8000/ by default

### Key Development Files
- `/test_*.php` - Various functionality testing scripts
- `/debug_*.php` - Debugging utilities for troubleshooting
- `/create_test_admin.php` - Creates admin user for testing
- `/check_*.php` - System status and validation scripts

### Common Development Tasks
- CSS changes: Use `npm run build-css` for live reloading
- Database changes: Create migration files in `/database/migrations/`
- New controllers: Follow existing naming pattern and include security logging
- New views: Organize by user role (admin/member) and include CSRF protection
