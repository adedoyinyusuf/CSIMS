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
        echo "Baseline extended member columns already exist. Continuing to apply additional migrations...\n";
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
        "ALTER TABLE members ADD INDEX idx_marital_status (marital_status)",
        
        // Member type normalization: create table, add columns, seed, backfill, and add FK
        "CREATE TABLE member_types (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE members ADD COLUMN member_type VARCHAR(100) AFTER occupation",
        "ALTER TABLE members ADD COLUMN member_type_id INT UNSIGNED NULL AFTER member_type",
        "ALTER TABLE members ADD INDEX idx_member_type (member_type)",
        "ALTER TABLE members ADD INDEX idx_member_type_id (member_type_id)",
        "INSERT IGNORE INTO member_types (name) SELECT DISTINCT TRIM(member_type) FROM members WHERE member_type IS NOT NULL AND TRIM(member_type) <> ''",
        "UPDATE members m JOIN member_types mt ON mt.name = m.member_type SET m.member_type_id = mt.id WHERE m.member_type_id IS NULL",
        "ALTER TABLE members ADD CONSTRAINT fk_members_member_type_id FOREIGN KEY (member_type_id) REFERENCES member_types(id) ON UPDATE CASCADE ON DELETE SET NULL"
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

        // Check if table already exists before creating
        if (stripos($statement, 'CREATE TABLE member_types') !== false) {
            $check_result = $conn->query("SHOW TABLES LIKE 'member_types'");
            if ($check_result && $check_result->num_rows > 0) {
                echo "⚠ Table 'member_types' already exists, skipping...\n";
                continue;
            }
        }

        // Skip seed/backfill statements if expected column 'name' doesn't exist; we'll handle dynamically later
        if (stripos($statement, "INSERT IGNORE INTO member_types (name)") !== false ||
            stripos($statement, "JOIN member_types mt ON mt.name = m.member_type") !== false) {
            $check_result = $conn->query("SHOW COLUMNS FROM member_types LIKE 'name'");
            if (!$check_result || $check_result->num_rows === 0) {
                echo "⚠ 'member_types.name' column not found; deferring seed/backfill to dynamic step...\n";
                continue;
            }
        }

        // Check if foreign key constraint already exists before adding
        if (stripos($statement, 'ADD CONSTRAINT fk_members_member_type_id') !== false) {
            // If constraint exists, skip
            $check_result = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND CONSTRAINT_NAME = 'fk_members_member_type_id'");
            if ($check_result && $check_result->num_rows > 0) {
                echo "⚠ Constraint 'fk_members_member_type_id' already exists, skipping...\n";
                continue;
            }
            // Pre-validate referenced column and types/index
            $mtIdCol = $conn->query("SHOW COLUMNS FROM member_types LIKE 'id'");
            $mMtIdCol = $conn->query("SHOW COLUMNS FROM members LIKE 'member_type_id'");
            if (!$mtIdCol || $mtIdCol->num_rows === 0 || !$mMtIdCol || $mMtIdCol->num_rows === 0) {
                echo "⚠ FK pre-check failed: required columns missing; skipping FK addition.\n";
                continue;
            }
            $mtIndexes = $conn->query("SHOW INDEX FROM member_types WHERE Column_name = 'id'");
            if (!$mtIndexes || $mtIndexes->num_rows === 0) {
                echo "⚠ FK pre-check failed: 'member_types.id' not indexed; skipping FK addition.\n";
                continue;
            }
            // Check type compatibility (unsigned vs signed)
            $mtId = $mtIdCol->fetch_assoc();
            $mMtId = $mMtIdCol->fetch_assoc();
            $mtType = strtolower($mtId['Type']);
            $mMtType = strtolower($mMtId['Type']);
            $mtUnsigned = strpos($mtType, 'unsigned') !== false;
            $mMtUnsigned = strpos($mMtType, 'unsigned') !== false;
            $mtBaseType = preg_replace('/[^a-z]/', '', $mtType);
            $mMtBaseType = preg_replace('/[^a-z]/', '', $mMtType);
            if ($mtBaseType !== $mMtBaseType || $mtUnsigned !== $mMtUnsigned) {
                echo "⚠ FK pre-check failed: type mismatch between members.member_type_id ($mMtType) and member_types.id ($mtType); skipping FK addition.\n";
                continue;
            }
        }

        if ($conn->query($statement)) {
            $executed++;
            echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
        } else {
            // Don't fail on duplicate/exists errors
            if (strpos($conn->error, 'Duplicate column name') !== false ||
                strpos($conn->error, 'Duplicate key name') !== false ||
                strpos($conn->error, "Table 'member_types' already exists") !== false ||
                strpos($conn->error, 'Cannot add foreign key constraint') !== false ||
                strpos($conn->error, 'Duplicate constraint name') !== false ||
                strpos($conn->error, 'Foreign key constraint is incorrectly formed') !== false) {
                echo "⚠ Skipped (already exists or cannot add FK due to current state): " . substr($statement, 0, 60) . "...\n";
            } else {
                throw new Exception("Error executing statement: " . $conn->error . "\nStatement: $statement");
            }
        }
    }

    // Dynamic seed/backfill step for member_types
    echo "\nSeeding member_types and backfilling members.member_type_id (dynamic)...\n";
    $mtCols = [];
    if ($res = $conn->query("SHOW COLUMNS FROM member_types")) {
        while ($row = $res->fetch_assoc()) { $mtCols[] = $row['Field']; }
    }
    $candidateCols = ['name','type_name','member_type','member_type_name','type'];
    $mtNameCol = null;
    foreach ($candidateCols as $c) {
        if (in_array($c, $mtCols)) { $mtNameCol = $c; break; }
    }

    // Detect primary key/ID column in member_types
    $mtPkCol = null;
    $pkIdx = $conn->query("SHOW INDEX FROM member_types WHERE Key_name = 'PRIMARY'");
    if ($pkIdx && $pkIdx->num_rows > 0) {
        $pkRow = $pkIdx->fetch_assoc();
        $mtPkCol = $pkRow['Column_name'];
        echo "Detected PRIMARY KEY column in member_types: '$mtPkCol'\n";
    } else {
        $colsRes = $conn->query("SHOW COLUMNS FROM member_types");
        if ($colsRes) {
            while ($r = $colsRes->fetch_assoc()) {
                if (stripos($r['Extra'], 'auto_increment') !== false) {
                    $mtPkCol = $r['Field'];
                    echo "Detected AUTO_INCREMENT column in member_types: '$mtPkCol'\n";
                    break;
                }
            }
        }
    }

    // Ensure 'id' column exists only if no PK/auto-inc detected
    if (!$mtPkCol) {
        echo "Attempting to add 'id' column to member_types...\n";
        // Try to add id with a key in a single ALTER TABLE to satisfy AUTO_INCREMENT requirements
        $alterWithKey = "ALTER TABLE member_types ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT, ADD UNIQUE KEY uk_member_types_id (id)";
        if ($conn->query($alterWithKey)) {
            echo "✓ Added 'id' column with UNIQUE key.\n";
            $mtPkCol = 'id';
            $mtCols[] = 'id';
        } else {
            if (strpos($conn->error, 'Duplicate column name') !== false) {
                echo "⚠ 'id' column already exists.\n";
                $mtPkCol = 'id';
            } elseif (strpos($conn->error, 'Incorrect table definition; there can be only one auto column') !== false) {
                echo "⚠ Table already has an AUTO_INCREMENT column; will not add another.\n";
            } else {
                echo "⚠ Could not add 'id' column with key: " . $conn->error . "\n";
            }
        }
    }

    if (!$mtNameCol) {
        if ($conn->query("ALTER TABLE member_types ADD COLUMN name VARCHAR(100) NOT NULL UNIQUE")) {
            echo "✓ Added 'name' column to member_types.\n";
            $mtNameCol = 'name';
        } else {
            echo "⚠ Could not add 'name' column to member_types: " . $conn->error . "\n";
        }
    }
    if ($mtNameCol) {
        $seedSql = "INSERT IGNORE INTO member_types ($mtNameCol) SELECT DISTINCT TRIM(member_type) FROM members WHERE member_type IS NOT NULL AND TRIM(member_type) <> ''";
        if ($conn->query($seedSql)) {
            echo "✓ Seeded member_types using column '$mtNameCol'.\n";
        } else {
            echo "⚠ Seed skipped: " . $conn->error . "\n";
        }
        // Backfill using detected PK column
        if ($mtPkCol) {
            $backfillSql = "UPDATE members m JOIN member_types mt ON mt.$mtNameCol = m.member_type SET m.member_type_id = mt.$mtPkCol WHERE m.member_type_id IS NULL";
            if ($conn->query($backfillSql)) {
                echo "✓ Backfilled members.member_type_id using '$mtNameCol' -> '$mtPkCol'.\n";
            } else {
                echo "⚠ Backfill skipped: " . $conn->error . "\n";
            }
        } else {
            echo "⚠ member_types has no suitable PK/ID column; skipping backfill of foreign key.\n";
        }
    } else {
        echo "⚠ No suitable name column found in member_types; seed/backfill cannot proceed.\n";
    }

    // Attempt FK addition dynamically after backfill
    echo "\nAttempting to add foreign key constraint dynamically...\n";
    $fkExists = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND CONSTRAINT_NAME = 'fk_members_member_type_id'");
    if ($fkExists && $fkExists->num_rows > 0) {
        echo "⚠ Foreign key already exists.\n";
    } else if ($mtPkCol) {
        $mtIdCol = $conn->query("SHOW COLUMNS FROM member_types LIKE '$mtPkCol'");
        $mMtIdCol = $conn->query("SHOW COLUMNS FROM members LIKE 'member_type_id'");
        if ($mtIdCol && $mtIdCol->num_rows > 0 && $mMtIdCol && $mMtIdCol->num_rows > 0) {
            $mtId = $mtIdCol->fetch_assoc();
            $mMtId = $mMtIdCol->fetch_assoc();
            $mtType = strtolower($mtId['Type']);
            $mMtType = strtolower($mMtId['Type']);
            $mtUnsigned = strpos($mtType, 'unsigned') !== false;
            $mMtUnsigned = strpos($mMtType, 'unsigned') !== false;
            $mtBaseType = preg_replace('/[^a-z]/', '', $mtType);
            $mMtBaseType = preg_replace('/[^a-z]/', '', $mMtType);
            if ($mtBaseType === $mMtBaseType && $mtUnsigned === $mMtUnsigned) {
                $addFk = "ALTER TABLE members ADD CONSTRAINT fk_members_member_type_id FOREIGN KEY (member_type_id) REFERENCES member_types($mtPkCol) ON UPDATE CASCADE ON DELETE SET NULL";
                if ($conn->query($addFk)) {
                    echo "✓ Added foreign key constraint fk_members_member_type_id referencing '$mtPkCol'.\n";
                } else {
                    echo "⚠ Could not add foreign key: " . $conn->error . "\n";
                }
            } else {
                echo "⚠ Type mismatch prevents FK addition (members.member_type_id: $mMtType, member_types.$mtPkCol: $mtType). Attempting to adjust column type...\n";
                // Try to modify members.member_type_id to match member_types PK type
                $desiredType = $mtType; // e.g., "int(11)" or "int(10) unsigned"
                $alterTypeSql = "ALTER TABLE members MODIFY COLUMN member_type_id $desiredType NULL";
                if ($conn->query($alterTypeSql)) {
                    echo "✓ Modified members.member_type_id to type '$desiredType'.\n";
                    // Re-fetch types
                    $mMtIdCol2 = $conn->query("SHOW COLUMNS FROM members LIKE 'member_type_id'");
                    if ($mMtIdCol2 && $mm2 = $mMtIdCol2->fetch_assoc()) {
                        $mMtType2 = strtolower($mm2['Type']);
                        $mMtUnsigned2 = strpos($mMtType2, 'unsigned') !== false;
                        $mMtBaseType2 = preg_replace('/[^a-z]/', '', $mMtType2);
                        if ($mtBaseType === $mMtBaseType2 && $mtUnsigned === $mMtUnsigned2) {
                            $addFk2 = "ALTER TABLE members ADD CONSTRAINT fk_members_member_type_id FOREIGN KEY (member_type_id) REFERENCES member_types($mtPkCol) ON UPDATE CASCADE ON DELETE SET NULL";
                            if ($conn->query($addFk2)) {
                                echo "✓ Added foreign key after type adjustment.\n";
                            } else {
                                echo "⚠ Could not add foreign key after type adjustment: " . $conn->error . "\n";
                            }
                        } else {
                            echo "⚠ Still mismatched after type adjustment (members.member_type_id: $mMtType2 vs member_types.$mtPkCol: $mtType). FK not added.\n";
                        }
                    }
                } else {
                    echo "⚠ Failed to modify members.member_type_id type: " . $conn->error . "\n";
                }
            }
        } else {
            echo "⚠ Required columns missing; FK not added.\n";
        }
    } else {
        echo "⚠ No detected PK/ID column in member_types; FK addition skipped.\n";
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