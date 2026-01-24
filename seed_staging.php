<?php
// seed_staging.php
// A Root-level script to initialize the database (bypassing /config/ .htaccess protection)

// Point to the config/database.php file
require_once __DIR__ . '/config/database.php';

// Check connection
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Unknown error'));
}

echo "<h1>Staging Database Seeder</h1>";

// Create Members table
$sql_members = "CREATE TABLE IF NOT EXISTS members (
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
)";

// Create Admins table
$sql_admins = "CREATE TABLE IF NOT EXISTS admins (
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
)";

// Create Membership_Types table
$sql_membership_types = "CREATE TABLE IF NOT EXISTS membership_types (
    membership_type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    duration INT NOT NULL COMMENT 'Duration in months',
    fee DECIMAL(10,2) NOT NULL,
    benefits TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Create Loans table
$sql_loans = "CREATE TABLE IF NOT EXISTS loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    term INT NOT NULL COMMENT 'Term in months',
    purpose TEXT,
    application_date DATE NOT NULL,
    approval_date DATE,
    approved_by INT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Disbursed', 'Paid', 'Running', 'Active', 'Defaulted', 'Overdue') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id)
)";

// Create Notifications table
$sql_notifications = "CREATE TABLE IF NOT EXISTS notifications (
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
)";

// Create Messages table
$sql_messages = "CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_type ENUM('Member', 'Admin') NOT NULL,
    sender_id INT NOT NULL,
    recipient_type ENUM('Member', 'Admin') NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create Announcements table
$sql_announcements = "CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('normal','medium','high') DEFAULT 'normal',
    target_audience ENUM('all','active','expired','expiring') DEFAULT 'all',
    expiry_date DATETIME NULL,
    created_by INT NOT NULL,
    status ENUM('active','archived','draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority_created (priority, created_at),
    FOREIGN KEY (created_by) REFERENCES admins(admin_id)
)";

// Execute queries
$tables = [
    'Members' => $sql_members,
    'Admins' => $sql_admins,
    'Membership_Types' => $sql_membership_types,
    'Loans' => $sql_loans,
    'Notifications' => $sql_notifications,
    'Messages' => $sql_messages,
    'Announcements' => $sql_announcements
];

$success = true;
$errors = [];

foreach ($tables as $table_name => $sql) {
    echo "Creating $table_name... ";
    if ($conn->query($sql) !== TRUE) {
        $success = false;
        $errors[] = "Error creating $table_name table: " . $conn->error;
        echo "<span style='color:red'>FAILED</span><br>";
    } else {
        echo "<span style='color:green'>OK</span><br>";
    }
}

// Insert default admin if none exists
echo "<hr>Checking Admin User... ";
$check_admin = "SELECT COUNT(*) as count FROM admins";
$result = $conn->query($check_admin);
// If table creation failed, result might be false
if ($result) {
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        // Create a default admin (password is 'admin123')
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO admins (username, password, first_name, last_name, email, role) 
                        VALUES ('admin', '$default_password', 'System', 'Administrator', 'admin@csims.com', 'Super Admin')";
        
        if ($conn->query($insert_admin) !== TRUE) {
            $success = false;
            $errors[] = "Error creating default admin: " . $conn->error;
            echo "<span style='color:red'>FAILED to insert admin</span><br>";
        } else {
            echo "<span style='color:green'>Inserted default admin (admin/admin123)</span><br>";
        }
    } else {
        echo "<span style='color:blue'>Admin already exists</span><br>";
    }
} else {
    echo "<span style='color:red'>Could not query admins table</span><br>";
}

// Output results
if ($success) {
    echo "<h2>Initialization COMPLETED!</h2>";
    echo "<p><a href='views/auth/login.php'>Go to Login Page</a></p>";
} else {
    echo "<h2>Initialization Completed with ERRORS:</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

$conn->close();
?>
