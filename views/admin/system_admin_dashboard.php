<?php
session_start();
require_once '../../config/database.php';
require_once '../../controllers/system_admin_controller.php';

// Check if admin is logged in and has super_admin role
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

// Check if user has system administration permissions
if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin'])) {
    header('Location: admin_dashboard.php?error=insufficient_permissions');
    exit();
}

$systemAdminController = new SystemAdminController();
$admin_id = $_SESSION['admin_id'];

// Handle form submissions
$errors = [];
$success = false;
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_admin':
            $admin_data = [
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'role' => $_POST['role'],
                'status' => $_POST['status'],
                'created_by' => $admin_id
            ];
            
            $new_admin_id = $systemAdminController->createAdminUser($admin_data);
            if ($new_admin_id) {
                $success = true;
                $action_message = 'Admin user created successfully!';
                
                // Log the activity
                $systemAdminController->logSecurityEvent([
                    'event_type' => 'system_change',
                    'user_id' => $admin_id,
                    'user_type' => 'admin',
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'event_description' => 'New admin user created: ' . $admin_data['email'],
                    'event_data' => json_encode(['new_admin_id' => $new_admin_id, 'role' => $admin_data['role']]),
                    'severity' => 'high'
                ]);
            } else {
                $errors[] = 'Failed to create admin user.';
            }
            break;
            
        case 'update_settings':
            $settings_updated = 0;
            foreach ($_POST['settings'] as $category => $category_settings) {
                foreach ($category_settings as $key => $value) {
                    if ($systemAdminController->updateSystemSetting($category, $key, $value, $admin_id)) {
                        $settings_updated++;
                    }
                }
            }
            
            if ($settings_updated > 0) {
                $success = true;
                $action_message = "$settings_updated system settings updated successfully!";
                
                // Log the activity
                $systemAdminController->logSecurityEvent([
                    'event_type' => 'system_change',
                    'user_id' => $admin_id,
                    'user_type' => 'admin',
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'event_description' => 'System settings updated',
                    'event_data' => json_encode(['settings_updated' => $settings_updated]),
                    'severity' => 'medium'
                ]);
            } else {
                $errors[] = 'Failed to update system settings.';
            }
            break;
            
        case 'create_backup':
            $backup_result = $systemAdminController->createDatabaseBackup();
            if ($backup_result['success']) {
                $success = true;
                $action_message = 'Database backup created successfully! File: ' . $backup_result['backup_file'];
            } else {
                $errors[] = 'Failed to create backup: ' . $backup_result['error'];
            }
            break;
            
        case 'cleanup_logs':
            $days_to_keep = (int)$_POST['days_to_keep'];
            $cleanup_result = $systemAdminController->cleanupSystemLogs($days_to_keep);
            if ($cleanup_result['success']) {
                $success = true;
                $action_message = 'System cleanup completed! Deleted ' . $cleanup_result['audit_logs_deleted'] . ' audit logs and ' . $cleanup_result['messages_deleted'] . ' old messages.';
            } else {
                $errors[] = 'Failed to cleanup system logs: ' . $cleanup_result['error'];
            }
            break;
    }
}

// Get dashboard data
$admin_users = $systemAdminController->getAllAdminUsers(50);
$system_settings = $systemAdminController->getSystemSettings();
$audit_stats = $systemAdminController->getAuditStatistics('30_days');
$recent_audit_logs = $systemAdminController->getSecurityAuditLogs([], 20);
$system_info = $systemAdminController->getSystemInfo();
$database_stats = $systemAdminController->getDatabaseStats();

