<?php
// setup/cloud_import.php
// Runs the SQL import on the server (Render)

// Simple security check (remove this file after use!)
$secret = $_GET['key'] ?? '';
if ($secret !== 'csims_deploy_2026') {
    die("Access Denied. Please provide the correct key.");
}

require_once __DIR__ . '/../config/database.php';

// Turn off buffering
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

echo "<html><body style='font-family:monospace; background:#1e1e1e; color:#0f0; padding:20px;'>";
echo "<h1>CSIMS Cloud Import</h1>";
echo "<p>Connecting to database...</p>";

try {
    // Use connection from config/database.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Global database connection failed.");
    }
    echo "<p>Connected to " . DB_HOST . " (" . DB_NAME . ") via SSL</p>";

    $sqlFile = __DIR__ . '/../docs/csims_production.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at $sqlFile");
    }

    echo "<p>Reading SQL file...</p>";
    $sqlContent = file_get_contents($sqlFile);
    
    // Split statements safely
    $lines = explode("\n", $sqlContent);
    $statements = [];
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

    echo "<p>Found " . count($statements) . " queries. Executing...</p>";
    echo "<div style='height:300px; overflow:auto; border:1px solid #444; padding:10px;'>";

    $success = 0;
    $errors = 0;
    foreach ($statements as $i => $sql) {
        if (trim($sql) == "") continue;
        if ($conn->query($sql) === TRUE) {
            $success++;
            if ($success % 50 == 0) echo ". ";
        } else {
             if ($conn->errno == 1062 || $conn->errno == 1050) {
                 // duplicate/exists - ignore
             } else {
                 echo "<br><span style='color:red'>Error: " . $conn->error . "</span>";
                 $errors++;
             }
        }
    }
    echo "</div>";
    echo "<h2>Import Complete.</h2>";
    echo "<p>Success: $success<br>Errors: $errors</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Fatal Error: " . $e->getMessage() . "</h2>";
}
echo "</body></html>";
?>
