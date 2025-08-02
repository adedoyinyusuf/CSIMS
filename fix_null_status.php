<?php
require_once 'config/database.php';

try {
    // First, let's see how many members have NULL status
    $check_sql = "SELECT COUNT(*) as null_count FROM members WHERE status IS NULL";
    $result = $conn->query($check_sql);
    $row = $result->fetch_assoc();
    echo "Found {$row['null_count']} members with NULL status.<br>";
    
    // Update NULL status values to 'Pending'
    $update_sql = "UPDATE members SET status = 'Pending' WHERE status IS NULL";
    
    if ($conn->query($update_sql) === TRUE) {
        $affected_rows = $conn->affected_rows;
        echo "Successfully updated {$affected_rows} members to 'Pending' status.<br>";
        
        // Verify the update
        $verify_sql = "SELECT COUNT(*) as pending_count FROM members WHERE status = 'Pending'";
        $verify_result = $conn->query($verify_sql);
        $verify_row = $verify_result->fetch_assoc();
        echo "Total members with 'Pending' status: {$verify_row['pending_count']}";
    } else {
        echo "Error updating member status: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>