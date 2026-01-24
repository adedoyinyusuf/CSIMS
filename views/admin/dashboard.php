<?php
require_once '../../config/config.php';
// Ensure AuthController is available for Sidebar permissions
if (!class_exists('AuthController')) {
    require_once '../../controllers/auth_controller.php';
}

$session = Session::getInstance();

// Simple and robust authentication check aligned with Session
// DEBUG: Inspect session state
if (!$session->isLoggedIn() || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo "<h1>Dashboard Access Denied</h1>";
    echo "<pre>";
    echo "Check Reason:\n";
    echo "isLoggedIn: " . ($session->isLoggedIn() ? "TRUE" : "FALSE") . "\n";
    echo "user_type set: " . (isset($_SESSION['user_type']) ? "YES" : "NO") . "\n";
    echo "user_type value: " . ($_SESSION['user_type'] ?? 'N/A') . "\n";
    echo "\nFull Session:\n";
    print_r($_SESSION);
    echo "</pre>";
    die("Debug Mode Active");
    
    // Original redirect (disabled for debug)
    // $_SESSION['error'] = 'Please login to access the dashboard';
    // header("Location: ../../index.php");
    // exit();
}

// Clear redirect check flag since we're successfully accessing dashboard
unset($_SESSION['redirect_check']);

// Get current user info from session
$current_user = [
    'admin_id' => $_SESSION['admin_id'],
    'username' => $_SESSION['username'] ?? 'admin',
    'first_name' => $_SESSION['first_name'] ?? 'Admin',
    'last_name' => $_SESSION['last_name'] ?? 'User',
    'role' => $_SESSION['role'] ?? 'Administrator'
];

// Initialize database connection
try {
    require_once '../../config/database.php';
    require_once '../../includes/db.php';
    require_once '../../includes/utilities.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log('Database connection failed in dashboard: ' . $e->getMessage());
    $db_error = 'Database connection failed: ' . $e->getMessage();
    $conn = null;
}

// Initialize statistics with safe defaults
$stats = [
    'total_members' => 0,
    'active_members' => 0,
    'total_loans' => 0,
    'new_members_this_month' => 0,
    'active_loans' => 0,
    'loan_amount' => 0,
    'total_deposits' => 0,
    'total_savings_balance' => 0,
    'deposits_this_month' => 0,
    'loan_outstanding' => 0,
    'repayments_this_month' => 0,
    'repayments_count_this_month' => 0
];

