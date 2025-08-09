<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../controllers/auth_controller.php';

// Initialize session and auth
$session = Session::getInstance();
$authController = new AuthController();
$auth = $authController; // Define $auth for sidebar compatibility

// Check if user is logged in and is admin
if (!$authController->isLoggedIn() || !$authController->hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

// Use the existing database connection from database.php
// $conn is the MySQLi connection defined in database.php

// Create PDO connection for settings functionality
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db = $pdo;
} catch(PDOException $e) {
    error_log("PDO Connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - CSIMS</title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="flex-1 md:ml-64 mt-16 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">System Settings</h1>
                <p class="text-gray-600">Configure system-wide settings and preferences</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 text-red-600"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <button type="button" class="text-red-600 hover:text-red-800" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-green-600"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <button type="button" class="text-green-600 hover:text-green-800" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Settings Tabs -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <button class="tab-button active border-b-2 border-primary-500 py-4 px-1 text-sm font-medium text-primary-600" data-tab="general">
                            <i class="fas fa-cog mr-2"></i> General
                        </button>
                        <button class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="membership">
                            <i class="fas fa-id-card mr-2"></i> Membership
                        </button>
                        <button class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="loan">
                            <i class="fas fa-hand-holding-usd mr-2"></i> Loans
                        </button>
                        <button class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="backup">
                            <i class="fas fa-database mr-2"></i> Backup
                        </button>
                    </nav>
                </div>
            
                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-panel active" id="general">
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">General System Settings</h3>
                                <p class="text-gray-600">Configure basic system information and contact details</p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_general">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="system_name" class="block text-sm font-medium text-gray-700 mb-2">System Name</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="system_name" name="system_name" value="<?php echo htmlspecialchars(getSetting('system_name', 'CSIMS')); ?>">
                                    </div>
                                    <div>
                                        <label for="system_email" class="block text-sm font-medium text-gray-700 mb-2">System Email</label>
                                        <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="system_email" name="system_email" value="<?php echo htmlspecialchars(getSetting('system_email', 'admin@csims.com')); ?>">
                                    </div>
                                    <div>
                                        <label for="system_phone" class="block text-sm font-medium text-gray-700 mb-2">System Phone</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="system_phone" name="system_phone" value="<?php echo htmlspecialchars(getSetting('system_phone', '')); ?>">
                                    </div>
                                    <div>
                                        <label for="system_address" class="block text-sm font-medium text-gray-700 mb-2">System Address</label>
                                        <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="system_address" name="system_address" rows="3"><?php echo htmlspecialchars(getSetting('system_address', '')); ?></textarea>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                                        <i class="fas fa-save mr-2"></i> Save General Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                
                    <!-- Membership Settings -->
                    <div class="tab-panel hidden" id="membership">
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Membership Settings</h3>
                                <p class="text-gray-600">Configure membership fees, duration, and penalties</p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_membership">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="default_membership_fee" class="block text-sm font-medium text-gray-700 mb-2">Default Membership Fee</label>
                                        <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="default_membership_fee" name="default_membership_fee" value="<?php echo htmlspecialchars(getSetting('default_membership_fee', '50.00')); ?>">
                                    </div>
                                    <div>
                                        <label for="membership_duration" class="block text-sm font-medium text-gray-700 mb-2">Membership Duration (months)</label>
                                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="membership_duration" name="membership_duration" value="<?php echo htmlspecialchars(getSetting('membership_duration', '12')); ?>">
                                    </div>
                                    <div>
                                        <label for="late_payment_penalty" class="block text-sm font-medium text-gray-700 mb-2">Late Payment Penalty (%)</label>
                                        <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="late_payment_penalty" name="late_payment_penalty" value="<?php echo htmlspecialchars(getSetting('late_payment_penalty', '5.00')); ?>">
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                                        <i class="fas fa-save mr-2"></i> Save Membership Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                
                    <!-- Loan Settings -->
                    <div class="tab-panel hidden" id="loan">
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Loan Settings</h3>
                                <p class="text-gray-600">Configure loan limits, interest rates, and requirements</p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_loan">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="max_loan_amount" class="block text-sm font-medium text-gray-700 mb-2">Maximum Loan Amount</label>
                                        <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="max_loan_amount" name="max_loan_amount" value="<?php echo htmlspecialchars(getSetting('max_loan_amount', '10000.00')); ?>">
                                    </div>
                                    <div>
                                        <label for="default_interest_rate" class="block text-sm font-medium text-gray-700 mb-2">Default Interest Rate (%)</label>
                                        <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="default_interest_rate" name="default_interest_rate" value="<?php echo htmlspecialchars(getSetting('default_interest_rate', '10.00')); ?>">
                                    </div>
                                    <div>
                                        <label for="max_loan_duration" class="block text-sm font-medium text-gray-700 mb-2">Maximum Loan Duration (months)</label>
                                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="max_loan_duration" name="max_loan_duration" value="<?php echo htmlspecialchars(getSetting('max_loan_duration', '24')); ?>">
                                    </div>
                                    <div>
                                        <label for="min_contribution_months" class="block text-sm font-medium text-gray-700 mb-2">Minimum Contribution Months for Loan</label>
                                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="min_contribution_months" name="min_contribution_months" value="<?php echo htmlspecialchars(getSetting('min_contribution_months', '6')); ?>">
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                                        <i class="fas fa-save mr-2"></i> Save Loan Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                
                    <!-- Backup Settings -->
                    <div class="tab-panel hidden" id="backup">
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Database Backup</h3>
                                <p class="text-gray-600">Create and manage database backups for data protection</p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="backup_database">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                                        <p class="text-blue-800">Create a backup of the database. This will export all data to a SQL file.</p>
                                    </div>
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                    <i class="fas fa-download mr-2"></i> Create Backup
                                </button>
                            </form>
                            
                            <?php if (!empty($backup_files)): ?>
                            <div class="mt-8">
                                <h4 class="text-md font-semibold text-gray-900 mb-4">Recent Backups</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach (array_slice($backup_files, 0, 10) as $file): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($file['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y H:i', $file['date']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="../../backups/<?php echo urlencode($file['name']); ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-lg hover:bg-blue-200 transition-colors" download>
                                                        <i class="fas fa-download mr-1"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include '../../views/includes/footer.php'; ?>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabPanels = document.querySelectorAll('.tab-panel');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetTab = button.getAttribute('data-tab');

                    // Remove active class from all buttons and panels
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active', 'border-primary-500', 'text-primary-600');
                        btn.classList.add('border-transparent', 'text-gray-500');
                    });
                    tabPanels.forEach(panel => {
                        panel.classList.add('hidden');
                        panel.classList.remove('active');
                    });

                    // Add active class to clicked button and corresponding panel
                    button.classList.add('active', 'border-primary-500', 'text-primary-600');
                    button.classList.remove('border-transparent', 'text-gray-500');
                    document.getElementById(targetTab).classList.remove('hidden');
                    document.getElementById(targetTab).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
