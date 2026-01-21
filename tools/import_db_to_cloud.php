<?php
// tools/import_db_to_cloud.php
// Connects to the DB defined in .env and imports csims_production.sql

require_once __DIR__ . '/../config/database.php';

echo "------------------------------------------------\n";
echo "       CSIMS Cloud Database Importer            \n";
echo "------------------------------------------------\n";
echo "Target Host: " . DB_HOST . "\n";
echo "Target DB:   " . DB_NAME . "\n";
echo "------------------------------------------------\n";

echo "WARNING: This will wipe the target database tables and import/append data.\n";
echo "Ensure .env contains your TiDB/Cloud credentials!\n";
echo "Press Ctrl+C to cancel, or wait 5 seconds to proceed...\n";
sleep(5);

// Use constants from config/database.php
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
$conn->set_charset("utf8mb4");

$sqlFile = __DIR__ . '/../docs/csims_production.sql';
if (!file_exists($sqlFile)) {
    die("Error: SQL file not found: $sqlFile\n");
}

echo "Reading SQL file...\n";
$sqlContent = file_get_contents($sqlFile);

// Filter out blank lines and comments for safer splitting
$statements = [];
$lines = explode("\n", $sqlContent);
$buffer = "";
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "" || strpos($line, "--") === 0) continue;
    $buffer .= $line . " ";
    if (substr($line, -1) === ';') {
        $statements[] = $buffer;
        $buffer = "";
    }
}

$total = count($statements);
$success = 0;
$errors = 0;

echo "Found approx $total statements. Executing...\n";

foreach ($statements as $i => $sql) {
    if (empty(trim($sql))) continue;
    try {
        if ($conn->query($sql) === TRUE) {
            $success++;
            if ($success % 50 == 0) echo ".";
        } else {
            // Ignore Duplicate and Table Exists errors
            if ($conn->errno == 1062 || $conn->errno == 1050) { 
                 // echo " (Skipping duplicate/exists) ";
            } else {
                echo "\nError at statement $i: " . $conn->error . "\n";
                // echo "Query: " . substr($sql, 0, 100) . "...\n";
                $errors++;
            }
        }
    } catch (Exception $e) {
        $errors++;
    }
}

echo "\n------------------------------------------------\n";
echo "Import Finished.\n";
echo "Successful: $success\n";
echo "Errors:     $errors\n";
echo "------------------------------------------------\n";

$conn->close();
?>
