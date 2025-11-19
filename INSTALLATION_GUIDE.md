# CSIMS Installation and Setup Guide

This guide will help you install and configure the refactored CSIMS system with its new modern architecture.

## Prerequisites

- **PHP 8.1 or higher** with the following extensions:
  - `mysqli`
  - `json`
  - `openssl`
  - `mbstring`
  - `curl`
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache** or **Nginx** web server
- **Composer** (optional, for dependency management)

## Installation Steps

### 1. Database Setup

First, create a new database for CSIMS:

```sql
CREATE DATABASE csims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'csims_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON csims.* TO 'csims_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Run Database Migrations

Execute the migration script to create all necessary tables:

```bash
mysql -u csims_user -p csims < src/Database/migrations.sql
```

Or through phpMyAdmin:
1. Open phpMyAdmin
2. Select the `csims` database
3. Go to the "Import" tab
4. Choose `src/Database/migrations.sql`
5. Click "Go"

### 3. Environment Configuration

Create a `.env` file in the root directory:

```bash
# Database Configuration
DB_HOST=localhost
DB_USERNAME=csims_user
DB_PASSWORD=your_secure_password
DB_DATABASE=csims
DB_PORT=3306
DB_CHARSET=utf8mb4

# Application Configuration
APP_NAME="Credit and Savings Information Management System"
APP_VERSION=2.0.0
APP_DEBUG=true
APP_URL=http://localhost/CSIMS

# Security Configuration
APP_KEY=your_32_character_secret_key_here
SESSION_LIFETIME=60
CSRF_TOKEN_LIFETIME=120

# Currency Settings
CURRENCY_CODE=USD
CURRENCY_SYMBOL=$

# Email Configuration (optional)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

### 4. Web Server Configuration

#### Apache (.htaccess)

Create or update `.htaccess` in the root directory:

```apache
RewriteEngine On

# API Routes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api.php [QSA,L]

# Security Headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'"

# Hide sensitive files
<Files ~ "^\.">
    Order allow,deny
    Deny from all
</Files>

<Files ~ "(\.env|composer\.(json|lock)|package\.json|\.sql)$">
    Order allow,deny
    Deny from all
</Files>
```

#### Nginx

Add this location block to your Nginx configuration:

