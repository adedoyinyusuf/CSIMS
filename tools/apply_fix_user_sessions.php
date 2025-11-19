<?php
require_once __DIR__ . '/../src/bootstrap.php';

$container = \CSIMS\bootstrap();
$mysqli = $container->resolve(\mysqli::class);

header('Content-Type: text/plain');

echo "Applying user_sessions schema fix...\n\n";

function execStmt(mysqli $db, string $sql): bool {
    echo "SQL: $sql\n";
    $ok = $db->query($sql);
    if (!$ok) {
        echo "  Error: " . $db->error . "\n";
    } else {
        echo "  ✓ Success\n";
    }
    echo "\n";
    return (bool)$ok;
}

// Detect current columns
$cols = [];
$res = $mysqli->query("DESCRIBE user_sessions");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cols[$row['Field']] = $row;
    }
} else {
    echo "Failed to DESCRIBE user_sessions: " . $mysqli->error . "\n";
    exit(1);
}

echo "Current columns: " . implode(', ', array_keys($cols)) . "\n\n";

$mysqli->autocommit(false);
$ok = true;

// 1) Drop legacy FK to admins if present
$fkName = null;
$showCreate = $mysqli->query("SHOW CREATE TABLE user_sessions");
if ($showCreate) {
    $row = $showCreate->fetch_assoc();
    $createSql = $row['Create Table'] ?? '';
    if (strpos($createSql, 'CONSTRAINT `user_sessions_ibfk_1`') !== false) {
        $fkName = 'user_sessions_ibfk_1';
    }
}
if ($fkName) {
    $ok = execStmt($mysqli, "ALTER TABLE user_sessions DROP FOREIGN KEY $fkName") && $ok;
}

// 2) Rename admin_id -> user_id if needed
if (isset($cols['admin_id']) && !isset($cols['user_id'])) {
    $ok = execStmt($mysqli, "ALTER TABLE user_sessions CHANGE admin_id user_id INT(11) NOT NULL") && $ok;
}

// Refresh columns map
$cols = [];
$res = $mysqli->query("DESCRIBE user_sessions");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cols[$row['Field']] = $row;
    }
}

// 3) Rename login_time -> created_at
if (isset($cols['login_time']) && !isset($cols['created_at'])) {
    $ok = execStmt($mysqli, "ALTER TABLE user_sessions CHANGE login_time created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP") && $ok;
}

// 4) Add expires_at if missing and backfill
if (!isset($cols['expires_at'])) {
    $ok = execStmt($mysqli, "ALTER TABLE user_sessions ADD COLUMN expires_at DATETIME NOT NULL AFTER created_at") && $ok;
    if ($ok) {
        // Backfill expires_at to created_at + 3600s
        $ok = execStmt($mysqli, "UPDATE user_sessions SET expires_at = DATE_ADD(created_at, INTERVAL 3600 SECOND) WHERE expires_at IS NULL") && $ok;
    }
}

// 5) Ensure index on user_id
$ok = execStmt($mysqli, "ALTER TABLE user_sessions ADD INDEX idx_user_sessions_user_id (user_id)") && $ok;

// 6) Add FK to users(user_id)
$ok = execStmt($mysqli, "ALTER TABLE user_sessions ADD CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE") && $ok;

if ($ok) {
    $mysqli->commit();
    echo "\nSchema fix applied successfully.\n";
} else {
    $mysqli->rollback();
    echo "\nSchema fix encountered errors and was rolled back.\n";
    exit(1);
}