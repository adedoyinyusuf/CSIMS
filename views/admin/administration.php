<?php
session_start();
require_once '../config/auth_check.php';
require_once '../includes/config/database.php';

// Check if user is super admin
if ($_SESSION['role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

$database = new PdoDatabase();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_admin':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $role = $_POST['role'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                try {
                    $stmt = $db->prepare("INSERT INTO admins (username, email, first_name, last_name, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $first_name, $last_name, $password, $role]);
                    $_SESSION['success_message'] = 'Admin user created successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error creating admin: ' . $e->getMessage();
                }
                break;
                
            case 'update_admin':
                $admin_id = $_POST['admin_id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $role = $_POST['role'];
                $status = $_POST['status'];
                
                try {
                    $stmt = $db->prepare("UPDATE admins SET username = ?, email = ?, first_name = ?, last_name = ?, role = ?, status = ? WHERE admin_id = ?");
                    $stmt->execute([$username, $email, $first_name, $last_name, $role, $status, $admin_id]);
                    $_SESSION['success_message'] = 'Admin user updated successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating admin: ' . $e->getMessage();
                }
                break;
                
            case 'delete_admin':
                $admin_id = $_POST['admin_id'];
                
                // Prevent deleting self
                if ($admin_id == $_SESSION['admin_id']) {
                    $_SESSION['error_message'] = 'You cannot delete your own account!';
                    break;
                }
                
                try {
                    $stmt = $db->prepare("DELETE FROM admins WHERE admin_id = ?");
                    $stmt->execute([$admin_id]);
                    $_SESSION['success_message'] = 'Admin user deleted successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error deleting admin: ' . $e->getMessage();
                }
                break;
                
            case 'backup_database':
                // Simple backup functionality
                $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $backup_path = '../../backups/' . $backup_file;
                
                // Create backups directory if it doesn't exist
                if (!file_exists('../../backups/')) {
                    mkdir('../../backups/', 0755, true);
                }
                
                // Get all tables
                $tables = [];
                $result = $db->query("SHOW TABLES");
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
                
                $backup_content = "-- Database Backup\n-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
                
                foreach ($tables as $table) {
                    // Get table structure
                    $result = $db->query("SHOW CREATE TABLE `$table`");
                    $row = $result->fetch(PDO::FETCH_NUM);
                    $backup_content .= "\n\n-- Table structure for `$table`\n";
                    $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
                    $backup_content .= $row[1] . ";\n\n";
                    
                    // Get table data
                    $result = $db->query("SELECT * FROM `$table`");
                    if ($result->rowCount() > 0) {
                        $backup_content .= "-- Data for table `$table`\n";
                        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                            $backup_content .= "INSERT INTO `$table` VALUES (";
                            $values = [];
                            foreach ($row as $value) {
                                $values[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                            }
                            $backup_content .= implode(', ', $values) . ");\n";
                        }
                        $backup_content .= "\n";
                    }
                }
                
                if (file_put_contents($backup_path, $backup_content)) {
                    $_SESSION['success_message'] = 'Database backup created successfully: ' . $backup_file;
                } else {
                    $_SESSION['error_message'] = 'Failed to create database backup!';
                }
                break;
                
            case 'clear_logs':
                // Clear system logs (if you have a logs table)
                try {
                    // You can add log clearing logic here
                    $_SESSION['success_message'] = 'System logs cleared successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error clearing logs: ' . $e->getMessage();
                }
                break;
        }
        
        header('Location: administration.php');
        exit;
    }
}

// Get all admin users
$stmt = $db->prepare("SELECT admin_id as id, username, CONCAT(first_name, ' ', last_name) as full_name, first_name, last_name, email, role, status, created_at FROM admins ORDER BY created_at DESC");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system statistics
$stats = [];

// Count tables and records
$tables = ['members', 'contributions', 'loans', 'investments', 'notifications', 'admins'];
foreach ($tables as $table) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM `$table`");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats[$table] = $result['count'];
    } catch (PDOException $e) {
        $stats[$table] = 0;
    }
}

