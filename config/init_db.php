<?php
require_once 'database.php';

// Create Members table
$sql_members = "CREATE TABLE IF NOT EXISTS members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
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

// Create Contributions table
$sql_contributions = "CREATE TABLE IF NOT EXISTS contributions (
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
    status ENUM('Pending', 'Approved', 'Rejected', 'Disbursed', 'Paid') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id)
)";

// Create Investments table
$sql_investments = "CREATE TABLE IF NOT EXISTS investments (
    investment_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    investment_date DATE NOT NULL,
    investment_type ENUM('Debenture', 'Asset', 'Stock', 'Other') NOT NULL,
    expected_return DECIMAL(10,2),
    maturity_date DATE,
    status ENUM('Active', 'Matured', 'Liquidated') DEFAULT 'Active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(admin_id)
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

// Execute queries
$tables = [
    'Members' => $sql_members,
    'Admins' => $sql_admins,
    'Membership_Types' => $sql_membership_types,
    'Contributions' => $sql_contributions,
    'Loans' => $sql_loans,
    'Investments' => $sql_investments,
    'Notifications' => $sql_notifications,
    'Messages' => $sql_messages
];

$success = true;
$errors = [];

foreach ($tables as $table_name => $sql) {
    if ($conn->query($sql) !== TRUE) {
        $success = false;
        $errors[] = "Error creating $table_name table: " . $conn->error;
    }
}

// Insert default admin if none exists
$check_admin = "SELECT COUNT(*) as count FROM admins";
$result = $conn->query($check_admin);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Create a default admin (password is 'admin123' - should be changed immediately)
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO admins (username, password, first_name, last_name, email, role) 
                    VALUES ('admin', '$default_password', 'System', 'Administrator', 'admin@csims.com', 'Super Admin')";
    
    if ($conn->query($insert_admin) !== TRUE) {
        $success = false;
        $errors[] = "Error creating default admin: " . $conn->error;
    }
}

// Insert default membership types if none exist
$check_membership = "SELECT COUNT(*) as count FROM membership_types";
$result = $conn->query($check_membership);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Create default membership types
    $membership_types = [
        ["Basic", "Standard membership with basic benefits", 12, 1000.00, "Access to basic cooperative services"],
        ["Premium", "Enhanced membership with additional benefits", 12, 2000.00, "Access to all cooperative services, priority loan processing"],
        ["Gold", "Premium membership with maximum benefits", 12, 3000.00, "Access to all cooperative services, priority loan processing, reduced interest rates"]
    ];
    
    $insert_membership = "INSERT INTO membership_types (name, description, duration, fee, benefits) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_membership);
    
    if ($stmt) {
        $stmt->bind_param("ssids", $name, $description, $duration, $fee, $benefits);
        
        foreach ($membership_types as $type) {
            $name = $type[0];
            $description = $type[1];
            $duration = $type[2];
            $fee = $type[3];
            $benefits = $type[4];
            
            if (!$stmt->execute()) {
                $success = false;
                $errors[] = "Error creating membership type $name: " . $stmt->error;
            }
        }
        
        $stmt->close();
    } else {
        $success = false;
        $errors[] = "Error preparing membership types statement: " . $conn->error;
    }
}

// Output results
if ($success) {
    echo "<h2>Database initialization completed successfully!</h2>";
    echo "<p>All tables have been created and default data has been inserted.</p>";
} else {
    echo "<h2>Database initialization completed with errors:</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

$conn->close();
?>