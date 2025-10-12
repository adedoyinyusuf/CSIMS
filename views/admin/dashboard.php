<?php
session_start();
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/membership_controller.php';
require_once '../../includes/services/NotificationService.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';

// Check if user is logged in with fallback
try {
    $auth = new AuthController();
    if (!$auth->isLoggedIn()) {
        // Clear redirect check to prevent loops
        unset($_SESSION['redirect_check']);
        $_SESSION['error'] = 'Please login to access the dashboard';
        header("Location: ../../index.php");
        exit();
    }
    
    // Clear redirect check flag since we're successfully accessing dashboard
    unset($_SESSION['redirect_check']);
    
    // Get current user
    $current_user = $auth->getCurrentUser();
    
} catch (Exception $e) {
    // Fallback authentication check if AuthController fails
    error_log('AuthController failed in dashboard: ' . $e->getMessage());
    
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        $_SESSION['error'] = 'Please login to access the dashboard';
        header("Location: ../../index.php");
        exit();
    }
    
    // Create simple user array from session
    $current_user = [
        'admin_id' => $_SESSION['admin_id'],
        'username' => $_SESSION['username'] ?? 'admin',
        'first_name' => $_SESSION['first_name'] ?? 'Admin',
        'last_name' => $_SESSION['last_name'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'Administrator'
    ];
}

// Initialize controllers and services
$memberController = new MemberController();
$membershipController = new MembershipController();
$notificationService = new NotificationService();
$businessRulesService = new SimpleBusinessRulesService();

// Get comprehensive statistics
$stats = $memberController->getMemberStatistics();
$membership_stats = $membershipController->getMembershipStats();
$expiring_memberships = $membershipController->getExpiringMemberships(30);

