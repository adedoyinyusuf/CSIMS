<?php
// CSIMS Data Wipe Script
// Usage: php scripts/wipe_database.php --force
// This script TRUNCATEs all tables in the active database, disables FK checks safely,
// and reseeds essential defaults (admin user and membership types) to restore baseline access.

// Ensure CLI-only execution
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

// Confirm intent
$force = in_array('--force', $argv, true) || getenv('WIPE_ALL') === '1';
if (!$force) {
    echo "Safety check: pass --force or set WIPE_ALL=1 to proceed.\n";
    echo "Example: php scripts/wipe_database.php --force\n";
    exit(1);
}

require_once __DIR__ . '/../config/database.php'; // provides $conn and DB_NAME

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "Failed to initialize database connection.\n";
    exit(1);
}

echo "\n=== CSIMS Full Data Wipe ===\n";
echo "Target database: " . DB_NAME . "\n";

// Gather tables
$tables = [];
$result = $conn->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
if (!$result) {
    echo "Error fetching tables: " . $conn->error . "\n";
    exit(1);
}
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    // The first column of SHOW TABLES result is the table name
    $tables[] = $row[0];
}

if (empty($tables)) {
    echo "No base tables found in database '" . DB_NAME . "'. Nothing to wipe.\n";
    exit(0);
}

// Disable foreign key checks
if (!$conn->query('SET FOREIGN_KEY_CHECKS=0')) {
    echo "Warning: could not disable foreign key checks: " . $conn->error . "\n";
}

$errors = [];
$successCount = 0;
echo "\nTruncating tables (" . count($tables) . ")...\n";
foreach ($tables as $table) {
    // Skip MySQL internal/system tables defensively (shouldn't be listed as BASE TABLEs under our DB)
    if (preg_match('/^mysql|^information_schema|^performance_schema|^sys$/i', $table)) {
        continue;
    }
    $sql = "TRUNCATE TABLE `" . $conn->real_escape_string($table) . "`";
    if ($conn->query($sql)) {
        $successCount++;
        echo "✓ Truncated: $table\n";
    } else {
        $errors[] = "Failed to truncate $table: " . $conn->error;
        echo "⚠ Failed: $table — " . $conn->error . "\n";
    }
}

// Re-enable foreign key checks
if (!$conn->query('SET FOREIGN_KEY_CHECKS=1')) {
    echo "Warning: could not re-enable foreign key checks: " . $conn->error . "\n";
}

echo "\nTruncate complete. Succeeded: $successCount; Failed: " . count($errors) . "\n";
if (!empty($errors)) {
    echo "Failures:\n";
    foreach ($errors as $err) { echo " - $err\n"; }
}

// Reseed essential defaults for baseline access
echo "\nReseeding defaults...\n";

// 1) Default admin user
$adminCheck = $conn->query("SELECT COUNT(*) AS c FROM admins");
if ($adminCheck && ($row = $adminCheck->fetch_assoc()) && (int)$row['c'] === 0) {
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $insertAdmin = "INSERT INTO admins (username, password, first_name, last_name, email, role, status) VALUES ('admin', '$defaultPassword', 'System', 'Administrator', 'admin@csims.com', 'Super Admin', 'Active')";
    if ($conn->query($insertAdmin)) {
        echo "✓ Default admin created (username: admin, password: admin123) — change immediately.\n";
    } else {
        echo "⚠ Could not create default admin: " . $conn->error . "\n";
    }
} else {
    echo "• Admins table has existing rows or table missing — skip default admin.\n";
}

// 2) Default membership types
$mtCheck = $conn->query("SELECT COUNT(*) AS c FROM membership_types");
if ($mtCheck && ($row = $mtCheck->fetch_assoc()) && (int)$row['c'] === 0) {
    $stmt = $conn->prepare("INSERT INTO membership_types (name, description, duration, fee, benefits) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $types = [
            ["Basic", "Standard membership with basic benefits", 12, 1000.00, "Access to basic cooperative services"],
            ["Premium", "Enhanced membership with additional benefits", 12, 2000.00, "Access to all cooperative services, priority loan processing"],
            ["Gold", "Premium membership with maximum benefits", 12, 3000.00, "Access to all cooperative services, priority loan processing, reduced interest rates"],
        ];
        $stmt->bind_param('ssids', $name, $description, $duration, $fee, $benefits);
        foreach ($types as $t) {
            [$name, $description, $duration, $fee, $benefits] = $t;
            if (!$stmt->execute()) {
                echo "⚠ Error inserting membership type $name: " . $stmt->error . "\n";
            }
        }
        $stmt->close();
        echo "✓ Default membership types inserted.\n";
    } else {
        echo "⚠ Could not prepare membership_types insert: " . $conn->error . "\n";
    }
} else {
    echo "• membership_types already populated or table missing — skip defaults.\n";
}

// Optional: output table row counts for quick verification
echo "\nPost-wipe table counts:\n";
foreach ($tables as $t) {
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM `" . $conn->real_escape_string($t) . "`");
    if ($res) {
        $r = $res->fetch_assoc();
        echo str_pad($t, 28) . " : " . (int)$r['cnt'] . "\n";
    } else {
        echo str_pad($t, 28) . " : (error) " . $conn->error . "\n";
    }
}

echo "\n=== Wipe Complete. You can now log in with the default admin and re-seed any other data as needed. ===\n";

$conn->close();

?>