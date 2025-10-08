<?php
/**
 * CSIMS Database Schema Analyzer
 * 
 * This script analyzes the current database structure to understand
 * what tables and columns exist before applying migrations
 */

require_once __DIR__ . '/config/database.php';

echo "=== CSIMS Database Schema Analysis ===\n\n";

// Check database connection
if (!$conn) {
    die("Error: Could not connect to database.\n");
}

echo "✓ Database connection successful\n";
echo "Database: " . DB_NAME . "\n\n";

// Get all tables
$tablesResult = $conn->query("SHOW TABLES");
if (!$tablesResult) {
    die("Error fetching tables: " . $conn->error . "\n");
}

$tables = [];
while ($row = $tablesResult->fetch_array()) {
    $tables[] = $row[0];
}

echo "Found " . count($tables) . " tables:\n";
foreach ($tables as $table) {
    echo "- $table\n";
}
echo "\n";

// Analyze key tables structure
$keyTables = ['members', 'admins', 'loans', 'contributions', 'membership_types'];

foreach ($keyTables as $tableName) {
    echo "=== Table: $tableName ===\n";
    
    $tableExists = in_array($tableName, $tables);
    if (!$tableExists) {
        echo "❌ Table '$tableName' does not exist\n\n";
        continue;
    }
    
    // Get table structure
    $structureResult = $conn->query("DESCRIBE $tableName");
    if (!$structureResult) {
        echo "❌ Error describing table: " . $conn->error . "\n\n";
        continue;
    }
    
    echo "Columns:\n";
    while ($column = $structureResult->fetch_assoc()) {
        $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column['Default'] !== null ? " DEFAULT '{$column['Default']}'" : '';
        $extra = $column['Extra'] ? " {$column['Extra']}" : '';
        echo "  - {$column['Field']} {$column['Type']} $null$default$extra\n";
    }
    
    // Get table row count
    $countResult = $conn->query("SELECT COUNT(*) as count FROM $tableName");
    if ($countResult) {
        $count = $countResult->fetch_assoc()['count'];
        echo "Rows: $count\n";
    }
    
    echo "\n";
}

// Check for foreign key relationships
echo "=== Foreign Key Constraints ===\n";
$fkResult = $conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE 
        REFERENCED_TABLE_SCHEMA = '" . DB_NAME . "'
        AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($fkResult && $fkResult->num_rows > 0) {
    while ($fk = $fkResult->fetch_assoc()) {
        echo "- {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
} else {
    echo "No foreign key constraints found\n";
}

echo "\n";

// Check for indexes
echo "=== Indexes ===\n";
foreach (['members', 'admins', 'loans', 'contributions'] as $table) {
    if (!in_array($table, $tables)) continue;
    
    echo "Table: $table\n";
    $indexResult = $conn->query("SHOW INDEX FROM $table");
    if ($indexResult) {
        while ($index = $indexResult->fetch_assoc()) {
            echo "  - {$index['Key_name']} on {$index['Column_name']}\n";
        }
    }
    echo "\n";
}

$conn->close();
echo "Schema analysis completed.\n";
?>