```nginx
location ~ ^/api/(.*)$ {
    try_files $uri /api.php?$query_string;
}

### Development Server (Built-in)

For local development without Apache/Nginx, start PHP's built-in server using the dev router:

```bash
php -S 127.0.0.1:8080 dev-router.php
```

- The dev router forwards all `/api/*` requests to the unified entry `api.php`.
- Access the app at `http://127.0.0.1:8080/` and the API at `http://127.0.0.1:8080/api/...`.
- Ensure `APP_URL` in `.env` reflects your chosen host and port.

location ~ /\. {
    deny all;
}

location ~ \.(env|sql|json|lock)$ {
    deny all;
}
```

### 5. File Permissions

Set appropriate permissions:

```bash
# Make directories writable
chmod 755 logs/
chmod 755 uploads/
chmod 644 .env

# Secure sensitive files
chmod 600 src/Database/migrations.sql
```

## API Testing

### 1. Health Check

Test the API health endpoint:

```bash
curl http://localhost/CSIMS/api/system/health
```

Expected response:
```json
{
    "status": "OK",
    "timestamp": "2024-01-01T12:00:00+00:00",
    "version": "2.0.0"
}
```

### 2. Test Loan Endpoints

Create a test loan:

```bash
curl -X POST http://localhost/CSIMS/api/loans \
  -H "Content-Type: application/json" \
  -d '{
    "member_id": 1,
    "amount": 5000.00,
    "interest_rate": 12.5,
    "term_months": 24,
    "purpose": "Business expansion",
    "csrf_token": "test_token"
  }'
```

Get loans list:

```bash
curl http://localhost/CSIMS/api/loans?page=1&limit=10
```

### 3. Test Contribution Endpoints

Create a test contribution:

```bash
curl -X POST http://localhost/CSIMS/api/contributions \
  -H "Content-Type: application/json" \
  -d '{
    "member_id": 1,
    "amount": 100.00,
    "contribution_type": "Monthly",
    "payment_method": "Cash",
    "csrf_token": "test_token"
  }'
```

## Default Login Credentials

The system creates a default admin user:
- **Username**: `admin`
- **Password**: `admin123`

⚠️ **IMPORTANT**: Change this password immediately after installation!

## API Endpoints Reference

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get current user info

### Loans
- `GET /api/loans` - List loans with filtering and pagination
- `GET /api/loans/{id}` - Get specific loan
- `POST /api/loans` - Create new loan
- `PUT /api/loans/{id}` - Update loan
- `DELETE /api/loans/{id}` - Delete loan
- `POST /api/loans/{id}/approve` - Approve loan
- `POST /api/loans/{id}/reject` - Reject loan
- `POST /api/loans/{id}/disburse` - Disburse loan
- `POST /api/loans/{id}/payment` - Process loan payment
- `GET /api/loans/{id}/schedule` - Get payment schedule
- `GET /api/loans/overdue` - Get overdue loans
- `GET /api/loans/statistics` - Get loan statistics

### Contributions (Legacy)
- `GET /api/contributions` - List contributions
- `GET /api/contributions/{id}` - Get specific contribution
- `POST /api/contributions` - Create new contribution
- `PUT /api/contributions/{id}` - Update contribution
- `DELETE /api/contributions/{id}` - Delete contribution
- `POST /api/contributions/{id}/confirm` - Confirm contribution
- `POST /api/contributions/{id}/reject` - Reject contribution
- `GET /api/contributions/statistics` - Get contribution statistics
- `POST /api/contributions/bulk-import` - Bulk import contributions
- `GET /api/contributions/report` - Generate contribution report
\
Note: These endpoints originate from the legacy system and may not be enabled in the unified Router-based API. Migrate to the new savings-related endpoints or bind legacy controllers as needed.

### Dashboard
- `GET /api/dashboard/stats` - Get dashboard statistics
- `GET /api/dashboard/recent-activities` - Get recent activities

### System
- `GET /api/system/health` - Health check
- `GET /api/system/settings` - Get system settings
- `PUT /api/system/settings` - Update system settings

## Request/Response Format

### Standard Request Format (POST/PUT)

```json
{
  "field1": "value1",
  "field2": "value2",
  "csrf_token": "required_for_state_changes"
}
```

### Standard Response Format

**Success Response:**
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... },
  "pagination": { ... }
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Error Type",
  "message": "Human readable error message",
  "errors": ["Detailed error messages"]
}
```

## Query Parameters

### Pagination
- `page` - Page number (default: 1)
- `limit` - Records per page (default: 10, max: 100)

### Filtering
- `status` - Filter by status
- `member_id` - Filter by member
- `date_from` - Start date filter (YYYY-MM-DD)
- `date_to` - End date filter (YYYY-MM-DD)
- `search` - Search term

### Sorting
- `sort_by` - Field to sort by
- `sort_order` - ASC or DESC (default: DESC)

## Security Features

### 1. Input Validation
All inputs are validated and sanitized using the SecurityService class.

### 2. SQL Injection Protection
All database queries use prepared statements through the QueryBuilder.

### 3. CSRF Protection
State-changing operations require valid CSRF tokens.

### 4. XSS Prevention
All outputs are properly encoded to prevent cross-site scripting.

### 5. Rate Limiting
The SecurityService includes rate limiting capabilities.

## Error Handling

The system uses structured exception handling:

1. **ValidationException** - Input validation errors (HTTP 400)
2. **SecurityException** - Security-related errors (HTTP 403)
3. **DatabaseException** - Database operation errors (HTTP 500)
4. **CSIMSException** - General application errors (HTTP 500)

## Performance Considerations

### 1. Database Indexing
The migration script creates optimal indexes for common queries.

### 2. Query Optimization
The QueryBuilder generates efficient SQL queries with proper JOINs.

### 3. Pagination
All list endpoints support pagination to handle large datasets.

### 4. Caching Ready
The architecture is designed to support caching layers when needed.

## Development vs Production

### Development Mode
Set `APP_DEBUG=true` in `.env` for:
- Detailed error messages
- Stack traces in API responses
- Query logging

### Production Mode
Set `APP_DEBUG=false` in `.env` for:
- Generic error messages
- Security-focused error handling
- Performance optimization

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `.env`
   - Ensure MySQL service is running
   - Verify database exists and user has permissions

2. **API Returns 404**
   - Check web server configuration
   - Verify URL rewrite rules are working
   - Ensure `api.php` exists and is accessible
   - If using PHP's built-in server, start it with `dev-router.php`
   - For Apache/Nginx, confirm `/api/*` routes point to `api.php`

3. **CSRF Token Error**
   - Include valid `csrf_token` in POST/PUT/DELETE requests
   - For testing, you can temporarily disable CSRF validation

4. **Permission Denied**
   - Check file permissions on directories and files
   - Ensure web server can read/write necessary files

### Debugging

Enable debug mode and check:
1. PHP error logs
2. Web server error logs
3. Application logs (if configured)
4. Database query logs

## Migration from Old System

### Data Migration

1. **Export existing data** from the old system
2. **Map data fields** to new schema
3. **Import using bulk endpoints** or direct SQL
4. **Verify data integrity** after migration

### Legacy Integration

The new API can coexist with the old system during migration:

1. Use different URL paths (`/api/` for new, legacy paths for old)
2. Gradually migrate functionality
3. Update frontend to use new endpoints
4. Retire old endpoints once migration is complete

See also: `documentation/LEGACY_API_MIGRATION.md` for endpoint mapping and migration steps from legacy `api/index.php` to unified `api.php` + Router.
See also: `documentation/MIGRATION_CHECKLIST.md` for verification steps to confirm a successful migration.

## Support and Maintenance

### Regular Tasks

1. **Database backups** - Set up automated backups
2. **Log rotation** - Configure log file rotation
3. **Security updates** - Keep PHP and MySQL updated
4. **Performance monitoring** - Monitor API response times
5. **Error monitoring** - Set up error alerting

### Monitoring Endpoints

Use these endpoints for system monitoring:

- `GET /api/system/health` - Basic health check
- `GET /api/dashboard/stats` - System statistics
- Database connection status through any API call

## Next Steps

After successful installation:

1. **Change default passwords**
2. **Configure email settings**
3. **Set up automated backups**
4. **Configure monitoring/alerting**
5. **Train users on new system**
6. **Plan data migration strategy**

For additional support or questions about the refactored CSIMS system, refer to the `REFACTORED_ARCHITECTURE.md` document for detailed technical information.