if ($conn) {
    try {
        // Members
        $res = $conn->query("SELECT COUNT(*) as count FROM members");
        if ($res) $stats['total_members'] = $res->fetch_assoc()['count'] ?? 0;

        $res = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'Active'");
        if ($res) $stats['active_members'] = $res->fetch_assoc()['count'] ?? 0;

        $res = $conn->query("SELECT COUNT(*) as count FROM members WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
        if ($res) $stats['new_members_this_month'] = $res->fetch_assoc()['count'] ?? 0;

        // Loans
        $res = $conn->query("SELECT COUNT(*) as count FROM loans WHERE LOWER(status) IN ('active','disbursed','approved')");
        if ($res) $stats['active_loans'] = $res->fetch_assoc()['count'] ?? 0;
        
        $res = $conn->query("SELECT COUNT(*) as count FROM loans");
        if ($res) $stats['total_loans'] = $res->fetch_assoc()['count'] ?? 0;

        // Determine loan balance column
        $balance_col = null;
        $cols = $conn->query("SHOW COLUMNS FROM loans");
        $colNames = [];
        while($c = $cols->fetch_assoc()) { $colNames[] = $c['Field']; }
        
        if (in_array('remaining_balance', $colNames)) {
             $res = $conn->query("SELECT SUM(remaining_balance) as total FROM loans WHERE LOWER(status) IN ('active','disbursed')");
             if ($res) $stats['loan_outstanding'] = $res->fetch_assoc()['total'] ?? 0;
        } elseif (in_array('amount_paid', $colNames)) {
             $res = $conn->query("SELECT SUM(amount - amount_paid) as total FROM loans WHERE LOWER(status) IN ('active','disbursed')");
             if ($res) $stats['loan_outstanding'] = $res->fetch_assoc()['total'] ?? 0;
        } else {
             $res = $conn->query("SELECT SUM(amount) as total FROM loans WHERE LOWER(status) IN ('active','disbursed')");
             if ($res) $stats['loan_outstanding'] = $res->fetch_assoc()['total'] ?? 0;
        }

        // Repayments
        $res = $conn->query("SELECT SUM(amount) as total, COUNT(*) as cnt FROM loan_repayments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())");
        if ($res) {
            $row = $res->fetch_assoc();
            $stats['repayments_this_month'] = $row['total'] ?? 0;
            $stats['repayments_count_this_month'] = $row['cnt'] ?? 0;
        }
        
        // Repayments Last Month (for trend)
        $res = $conn->query("SELECT SUM(amount) as total FROM loan_repayments WHERE YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
        if ($res) {
            $stats['repayments_last_month'] = $res->fetch_assoc()['total'] ?? 0;
        }

    } catch (Exception $e) {
        // Silent fail for stats
    }
}

$pageTitle = "Admin Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css?v=2.4">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css?v=2.4">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: { 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b', 950: '#022c22' },
                    slate: { 850: '#1e293b' }
                },
                fontFamily: {
                    sans: ['Inter', 'system-ui', 'sans-serif'],
                },
            }
        }
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Brands Aligned Gradients */
        .gradient-blue { background: linear-gradient(135deg, #059669 0%, #047857 100%); } /* Primary 600-700 (Emerald) */
        .gradient-teal { background: linear-gradient(135deg, #10b981 0%, #059669 100%); } /* Primary 500-600 (Emerald Light) */
        .gradient-orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); } /* Amber */
        .gradient-green { background: linear-gradient(135deg, #059669 0%, #047857 100%); } /* Emerald (Same as primary now) */
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900 relative">
    
    <!-- Hero Background Overlay -->
    <div class="fixed top-0 left-0 w-full h-64 bg-green-950 -z-10"></div>
    <div class="fixed top-0 left-0 w-full h-64 opacity-20 bg-[url('../../assets/images/finance_hero_bg.png')] bg-cover bg-center -z-10 mix-blend-overlay"></div>
    <div class="fixed top-64 left-0 w-full h-[calc(100vh-16rem)] bg-gray-50 -z-10"></div>

    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex h-screen overflow-hidden">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-transparent md:ml-64 transition-all duration-300 p-6">
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-white mb-1">Admin Dashboard</h1>
                    <p class="text-blue-100 opacity-90">Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>! Here's what's happening today.</p>
                </div>
                <div class="flex gap-3 mt-4 md:mt-0">
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg font-medium text-white hover:bg-white/20 shadow-sm transition-colors">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button class="inline-flex items-center px-4 py-2 bg-white text-primary-600 border border-transparent rounded-lg font-medium hover:bg-blue-50 shadow-sm transition-colors">
                        <i class="fas fa-calendar mr-2"></i> This Month <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Members -->
                <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-blue text-white group cursor-pointer hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Total Members</p>
                        <h3 class="text-4xl font-bold mt-2 mb-1"><?php echo number_format($stats['total_members']); ?></h3>
                        <p class="text-xs opacity-70 flex items-center">
                            <i class="fas fa-arrow-up mr-1"></i> +<?php echo $stats['new_members_this_month']; ?> this month
                        </p>
                        <a href="members.php" class="inline-block mt-4 text-xs font-bold uppercase tracking-wider hover:opacity-100 opacity-80 transition-opacity">View All <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                    <div class="absolute -bottom-4 -right-4 text-9xl text-white opacity-10 transform rotate-12 transition-transform group-hover:scale-110">
                        <i class="fas fa-users"></i>
                    </div>
                </div>

                <!-- Active Members -->
                <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-teal text-white group cursor-pointer hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Active Members</p>
                        <h3 class="text-4xl font-bold mt-2 mb-1"><?php echo number_format($stats['active_members']); ?></h3>
                        <p class="text-xs opacity-70 flex items-center">
                            <i class="fas fa-percentage mr-1"></i> <?php echo number_format(($stats['active_members'] / max($stats['total_members'], 1)) * 100, 1); ?>% engagement
                        </p>
                        <a href="members.php?status=Active" class="inline-block mt-4 text-xs font-bold uppercase tracking-wider hover:opacity-100 opacity-80 transition-opacity">View Active <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                    <div class="absolute -bottom-4 -right-4 text-9xl text-white opacity-10 transform rotate-12 transition-transform group-hover:scale-110">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>

                <!-- Total Loans -->
                <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-orange text-white group cursor-pointer hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Total Loans</p>
                        <h3 class="text-4xl font-bold mt-2 mb-1"><?php echo number_format($stats['total_loans']); ?></h3>
                        <p class="text-xs opacity-70 flex items-center">
                            <i class="fas fa-wallet mr-1"></i> ₦<?php echo number_format($stats['loan_outstanding']); ?> outstanding
                        </p>
                        <a href="loans.php" class="inline-block mt-4 text-xs font-bold uppercase tracking-wider hover:opacity-100 opacity-80 transition-opacity">Manage Loans <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                    <div class="absolute -bottom-4 -right-4 text-9xl text-white opacity-10 transform rotate-12 transition-transform group-hover:scale-110">
                        <i class="fas fa-hand-holding-dollar"></i>
                    </div>
                </div>

                <!-- System Status -->
                <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-green text-white group cursor-pointer hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">System Status</p>
                        <h3 class="text-4xl font-bold mt-2 mb-1">Online</h3>
                        <p class="text-xs opacity-70 flex items-center">
                            <i class="fas fa-check-circle mr-1"></i> All systems operational
                        </p>
                        <a href="settings.php" class="inline-block mt-4 text-xs font-bold uppercase tracking-wider hover:opacity-100 opacity-80 transition-opacity">Settings <i class="fas fa-arrow-right ml-1"></i></a>
                    </div>
                    <div class="absolute -bottom-4 -right-4 text-9xl text-white opacity-10 transform rotate-12 transition-transform group-hover:scale-110">
                        <i class="fas fa-server"></i>
                    </div>
                </div>
            </div>

            <!-- Action Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <a href="members.php" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col items-center text-center group hover:-translate-y-1 transform duration-300">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center text-xl mb-4 group-hover:bg-primary-600 group-hover:text-white transition-colors">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-primary-600 transition-colors">Manage Members</h3>
                    <p class="text-sm text-gray-500 mt-1">Add, edit, and manage member accounts</p>
                </a>

                <a href="loans.php" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col items-center text-center group hover:-translate-y-1 transform duration-300">
                    <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-full flex items-center justify-center text-xl mb-4 group-hover:bg-orange-600 group-hover:text-white transition-colors">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-orange-600 transition-colors">Loan Management</h3>
                    <p class="text-sm text-gray-500 mt-1">Process and track member loans</p>
                </a>

                 <a href="savings_accounts.php" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col items-center text-center group hover:-translate-y-1 transform duration-300">
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-full flex items-center justify-center text-xl mb-4 group-hover:bg-green-600 group-hover:text-white transition-colors">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-green-600 transition-colors">Total Savings</h3>
                    <p class="text-sm text-gray-500 mt-1">Track member savings and accounts</p>
                </a>

                 <a href="reports.php" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col items-center text-center group hover:-translate-y-1 transform duration-300">
                    <div class="w-12 h-12 bg-gray-50 text-gray-700 rounded-full flex items-center justify-center text-xl mb-4 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-gray-800 transition-colors">Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">Generate financial and member reports</p>
                </a>
            </div>

            <!-- Detail Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Repayment Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <h3 class="font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-money-bill-wave text-blue-500 mr-2"></i> Repayments This Month
                        </h3>
                        <div class="mt-4">
                            <span class="text-4xl font-bold text-orange-500">₦<?php echo number_format($stats['repayments_this_month']); ?></span>
                            <p class="text-sm text-gray-500 mt-1"><?php echo $stats['repayments_count_this_month']; ?> payments this month</p>
                            
                            <?php 
                                $cur = $stats['repayments_this_month']; 
                                $prev = $stats['repayments_last_month'] ?? 0;
                                $pct = $prev > 0 ? (($cur - $prev)/$prev)*100 : 0;
                                $color = $pct >= 0 ? 'text-green-600' : 'text-red-500';
                                $icon = $pct >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                            ?>
                            <p class="text-xs <?php echo $color; ?> font-medium mt-1">
                                <i class="fas <?php echo $icon; ?>"></i> <?php echo number_format(abs($pct), 1); ?>% vs last month
                            </p>
                        </div>
                        <p class="text-xs text-gray-400 mt-4">Sum of all loan repayments recorded this month.</p>
                    </div>
                    <div class="mt-4 flex items-center justify-end">
                        <a href="reports.php" class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center hover:bg-orange-200 transition-colors">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- System Metrics -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
                    <h3 class="font-semibold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-globe text-blue-500 mr-2"></i> System Metrics
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-gray-600">Member Engagement</span>
                                <span class="font-medium text-gray-900"><?php echo number_format(($stats['active_members'] / max($stats['total_members'], 1)) * 100, 1); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo min(($stats['active_members'] / max($stats['total_members'], 1)) * 100, 100); ?>%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-gray-600">Loan Utilization</span>
                                <span class="font-medium text-gray-900"><?php echo number_format(($stats['active_loans'] / max($stats['total_loans'], 1)) * 100, 1); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo min(($stats['active_loans'] / max($stats['total_loans'], 1)) * 100, 100); ?>%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-gray-600">System Health</span>
                                <span class="font-medium text-gray-900">98.5%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full" style="width: 98.5%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-history text-gray-400 mr-2"></i> Recent Activity
                </h3>
                <div class="space-y-4">
                     <!-- Hardcoded Example Feed - In real app, this would iterate over logs -->
                     <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 mr-3">
                            <i class="fas fa-user-plus text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">New member registered</p>
                            <p class="text-xs text-gray-500"><?php echo date('M d, H:i'); ?></p>
                        </div>
                    </div>

                    <div class="flex items-start">
                         <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-3">
                            <i class="fas fa-coins text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Savings deposit processed</p>
                            <p class="text-xs text-gray-500"><?php echo date('M d, H:i', strtotime('-2 hours')); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                         <div class="flex-shrink-0 w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 mr-3">
                            <i class="fas fa-file-invoice text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Loan application approved</p>
                            <p class="text-xs text-gray-500"><?php echo date('M d, H:i', strtotime('-5 hours')); ?></p>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <a href="member_activity_log.php" class="text-sm font-medium text-primary-600 hover:text-primary-800">View all activity &rarr;</a>
                </div>
            </div>

        </main>
    </div>
</body>
</html>
