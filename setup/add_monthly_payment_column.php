<?php
/**
 * Add monthly_payment column to loans table
 * This stores the monthly deduction amount from the loan CSV
 */

require_once __DIR__ . '/../config/config.php';

$db = \Database::getInstance()->getConnection();

echo "Adding monthly_payment column to loans table...\n\n";

//Check if column already exists
$checkCol = $db->query("SHOW COLUMNS FROM loans LIKE 'monthly_payment'");
if ($checkCol->num_rows > 0) {
    echo "✓ monthly_payment column already exists\n";
    exit(0);
}

// Add the column
$sql = "ALTER TABLE loans ADD COLUMN monthly_payment DECIMAL(10,2) NULL AFTER interest_rate";

if ($db->query($sql)) {
    echo "✓ Successfully added monthly_payment column to loans table\n";
    echo "  Column added after 'interest_rate'\n";
    echo "  Type: DECIMAL(10,2) NULL\n\n";
    
    // Verify
    $verify = $db->query("SHOW COLUMNS FROM loans LIKE 'monthly_payment'");
    if ($verify->num_rows > 0) {
        $col = $verify->fetch_assoc();
        echo "✓ Verified: monthly_payment column exists\n";
        echo "  Type: " . $col['Type'] . "\n";
        echo "  Null: " . $col['Null'] . "\n";
    }
} else {
    echo "✗ ERROR: Failed to add monthly_payment column\n";
    echo "  Error: " . $db->error . "\n";
    exit(1);
}

echo "\nDONE!\n";
?>
