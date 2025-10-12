<?php
/**
 * CSIMS Admin Page Template
 * 
 * This template provides a consistent structure and styling framework
 * for all admin pages in the CSIMS system. Use this as a reference
 * for implementing Phase 1&2 integrations and CSIMS color scheme.
 */

session_start();
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../includes/services/NotificationService.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize common services
$notificationService = new NotificationService();
$businessRulesService = new SimpleBusinessRulesService();

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Page-specific variables (customize for each page)
$pageTitle = "Page Title";
$pageDescription = "Page description";
$pageIcon = "fas fa-cog";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <!-- Tailwind CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
    <!-- Custom styles for this page (optional) -->
    <style>
        /* Page-specific styles go here */
    </style>
</head>

<body class="bg-admin">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
                    <h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">
                        <i class="<?php echo $pageIcon; ?> mr-3" style="color: var(--persian-orange);"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <p style="color: var(--text-muted);"><?php echo $pageDescription; ?></p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <!-- Action buttons - customize for each page -->
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i> Add New
                    </button>
                    <button type="button" class="btn btn-secondary">
                        <i class="fas fa-file-import mr-2"></i> Import
                    </button>
                    <button type="button" class="btn btn-outline">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards (optional - customize for each page) -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Total Items</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);">0</p>
                                <p class="text-xs" style="color: var(--success);">+0 this month</p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                <i class="fas fa-chart-bar text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add more stat cards as needed -->
            </div>
            
            <!-- Enhanced Flash Messages -->
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
            
            <!-- Enhanced Filter and Search Section (optional) -->
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-filter mr-2" style="color: var(--lapis-lazuli);"></i>
                        Filter & Search
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label for="search" class="form-label">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-muted);"></i>
                                </div>
                                <input type="text" class="form-control pl-10" id="search" name="search" 
                                       placeholder="Search..." value="">
                            </div>
                        </div>
                        
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="type" class="form-label">Type</label>
                            <select class="form-control" id="type" name="type">
                                <option value="">All Types</option>
                                <!-- Add options dynamically -->
                            </select>
                        </div>
                        
                        <div>
                            <label for="per_page" class="form-label">Show</label>
                            <select class="form-control" id="per_page" name="per_page">
                                <option value="15">15 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">
                                <i class="fas fa-times mr-2"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Main Content Table/Cards -->
            <div class="card card-admin animate-fade-in">
                <div class="card-header flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Data Table</h3>
                    <span class="badge" style="background: var(--lapis-lazuli); color: white;">
                        0 Total Items
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="dataTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populate with data -->
                                <tr>
                                    <td colspan="5" class="text-center py-8" style="color: var(--text-muted);">
                                        <i class="fas fa-inbox text-3xl mb-2"></i>
                                        <p>No data found</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination (if needed) -->
                    <div class="mt-4">
                        <!-- Add pagination here -->
                    </div>
                </div>
            </div>
            
        </main>
    </div>
    
    <!-- Include Footer -->
    <?php include '../../views/includes/footer.php'; ?>
    
    <!-- JavaScript -->
    <script>
        // Page-specific JavaScript functionality
        
        // Export function
        function exportData() {
            alert('Export functionality - to be implemented');
        }
        
        // Print function
        function printData() {
            window.print();
        }
        
        // Import modal function
        function openImportModal() {
            alert('Import modal - to be implemented');
        }
        
        // Enhanced error handling
        window.addEventListener('error', function(event) {
            console.error('Page error:', event.error);
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
    
    <!-- Page-specific JavaScript (customize for each page) -->
    <script>
        // Add page-specific functionality here
    </script>
</body>
</html>