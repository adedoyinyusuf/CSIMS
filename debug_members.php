<?php
require_once 'config/database.php';

// Check if the status column has been updated
echo "<h2>Checking Members Table Schema</h2>";
$result = $conn->query("SHOW COLUMNS FROM members LIKE 'status'");
if ($row = $result->fetch_assoc()) {
    echo "<p>Status column type: " . $row['Type'] . "</p>";
    echo "<p>Default value: " . $row['Default'] . "</p>";
} else {
    echo "<p>Status column not found!</p>";
}

echo "<h2>All Members in Database</h2>";
$result = $conn->query("SELECT member_id, first_name, last_name, email, status, created_at FROM members ORDER BY member_id DESC LIMIT 10");
if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['member_id'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . ($row['created_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No members found in database.</p>";
}

echo "<h2>Pending Members Only</h2>";
$result = $conn->query("SELECT member_id, first_name, last_name, email, status FROM members WHERE status = 'Pending'");
if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['member_id'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No pending members found.</p>";
}

echo "<h2>Status Count</h2>";
$result = $conn->query("SELECT status, COUNT(*) as count FROM members GROUP BY status");
if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No status data found.</p>";
}

$conn->close();
?>