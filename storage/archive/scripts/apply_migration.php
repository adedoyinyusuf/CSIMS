<?php
/**
 * CSIMS Enhanced Schema Migration Script
 * 
 * This script applies the enhanced cooperative schema to the existing CSIMS database
 * It includes safety checks and rollback capabilities
 */

require_once __DIR__ . '/config/database.php';

echo "=== CSIMS Enhanced Schema Migration ===\n\n";

// Check if database exists and is accessible
if (!$conn) {
    die("Error: Could not connect to database. Please check your database configuration.\n");
}

echo "✓ Database connection successful\n";
echo "Database: " . DB_NAME . "\n";
echo "Host: " . DB_HOST . "\n\n";

// Check if core tables exist
$requiredTables = ['members', 'admins', 'loans', 'contributions'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "⚠️  Warning: Missing core tables: " . implode(', ', $missingTables) . "\n";
    echo "You may need to run the basic schema first.\n\n";
}

// Create migrations tracking table
echo "Creating migrations tracking table...\n";
$migrationTrackingSql = "
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('success', 'failed') DEFAULT 'success',
    notes TEXT
)";

if ($conn->query($migrationTrackingSql)) {
    echo "✓ Migrations tracking table ready\n\n";
} else {
    die("❌ Error creating migrations table: " . $conn->error . "\n");
}

// Check if this migration was already applied
$checkMigration = $conn->query("SELECT * FROM schema_migrations WHERE migration_name = '007_enhanced_cooperative_schema_simple'");
if ($checkMigration && $checkMigration->num_rows > 0) {
    echo "⚠️  Migration '007_enhanced_cooperative_schema_simple' already applied.\n";
    echo "Do you want to continue anyway? This might cause errors if tables already exist.\n";
    echo "Type 'yes' to continue or 'no' to abort: ";
    $handle = fopen("php://stdin", "r");
    $confirmation = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($confirmation) !== 'yes') {
        echo "Migration aborted by user.\n";
        exit(0);
    }
}

echo "Starting migration...\n\n";

// Begin transaction for safe migration
$conn->begin_transaction();

try {
    // Read and execute the migration SQL
    $migrationFile = __DIR__ . '/database/migrations/007_enhanced_cooperative_schema_simple.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    echo "📄 Migration file loaded successfully\n";
    echo "File size: " . number_format(strlen($sql)) . " bytes\n\n";
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $successCount = 0;
    $skipCount = 0;
    
    echo "Executing " . count($statements) . " SQL statements...\n\n";
    
    foreach ($statements as $index => $statement) {
        if (empty($statement) || substr(trim($statement), 0, 2) === '--') {
            continue; // Skip empty statements and comments
        }
        
        // Show progress
        $statementNumber = $index + 1;
        $preview = substr(trim($statement), 0, 80) . (strlen($statement) > 80 ? '...' : '');
        echo "[$statementNumber] $preview\n";
        
        $result = $conn->query($statement);
        
        if ($result === false) {
            // Check if it's a "table already exists" error (which we can ignore)
            if (strpos($conn->error, 'already exists') !== false) {
                echo "   ⚠️  Already exists - skipped\n";
                $skipCount++;
            } else {
                throw new Exception("SQL Error in statement $statementNumber: " . $conn->error . "\nStatement: " . $statement);
            }
        } else {
            echo "   ✓ Success\n";
            $successCount++;
        }
    }
    
    // Record successful migration
    $conn->query("INSERT IGNORE INTO schema_migrations (migration_name, status, notes) 
                  VALUES ('007_enhanced_cooperative_schema_simple', 'success',
                         'Enhanced cooperative schema with $successCount successful statements, $skipCount skipped')");
    
    // Commit transaction
    $conn->commit();
    
    echo "\n=== Migration Completed Successfully! ===\n";
    echo "✅ $successCount statements executed successfully\n";
    echo "⚠️  $skipCount statements skipped (already existed)\n\n";
    
    // Show summary of new tables created
    echo "New tables added:\n";
    $newTables = [
        'loan_guarantors' => 'Loan guarantor management',
        'loan_collateral' => 'Loan collateral tracking',
        'loan_payment_schedule' => 'Detailed payment schedules',
        'loan_penalty_config' => 'Penalty configuration',
        'contribution_targets' => 'Member contribution targets',
        'contribution_withdrawals' => 'Withdrawal management',
        'share_capital' => 'Share capital management',
        'dividend_declarations' => 'Dividend management',
        'member_dividend_payments' => 'Individual dividend payments',
        'financial_audit_trail' => 'Financial audit trail',
        'workflow_approvals' => 'Approval workflows',
        'notification_queue' => 'Notification system'
    ];
    
    foreach ($newTables as $table => $description) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $status = ($result && $result->num_rows > 0) ? '✓' : '❌';
        echo "$status $table - $description\n";
    }
    
    echo "\n🎉 Your CSIMS system now has enhanced cooperative society features!\n";
    echo "📖 Check the documentation at: documentation/ADMIN_MEMBER_INTEGRATION_GUIDE.md\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Record failed migration
    $conn->query("INSERT IGNORE INTO schema_migrations (migration_name, status, notes) 
                  VALUES ('007_enhanced_cooperative_schema_simple', 'failed', " . $conn->real_escape_string($e->getMessage()) . ")");
    
    echo "\n❌ Migration Failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
    exit(1);
}

echo "\nMigration completed at: " . date('Y-m-d H:i:s') . "\n";
$conn->close();
?>