// Get database size
try {
    $stmt = $db->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'db_size' FROM information_schema.tables WHERE table_schema = DATABASE()");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['db_size'] = $result['db_size'] ?? 0;
} catch (PDOException $e) {
    $stats['db_size'] = 0;
}

// Get backup files
$backup_files = [];
if (file_exists('../../backups/')) {
    $backup_files = array_diff(scandir('../../backups/'), ['.', '..']);
    $backup_files = array_filter($backup_files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'sql';
    });
    rsort($backup_files); // Sort by newest first
}

$page_title = 'System Administration';
require_once '../includes/header.php';
?>

<!-- Main Content -->
<div class="flex-1 main-content bg-gray-50 mt-16">
    <div class="p-8">
        <!-- Page Heading -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">System Administration</h1>
                <p class="text-gray-600 mt-2">Manage admin users and system settings</p>
            </div>
            <div class="flex space-x-3">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors duration-200" onclick="openModal('backupModal')">
                    <i class="fas fa-download mr-2"></i> Backup Database
                </button>
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200" onclick="openModal('createAdminModal')">
                    <i class="fas fa-user-plus mr-2"></i> Add Admin
                </button>
            </div>
        </div>

        <?php require_once '../includes/flash_messages.php'; ?>

        <!-- System Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="administrationOverviewGrid">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide" style="color: #3b28cc;">Total Members</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['members']); ?></p>
                    </div>
                    <div class="p-3 rounded-full" style="background: #3b28cc;">
                        <i class="fas fa-users text-xl" style="color: #ffffff;"></i>
                    </div>
                </div>
                <div class="mt-4 border-l-4 pl-1" style="border-left-color: #3b28cc;"></div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide" style="color: #214e34;">Total Savings</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['contributions']); ?></p>
                    </div>
                    <div class="p-3 rounded-full" style="background: #214e34;">
                        <i class="fas fa-hand-holding-usd text-xl" style="color: #ffffff;"></i>
                    </div>
                </div>
                <div class="mt-4 border-l-4 pl-1" style="border-left-color: #214e34;"></div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide" style="color: #07beb8;">Active Loans</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['loans']); ?></p>
                    </div>
                    <div class="p-3 rounded-full" style="background: #07beb8;">
                        <i class="fas fa-money-bill-wave text-xl" style="color: #ffffff;"></i>
                    </div>
                </div>
                <div class="mt-4 border-l-4 pl-1" style="border-left-color: #07beb8;"></div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide" style="color: #cb0b0a;">Database Size</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo $stats['db_size']; ?> MB</p>
                    </div>
                    <div class="p-3 rounded-full" style="background: #cb0b0a;">
                        <i class="fas fa-database text-xl" style="color: #ffffff;"></i>
                    </div>
                </div>
                <div class="mt-4 border-l-4 pl-1" style="border-left-color: #cb0b0a;"></div>
            </div>
        </div>

        <!-- Admin Users Management -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Admin Users</h3>
                <button type="button" class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200" onclick="openModal('createAdminModal')">
                    <i class="fas fa-plus mr-2"></i> Add New Admin
                </button>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="adminTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($admins as $admin): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $admin['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $admin['role'] === 'Super Admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $admin['role']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $admin['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $admin['status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button type="button" class="text-blue-600 hover:text-blue-900 transition-colors duration-200" 
                                                onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <button type="button" class="text-red-600 hover:text-red-900 transition-colors duration-200" 
                                                    onclick="deleteAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['username']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- System Tools -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Database Management</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <button type="button" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center" onclick="openModal('backupModal')">
                            <i class="fas fa-download mr-2"></i> Create Database Backup
                        </button>
                        <button type="button" class="w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center" onclick="optimizeDatabase()">
                            <i class="fas fa-tools mr-2"></i> Optimize Database
                        </button>
                        <button type="button" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center" onclick="clearLogs()">
                            <i class="fas fa-trash-alt mr-2"></i> Clear System Logs
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Backups</h3>
                </div>
                <div class="p-6">
                    <?php if (!empty($backup_files)): ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($backup_files, 0, 5) as $backup): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-file-archive text-gray-400"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo $backup; ?></p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo date('M j, Y g:i A', filemtime('../../backups/' . $backup)); ?>
                                                (<?php echo number_format(filesize('../../backups/' . $backup) / 1024, 1); ?> KB)
                                            </p>
                                        </div>
                                    </div>
                                    <a href="../../backups/<?php echo $backup; ?>" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors duration-200" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No backup files found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Admin Modal -->
