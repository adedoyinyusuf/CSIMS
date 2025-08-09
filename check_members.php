<?php
require_once 'includes/db.php';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Get the most recent members (last 10)
    echo "<h2>Recent Members (Last 10 added):</h2>";
    $result = $db->query("SELECT member_id, ippis_no, username, first_name, last_name, email, phone, gender, dob, address, membership_type_id, join_date, status, created_at FROM members ORDER BY created_at DESC LIMIT 10");
    $recentMembers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentMembers[] = $row;
        }
    }
    
    if (empty($recentMembers)) {
        echo "<p>No members found in the database.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>ID</th><th>IPPIS</th><th>Username</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>DOB</th><th>Address</th><th>Type ID</th><th>Join Date</th><th>Status</th><th>Created At</th>";
        echo "</tr>";
        
        foreach ($recentMembers as $member) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($member['member_id']) . "</td>";
            echo "<td>" . htmlspecialchars($member['ippis_no'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['email']) . "</td>";
            echo "<td>" . htmlspecialchars($member['phone'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['gender'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['dob'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['address'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['membership_type_id']) . "</td>";
            echo "<td>" . htmlspecialchars($member['join_date']) . "</td>";
            echo "<td>" . htmlspecialchars($member['status']) . "</td>";
            echo "<td>" . htmlspecialchars($member['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Get total count
    $result = $db->query("SELECT COUNT(*) as total FROM members");
    $total = $result->fetch_assoc();
    echo "<p><strong>Total members in database: " . $total['total'] . "</strong></p>";
    
    // Get members added today
    echo "<h3>Members added today:</h3>";
    $result = $db->query("SELECT member_id, ippis_no, username, first_name, last_name, email, created_at FROM members WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC");
    $todayMembers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $todayMembers[] = $row;
        }
    }
    
    if (empty($todayMembers)) {
        echo "<p>No members added today.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>IPPIS</th><th>Username</th><th>Name</th><th>Email</th><th>Created At</th></tr>";
        foreach ($todayMembers as $member) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($member['member_id']) . "</td>";
            echo "<td>" . htmlspecialchars($member['ippis_no'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['email']) . "</td>";
            echo "<td>" . htmlspecialchars($member['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>