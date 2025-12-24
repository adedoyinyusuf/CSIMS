<?php
/**
 * Add Missing Tables and Triggers
 * 
 * This script creates:
 * 1. loan_types table (missing from migration 007)
 * 2. Notification triggers (if notification system is configured)
 * 
 * Run: php scripts/add_missing_tables.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   CSIMS - Add Missing Tables and Triggers                     ║\n";
echo "║   " . date('Y-m-d H:i:s') . "                                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if (!$conn) {
    die("❌ Database connection failed\n");
}

echo "✅ Connected to database: " . DB_NAME . "\n\n";

// ============================================================================
// PART 1: Create loan_types table
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 1: Creating loan_types table\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Check if table already exists
$check = $conn->query("SHOW TABLES LIKE 'loan_types'");
if ($check->num_rows > 0) {
    echo "ℹ️  loan_types table already exists. Skipping creation.\n\n";
} else {
    echo "Creating loan_types table...\n";
    
    $sql = "CREATE TABLE `loan_types` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `code` VARCHAR(50) NOT NULL,
        `description` TEXT,
        `interest_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Annual interest rate in percentage',
        `min_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `max_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `min_duration_months` INT(11) NOT NULL DEFAULT 1,
        `max_duration_months` INT(11) NOT NULL DEFAULT 12,
        `processing_fee_percentage` DECIMAL(5,2) DEFAULT 0.00,
        `requires_guarantors` TINYINT(1) DEFAULT 1,
        `min_guarantors` INT(11) DEFAULT 2,
        `max_guarantors` INT(11) DEFAULT 5,
        `eligibility_months` INT(11) DEFAULT 6 COMMENT 'Minimum membership months required',
        `max_outstanding_loans` INT(11) DEFAULT 1 COMMENT 'Maximum loans member can have simultaneously',
        `repayment_frequency` ENUM('weekly', 'biweekly', 'monthly') DEFAULT 'monthly',
        `grace_period_days` INT(11) DEFAULT 0,
        `late_payment_penalty_percentage` DECIMAL(5,2) DEFAULT 0.00,
        `is_active` TINYINT(1) DEFAULT 1,
        `allow_early_repayment` TINYINT(1) DEFAULT 1,
        `early_repayment_penalty_percentage` DECIMAL(5,2) DEFAULT 0.00,
        `insurance_required` TINYINT(1) DEFAULT 0,
        `insurance_percentage` DECIMAL(5,2) DEFAULT 0.00,
        `collateral_required` TINYINT(1) DEFAULT 0,
        `collateral_percentage` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage of loan amount required as collateral',
        `auto_approval_threshold` DECIMAL(15,2) DEFAULT NULL COMMENT 'Amount below which loan is auto-approved if eligible',
        `approval_levels_required` INT(11) DEFAULT 1,
        `terms_and_conditions` TEXT,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        UNIQUE KEY `name` (`name`),
        KEY `is_active` (`is_active`),
        KEY `created_by` (`created_by`),
        KEY `idx_active_loans` (`is_active`, `deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Loan type definitions and configurations'";
    
    if ($conn->query($sql)) {
        echo "✅ loan_types table created successfully\n";
        
        // Insert default loan types
        echo "Inserting default loan types...\n";
        
        $defaults = [
            ['Personal Loan', 'PERSONAL', 'General purpose personal loan for members in good standing', 5.00, 10000, 500000, 3, 24, 1, 2, 6],
            ['Emergency Loan', 'EMERGENCY', 'Quick access loan for emergency situations', 3.00, 5000, 100000, 1, 6, 1, 1, 3],
            ['Business Loan', 'BUSINESS', 'Loan for business development and expansion', 7.00, 50000, 2000000, 6, 48, 1, 3, 12],
            ['Education Loan', 'EDUCATION', 'Loan for educational purposes and school fees', 4.00, 20000, 1000000, 6, 36, 1, 2, 6],
            ['Agricultural Loan', 'AGRICULTURE', 'Loan for agricultural and farming activities', 6.00, 30000, 1500000, 6, 36, 1, 2, 6],
            ['Housing Loan', 'HOUSING', 'Loan for house construction, purchase, or renovation', 8.00, 100000, 5000000, 12, 120, 1, 4, 24],
            ['Salary Advance', 'SALARY_ADVANCE', 'Short-term advance against expected salary', 2.00, 5000, 50000, 1, 3, 0, 0, 3]
        ];
        
        $stmt = $conn->prepare("INSERT INTO `loan_types` (`name`, `code`, `description`, `interest_rate`, `min_amount`, `max_amount`, `min_duration_months`, `max_duration_months`, `requires_guarantors`, `min_guarantors`, `eligibility_months`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $inserted = 0;
        foreach ($defaults as $loan_type) {
            $stmt->bind_param('sssdiiiiiii', 
                $loan_type[0], $loan_type[1], $loan_type[2], $loan_type[3], 
                $loan_type[4], $loan_type[5], $loan_type[6], $loan_type[7],
                $loan_type[8], $loan_type[9], $loan_type[10]
            );
            if ($stmt->execute()) {
                $inserted++;
                echo "  ✓ {$loan_type[0]}\n";
            }
        }
        
        echo "✅ Inserted $inserted default loan types\n\n";
        $stmt->close();
    } else {
        echo "❌ Error creating loan_types table: " . $conn->error . "\n\n";
    }
}

// ============================================================================
// PART 2: Check notification system tables
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 2: Checking Notification System\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Check if notifications table exists
$check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check->num_rows > 0) {
    echo "✅ notifications table exists\n";
    
    // Check for triggers
    $triggers = $conn->query("SHOW TRIGGERS LIKE '%notification%'");
    $trigger_count = $triggers->num_rows;
    
    echo "Found $trigger_count notification-related triggers\n";
    
    if ($trigger_count == 0) {
        echo "\nℹ️  No notification triggers detected.\n";
        echo "📝 Notification trigger schemas available:\n";
        echo "   - database/notification_triggers_schema.sql (comprehensive)\n";
        echo "   - database/notification_triggers_schema_simple.sql (basic)\n";
        echo "\n💡 To implement triggers, run one of these SQL files manually:\n";
        echo "   mysql -u root -p " . DB_NAME . " < database/notification_triggers_schema_simple.sql\n\n";
    } else {
        echo "✅ Notification triggers are configured\n\n";
    }
} else {
    echo "⚠️  notifications table does not exist\n";
    echo "💡 Notification system may not be fully configured\n\n";
}

// ============================================================================
// PART 3: Verify all expected tables
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "PART 3: Database Table Verification\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$expected_tables = [
    'workflow_approvals' => 'Loan approval workflow',
    'loan_guarantors' => 'Loan guarantor management',
    'savings_accounts' => 'Member savings accounts',
    'member_types' => 'Membership type definitions',
    'loan_types' => 'Loan type configurations',
    'system_config' => 'System configuration',
    'notifications' => 'Notification queue',
    'admins' => 'Administrator accounts',
    'members' => 'Member information',
    'loans' => 'Loan records',
    'contributions' => 'Member contributions',
    'user_sessions' => 'Session management'
];

echo "Checking critical tables:\n\n";
echo str_pad("Table Name", 30) . str_pad("Status", 15) . "Purpose\n";
echo str_repeat("─", 70) . "\n";

$missing_tables = [];
foreach ($expected_tables as $table => $purpose) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $check->num_rows > 0;
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    
    echo str_pad($table, 30) . str_pad($status, 15) . $purpose . "\n";
    
    if (!$exists) {
        $missing_tables[] = $table;
    }
}

echo str_repeat("─", 70) . "\n\n";

if (empty($missing_tables)) {
    echo "✅ All critical tables are present!\n\n";
} else {
    echo "⚠️  Missing tables detected: " . implode(', ', $missing_tables) . "\n";
    echo "💡 These tables may need to be created manually\n\n";
}

// ============================================================================
// PART 4: Summary
// ============================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Count total tables
$result = $conn->query("SHOW TABLES");
$total_tables = $result->num_rows;

echo "Database Statistics:\n";
echo "  • Total tables: $total_tables\n";
echo "  • Missing tables: " . count($missing_tables) . "\n";

// Check loan_types
$check = $conn->query("SHOW TABLES LIKE 'loan_types'");
if ($check->num_rows > 0) {
    $count = $conn->query("SELECT COUNT(*) as count FROM loan_types")->fetch_assoc()['count'];
    echo "  • Loan types configured: $count\n";
}

echo "\n";
echo "✅ Script completed successfully!\n\n";

// Save log
$log = "Missing Tables Setup - " . date('Y-m-d H:i:s') . "\n\n";
$log .= "loan_types table: " . ($check->num_rows > 0 ? "Created/Verified" : "Failed") . "\n";
$log .= "Missing tables: " . (empty($missing_tables) ? "None" : implode(', ', $missing_tables)) . "\n";

file_put_contents('logs/missing_tables_setup.log', $log, FILE_APPEND);

echo "📄 Log saved to: logs/missing_tables_setup.log\n\n";

$conn->close();
