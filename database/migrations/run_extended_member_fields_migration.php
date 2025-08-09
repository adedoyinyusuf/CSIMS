<?php
require_once __DIR__ . '/../../config/database.php';

echo "Running extended member fields migration...\n";

try {
    // Check if columns already exist to avoid errors
    $check_queries = [
        "SHOW COLUMNS FROM members LIKE 'middle_name'",
        "SHOW COLUMNS FROM members LIKE 'employee_rank'",
        "SHOW COLUMNS FROM members LIKE 'bank_name'",
        "SHOW COLUMNS FROM members LIKE 'next_of_kin_name'"
    ];
    
    $columns_exist = [];
    foreach ($check_queries as $query) {
        $result = $conn->query($query);
        $columns_exist[] = $result->num_rows > 0;
    }
    
    if (all($columns_exist)) {
        echo "All extended member fields already exist. Migration skipped.\n";
        exit(0);
    }
    
    // Define the migration statements without IF NOT EXISTS
    $statements = [
        // Personal information fields
        "ALTER TABLE members ADD COLUMN middle_name VARCHAR(50) AFTER first_name",
        "ALTER TABLE members ADD COLUMN marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Other') AFTER gender",
        "ALTER TABLE members ADD COLUMN highest_qualification VARCHAR(100) AFTER occupation",
        "ALTER TABLE members ADD COLUMN years_of_residence INT AFTER address",
        
        // Employment information fields
        "ALTER TABLE members ADD COLUMN employee_rank VARCHAR(50) AFTER occupation",
        "ALTER TABLE members ADD COLUMN grade_level VARCHAR(20) AFTER employee_rank",
        "ALTER TABLE members ADD COLUMN position VARCHAR(100) AFTER grade_level",
        "ALTER TABLE members ADD COLUMN department VARCHAR(100) AFTER position",
        "ALTER TABLE members ADD COLUMN date_of_first_appointment DATE AFTER department",
        "ALTER TABLE members ADD COLUMN date_of_retirement DATE AFTER date_of_first_appointment",
        
        // Banking information fields
        "ALTER TABLE members ADD COLUMN bank_name VARCHAR(100) AFTER phone",
        "ALTER TABLE members ADD COLUMN account_number VARCHAR(20) AFTER bank_name",
        "ALTER TABLE members ADD COLUMN account_name VARCHAR(100) AFTER account_number",
        
        // Next of kin information fields
        "ALTER TABLE members ADD COLUMN next_of_kin_name VARCHAR(100) AFTER account_name",
        "ALTER TABLE members ADD COLUMN next_of_kin_relationship VARCHAR(50) AFTER next_of_kin_name",
        "ALTER TABLE members ADD COLUMN next_of_kin_phone VARCHAR(20) AFTER next_of_kin_relationship",
        "ALTER TABLE members ADD COLUMN next_of_kin_address TEXT AFTER next_of_kin_phone",
        
        // Add indexes for better performance
        "ALTER TABLE members ADD INDEX idx_employee_rank (employee_rank)",
        "ALTER TABLE members ADD INDEX idx_department (department)",
        "ALTER TABLE members ADD INDEX idx_marital_status (marital_status)"
    ];
    
    echo "Found " . count($statements) . " SQL statements to execute.\n\n";
    
    $executed = 0;
    foreach ($statements as $statement) {
        // Check if column already exists before adding
        if (strpos($statement, 'ADD COLUMN') !== false) {
            preg_match('/ADD COLUMN (\w+)/', $statement, $matches);
            if (isset($matches[1])) {
                $column_name = $matches[1];
                $check_result = $conn->query("SHOW COLUMNS FROM members LIKE '$column_name'");
                if ($check_result->num_rows > 0) {
                    echo "⚠ Column '$column_name' already exists, skipping...\n";
                    continue;
                }
            }
        }
        
        // Check if index already exists before adding
        if (strpos($statement, 'ADD INDEX') !== false) {
            preg_match('/ADD INDEX (\w+)/', $statement, $matches);
            if (isset($matches[1])) {
                $index_name = $matches[1];
                $check_result = $conn->query("SHOW INDEX FROM members WHERE Key_name = '$index_name'");
                if ($check_result->num_rows > 0) {
                    echo "⚠ Index '$index_name' already exists, skipping...\n";
                    continue;
                }
            }
        }
        
        if ($conn->query($statement)) {
            $executed++;
            echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
        } else {
            // Don't fail on duplicate column errors
            if (strpos($conn->error, 'Duplicate column name') !== false || 
                strpos($conn->error, 'Duplicate key name') !== false) {
                echo "⚠ Skipped (already exists): " . substr($statement, 0, 60) . "...\n";
            } else {
                throw new Exception("Error executing statement: " . $conn->error . "\nStatement: $statement");
            }
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Executed $executed SQL statements.\n";
    echo "Extended member fields have been added to the database.\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

function all($array) {
    foreach ($array as $item) {
        if (!$item) return false;
    }
    return true;
}

$conn->close();
?>