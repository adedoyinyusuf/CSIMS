<?php
require_once '../config/auth_check.php';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/session.php';

// Use the MySQLi connection from database.php
$db = $conn;

// Create PDO connection for compatibility
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db = $pdo;
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is Super Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit();
}

$page_title = 'System Settings';
$current_page = 'settings';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general':
                $system_name = trim($_POST['system_name']);
                $system_email = trim($_POST['system_email']);
                $system_phone = trim($_POST['system_phone']);
                $system_address = trim($_POST['system_address']);
                
                // Update or insert settings
                $settings = [
                    'system_name' => $system_name,
                    'system_email' => $system_email,
                    'system_phone' => $system_phone,
                    'system_address' => $system_address
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $success = 'General settings updated successfully.';
                break;
                
            case 'update_membership':
                $default_membership_fee = $_POST['default_membership_fee'];
                $membership_duration = $_POST['membership_duration'];
                $late_payment_penalty = $_POST['late_payment_penalty'];
                
                $settings = [
                    'default_membership_fee' => $default_membership_fee,
                    'membership_duration' => $membership_duration,
                    'late_payment_penalty' => $late_payment_penalty
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $success = 'Membership settings updated successfully.';
                break;
                
            case 'update_loan':
                $max_loan_amount = $_POST['max_loan_amount'];
                $default_interest_rate = $_POST['default_interest_rate'];
                $max_loan_duration = $_POST['max_loan_duration'];
                $min_contribution_months = $_POST['min_contribution_months'];
                
                $settings = [
                    'max_loan_amount' => $max_loan_amount,
                    'default_interest_rate' => $default_interest_rate,
                    'max_loan_duration' => $max_loan_duration,
                    'min_contribution_months' => $min_contribution_months
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $success = 'Loan settings updated successfully.';
                break;
                
            case 'backup_database':
                $backup_dir = '../../backups/';
                if (!is_dir($backup_dir)) {
                    mkdir($backup_dir, 0755, true);
                }
                
                $filename = 'csims_backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backup_dir . $filename;
                
                // Create backup command
                $command = "mysqldump --user=" . DB_USER . " --password=" . DB_PASS . " --host=" . DB_HOST . " " . DB_NAME . " > " . $filepath;
                
                exec($command, $output, $return_code);
                
                if ($return_code === 0) {
                    $success = 'Database backup created successfully: ' . $filename;
                } else {
                    $error = 'Failed to create database backup.';
                }
                break;
        }
    }
}

// Get current settings
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

// Get backup files
$backup_dir = '../../backups/';
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => filemtime($backup_dir . $file)
            ];
        }
    }
    // Sort by date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">System Settings</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Settings Tabs -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="fas fa-cog"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="membership-tab" data-bs-toggle="tab" data-bs-target="#membership" type="button" role="tab">
                        <i class="fas fa-id-card"></i> Membership
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="loan-tab" data-bs-toggle="tab" data-bs-target="#loan" type="button" role="tab">
                        <i class="fas fa-hand-holding-usd"></i> Loans
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">
                        <i class="fas fa-database"></i> Backup
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabContent">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">General System Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_general">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="system_name" class="form-label">System Name</label>
                                            <input type="text" class="form-control" id="system_name" name="system_name" value="<?php echo htmlspecialchars(getSetting('system_name', 'CSIMS')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="system_email" class="form-label">System Email</label>
                                            <input type="email" class="form-control" id="system_email" name="system_email" value="<?php echo htmlspecialchars(getSetting('system_email', 'admin@csims.com')); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="system_phone" class="form-label">System Phone</label>
                                            <input type="text" class="form-control" id="system_phone" name="system_phone" value="<?php echo htmlspecialchars(getSetting('system_phone', '')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="system_address" class="form-label">System Address</label>
                                            <textarea class="form-control" id="system_address" name="system_address" rows="3"><?php echo htmlspecialchars(getSetting('system_address', '')); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Save General Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Membership Settings -->
                <div class="tab-pane fade" id="membership" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Membership Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_membership">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="default_membership_fee" class="form-label">Default Membership Fee</label>
                                            <input type="number" step="0.01" class="form-control" id="default_membership_fee" name="default_membership_fee" value="<?php echo htmlspecialchars(getSetting('default_membership_fee', '50.00')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="membership_duration" class="form-label">Membership Duration (months)</label>
                                            <input type="number" class="form-control" id="membership_duration" name="membership_duration" value="<?php echo htmlspecialchars(getSetting('membership_duration', '12')); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="late_payment_penalty" class="form-label">Late Payment Penalty (%)</label>
                                            <input type="number" step="0.01" class="form-control" id="late_payment_penalty" name="late_payment_penalty" value="<?php echo htmlspecialchars(getSetting('late_payment_penalty', '5.00')); ?>">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Save Membership Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Loan Settings -->
                <div class="tab-pane fade" id="loan" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Loan Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_loan">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_loan_amount" class="form-label">Maximum Loan Amount</label>
                                            <input type="number" step="0.01" class="form-control" id="max_loan_amount" name="max_loan_amount" value="<?php echo htmlspecialchars(getSetting('max_loan_amount', '10000.00')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="default_interest_rate" class="form-label">Default Interest Rate (%)</label>
                                            <input type="number" step="0.01" class="form-control" id="default_interest_rate" name="default_interest_rate" value="<?php echo htmlspecialchars(getSetting('default_interest_rate', '10.00')); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_loan_duration" class="form-label">Maximum Loan Duration (months)</label>
                                            <input type="number" class="form-control" id="max_loan_duration" name="max_loan_duration" value="<?php echo htmlspecialchars(getSetting('max_loan_duration', '24')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="min_contribution_months" class="form-label">Minimum Contribution Months for Loan</label>
                                            <input type="number" class="form-control" id="min_contribution_months" name="min_contribution_months" value="<?php echo htmlspecialchars(getSetting('min_contribution_months', '6')); ?>">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Save Loan Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Backup Settings -->
                <div class="tab-pane fade" id="backup" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Database Backup</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="backup_database">
                                <p>Create a backup of the database. This will export all data to a SQL file.</p>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-download"></i> Create Backup
                                </button>
                            </form>
                            
                            <?php if (!empty($backup_files)): ?>
                            <hr>
                            <h6>Recent Backups</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($backup_files, 0, 10) as $file): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($file['name']); ?></td>
                                            <td><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
                                            <td><?php echo date('M j, Y H:i', $file['date']); ?></td>
                                            <td>
                                                <a href="../../backups/<?php echo urlencode($file['name']); ?>" class="btn btn-sm btn-outline-primary" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>