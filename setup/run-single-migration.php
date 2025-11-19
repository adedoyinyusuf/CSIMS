<?php
// Run a single SQL migration file using simple mysqli connection
require_once __DIR__ . '/../config/database.php';

$pathArg = isset($argv[1]) ? $argv[1] : null;
if ($pathArg && file_exists($pathArg)) {
    $path = $pathArg;
} else {
    $path = __DIR__ . '/../database/migrations/' . ($pathArg ?: '001_create_users_and_sessions.sql');
}
if (!file_exists($path)) {
    fwrite(STDERR, "Migration file not found: $path\n");
    exit(1);
}

$sql = file_get_contents($path);
if ($sql === false) {
    fwrite(STDERR, "Failed to read migration file\n");
    exit(1);
}

// Strip SQL comments safely (both -- line comments and /* */ block comments)
$clean = preg_replace('/--.*$/m', '', $sql);
$clean = preg_replace('/\/\*.*?\*\//s', '', $clean);

$chunks = explode(';', $clean);
$statements = [];
foreach ($chunks as $chunk) {
    $stmt = trim($chunk);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
}

echo "Statements: " . count($statements) . "\n";

$success = true;
$conn->autocommit(false);
foreach ($statements as $statement) {
    if (!$conn->query($statement)) {
        $success = false;
        fwrite(STDERR, "SQL Error: " . $conn->error . "\nStatement: " . $statement . "\n");
        break;
    }
}

if ($success) {
    $conn->commit();
    echo "Migration applied successfully.\n";
} else {
    $conn->rollback();
    echo "Migration failed and rolled back.\n";
}