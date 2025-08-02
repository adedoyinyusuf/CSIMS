<?php
require_once 'config/database.php';

try {
    $sql = "ALTER TABLE members MODIFY COLUMN status ENUM('Pending', 'Active', 'Inactive', 'Suspended', 'Expired') DEFAULT 'Active'";
    
    if ($conn->query($sql) === TRUE) {
        echo "Database schema updated successfully! 'Pending' status has been added to members table.";
    } else {
        echo "Error updating schema: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>