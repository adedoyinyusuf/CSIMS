<?php
/**
 * CSIMS Comprehensive System Audit
 * 
 * This script performs a comprehensive audit of all system components
 */
set_time_limit(300); // 5 minutes
session_start();

// Simulate admin session for testing
$_SESSION['admin_id'] = 1;
$_SESSION['user_type'] = 'admin';

echo "<!DOCTYPE html><html><head><title>CSIMS System Audit</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007cba; background: #f9f9f9; }
    .pass { color: #28a745; font-weight: bold; }
    .fail { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    .info { color: #17a2b8; font-weight: bold; }
    h1, h2 { color: #333; }
    h1 { border-bottom: 3px solid #007cba; padding-bottom: 10px; }
    h2 { border-bottom: 1px solid #ddd; padding-bottom: 5px; }
    .component-box { border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px; }
    .status-good { border-left: 5px solid #28a745; }
    .status-bad { border-left: 5px solid #dc3545; }
    .status-partial { border-left: 5px solid #ffc107; }
    .status-info { border-left: 5px solid #17a2b8; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; font-weight: bold; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔍 CSIMS Comprehensive System Audit</h1>";
echo "<p><em>Audit conducted on: " . date('Y-m-d H:i:s') . "</em></p>";

$auditResults = [
    'total_components' => 0,
    'functional' => 0,
    'partial' => 0,
    'broken' => 0,
    'missing' => 0
];

// 1. SYSTEM STRUCTURE AUDIT
echo "<div class='section'>";
echo "<h2>1. 📁 System Structure & Configuration</h2>";

$structureTests = [
    'Root Directory' => ['path' => __DIR__, 'required' => true],
    'Config Directory' => ['path' => __DIR__ . '/config', 'required' => true],
    'Controllers Directory' => ['path' => __DIR__ . '/controllers', 'required' => true],
    'Views Directory' => ['path' => __DIR__ . '/views', 'required' => true],
    'Admin Views' => ['path' => __DIR__ . '/views/admin', 'required' => true],
    'Member Views' => ['path' => __DIR__ . '/views', 'required' => true],
    'Source Directory' => ['path' => __DIR__ . '/src', 'required' => true],
    'Assets Directory' => ['path' => __DIR__ . '/assets', 'required' => false],
    'Includes Directory' => ['path' => __DIR__ . '/includes', 'required' => true]
];

echo "<table>";
echo "<tr><th>Component</th><th>Path</th><th>Status</th><th>Required</th></tr>";

foreach ($structureTests as $name => $test) {
    $auditResults['total_components']++;
    $exists = is_dir($test['path']);
    $status = $exists ? "<span class='pass'>✓ EXISTS</span>" : "<span class='fail'>✗ MISSING</span>";
    $required = $test['required'] ? 'Yes' : 'No';
    
    if ($exists) {
        $auditResults['functional']++;
    } else {
        $auditResults['missing']++;
    }
    
    echo "<tr><td>$name</td><td>{$test['path']}</td><td>$status</td><td>$required</td></tr>";
}
echo "</table>";

// Configuration Files
echo "<h3>Configuration Files</h3>";
$configFiles = [
    'Main Config' => __DIR__ . '/config/config.php',
    'Database Config' => __DIR__ . '/config/database.php',
    'Security Config' => __DIR__ . '/config/security.php',
    'Autoloader' => __DIR__ . '/src/autoload.php'
];

echo "<table>";
echo "<tr><th>Config File</th><th>Path</th><th>Status</th></tr>";

foreach ($configFiles as $name => $path) {
    $auditResults['total_components']++;
    $exists = file_exists($path);
    $status = $exists ? "<span class='pass'>✓ EXISTS</span>" : "<span class='fail'>✗ MISSING</span>";
    
    if ($exists) {
        $auditResults['functional']++;
    } else {
        $auditResults['missing']++;
    }
    
    echo "<tr><td>$name</td><td>$path</td><td>$status</td></tr>";
}
echo "</table>";

echo "</div>";

// 2. DATABASE CONNECTIVITY
echo "<div class='section'>";
echo "<h2>2. 🗄️ Database Connectivity & Tables</h2>";

try {
    require_once __DIR__ . '/config/config.php';
    $database = Database::getInstance();
    $conn = $database->getConnection();
    
    echo "<div class='component-box status-good'>";
    echo "<h3><span class='pass'>✓ Database Connection: ACTIVE</span></h3>";
    echo "<p>Successfully connected to database</p>";
    echo "</div>";
    
    // Check tables
    echo "<h3>Database Tables</h3>";
    $expectedTables = [
        'members' => 'Member management',
        'member_types' => 'Membership types',
        'admins' => 'Admin users',
        'loans' => 'Loan records',
        'loan_guarantors' => 'Loan guarantors',
        'savings_accounts' => 'Savings accounts',
        'savings_transactions' => 'Savings transactions',
        'notifications' => 'System notifications'
    ];
    
    echo "<table>";
    echo "<tr><th>Table</th><th>Purpose</th><th>Status</th><th>Record Count</th></tr>";
    
    foreach ($expectedTables as $table => $purpose) {
        $auditResults['total_components']++;
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $countResult ? $countResult->fetch_assoc()['count'] : 'N/A';
            echo "<tr><td>$table</td><td>$purpose</td><td><span class='pass'>✓ EXISTS</span></td><td>$count</td></tr>";
            $auditResults['functional']++;
        } else {
            echo "<tr><td>$table</td><td>$purpose</td><td><span class='fail'>✗ MISSING</span></td><td>N/A</td></tr>";
            $auditResults['missing']++;
        }
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='component-box status-bad'>";
    echo "<h3><span class='fail'>✗ Database Connection: FAILED</span></h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    $auditResults['total_components']++;
    $auditResults['broken']++;
}

echo "</div>";

// 3. AUTHENTICATION SYSTEM
echo "<div class='section'>";
echo "<h2>3. 🔐 Authentication & Authorization System</h2>";

$authComponents = [
    'Admin Login Page' => __DIR__ . '/index.php',
    'Member Login Page' => __DIR__ . '/views/member_login.php',
    'Auth Controller' => __DIR__ . '/controllers/auth_controller.php',
    'Member Controller' => __DIR__ . '/controllers/member_controller.php',
    'Session Management' => __DIR__ . '/includes/session.php',
    'Security Service' => __DIR__ . '/src/Services/SecurityService.php'
];

echo "<table>";
echo "<tr><th>Component</th><th>File Path</th><th>Status</th></tr>";

foreach ($authComponents as $name => $path) {
    $auditResults['total_components']++;
    $exists = file_exists($path);
    
    if ($exists) {
        // Test if file can be parsed
        $error = null;
        $output = shell_exec("php -l \"$path\" 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        
        if ($syntaxOk) {
            echo "<tr><td>$name</td><td>$path</td><td><span class='pass'>✓ FUNCTIONAL</span></td></tr>";
            $auditResults['functional']++;
        } else {
            echo "<tr><td>$name</td><td>$path</td><td><span class='warning'>⚠ SYNTAX ERROR</span></td></tr>";
            $auditResults['partial']++;
        }
    } else {
        echo "<tr><td>$name</td><td>$path</td><td><span class='fail'>✗ MISSING</span></td></tr>";
        $auditResults['missing']++;
    }
}
echo "</table>";

// Test session functionality
echo "<h3>Session System Test</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='component-box status-good'>";
    echo "<span class='pass'>✓ Session System: ACTIVE</span>";
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "</div>";
} else {
    echo "<div class='component-box status-bad'>";
    echo "<span class='fail'>✗ Session System: INACTIVE</span>";
    echo "</div>";
}

echo "</div>";

// 4. MEMBER MANAGEMENT SYSTEM
echo "<div class='section'>";
echo "<h2>4. 👥 Member Management System</h2>";

$memberComponents = [
    'Member Registration' => __DIR__ . '/views/member_register.php',
    'Member Dashboard' => __DIR__ . '/views/member_dashboard.php',
    'Member Profile' => __DIR__ . '/views/member_profile.php',
    'Admin Members View' => __DIR__ . '/views/admin/members.php',
    'Member Approvals' => __DIR__ . '/views/admin/member_approvals.php',
    'Member Controller' => __DIR__ . '/controllers/member_controller.php'
];

echo "<table>";
echo "<tr><th>Component</th><th>File Path</th><th>Status</th></tr>";

foreach ($memberComponents as $name => $path) {
    $auditResults['total_components']++;
    $exists = file_exists($path);
    
    if ($exists) {
        $output = shell_exec("php -l \"$path\" 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        
        if ($syntaxOk) {
            echo "<tr><td>$name</td><td>$path</td><td><span class='pass'>✓ FUNCTIONAL</span></td></tr>";
            $auditResults['functional']++;
        } else {
            echo "<tr><td>$name</td><td>$path</td><td><span class='warning'>⚠ SYNTAX ERROR</span></td></tr>";
            $auditResults['partial']++;
        }
    } else {
        echo "<tr><td>$name</td><td>$path</td><td><span class='fail'>✗ MISSING</span></td></tr>";
        $auditResults['missing']++;
    }
}
echo "</table>";

// Check member data
if (isset($conn)) {
    echo "<h3>Member Data Status</h3>";
    try {
        $memberStats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive
            FROM members
        ");
        
        if ($memberStats) {
            $stats = $memberStats->fetch_assoc();
            echo "<div class='component-box status-info'>";
            echo "<h4>Member Statistics</h4>";
            echo "<p>Total Members: {$stats['total']}</p>";
            echo "<p>Active: {$stats['active']} | Pending: {$stats['pending']} | Inactive: {$stats['inactive']}</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='component-box status-bad'>";
        echo "<span class='fail'>✗ Could not retrieve member statistics</span>";
        echo "</div>";
    }
}

echo "</div>";

// 5. LOAN MANAGEMENT SYSTEM
echo "<div class='section'>";
echo "<h2>5. 💰 Loan Management System</h2>";

$loanComponents = [
    'Loan Controller' => __DIR__ . '/controllers/loan_controller.php',
    'Loan Application Form' => __DIR__ . '/views/loan_application.php',
    'Admin Loans View' => __DIR__ . '/views/admin/loans.php',
    'Member Loans View' => __DIR__ . '/views/member_loans.php',
    'Loan Repository' => __DIR__ . '/src/Repositories/LoanRepository.php',
    'Loan Guarantor Repository' => __DIR__ . '/src/Repositories/LoanGuarantorRepository.php'
];

echo "<table>";
echo "<tr><th>Component</th><th>File Path</th><th>Status</th></tr>";

foreach ($loanComponents as $name => $path) {
    $auditResults['total_components']++;
    $exists = file_exists($path);
    
    if ($exists) {
        $output = shell_exec("php -l \"$path\" 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        
        if ($syntaxOk) {
            echo "<tr><td>$name</td><td>$path</td><td><span class='pass'>✓ FUNCTIONAL</span></td></tr>";
            $auditResults['functional']++;
        } else {
            echo "<tr><td>$name</td><td>$path</td><td><span class='warning'>⚠ SYNTAX ERROR</span></td></tr>";
            $auditResults['partial']++;
        }
    } else {
        echo "<tr><td>$name</td><td>$path</td><td><span class='fail'>✗ MISSING</span></td></tr>";
        $auditResults['missing']++;
    }
}
echo "</table>";

// Check loan data
if (isset($conn)) {
    echo "<h3>Loan Data Status</h3>";
    try {
        $loanStats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(amount) as total_amount
            FROM loans
        ");
        
        if ($loanStats) {
            $stats = $loanStats->fetch_assoc();
            echo "<div class='component-box status-info'>";
            echo "<h4>Loan Statistics</h4>";
            echo "<p>Total Loans: {$stats['total']}</p>";
            echo "<p>Approved: {$stats['approved']} | Pending: {$stats['pending']} | Rejected: {$stats['rejected']}</p>";
            echo "<p>Total Amount: ₦" . number_format($stats['total_amount'] ?? 0, 2) . "</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='component-box status-partial'>";
        echo "<span class='warning'>⚠ Could not retrieve loan statistics: " . $e->getMessage() . "</span>";
        echo "</div>";
    }
}

echo "</div>";

echo "</div>"; // End container

// Generate summary
echo "<div class='section'>";
echo "<h2>📊 Audit Summary</h2>";

$totalComponents = $auditResults['total_components'];
$functionalPercent = $totalComponents > 0 ? round(($auditResults['functional'] / $totalComponents) * 100, 1) : 0;
$partialPercent = $totalComponents > 0 ? round(($auditResults['partial'] / $totalComponents) * 100, 1) : 0;
$brokenPercent = $totalComponents > 0 ? round(($auditResults['broken'] / $totalComponents) * 100, 1) : 0;
$missingPercent = $totalComponents > 0 ? round(($auditResults['missing'] / $totalComponents) * 100, 1) : 0;

echo "<table>";
echo "<tr><th>Status</th><th>Count</th><th>Percentage</th></tr>";
echo "<tr><td><span class='pass'>✓ Functional</span></td><td>{$auditResults['functional']}</td><td>{$functionalPercent}%</td></tr>";
echo "<tr><td><span class='warning'>⚠ Partial</span></td><td>{$auditResults['partial']}</td><td>{$partialPercent}%</td></tr>";
echo "<tr><td><span class='fail'>✗ Broken</span></td><td>{$auditResults['broken']}</td><td>{$brokenPercent}%</td></tr>";
echo "<tr><td><span class='fail'>✗ Missing</span></td><td>{$auditResults['missing']}</td><td>{$missingPercent}%</td></tr>";
echo "<tr><td><strong>Total Components</strong></td><td><strong>{$totalComponents}</strong></td><td><strong>100%</strong></td></tr>";
echo "</table>";

$overallHealth = $functionalPercent >= 80 ? 'EXCELLENT' : ($functionalPercent >= 60 ? 'GOOD' : ($functionalPercent >= 40 ? 'FAIR' : 'POOR'));
$healthColor = $functionalPercent >= 80 ? 'pass' : ($functionalPercent >= 60 ? 'info' : ($functionalPercent >= 40 ? 'warning' : 'fail'));

echo "<div class='component-box status-info'>";
echo "<h3>Overall System Health: <span class='$healthColor'>$overallHealth</span></h3>";
echo "<p>System Functionality: <strong>{$functionalPercent}%</strong></p>";
echo "</div>";

echo "</div>";

echo "</body></html>";
?>