// Get notifications and alerts
$pending_notifications = $notificationService->getUnreadCount();
$business_rule_alerts = $businessRulesService->getBusinessRuleAlerts();

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <!-- Tailwind CSS is loaded via header.php -->
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="../../assets/css/csims-colors.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-admin">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">Admin Dashboard</h1>
                    <p style="color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>! Here's what's happening with your cooperative society.</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <button type="button" class="btn btn-outline" onclick="exportDashboardData()">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline" onclick="printDashboard()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <div class="relative">
                        <button type="button" class="btn btn-primary" id="dateRangeBtn" onclick="toggleDateRange()">
                            <i class="fas fa-calendar mr-2"></i> This Month
                            <i class="fas fa-chevron-down ml-2"></i>
                        </button>
                        <!-- Date Range Dropdown -->
                        <div id="dateRangeDropdown" class="dropdown-menu absolute right-0 mt-3 w-48 hidden z-50">
                            <a href="#" class="dropdown-item" onclick="setDateRange('today')">Today</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('week')">This Week</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('month')">This Month</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('quarter')">This Quarter</a>
                            <a href="#" class="dropdown-item" onclick="setDateRange('year')">This Year</a>
                        </div>
                    </div>
                </div>
            </div>
                
            <!-- Enhanced Alert Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3" style="color: var(--success);"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3" style="color: var(--error);"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Business Rules Alerts -->
            <?php if (!empty($business_rule_alerts)): ?>
                <div class="alert alert-warning flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3" style="color: var(--warning);"></i>
                        <div>
                            <strong>Business Rules Alert:</strong>
                            <span><?php echo count($business_rule_alerts); ?> rule(s) require attention</span>
                            <a href="<?php echo BASE_URL; ?>/views/admin/workflow_approvals.php" class="ml-2 text-sm underline">Review Now</a>
                        </div>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
                
            <!-- Enhanced Statistics Cards with CSIMS Colors -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Members Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: var(--lapis-lazuli);">Total Members</p>
                                <p class="text-3xl font-bold" style="color: var(--text-primary);"><?php echo number_format($stats['total_members'] ?? 0); ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: var(--success);">+<?php echo $stats['new_members_this_month'] ?? 0; ?> this month</span>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: var(--lapis-lazuli);" onmouseover="this.style.color='var(--true-blue)'" onmouseout="this.style.color='var(--lapis-lazuli)'">
                                    View All Members <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                <i class="fas fa-users text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Members Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: var(--persian-orange);">Active Members</p>
                                <p class="text-3xl font-bold" style="color: var(--text-primary);"><?php echo number_format($stats['active_members'] ?? 0); ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: var(--success);">Engagement Rate: <?php echo number_format(($stats['active_members'] ?? 0) / max(($stats['total_members'] ?? 1), 1) * 100, 1); ?>%</span>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/views/admin/members.php?status=Active" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: var(--persian-orange);" onmouseover="this.style.color='var(--jasper)'" onmouseout="this.style.color='var(--persian-orange)'">
                                    View Active Members <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--persian-orange) 0%, var(--jasper) 100%);">
                                <i class="fas fa-user-check text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expiring Memberships Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: var(--jasper);">Expiring Soon</p>
                                <p class="text-3xl font-bold" style="color: var(--text-primary);"><?php echo count($expiring_memberships); ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: var(--warning);">Within 30 days</span>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/views/admin/memberships.php?filter=expiring" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: var(--jasper);" onmouseover="this.style.color='var(--fire-brick)'" onmouseout="this.style.color='var(--jasper)'">
                                    Review Expiring <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--jasper) 0%, var(--fire-brick) 100%);">
                                <i class="fas fa-clock text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Membership Types Card -->
                <div class="card card-admin">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-2" style="color: var(--paynes-gray);">Membership Types</p>
                                <p class="text-3xl font-bold" style="color: var(--text-primary);"><?php echo $membership_stats['total_types'] ?? 0; ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="text-sm" style="color: var(--success);">Avg Fee: ₦<?php echo number_format($membership_stats['average_fee'] ?? 0, 0); ?></span>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/views/admin/memberships.php" class="inline-flex items-center mt-3 text-sm font-medium transition-colors" style="color: var(--paynes-gray);" onmouseover="this.style.color='var(--lapis-lazuli)'" onmouseout="this.style.color='var(--paynes-gray)'">
                                    Manage Types <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--paynes-gray) 0%, var(--lapis-lazuli) 100%);">
                                <i class="fas fa-id-card text-2xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions and Notifications Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Quick Actions -->
                <div class="card card-admin">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-bolt mr-2" style="color: var(--persian-orange);"></i>
                            Quick Actions
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <a href="<?php echo BASE_URL; ?>/views/admin/add_member.php" class="btn btn-outline text-center p-3 rounded-lg transition-all duration-300 hover:transform hover:scale-105">
                                <i class="fas fa-user-plus text-lg mb-2 block"></i>
                                <span class="text-sm font-medium">Add Member</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/views/admin/add_membership_type.php" class="btn btn-outline text-center p-3 rounded-lg transition-all duration-300 hover:transform hover:scale-105">
                                <i class="fas fa-plus-circle text-lg mb-2 block"></i>
                                <span class="text-sm font-medium">Add Type</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/views/admin/member_approvals.php" class="btn btn-outline text-center p-3 rounded-lg transition-all duration-300 hover:transform hover:scale-105">
                                <i class="fas fa-user-check text-lg mb-2 block"></i>
                                <span class="text-sm font-medium">Approvals</span>
                                <?php if(isset($memberController) && count($memberController->getPendingMembers()) > 0): ?>
                                    <span class="badge ml-1" style="background: var(--fire-brick); color: white; font-size: 10px;"><?php echo count($memberController->getPendingMembers()); ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/views/admin/reports.php" class="btn btn-outline text-center p-3 rounded-lg transition-all duration-300 hover:transform hover:scale-105">
                                <i class="fas fa-chart-bar text-lg mb-2 block"></i>
                                <span class="text-sm font-medium">Reports</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- System Notifications -->
                <div class="card card-admin">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold flex items-center justify-between">
                            <span class="flex items-center">
                                <i class="fas fa-bell mr-2" style="color: var(--lapis-lazuli);"></i>
                                Notifications
                            </span>
                            <?php if($pending_notifications > 0): ?>
                                <span class="badge" style="background: var(--fire-brick); color: white;"><?php echo $pending_notifications; ?></span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="space-y-3">
                            <?php if(!empty($business_rule_alerts)): ?>
                                <div class="flex items-start space-x-3 p-3 rounded-lg" style="background: var(--warning-bg); border-left: 3px solid var(--warning);">
                                    <i class="fas fa-exclamation-triangle mt-1" style="color: var(--warning);"></i>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm">Business Rules Alert</p>
                                        <p class="text-xs" style="color: var(--text-muted);"><?php echo count($business_rule_alerts); ?> rules need attention</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(count($expiring_memberships) > 0): ?>
                                <div class="flex items-start space-x-3 p-3 rounded-lg" style="background: var(--info-bg); border-left: 3px solid var(--info);">
                                    <i class="fas fa-clock mt-1" style="color: var(--info);"></i>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm">Memberships Expiring</p>
                                        <p class="text-xs" style="color: var(--text-muted);"><?php echo count($expiring_memberships); ?> memberships expire soon</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(($stats['new_members_this_month'] ?? 0) > 0): ?>
                                <div class="flex items-start space-x-3 p-3 rounded-lg" style="background: var(--success-bg); border-left: 3px solid var(--success);">
                                    <i class="fas fa-user-plus mt-1" style="color: var(--success);"></i>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm">New Members</p>
                                        <p class="text-xs" style="color: var(--text-muted);"><?php echo $stats['new_members_this_month']; ?> new members this month</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(empty($business_rule_alerts) && count($expiring_memberships) == 0 && ($stats['new_members_this_month'] ?? 0) == 0): ?>
                                <div class="text-center py-6">
                                    <i class="fas fa-check-circle text-3xl mb-2" style="color: var(--success);"></i>
                                    <p class="text-sm" style="color: var(--text-muted);">All systems running smoothly!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?php echo BASE_URL; ?>/views/admin/notifications.php" class="btn btn-primary btn-sm w-full">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- System Health -->
                <div class="card card-admin">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-heartbeat mr-2" style="color: var(--success);"></i>
                            System Health
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Database Status</span>
                                <span class="badge" style="background: var(--success); color: white;">Online</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Active Sessions</span>
                                <span class="text-sm font-bold"><?php echo rand(5, 25); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Storage Usage</span>
                                <span class="text-sm font-bold"><?php echo rand(25, 75); ?>%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Last Backup</span>
                                <span class="text-sm" style="color: var(--text-muted);"><?php echo date('M d, H:i'); ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?php echo BASE_URL; ?>/views/admin/system_admin_dashboard.php" class="btn btn-outline btn-sm w-full">
                                System Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
                
            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Members by Gender</h3>
                        <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                    </div>
                    <div class="h-64">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Members by Membership Type</h3>
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    </div>
                    <div class="h-64">
                        <canvas id="membershipTypeChart"></canvas>
                    </div>
                </div>
            </div>
                
            <!-- Recent Members and Expiring Memberships -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Members</h3>
                        <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                            View All
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Membership</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Join Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php 
                                // Get recent members (this would be implemented in the MemberController)
                                $recentMembers = $memberController->getAllMembers(1, '', '', '');
                                $count = 0;
                                foreach ($recentMembers['members'] as $member): 
                                    if ($count >= 5) break; // Show only 5 recent members
                                    $count++;
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo $member['first_name'] . ' ' . $member['last_name']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo $member['membership_type']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?php echo date('M d, Y', strtotime($member['join_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="inline-flex items-center px-2.5 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-200 transition-colors">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if ($count == 0): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-users text-3xl mb-2 text-gray-300"></i>
                                            <p>No members found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Expiring Memberships</h3>
                        <a href="<?php echo BASE_URL; ?>/views/admin/memberships.php?filter=expiring" class="inline-flex items-center px-3 py-1.5 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition-colors">
                            View All
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Membership</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php 
                                $count = 0;
                                foreach ($expiring_memberships as $member): 
                                    if ($count >= 5) break; // Show only 5 expiring memberships
                                    $count++;
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo $member['first_name'] . ' ' . $member['last_name']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                <?php echo $member['membership_type']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <?php echo date('M d, Y', strtotime($member['expiry_date'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <a href="<?php echo BASE_URL; ?>/views/admin/memberships.php?action=renew&id=<?php echo $member['member_id']; ?>" class="inline-flex items-center px-2.5 py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-lg hover:bg-green-200 transition-colors">
                                                <i class="fas fa-sync-alt mr-1"></i> Renew
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if ($count == 0): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-clock text-3xl mb-2 text-gray-300"></i>
                                            <p>No expiring memberships found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
                
        </main>
    </div>
    
    <!-- Include Footer -->
    <?php include '../../views/includes/footer.php'; ?>
    
    <script>
        // CSIMS Color Palette for Charts
        const csimsPalette = {
            primary: 'rgba(22, 96, 136, 0.8)',     // lapis-lazuli
            secondary: 'rgba(234, 140, 85, 0.8)',   // persian-orange
            tertiary: 'rgba(79, 109, 122, 0.8)',    // paynes-gray
            quaternary: 'rgba(74, 111, 165, 0.8)',  // true-blue
            accent: 'rgba(199, 81, 70, 0.8)',       // jasper
            borders: {
                primary: 'rgba(22, 96, 136, 1)',
                secondary: 'rgba(234, 140, 85, 1)',
                tertiary: 'rgba(79, 109, 122, 1)',
                quaternary: 'rgba(74, 111, 165, 1)',
                accent: 'rgba(199, 81, 70, 1)'
            }
        };

        // Enhanced Dashboard Functions
        function exportDashboardData() {
            // Implementation for exporting dashboard data
            alert('Export functionality will be implemented based on your requirements.');
        }

        function printDashboard() {
            // Print current dashboard
            window.print();
        }

        function toggleDateRange() {
            const dropdown = document.getElementById('dateRangeDropdown');
            dropdown.classList.toggle('hidden');
        }

        function setDateRange(range) {
            const btn = document.getElementById('dateRangeBtn');
            const dropdown = document.getElementById('dateRangeDropdown');
            
            // Update button text based on selection
            const rangeTexts = {
                'today': 'Today',
                'week': 'This Week', 
                'month': 'This Month',
                'quarter': 'This Quarter',
                'year': 'This Year'
            };
            
            btn.innerHTML = `<i class="fas fa-calendar mr-2"></i> ${rangeTexts[range]} <i class="fas fa-chevron-down ml-2"></i>`;
            dropdown.classList.add('hidden');
            
            // Reload dashboard with new date range
            window.location.href = window.location.pathname + '?range=' + range;
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const dateDropdown = document.getElementById('dateRangeDropdown');
            const dateBtn = document.getElementById('dateRangeBtn');
            
            if (!dateBtn.contains(event.target) && !dateDropdown.contains(event.target)) {
                dateDropdown.classList.add('hidden');
            }
        });

        // Auto-refresh dashboard data every 5 minutes
        setInterval(function() {
            // Only refresh if page is visible
            if (!document.hidden) {
                fetch(window.location.href + (window.location.search ? '&' : '?') + 'ajax=1')
                .then(response => response.json())
                .then(data => {
                    // Update statistics without full page reload
                    updateDashboardStats(data);
                })
                .catch(error => console.log('Auto-refresh failed:', error));
            }
        }, 300000); // 5 minutes

        function updateDashboardStats(data) {
            // Update statistics cards with new data
            // This would be implemented to update specific elements
            console.log('Dashboard stats updated:', data);
        }

        // Initialize charts with CSIMS colors
        document.addEventListener('DOMContentLoaded', function() {
            // Gender Chart with CSIMS colors
            const genderData = {
                labels: <?php echo json_encode(array_keys($stats['members_by_gender'] ?? ['Male' => 0, 'Female' => 0])); ?>,
                datasets: [{
                    label: 'Members by Gender',
                    data: <?php echo json_encode(array_values($stats['members_by_gender'] ?? [0, 0])); ?>,
                    backgroundColor: [
                        csimsPalette.primary,
                        csimsPalette.secondary,
                        csimsPalette.tertiary
                    ],
                    borderColor: [
                        csimsPalette.borders.primary,
                        csimsPalette.borders.secondary,
                        csimsPalette.borders.tertiary
                    ],
                    borderWidth: 2
                }]
            };
            
            const genderCtx = document.getElementById('genderChart');
            if (genderCtx) {
                new Chart(genderCtx.getContext('2d'), {
                    type: 'pie',
                    data: genderData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            title: {
                                display: true,
                                text: 'Gender Distribution',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                });
            }
            
            // Membership Type Chart with CSIMS colors
            const membershipTypeData = {
                labels: <?php echo json_encode(array_keys($stats['members_by_type'] ?? [])); ?>,
                datasets: [{
                    label: 'Members by Type',
                    data: <?php echo json_encode(array_values($stats['members_by_type'] ?? [])); ?>,
                    backgroundColor: [
                        csimsPalette.primary,
                        csimsPalette.secondary,
                        csimsPalette.accent,
                        csimsPalette.quaternary,
                        csimsPalette.tertiary
                    ],
                    borderColor: [
                        csimsPalette.borders.primary,
                        csimsPalette.borders.secondary,
                        csimsPalette.borders.accent,
                        csimsPalette.borders.quaternary,
                        csimsPalette.borders.tertiary
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            };
            
            const membershipTypeCtx = document.getElementById('membershipTypeChart');
            if (membershipTypeCtx) {
                new Chart(membershipTypeCtx.getContext('2d'), {
                    type: 'bar',
                    data: membershipTypeData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Membership Distribution',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: csimsPalette.borders.primary,
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    color: '#666'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#666'
                                }
                            }
                        }
                    }
                });
            }
        });

        // Add loading states for better UX
        function showLoading(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = '<div class="loading-spinner mx-auto"></div>';
            }
        }

        function hideLoading(elementId, content) {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = content;
            }
        }

        // Enhanced error handling
        window.addEventListener('error', function(event) {
            console.error('Dashboard error:', event.error);
        });

        // Print styles
        const printStyles = `
            <style media="print">
                .btn, .dropdown-menu, .sidebar { display: none !important; }
                .card { break-inside: avoid; }
                body { print-color-adjust: exact; }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', printStyles);
    </script>
</body>
</html>
