<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../controllers/auth_controller.php';
require_once '../../includes/config/SystemConfigService.php';

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
    // Initialize centralized configuration service
    try {
        $sysConfig = SystemConfigService::getInstance($db);
    } catch (Exception $e) {
        $sysConfig = null;
        error_log('Admin settings: SystemConfigService init failed: ' . $e->getMessage());
    }
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
            case 'update_settings':
                $settings_updated = 0;
                if (isset($_POST['settings']) && is_array($_POST['settings'])) {
                    foreach ($_POST['settings'] as $category => $category_settings) {
                        foreach ($category_settings as $key => $value) {
                            if ($sysConfig) {
                                if ($sysConfig->set($key, $value, $_SESSION['user_id'] ?? null)) {
                                    $settings_updated++;
                                }
                            }
                        }
                    }
                }

                if ($settings_updated > 0) {
                    $success = "$settings_updated system settings updated successfully.";
                } else {
                    $error = 'No settings updated or configuration service unavailable.';
                }
                break;
            case 'update_general':
                $system_name = trim($_POST['system_name']);
                $system_email = trim($_POST['system_email']);
                $system_phone = trim($_POST['system_phone']);
                $system_address = trim($_POST['system_address']);
                
                // Migrate to centralized system_config store
                if ($sysConfig) {
                    try { $sysConfig->set('SYSTEM_NAME', (string)$system_name, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set SYSTEM_NAME failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('SYSTEM_EMAIL', (string)$system_email, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set SYSTEM_EMAIL failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('SYSTEM_PHONE', (string)$system_phone, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set SYSTEM_PHONE failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('SYSTEM_ADDRESS', (string)$system_address, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set SYSTEM_ADDRESS failed: ' . $e->getMessage()); }
                } else {
                    error_log('Admin settings: SystemConfigService unavailable, general settings not persisted to system_config');
                }
                
                $success = 'General settings updated successfully.';
                break;
                
            case 'update_membership':
                $default_membership_fee = $_POST['default_membership_fee'];
                $membership_duration = $_POST['membership_duration'];
                $late_payment_penalty = $_POST['late_payment_penalty'];
                
                // Migrate to centralized system_config store
                if ($sysConfig) {
                    try { $sysConfig->set('DEFAULT_MEMBERSHIP_FEE', (float)$default_membership_fee, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set DEFAULT_MEMBERSHIP_FEE failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('MEMBERSHIP_DURATION', (int)$membership_duration, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set MEMBERSHIP_DURATION failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('LATE_PAYMENT_PENALTY', (float)$late_payment_penalty, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set LATE_PAYMENT_PENALTY failed: ' . $e->getMessage()); }
                } else {
                    error_log('Admin settings: SystemConfigService unavailable, membership settings not persisted to system_config');
                }
                
                $success = 'Membership settings updated successfully.';
                break;
                
            case 'update_loan':
                $max_loan_amount = $_POST['max_loan_amount'];
                $default_interest_rate = $_POST['default_interest_rate'];
                $max_loan_duration = $_POST['max_loan_duration'];
                $min_savings_months = $_POST['min_savings_months'];
                
                // Persist only to centralized system_config
                
                // Also write to centralized system_config for consistency
                if ($sysConfig) {
                    try { $sysConfig->set('MAX_LOAN_AMOUNT', (float)$max_loan_amount, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set MAX_LOAN_AMOUNT failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('DEFAULT_INTEREST_RATE', (float)$default_interest_rate, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set DEFAULT_INTEREST_RATE failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('MAX_LOAN_DURATION', (int)$max_loan_duration, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set MAX_LOAN_DURATION failed: ' . $e->getMessage()); }
                    try { $sysConfig->set('MIN_SAVINGS_MONTHS', (int)$min_savings_months, $_SESSION['user_id'] ?? null); } catch (Exception $e) { error_log('syscfg set MIN_SAVINGS_MONTHS failed: ' . $e->getMessage()); }
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

// Legacy settings helper removed as part of migration to SystemConfigService

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

// Build system settings from SystemConfigService, grouped by category
$system_settings = [];
if (isset($sysConfig) && $sysConfig) {
    try {
        $allConfigs = $sysConfig->getAll();
        foreach ($allConfigs as $configKey => $configValue) {
            $meta = $sysConfig->getMetadata($configKey);
            $category = $meta['category'] ?? 'general';
            $type = $meta['type'] ?? 'string';
            $description = $meta['description'] ?? '';
            $uiType = ($type === 'integer' || $type === 'decimal') ? 'number' : ($type === 'boolean' ? 'boolean' : 'text');
            $system_settings[$category][] = [
                'setting_key' => $configKey,
                'setting_value' => $configValue,
                'setting_type' => $uiType,
                'description' => $description,
            ];
        }
        ksort($system_settings);
    } catch (Exception $e) {
        error_log('Admin settings: building system_settings failed: ' . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - CSIMS</title>
    
    <!-- Font Awesome -->
    
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
                        <i class="fas fa-exclamation-circle mr-3 icon-error"></i>
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
                        <i class="fas fa-check-circle mr-3 icon-success"></i>
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
                        <button class="tab-button active border-b-2 border-primary-500 py-4 px-1 text-sm font-medium text-primary-600" data-tab="settings">
                            <i class="fas fa-sliders-h mr-2"></i> System Settings
                        </button>
                        <button class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="backup">
                            <i class="fas fa-database mr-2"></i> Backup
                        </button>
                    </nav>
                </div>
            
                <div class="tab-content">
                    <!-- System Settings (Dynamic via SystemConfigService) -->
                    <div class="tab-panel active" id="settings">
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">System Settings</h3>
                                <p class="text-gray-600">Manage all configurable parameters centrally</p>
                            </div>
                            <?php if (!empty($system_settings)): ?>
                                <?php 
                                    $settings_action = 'update_settings';
                                    $settings_submit_label = 'Save Settings';
                                    include_once __DIR__ . '/../includes/system_settings_form.php';
                                ?>
                            <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                                    SystemConfigService unavailable or no settings found.
                                </div>
                            <?php endif; ?>
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