// Get filter parameters
$audit_filters = [
    'event_type' => $_GET['event_type'] ?? '',
    'severity' => $_GET['severity'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administration - NPC CTLStaff Loan Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 shadow-xl" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
            <div class="flex flex-col h-full p-6">
                <h4 class="text-white text-xl font-bold mb-6">
                    <i class="fas fa-university mr-2"></i> System Admin
                </h4>
                
                <nav class="flex-1 space-y-2">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="manage_members.php">
                        <i class="fas fa-users mr-3"></i> Members
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="manage_loans.php">
                        <i class="fas fa-money-bill-wave mr-3"></i> Loans
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="reports_dashboard.php">
                        <i class="fas fa-chart-bar mr-3"></i> Reports
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="communication_dashboard.php">
                        <i class="fas fa-bullhorn mr-3"></i> Communication
                    </a>
                    <a class="flex items-center px-4 py-3 text-white bg-white/20 rounded-lg font-medium" href="system_admin_dashboard.php">
                        <i class="fas fa-cog mr-3"></i> System Admin
                    </a>
                </nav>
                
                <div class="mt-auto">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="admin_logout.php">
                        <i class="fas fa-sign-out-alt mr-3"></i> Logout
                    </a>
                </div>
            </div>
        </div>
            
        <!-- Main Content -->
        <div class="flex-1 overflow-hidden">
            <div class="p-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-cog mr-3 text-primary-600"></i> System Administration
                        </h1>
                        <p class="text-gray-600 mt-2">Manage users, system settings, security, and maintenance</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="showCreateAdminModal()" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 transition-colors duration-200 shadow-lg">
                            <i class="fas fa-user-plus mr-2"></i> Add Admin
                        </button>
                        <button onclick="showBackupModal()" class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors duration-200 shadow-lg">
                            <i class="fas fa-database mr-2"></i> Backup
                        </button>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Success!</h3>
                                <p class="mt-2 text-sm text-green-700"><?php echo htmlspecialchars($action_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Admin Users</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo count($admin_users); ?></p>
                                <p class="text-sm text-gray-500">Active administrators</p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users-cog text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Database Size</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $database_stats['database_size_mb']; ?> MB</p>
                                <p class="text-sm text-gray-500">Total database size</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-database text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-yellow-600 uppercase tracking-wider mb-2">Security Events</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $audit_stats['total_events']; ?></p>
                                <p class="text-sm text-gray-500">Last 30 days</p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-shield-alt text-2xl text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">System Health</p>
                                <p class="text-2xl font-bold text-gray-800">
                                    <?php 
                                    $free_space_percent = round((intval($system_info['disk_free_space']) / intval($system_info['disk_total_space'])) * 100, 1);
                                    echo $free_space_percent; 
                                    ?>%
                                </p>
                                <p class="text-sm text-gray-500">Free disk space</p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-heartbeat text-2xl text-red-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button onclick="showTab('users')" class="tab-button active border-b-2 border-primary-500 py-2 px-1 text-sm font-medium text-primary-600">
                                <i class="fas fa-users-cog mr-2"></i> Admin Users (<?php echo count($admin_users); ?>)
                            </button>
                            <button onclick="showTab('settings')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-sliders-h mr-2"></i> System Settings
                            </button>
                            <button onclick="showTab('security')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-shield-alt mr-2"></i> Security Audit
                            </button>
                            <button onclick="showTab('maintenance')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-tools mr-2"></i> Maintenance
                            </button>
                            <button onclick="showTab('system')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-info-circle mr-2"></i> System Info
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Tab Contents -->
                
                <!-- Admin Users Tab -->
                <div id="users-tab" class="tab-content">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-users-cog mr-3 text-primary-600"></i> Admin Users Management
                                </h3>
                                <button onclick="showCreateAdminModal()" class="text-primary-600 hover:text-primary-700 flex items-center text-sm">
                                    <i class="fas fa-plus mr-2"></i> Add New Admin
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Email</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Role</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Last Login</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($admin_users as $user): ?>
                                            <tr>
                                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td class="px-4 py-3"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                        echo match($user['role']) {
                                                            'super_admin' => 'bg-red-100 text-red-800',
                                                            'admin' => 'bg-blue-100 text-blue-800',
                                                            'manager' => 'bg-green-100 text-green-800',
                                                            'staff' => 'bg-gray-100 text-gray-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                        echo match($user['status']) {
                                                            'Active' => 'bg-green-100 text-green-800',
                                                            'Inactive' => 'bg-red-100 text-red-800',
                                                            'Suspended' => 'bg-yellow-100 text-yellow-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    ?>">
                                                        <?php echo $user['status']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3"><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                                <td class="px-4 py-3">
                                                    <div class="flex space-x-2">
                                                        <button class="text-primary-600 hover:text-primary-700" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="text-yellow-600 hover:text-yellow-700" title="Permissions">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                        <?php if ($user['admin_id'] != $admin_id): ?>
                                                            <button class="text-red-600 hover:text-red-700" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings Tab -->
                <div id="settings-tab" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-sliders-h mr-3 text-primary-600"></i> System Settings
                            </h3>
                        </div>
                        <div class="p-6">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_settings">
                                
                                <div class="space-y-8">
                                    <?php foreach ($system_settings as $category => $settings): ?>
                                        <div class="border border-gray-200 rounded-lg p-6">
                                            <h4 class="text-lg font-semibold text-gray-900 mb-4 capitalize">
                                                <?php echo str_replace('_', ' ', $category); ?> Settings
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <?php foreach ($settings as $setting): ?>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                                            <?php echo ucfirst(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                            <?php if (!empty($setting['description'])): ?>
                                                                <span class="text-gray-500 font-normal">- <?php echo $setting['description']; ?></span>
                                                            <?php endif; ?>
                                                        </label>
                                                        <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                            <select name="settings[<?php echo $category; ?>][<?php echo $setting['setting_key']; ?>]" 
                                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                                                <option value="1" <?php echo $setting['setting_value'] ? 'selected' : ''; ?>>Yes</option>
                                                                <option value="0" <?php echo !$setting['setting_value'] ? 'selected' : ''; ?>>No</option>
                                                            </select>
                                                        <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                            <input type="number" 
                                                                   name="settings[<?php echo $category; ?>][<?php echo $setting['setting_key']; ?>]" 
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                                        <?php elseif ($setting['setting_type'] === 'email'): ?>
                                                            <input type="email" 
                                                                   name="settings[<?php echo $category; ?>][<?php echo $setting['setting_key']; ?>]" 
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                                        <?php else: ?>
                                                            <input type="text" 
                                                                   name="settings[<?php echo $category; ?>][<?php echo $setting['setting_key']; ?>]" 
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-6 flex justify-end">
                                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                                        <i class="fas fa-save mr-2"></i> Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Audit Tab -->
                <div id="security-tab" class="tab-content hidden">
                    <div class="space-y-6">
                        <!-- Security Statistics -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-chart-pie mr-3 text-primary-600"></i> Security Statistics (Last 30 Days)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-gray-800"><?php echo $audit_stats['total_events']; ?></div>
                                    <div class="text-sm text-gray-500">Total Security Events</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-red-600">
                                        <?php 
                                        $critical_high = array_sum(array_column($audit_stats['event_types'], 'critical_count')) + 
                                                        array_sum(array_column($audit_stats['event_types'], 'high_count'));
                                        echo $critical_high;
                                        ?>
                                    </div>
                                    <div class="text-sm text-gray-500">Critical/High Severity</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">
                                        <?php echo count(array_unique(array_column($recent_audit_logs, 'user_id'))); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">Unique Users</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Audit Logs -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-shield-alt mr-3 text-primary-600"></i> Recent Security Events
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Event Type</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">User</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Description</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Severity</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($recent_audit_logs as $log): ?>
                                                <tr>
                                                    <td class="px-4 py-3"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            <?php echo ucfirst(str_replace('_', ' ', $log['event_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div>
                                                            <div class="font-medium"><?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?></div>
                                                            <?php if (!empty($log['user_email'])): ?>
                                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['user_email']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3"><?php echo htmlspecialchars($log['event_description']); ?></td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                            echo match($log['severity']) {
                                                                'critical' => 'bg-red-100 text-red-800',
                                                                'high' => 'bg-orange-100 text-orange-800',
                                                                'medium' => 'bg-yellow-100 text-yellow-800',
                                                                'low' => 'bg-green-100 text-green-800',
                                                                default => 'bg-gray-100 text-gray-800'
                                                            };
                                                        ?>">
                                                            <?php echo ucfirst($log['severity']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 font-mono text-xs"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Tab -->
                <div id="maintenance-tab" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Database Backup -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-database mr-3 text-green-600"></i> Database Backup
                            </h3>
                            <p class="text-gray-600 mb-4">Create a backup of the entire database including all tables and data.</p>
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-sm text-gray-600 space-y-2">
                                        <div class="flex justify-between">
                                            <span>Database Size:</span>
                                            <span class="font-medium"><?php echo $database_stats['database_size_mb']; ?> MB</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Total Tables:</span>
                                            <span class="font-medium"><?php echo count($database_stats['tables']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="showBackupModal()" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    <i class="fas fa-download mr-2"></i> Create Backup Now
                                </button>
                            </div>
                        </div>
                        
                        <!-- System Cleanup -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-broom mr-3 text-yellow-600"></i> System Cleanup
                            </h3>
                            <p class="text-gray-600 mb-4">Clean up old log files and temporary data to free up disk space.</p>
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-sm text-gray-600 space-y-2">
                                        <div class="flex justify-between">
                                            <span>Security Logs:</span>
                                            <span class="font-medium"><?php echo $audit_stats['total_events']; ?> events</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Free Space:</span>
                                            <span class="font-medium"><?php echo round(intval($system_info['disk_free_space']) / (1024*1024*1024), 2); ?> GB</span>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="showCleanupModal()" class="w-full px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                                    <i class="fas fa-trash mr-2"></i> Cleanup Old Logs
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Info Tab -->
                <div id="system-tab" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Server Information -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-server mr-3 text-blue-600"></i> Server Information
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">PHP Version:</span>
                                    <span class="font-medium"><?php echo $system_info['php_version']; ?></span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Server Software:</span>
                                    <span class="font-medium"><?php echo $system_info['server_software']; ?></span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Database Version:</span>
                                    <span class="font-medium"><?php echo $system_info['database_version']; ?></span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Memory Limit:</span>
                                    <span class="font-medium"><?php echo $system_info['memory_limit']; ?></span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Max Upload Size:</span>
                                    <span class="font-medium"><?php echo $system_info['upload_max_filesize']; ?></span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Timezone:</span>
                                    <span class="font-medium"><?php echo $system_info['timezone']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Database Statistics -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-database mr-3 text-green-600"></i> Database Statistics
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Database Size:</span>
                                    <span class="font-medium"><?php echo $database_stats['database_size_mb']; ?> MB</span>
                                </div>
                                <?php foreach ($database_stats['tables'] as $table => $count): ?>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600"><?php echo ucfirst($table); ?>:</span>
                                        <span class="font-medium"><?php echo number_format($count); ?> records</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Admin Modal -->
    <div id="createAdminModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_admin">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Create New Admin User</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="last_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password" id="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role <span class="text-red-500">*</span></label>
                                <select name="role" id="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                    <option value="staff">Staff</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                    <option value="super_admin">Super Admin</option>
                                </select>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                                <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeCreateAdminModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            Create Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Backup Modal -->
    <div id="backupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-md w-full">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_backup">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Create Database Backup</h3>
                    </div>
                    <div class="p-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <p class="text-sm text-yellow-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                This will create a complete backup of the database. The process may take a few minutes.
                            </p>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600 mb-2">Database Size: <span class="font-medium"><?php echo $database_stats['database_size_mb']; ?> MB</span></p>
                                <p class="text-sm text-gray-600">Backup Location: <code>../backups/</code></p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeBackupModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i> Create Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cleanup Modal -->
    <div id="cleanupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-md w-full">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="cleanup_logs">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">System Cleanup</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <p class="text-sm text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                This will permanently delete old log files. Critical and high severity logs will be preserved.
                            </p>
                        </div>
                        <div>
                            <label for="days_to_keep" class="block text-sm font-medium text-gray-700 mb-2">Keep logs for (days):</label>
                            <input type="number" name="days_to_keep" id="days_to_keep" value="90" min="30" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <p class="text-xs text-gray-500 mt-1">Minimum: 30 days, Maximum: 365 days</p>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeCleanupModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                            <i class="fas fa-trash mr-2"></i> Cleanup Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-primary-500', 'text-primary-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            const activeButton = document.querySelector(`[onclick="showTab('${tabName}')"]`);
            activeButton.classList.add('active', 'border-primary-500', 'text-primary-600');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        // Modal functions
        function showCreateAdminModal() {
            document.getElementById('createAdminModal').classList.remove('hidden');
        }

        function closeCreateAdminModal() {
            document.getElementById('createAdminModal').classList.add('hidden');
        }

        function showBackupModal() {
            document.getElementById('backupModal').classList.remove('hidden');
        }

        function closeBackupModal() {
            document.getElementById('backupModal').classList.add('hidden');
        }

        function showCleanupModal() {
            document.getElementById('cleanupModal').classList.remove('hidden');
        }

        function closeCleanupModal() {
            document.getElementById('cleanupModal').classList.add('hidden');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showTab('users');
        });
    </script>
</body>
</html>