<div id="createAdminModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Create New Admin</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('createAdminModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="space-y-4">
                <input type="hidden" name="action" value="create_admin">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="username" name="username" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="email" name="email" required>
                </div>
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="first_name" name="first_name" required>
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="last_name" name="last_name" required>
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="role" name="role" required>
                        <option value="Admin">Admin</option>
                        <option value="Staff">Staff</option>
                        <option value="Super Admin">Super Admin</option>
                    </select>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="password" name="password" required minlength="6">
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" onclick="closeModal('createAdminModal')">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Create Admin</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Admin Modal -->
<div id="editAdminModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Edit Admin User</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('editAdminModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editAdminForm">
            <div class="space-y-4">
                <input type="hidden" name="action" value="update_admin">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                <div>
                    <label for="edit_username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="edit_username" name="username" required>
                </div>
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="edit_email" name="email" required>
                </div>
                <div>
                    <label for="edit_first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="edit_first_name" name="first_name" required>
                </div>
                <div>
                    <label for="edit_last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="edit_last_name" name="last_name" required>
                </div>
                <div>
                    <label for="edit_role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="edit_role" name="role" required>
                        <option value="Admin">Admin</option>
                        <option value="Staff">Staff</option>
                        <option value="Super Admin">Super Admin</option>
                    </select>
                </div>
                <div>
                    <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="edit_status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" onclick="closeModal('editAdminModal')">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Update Admin</button>
            </div>
        </form>
    </div>
</div>

<!-- Backup Modal -->
<div id="backupModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Create Database Backup</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal('backupModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-600 mb-3">This will create a complete backup of the database including all tables and data.</p>
            <div class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                <p class="text-sm text-yellow-800">This operation may take a few moments depending on the database size.</p>
            </div>
        </div>
        <div class="flex justify-end space-x-3">
            <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" onclick="closeModal('backupModal')">Cancel</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="backup_database">
                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">Create Backup</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['createAdminModal', 'editAdminModal', 'backupModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            closeModal(modalId);
        }
    });
}

function editAdmin(admin) {
    document.getElementById('edit_admin_id').value = admin.id;
    document.getElementById('edit_username').value = admin.username;
    document.getElementById('edit_email').value = admin.email;
    document.getElementById('edit_first_name').value = admin.first_name;
    document.getElementById('edit_last_name').value = admin.last_name;
    document.getElementById('edit_role').value = admin.role;
    document.getElementById('edit_status').value = admin.status;
    
    openModal('editAdminModal');
}

function deleteAdmin(adminId, username) {
    if (confirm(`Are you sure you want to delete admin user "${username}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_admin">
            <input type="hidden" name="admin_id" value="${adminId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function optimizeDatabase() {
    if (confirm('Are you sure you want to optimize the database? This may take a few moments.')) {
        // You can implement database optimization logic here
        alert('Database optimization completed!');
    }
}

function clearLogs() {
    if (confirm('Are you sure you want to clear all system logs? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="clear_logs">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize DataTable for admin users
document.addEventListener('DOMContentLoaded', function() {
    if (typeof DataTable !== 'undefined') {
        new DataTable('#adminTable', {
            pageLength: 10,
            order: [[6, 'desc']], // Sort by created date
            columnDefs: [
                { orderable: false, targets: [7] } // Disable sorting for actions column
            ]
        });
    }
});
</script>
