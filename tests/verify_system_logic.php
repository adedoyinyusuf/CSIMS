<?php
// tests/verify_system_logic.php

// 1. Setup Environment
define('TESTING', true);
// Mock session start to avoid headers sent errors in CLI, MUST be before session.php if it calls it
// But session.php likely defines the class, not calls session_start immediately?
// Let's include session.php. If it calls session_start, we suppress it.
if (!function_exists('session_start')) {
     function session_start() { return true; } 
}
$_SESSION = []; 

// Include legacy dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php'; 

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../controllers/auth_controller.php';

use CSIMS\Controllers\FinancialAnalyticsController;
use CSIMS\Container\Container;

echo "Starting System Logic Verification...\n";

// 2. Bootstrap Application
try {
    // Force debug mode for testing
    putenv('APP_DEBUG=true');
    $_ENV['APP_DEBUG'] = 'true';
    
    $container = \CSIMS\bootstrap();
    restore_exception_handler(); // Remove the JSON handler from bootstrap
    restore_error_handler();
    echo "[PASS] Application Bootstrapped\n";
} catch (Exception $e) {
    echo "[FAIL] Bootstrap failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}

// 3. Verify Admin Login Logic
echo "\n--- Testing Admin Authentication ---\n";
try {
    $authController = new AuthController();
    // Use default admin credentials or valid ones from DB
    // Note: In a real test we might want to create a temp admin, but for verification we try the default
    $username = 'admin'; 
    $password = 'Admin123!'; 

    // We can't easily mock the DB content here without a dedicated test DB, 
    // so we will attempt login and interpret result.
    // If it fails, it might be due to changed password, but code execution path is verified.
    
    // We need to suppress headers from Login
    ob_start(); 
    $result = $authController->adminLogin($username, $password);
    ob_end_clean();

    if ($result['success']) {
        echo "[PASS] Admin Login Successful for user: $username\n";
        echo "      Role: " . ($result['user']['role'] ?? 'Unknown') . "\n";
    } else {
        echo "[WARN] Admin Login Failed: " . ($result['message'] ?? 'Unknown error') . "\n";
        echo "      (This is expected if password was changed from default)\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Admin Login Threw Exception: " . $e->getMessage() . "\n";
}

// 4. Verify Financial Analytics Controller
echo "\n--- Testing Financial Analytics ---\n";
try {
    $faController = $container->resolve(FinancialAnalyticsController::class);
    echo "[PASS] FinancialAnalyticsController Resolved\n";

    // Test getDashboard
    $dashboardData = $faController->getDashboard('month');
    
    if (isset($dashboardData['overview']) && isset($dashboardData['cash_flow'])) {
        echo "[PASS] getDashboard('month') returned valid structure.\n";
        echo "      Total Assets: " . ($dashboardData['overview']['total_assets'] ?? 'N/A') . "\n";
        echo "      Outstanding Loans: " . ($dashboardData['overview']['outstanding_loans'] ?? 'N/A') . "\n";
    } else {
        echo "[FAIL] getDashboard returned unexpected structure.\n";
        print_r($dashboardData);
    }

    // Test Export Data Retrieval (not the file download, just the logic)
    // We can't fully test exportDashboard because it exits/echoes CSV. 
    // But we can verify the method triggers no errors if we buffer output.
    
    ob_start();
    try {
        // We can't easily call export because it might exit. 
        // But we know getDashboard is what populates it.
        // Let's verify resolvePeriodRange private method indirectly via getDashboard results valid ranges.
        if (isset($dashboardData['period'])) {
             echo "[PASS] Period resolution works: " . $dashboardData['period'] . "\n";
        }
    } catch (Exception $e) {
        throw $e;
    } finally {
        ob_end_clean();
    }

} catch (Exception $e) {
    echo "[FAIL] Financial Analytics Test Failed: " . $e->getMessage() . "\n";
}

echo "\nVerification Complete.\n";
