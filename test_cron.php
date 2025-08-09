<?php
/**
 * Test script to verify cron job functionality
 * This script can be run manually to test the cron job functionality
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/cron/cron_runner.php';

echo "Testing Cron Job Functionality\n";
echo "================================\n\n";

try {
    $cronRunner = new CronRunner();
    
    echo "1. Testing database connection...\n";
    $db = Database::getInstance()->getConnection();
    if ($db) {
        echo "   ✓ Database connection successful\n\n";
    } else {
        echo "   ✗ Database connection failed\n\n";
        exit(1);
    }
    
    echo "2. Testing system stats table...\n";
    $cronRunner->ensureSystemStatsTable($db);
    echo "   ✓ System stats table verified\n\n";
    
    echo "3. Testing complete cron runner...\n";
    $cronRunner->runAllTasks();
    echo "   ✓ All cron tasks completed successfully\n\n";
    
    echo "All cron job tests completed successfully!\n";
    echo "The cron system is ready to use.\n\n";
    
    echo "To set up automatic processing, add this to your system's cron tab:\n";
    echo "*/5 * * * * php " . __DIR__ . "/cron/cron_runner.php\n";
    echo "(This will run every 5 minutes)\n";
    
} catch (Exception $e) {
    echo "Error during cron test: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>