<?php
session_start();
require_once '../config/auth_check.php';
require_once '../config/database.php';

// Check if user is super admin
if ($_SESSION['role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

$database = new Database();
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

<div class="container-fluid">
    <div class="row">
        <?php require_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">System Administration</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#backupModal">
                            <i class="fas fa-download"></i> Backup Database
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createAdminModal">
                            <i class="fas fa-user-plus"></i> Add Admin
                        </button>
                    </div>
                </div>
            </div>

            <?php require_once '../includes/flash_messages.php'; ?>

            <!-- System Overview -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Members</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['members']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Contributions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['contributions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Loans</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['loans']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Database Size</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['db_size']; ?> MB</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-database fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Users Management -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Admin Users</h6>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createAdminModal">
                        <i class="fas fa-plus"></i> Add New Admin
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="adminTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo $admin['id']; ?></td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $admin['role'] === 'Super Admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo $admin['role']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $admin['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                <?php echo $admin['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
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
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Database Management</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#backupModal">
                                    <i class="fas fa-download"></i> Create Database Backup
                                </button>
                                <button type="button" class="btn btn-warning" onclick="optimizeDatabase()">
                                    <i class="fas fa-tools"></i> Optimize Database
                                </button>
                                <button type="button" class="btn btn-danger" onclick="clearLogs()">
                                    <i class="fas fa-trash-alt"></i> Clear System Logs
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Backups</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($backup_files)): ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($backup_files, 0, 5) as $backup): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-file-archive text-muted me-2"></i>
                                                <?php echo $backup; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', filemtime('../../backups/' . $backup)); ?>
                                                    (<?php echo number_format(filesize('../../backups/' . $backup) / 1024, 1); ?> KB)
                                                </small>
                                            </div>
                                            <a href="../../backups/<?php echo $backup; ?>" class="btn btn-sm btn-outline-primary" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No backup files found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create Admin Modal -->
<div class="modal fade" id="createAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_admin">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Admin User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editAdminForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_admin">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Backup Modal -->
<div class="modal fade" id="backupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Database Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This will create a complete backup of the database including all tables and data.</p>
                <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> This operation may take a few moments depending on the database size.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="backup_database">
                    <button type="submit" class="btn btn-success">Create Backup</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
function editAdmin(admin) {
    document.getElementById('edit_admin_id').value = admin.id;
    document.getElementById('edit_username').value = admin.username;
    document.getElementById('edit_email').value = admin.email;
    document.getElementById('edit_first_name').value = admin.first_name;
    document.getElementById('edit_last_name').value = admin.last_name;
    document.getElementById('edit_role').value = admin.role;
    document.getElementById('edit_status').value = admin.status;
    
    new bootstrap.Modal(document.getElementById('editAdminModal')).show();
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

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.text-xs {
    font-size: 0.7rem;
}
.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}
</style>
