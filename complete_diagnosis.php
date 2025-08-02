<?php
require_once 'config/database.php';

echo "<h2>Complete Database Diagnosis</h2>";

// 1. Check current schema
echo "<h3>1. Current Members Table Schema</h3>";
$schema_sql = "SHOW COLUMNS FROM members LIKE 'status'";
$schema_result = $conn->query($schema_sql);
if ($schema_result && $schema_result->num_rows > 0) {
    $schema_row = $schema_result->fetch_assoc();
    echo "Status column type: " . $schema_row['Type'] . "<br>";
    echo "Default value: " . $schema_row['Default'] . "<br>";
} else {
    echo "Could not retrieve schema information.<br>";
}

// 2. Check all members and their status
echo "<h3>2. All Members with Status</h3>";
$all_sql = "SELECT member_id, first_name, last_name, email, status, join_date FROM members ORDER BY member_id DESC";
$all_result = $conn->query($all_sql);
if ($all_result && $all_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Join Date</th></tr>";
    while ($row = $all_result->fetch_assoc()) {
        $status_display = $row['status'] === null ? 'NULL' : $row['status'];
        echo "<tr><td>{$row['member_id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td>{$row['email']}</td><td><strong>{$status_display}</strong></td><td>{$row['join_date']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "No members found.<br>";
}

// 3. Try to manually update NULL status to Pending
echo "<h3>3. Manual Status Update</h3>";
$update_sql = "UPDATE members SET status = 'Pending' WHERE status IS NULL OR status = ''";
if ($conn->query($update_sql) === TRUE) {
    $affected = $conn->affected_rows;
    echo "Updated {$affected} members from NULL/empty to 'Pending'.<br>";
} else {
    echo "Error updating: " . $conn->error . "<br>";
}

// 4. Check pending members after update
echo "<h3>4. Pending Members After Update</h3>";
$pending_sql = "SELECT member_id, first_name, last_name, email, status FROM members WHERE status = 'Pending'";
$pending_result = $conn->query($pending_sql);
if ($pending_result && $pending_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr>";
    while ($row = $pending_result->fetch_assoc()) {
        echo "<tr><td>{$row['member_id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td>{$row['email']}</td><td>{$row['status']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "No pending members found.<br>";
}

// 5. Test the getPendingMembers method
echo "<h3>5. Testing getPendingMembers Method</h3>";
require_once 'controllers/member_controller.php';
$memberController = new MemberController();
$pendingMembers = $memberController->getPendingMembers();
echo "getPendingMembers() returned " . count($pendingMembers) . " members:<br>";
if (!empty($pendingMembers)) {
    foreach ($pendingMembers as $member) {
        echo "- {$member['first_name']} {$member['last_name']} ({$member['email']}) - Status: {$member['status']}<br>";
    }
} else {
    echo "No pending members returned by the method.<br>";
}

$conn->close();
?>