<?php
/**
 * Data Integrity Enhancement Script
 * 
 * Adds database constraints to ensure data integrity:
 * 1. CHECK constraints for positive amounts
 * 2. Valid percentage ranges (0-100)
 * 3. Logical date constraints
 * 4. Enum/status validation
 * 5. Additional foreign key constraints
 * 
 * Run: php scripts/enhance_data_integrity.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   CSIMS Data Integrity Enhancement                            ║\n";
echo "║   " . date('Y-m-d H:i:s') . "                                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if (!$conn) {
    die("❌ Database connection failed\n");
}

echo "✅ Connected to database: " . DB_NAME . "\n\n";

$constraints_added = 0;
$constraints_failed = 0;
$already_exist = 0;

// ============================================================================
// PART 1: Positive Amount Constraints
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 1: Adding Positive Amount Constraints\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$amount_constraints = [
    // Loans table
    ['table' => 'loans', 'column' => 'amount', 'constraint' => 'chk_loans_amount_positive'],
    ['table' => 'loans', 'column' => 'interest_amount', 'constraint' => 'chk_loans_interest_positive'],
    ['table' => 'loans', 'column' => 'total_amount', 'constraint' => 'chk_loans_total_positive'],
    ['table' => 'loans', 'column' => 'amount_paid', 'constraint' => 'chk_loans_paid_positive'],
    ['table' => 'loans', 'column' => 'balance', 'constraint' => 'chk_loans_balance_positive'],
    
    // Loan types
    ['table' => 'loan_types', 'column' => 'min_amount', 'constraint' => 'chk_loan_types_min_positive'],
    ['table' => 'loan_types', 'column' => 'max_amount', 'constraint' => 'chk_loan_types_max_positive'],
    
    // Contributions
    ['table' => 'contributions', 'column' => 'amount', 'constraint' => 'chk_contributions_amount_positive'],
    
    // Savings accounts
    ['table' => 'savings_accounts', 'column' => 'balance', 'constraint' => 'chk_savings_balance_positive'],
    ['table' => 'savings_accounts', 'column' => 'monthly_contribution', 'constraint' => 'chk_savings_contribution_positive'],
    
    // Transactions
    ['table' => 'transactions', 'column' => 'amount', 'constraint' => 'chk_transactions_amount_positive'],
    
    // Members (if has membership fee)
    ['table' => 'members', 'column' => 'total_savings', 'constraint' => 'chk_members_savings_positive'],
    ['table' => 'members', 'column' => 'total_shares', 'constraint' => 'chk_members_shares_positive'],
];

echo "Adding positive amount constraints...\n\n";

foreach ($amount_constraints as $constraint) {
    $table = $constraint['table'];
    $column = $constraint['column'];
    $constraint_name = $constraint['constraint'];
    
    // Check if table and column exist
    $check_column = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$check_column || $check_column->num_rows == 0) {
        echo "  ⚠️  Skipped: $table.$column (column doesn't exist)\n";
        continue;
    }
    
    // Try to add constraint
    $sql = "ALTER TABLE `$table` ADD CONSTRAINT `$constraint_name` CHECK (`$column` >= 0)";
    
    if ($conn->query($sql)) {
        echo "  ✓ Added: $table.$column >= 0\n";
        $constraints_added++;
    } else {
        if (strpos($conn->error, 'Duplicate') !== false || strpos($conn->error, 'already exists') !== false) {
            echo "  ℹ️  Exists: $table.$column (already has constraint)\n";
            $already_exist++;
        } else {
            echo "  ❌ Failed: $table.$column - " . $conn->error . "\n";
            $constraints_failed++;
        }
    }
}

echo "\n";

// ============================================================================
// PART 2: Percentage Range Constraints (0-100)
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 2: Adding Percentage Range Constraints\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$percentage_constraints = [
    ['table' => 'loans', 'column' => 'interest_rate', 'constraint' => 'chk_loans_interest_rate_range'],
    ['table' => 'loan_types', 'column' => 'interest_rate', 'constraint' => 'chk_loan_types_interest_range'],
    ['table' => 'loan_types', 'column' => 'processing_fee_percentage', 'constraint' => 'chk_loan_types_processing_fee_range'],
    ['table' => 'loan_types', 'column' => 'late_payment_penalty_percentage', 'constraint' => 'chk_loan_types_penalty_range'],
    ['table' => 'loan_types', 'column' => 'early_repayment_penalty_percentage', 'constraint' => 'chk_loan_types_early_penalty_range'],
    ['table' => 'loan_types', 'column' => 'insurance_percentage', 'constraint' => 'chk_loan_types_insurance_range'],
    ['table' => 'loan_types', 'column' => 'collateral_percentage', 'constraint' => 'chk_loan_types_collateral_range'],
];

echo "Adding percentage range constraints (0-100)...\n\n";

foreach ($percentage_constraints as $constraint) {
    $table = $constraint['table'];
    $column = $constraint['column'];
    $constraint_name = $constraint['constraint'];
    
    // Check if column exists
    $check_column = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$check_column || $check_column->num_rows == 0) {
        echo "  ⚠️  Skipped: $table.$column (column doesn't exist)\n";
        continue;
    }
    
    $sql = "ALTER TABLE `$table` ADD CONSTRAINT `$constraint_name` CHECK (`$column` >= 0 AND `$column` <= 100)";
    
    if ($conn->query($sql)) {
        echo "  ✓ Added: $table.$column (0-100%)\n";
        $constraints_added++;
    } else {
        if (strpos($conn->error, 'Duplicate') !== false || strpos($conn->error, 'already exists') !== false) {
            echo "  ℹ️  Exists: $table.$column\n";
            $already_exist++;
        } else {
            echo "  ❌ Failed: $table.$column - " . $conn->error . "\n";
            $constraints_failed++;
        }
    }
}

echo "\n";

// ============================================================================
// PART 3: Logical Constraints
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 3: Adding Logical Constraints\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$logical_constraints = [
    // Loan amounts must make sense
    [
        'table' => 'loans',
        'constraint' => 'chk_loans_total_amount_logic',
        'check' => '`total_amount` >= `amount`',
        'description' => 'Total amount >= principal amount'
    ],
    [
        'table' => 'loans',
        'constraint' => 'chk_loans_balance_logic',
        'check' => '`balance` <= `total_amount`',
        'description' => 'Balance <= total amount'
    ],
    [
        'table' => 'loans',
        'constraint' => 'chk_loans_paid_logic',
        'check' => '`amount_paid` <= `total_amount`',
        'description' => 'Amount paid <= total amount'
    ],
    
    // Loan types min/max
    [
        'table' => 'loan_types',
        'constraint' => 'chk_loan_types_amount_range',
        'check' => '`max_amount` >= `min_amount`',
        'description' => 'Max amount >= min amount'
    ],
    [
        'table' => 'loan_types',
        'constraint' => 'chk_loan_types_duration_range',
        'check' => '`max_duration_months` >= `min_duration_months`',
        'description' => 'Max duration >= min duration'
    ],
    [
        'table' => 'loan_types',
        'constraint' => 'chk_loan_types_guarantors_range',
        'check' => '`max_guarantors` >= `min_guarantors`',
        'description' => 'Max guarantors >= min guarantors'
    ],
    
    // Positive counts
    [
        'table' => 'loan_types',
        'constraint' => 'chk_loan_types_duration_positive',
        'check' => '`min_duration_months` > 0',
        'description' => 'Minimum duration must be positive'
    ],
];

echo "Adding logical constraints...\n\n";

foreach ($logical_constraints as $constraint) {
    $table = $constraint['table'];
    $constraint_name = $constraint['constraint'];
    $check = $constraint['check'];
    $description = $constraint['description'];
    
    // Check if table exists
    $check_table = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$check_table || $check_table->num_rows == 0) {
        echo "  ⚠️  Skipped: $table (table doesn't exist)\n";
        continue;
    }
    
    $sql = "ALTER TABLE `$table` ADD CONSTRAINT `$constraint_name` CHECK ($check)";
    
    if ($conn->query($sql)) {
        echo "  ✓ Added: $description\n";
        $constraints_added++;
    } else {
        if (strpos($conn->error, 'Duplicate') !== false || strpos($conn->error, 'already exists') !== false) {
            echo "  ℹ️  Exists: $description\n";
            $already_exist++;
        } else {
            echo "  ❌ Failed: $description - " . $conn->error . "\n";
            $constraints_failed++;
        }
    }
}

echo "\n";

// ============================================================================
// PART 4: Date Logic Constraints
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 4: Adding Date Logic Constraints\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$date_constraints = [
    [
        'table' => 'loans',
        'constraint' => 'chk_loans_disbursement_date',
        'check' => '`disbursement_date` IS NULL OR `disbursement_date` >= `application_date`',
        'description' => 'Disbursement date >= application date'
    ],
    [
        'table' => 'savings_accounts',
        'constraint' => 'chk_savings_maturity_date',
        'check' => '`maturity_date` IS NULL OR `maturity_date` >= `date_opened`',
        'description' => 'Maturity date >= opening date'
    ],
];

echo "Adding date logic constraints...\n\n";

foreach ($date_constraints as $constraint) {
    $table = $constraint['table'];
    $constraint_name = $constraint['constraint'];
    $check = $constraint['check'];
    $description = $constraint['description'];
    
    // Check if table exists
    $check_table = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$check_table || $check_table->num_rows == 0) {
        echo "  ⚠️  Skipped: $table (table doesn't exist)\n";
        continue;
    }
    
    $sql = "ALTER TABLE `$table` ADD CONSTRAINT `$constraint_name` CHECK ($check)";
    
    if ($conn->query($sql)) {
        echo "  ✓ Added: $description\n";
        $constraints_added++;
    } else {
        if (strpos($conn->error, 'Duplicate') !== false || strpos($conn->error, 'already exists') !== false) {
            echo "  ℹ️  Exists: $description\n";
            $already_exist++;
        } else {
            echo "  ❌ Failed: $description - " . $conn->error . "\n";
            $constraints_failed++;
        }
    }
}

echo "\n";

// ============================================================================
// PART 5: Verify Foreign Key Constraints
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 5: Verifying Foreign Key Constraints\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get all foreign keys
$fk_query = "
    SELECT 
        TABLE_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM 
        information_schema.KEY_COLUMN_USAGE
    WHERE 
        TABLE_SCHEMA = '" . DB_NAME . "'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY 
        TABLE_NAME, CONSTRAINT_NAME
";

$fk_result = $conn->query($fk_query);
$fk_count = 0;

if ($fk_result && $fk_result->num_rows > 0) {
    echo "Existing Foreign Key Constraints:\n\n";
    $current_table = '';
    while ($row = $fk_result->fetch_assoc()) {
        if ($current_table != $row['TABLE_NAME']) {
            if ($current_table != '') echo "\n";
            $current_table = $row['TABLE_NAME'];
            echo "  {$row['TABLE_NAME']}:\n";
        }
        echo "    ✓ {$row['CONSTRAINT_NAME']} → {$row['REFERENCED_TABLE_NAME']}\n";
        $fk_count++;
    }
    echo "\n  Total: $fk_count foreign key constraints\n\n";
} else {
    echo "  ⚠️  No foreign key constraints found\n\n";
}

// ============================================================================
// PART 6: Data Validation Report
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 6: Data Validation Report\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$validation_checks = [];

// Check for negative amounts in loans
$check = $conn->query("SELECT COUNT(*) as count FROM loans WHERE amount < 0");
if ($check) {
    $count = $check->fetch_assoc()['count'];
    if ($count > 0) {
        $validation_checks[] = "⚠️  Found $count loans with negative amounts";
    } else {
        echo "  ✓ No loans with negative amounts\n";
    }
}

// Check for invalid interest rates
$check = $conn->query("SELECT COUNT(*) as count FROM loan_types WHERE interest_rate < 0 OR interest_rate > 100");
if ($check) {
    $count = $check->fetch_assoc()['count'];
    if ($count > 0) {
        $validation_checks[] = "⚠️  Found $count loan types with invalid interest rates";
    } else {
        echo "  ✓ All loan type interest rates are valid (0-100%)\n";
    }
}

// Check for logical inconsistencies
$check = $conn->query("SELECT COUNT(*) as count FROM loan_types WHERE max_amount < min_amount");
if ($check) {
    $count = $check->fetch_assoc()['count'];
    if ($count > 0) {
        $validation_checks[] = "⚠️  Found $count loan types where max_amount < min_amount";
    } else {
        echo "  ✓ All loan types have logical min/max amounts\n";
    }
}

echo "\n";

if (!empty($validation_checks)) {
    echo "Data Validation Issues Found:\n";
    foreach ($validation_checks as $issue) {
        echo "  $issue\n";
    }
    echo "\n💡 Run data cleanup before deploying to production\n\n";
}

// ============================================================================
// Summary
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Constraints Added: $constraints_added\n";
echo "Already Existed: $already_exist\n";
echo "Failed: $constraints_failed\n";
echo "Foreign Keys: $fk_count\n\n";

$total_constraints = $constraints_added + $already_exist;
echo "Total Active Constraints: $total_constraints\n\n";

if ($constraints_added > 0) {
    echo "✅ Database integrity enhanced with $constraints_added new constraints!\n";
} else if ($already_exist > 0) {
    echo "✅ Database already has good integrity constraints ($already_exist existing)\n";
} else {
    echo "ℹ️  No new constraints added\n";
}

echo "\n";

// Save report
$report = "Data Integrity Enhancement - " . date('Y-m-d H:i:s') . "\n\n";
$report .= "Constraints Added: $constraints_added\n";
$report .= "Already Existed: $already_exist\n";
$report .= "Failed: $constraints_failed\n";
$report .= "Foreign Keys: $fk_count\n";
$report .= "Total Active Constraints: $total_constraints\n\n";

if (!empty($validation_checks)) {
    $report .= "Data Validation Issues:\n";
    foreach ($validation_checks as $issue) {
        $report .= "$issue\n";
    }
}

file_put_contents('logs/data_integrity.log', $report, FILE_APPEND);

echo "📄 Report saved to: logs/data_integrity.log\n\n";
echo "✅ Data integrity enhancement complete!\n\n";

$conn->close();
?>
