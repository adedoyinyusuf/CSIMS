# CSIMS Technical Documentation

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Database Design](#database-design)
3. [File Structure](#file-structure)
4. [Core Components](#core-components)
5. [Security Implementation](#security-implementation)
6. [API Endpoints](#api-endpoints)
7. [Development Guidelines](#development-guidelines)
8. [Deployment](#deployment)

## System Architecture

### Technology Stack
- **Backend**: PHP 8.2+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Server**: Apache/Nginx
- **Dependencies**: Composer, PHPMailer

### Architecture Pattern
- **MVC Pattern**: Model-View-Controller architecture
- **Separation of Concerns**: Clear separation between business logic, data access, and presentation
- **Modular Design**: Component-based structure for maintainability

### System Components
```
CSIMS/
├── config/          # Configuration files
├── controllers/     # Business logic controllers
├── views/          # User interface templates
├── includes/       # Utility functions and services
├── assets/         # Static resources (CSS, JS, images)
├── database/       # Database schemas and migrations
├── logs/           # System logs
└── vendor/         # Third-party dependencies
```

## Database Design

### Core Tables

#### Members Table
```sql
CREATE TABLE members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    ippis_no VARCHAR(6) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE,
    occupation VARCHAR(100),
    photo VARCHAR(255),
    membership_type_id INT NOT NULL,
    join_date DATE NOT NULL DEFAULT CURRENT_DATE,
    expiry_date DATE,
    status ENUM('Active', 'Inactive', 'Suspended', 'Expired') DEFAULT 'Active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Admins Table
```sql
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('Super Admin', 'Admin', 'Staff') DEFAULT 'Admin',
    last_login DATETIME,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Financial Tables

**Contributions**
```sql
CREATE TABLE contributions (
    contribution_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    contribution_date DATE NOT NULL,
    contribution_type ENUM('Dues', 'Investment', 'Other') NOT NULL,
    description TEXT,
    received_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (received_by) REFERENCES admins(admin_id)
);
```

**Loans**
```sql
CREATE TABLE loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    term INT NOT NULL COMMENT 'Term in months',
    purpose TEXT,
    application_date DATE NOT NULL,
    approval_date DATE,
    approved_by INT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Disbursed', 'Paid') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id)
);
```

#### Communication Tables

**Notifications**
```sql
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    recipient_type ENUM('All', 'Member', 'Admin') NOT NULL,
    recipient_id INT COMMENT 'NULL if recipient_type is All',
    notification_type ENUM('Payment', 'Meeting', 'Policy', 'General') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(admin_id)
);
```

**Messages**
```sql
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_type ENUM('Member', 'Admin') NOT NULL,
    sender_id INT NOT NULL,
    recipient_type ENUM('Member', 'Admin') NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Database Relationships
- **One-to-Many**: Members → Contributions, Loans
- **Many-to-One**: Contributions → Members, Admins
- **Foreign Keys**: Maintain referential integrity
- **Indexes**: Optimized for common queries

## File Structure

### Configuration Files
```
config/
├── config.php              # Main configuration
├── database.php            # Database connection
├── auth_check.php          # Authentication middleware
├── init_db.php            # Database initialization
└── notification_config.php # Notification settings
```

### Controllers
```
controllers/
├── auth_controller.php           # Authentication logic
├── member_controller.php         # Member management
├── contribution_controller.php   # Financial contributions
├── loan_controller.php          # Loan processing
├── investment_controller.php    # Investment management
├── message_controller.php       # Messaging system
├── notification_controller.php  # Notification system
├── report_controller.php        # Report generation
└── security_controller.php      # Security features
```

### Views Structure
```
views/
├── admin/                  # Admin interface
│   ├── dashboard.php
│   ├── members.php
│   ├── contributions.php
│   ├── loans.php
│   ├── messages.php
│   └── notifications.php
├── auth/                   # Authentication pages
├── includes/              # Shared components
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
└── member_*.php           # Member interface
```

## Core Components

### Authentication System

#### Session Management
```php
// Session configuration
session_start();
session_regenerate_id(true);

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
```

#### Password Security
```php
// Password hashing
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Password verification
if (password_verify($password, $hashed_password)) {
    // Authentication successful
}
```

### Database Connection
```php
class Database {
    private $host = DB_HOST;
    private $username = DB_USERNAME;
    private $password = DB_PASSWORD;
    private $database = DB_NAME;
    private $conn;
    
    public function connect() {
        $this->conn = new mysqli(
            $this->host, 
            $this->username, 
            $this->password, 
            $this->database
        );
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        
        return $this->conn;
    }
}
```

### Controller Pattern
```php
class MemberController {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    public function getAllMembers($limit = 10, $offset = 0) {
        $sql = "SELECT * FROM members LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
```

## Security Implementation

### Input Validation
```php
// Sanitize input
$input = filter_var($input, FILTER_SANITIZE_STRING);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception("Invalid email format");
}

// Prepared statements
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
```

### CSRF Protection
```php
// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validate CSRF token
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('CSRF token mismatch');
}
```

### SQL Injection Prevention
- Use prepared statements exclusively
- Validate and sanitize all inputs
- Implement proper error handling
- Use parameterized queries

### XSS Prevention
```php
// Output escaping
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'");
```

## API Endpoints

### Authentication Endpoints
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get current authenticated user

### Member Management
- `GET /api/members` - List members
- `POST /api/members` - Create member
- `PUT /api/members/{id}` - Update member
- `DELETE /api/members/{id}` - Delete member

### Financial Operations
- `GET /api/contributions` - List contributions (legacy)
- `POST /api/contributions` - Add contribution (legacy)
- `GET /api/loans` - List loans
- `POST /api/loans` - Create loan application

### Communication
- `GET /api/messages` - List messages
- `POST /api/messages` - Send message
- `GET /api/notifications` - List notifications
- `POST /api/notifications` - Create notification

## Development Guidelines

### Coding Standards
- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Implement proper error handling
- Write comprehensive comments
- Use consistent indentation (4 spaces)

### Database Guidelines
- Use prepared statements
- Implement proper indexing
- Follow normalization principles
- Use appropriate data types
- Implement foreign key constraints

### Security Best Practices
- Validate all inputs
- Use HTTPS in production
- Implement proper session management
- Regular security audits
- Keep dependencies updated

### Testing
- Unit testing for controllers
- Integration testing for APIs
- User acceptance testing
- Security testing
- Performance testing

## Deployment

### Server Requirements
- PHP 8.2 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- SSL certificate for HTTPS
- Sufficient disk space and memory

### Deployment Steps
1. Clone repository to server
2. Configure database connection
3. Set up virtual host
4. Install dependencies via Composer
5. Run database migrations
6. Configure file permissions
7. Set up SSL certificate
8. Configure backup procedures

### Environment Configuration
```php
// Production settings
define('DB_HOST', 'production-db-host');
define('DB_NAME', 'csims_production');
define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production');
```

### Monitoring and Maintenance
- Regular database backups
- Log file monitoring
- Performance monitoring
- Security updates
- Regular system maintenance

---

*This technical documentation is maintained by the development team and updated with each system release.*

#### Loan Interest Rate Calculation

The interest rate for loans is now dynamically determined based on the loan amount and duration (term in months). The logic is implemented in the `LoanController::getInterestRate` method in `controllers/loan_controller.php`. The system applies tiered rates as follows:

- Up to 50,000 and up to 12 months: 5.0%
- Up to 50,000 and more than 12 months: 6.0%
- Above 50,000 and up to 24 months: 7.0%
- Above 50,000 and more than 24 months: 8.0%

This ensures fair and scalable interest rates for different loan scenarios.

#### Loan Application Fields

The following fields are now included in the loan application process:
- `savings`: Member's savings amount
- `month_deduction_started`: Month deduction starts (YYYY-MM)
- `month_deduction_should_end`: Month deduction ends (YYYY-MM)
- `other_payment_plans`: Description of other payment plans
- `remarks`: Additional remarks

These fields are available in both the API and the user interface.

## Updated Architecture (Router + DI)

### Modern File Structure
```
CSIMS/
├── api.php                  # Unified API entry point
├── dev-router.php           # Dev server router forwarding /api/* to api.php
├── src/
│   ├── API/Router.php       # Central API router
│   ├── bootstrap.php        # Dependency Injection container bindings
│   ├── Services/            # Business services (AuthService, LoanService, etc.)
│   ├── Repositories/        # Data access layers
│   └── Controllers/         # HTTP controllers (when applicable)
└── documentation/           # Docs (API, technical, etc.)
```

### Unified API Entry and Routing
- All API requests are served through `api.php`, which bootstraps the app and dispatches via `CSIMS\API\Router`.
- During development, start the built-in server with `dev-router.php` to route `/api/*` to `api.php`.
- Web servers (Apache/Nginx) should rewrite `/api/*` requests to `api.php`.

### Dependency Injection (DI) Container
- `src/bootstrap.php` registers and binds shared services using a simple `Container`.
- Key bindings include `ConfigurationManager` (singleton), `SecurityService`, repositories, and `AuthService`.
- This enables controllers and the `Router` to resolve dependencies consistently across the application.

### Router-Managed Endpoints
- Authentication: `/api/auth/login`, `/api/auth/logout`, `/api/auth/me`.
- Members: `/api/members`, `/api/members/{id}`, `/api/members/search`, `/api/members/{id}/summary`.
- Loans: `/api/loans`, `/api/loans/{id}` and related operations.
- System: `/api/system/health` for health checks.

### Legacy Endpoints
- Contribution endpoints under `/api/contributions/*` are considered legacy and may be disabled.
- Migrate usage to new savings-related endpoints or update bindings if legacy controllers are required.