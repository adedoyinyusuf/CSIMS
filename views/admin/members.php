<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/membership_controller.php';

// Ensure we use the unified Session instance and cookie
$session = Session::getInstance();

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize controllers and services
$memberController = new MemberController();
$membershipController = new MembershipController();

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$membership_type = isset($_GET['membership_type']) ? $_GET['membership_type'] : '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;

// Get members with pagination
$result = $memberController->getAllMembers($page, $search, $status, $membership_type, $per_page);
$members = $result['members'] ?? [];
$pagination = $result['pagination'] ?? [];
$total_pages = $pagination['total_pages'] ?? 1;
$total_members = $pagination['total_items'] ?? 0;

// Get membership types for filter
$membership_types = $membershipController->getAllMembershipTypes(1, 100)['membership_types'] ?? [];

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get member statistics for dashboard mini-cards
$member_stats = $memberController->getMemberStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - <?php echo APP_NAME; ?></title>
    <!-- Font Awesome -->
    
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
</head>
<body class="bg-admin">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <!-- Page Header with Statistics -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
                    <h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">Members Management</h1>
                    <p style="color: var(--text-muted);">Manage and view all cooperative society members</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <a href="add_member.php" class="btn btn-primary">
                        <i class="fas fa-user-plus mr-2"></i> Add New Member
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="openImportModal()">
                        <i class="fas fa-file-import mr-2"></i> Import Members
                    </button>
                    <button type="button" class="btn btn-outline" onclick="exportMembers()">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline" onclick="printMembers()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Quick Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">Total Members</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($total_members); ?></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                <i class="fas fa-users text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: #3b28cc;">Active Members</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($member_stats['active_members'] ?? 0); ?></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);">
                                <i class="fas fa-user-check text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: #3b28cc;">New This Month</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php echo number_format($member_stats['new_members_this_month'] ?? 0); ?></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: #3b28cc;">
                                <i class="fas fa-user-plus text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card card-admin">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="form-label text-xs mb-1" style="color: #cb0b0a;">Expiring Soon</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);"><?php 
                                    $expiring_count = 0;
                                    $today = strtotime('today');
                                    foreach($members as $member) {
                                        $raw = $member['expiry_date'] ?? null;
                                        if (empty($raw)) { continue; }
                                        $expiry_ts = strtotime($raw);
                                        if ($expiry_ts === false) { continue; }
                                        $days_left = (int)round(($expiry_ts - $today) / (60 * 60 * 24));
                                        if ($days_left <= 30 && $days_left >= 0) { $expiring_count++; }
                                    }
                                    echo number_format($expiring_count);
                                ?></p>
                            </div>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: #cb0b0a;">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                
            <!-- Enhanced Flash Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 icon-success"></i>
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
                        <i class="fas fa-exclamation-circle mr-3 icon-error"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
                
            <!-- Enhanced Filter and Search -->
            <div class="card card-admin animate-fade-in">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-filter mr-2" style="color: #3b28cc;"></i>
                        Filter & Search Members
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <div class="lg:col-span-2">
                            <label for="search" class="form-label">Search Members</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search icon-muted"></i>
                                </div>
                                <input type="text" class="form-control pl-10" id="search" name="search" 
                                       placeholder="Name, Email, Phone, Member ID" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div>
                            <label for="status" class="form-label">Membership Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Expired" <?php echo ($status == 'Expired') ? 'selected' : ''; ?>>Expired</option>
                                <option value="Pending" <?php echo ($status == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div>
                            <label for="membership_type" class="form-label">Membership Type</label>
                            <select class="form-control" id="membership_type" name="membership_type">
                                <option value="">All Types</option>
                                <?php foreach ($membership_types as $type): ?>
                                    <option value="<?php echo $type['membership_type_id']; ?>" <?php echo ($membership_type == $type['membership_type_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="per_page" class="form-label">Show</label>
                            <select class="form-control" id="per_page" name="per_page">
                                <option value="15" <?php echo ($per_page == 15) ? 'selected' : ''; ?>>15 per page</option>
                                <option value="25" <?php echo ($per_page == 25) ? 'selected' : ''; ?>>25 per page</option>
                                <option value="50" <?php echo ($per_page == 50) ? 'selected' : ''; ?>>50 per page</option>
                                <option value="100" <?php echo ($per_page == 100) ? 'selected' : ''; ?>>100 per page</option>
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
                
            <!-- Members Table -->
            <div class="card animate-fade-in">
                <div class="card-header flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Member List</h3>
                    <span class="badge badge-info">
                        <?php echo $total_members; ?> Total Members
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="membersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Membership</th>
                                <th>Join Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($members) > 0): ?>
                                <?php foreach ($members as $member): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo $member['member_id']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php if (!empty($member['photo'])): ?>
                                                    <?php 
                                                    // Handle different photo path formats
                                                    $photo_url = $member['photo'];
                                                    if (strpos($photo_url, 'assets/') === 0) {
                                                        // Photo path already includes assets/ directory
                                                        $photo_url = BASE_URL . '/' . $photo_url;
                                                    } else {
                                                        // Photo path is just filename, use default directory
                                                        $photo_url = BASE_URL . '/assets/images/members/' . $photo_url;
                                                    }
                                                    ?>
                                                    <img src="<?php echo $photo_url; ?>" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-user text-gray-600"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo $member['gender']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $member['email']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $member['phone']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="badge badge-info">
                                                <?php echo isset($member['member_type_label']) && !empty($member['member_type_label']) ? ucfirst($member['member_type_label']) : (isset($member['member_type']) ? ucfirst($member['member_type']) : 'Member'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php 
                                                $joinRaw = $member['join_date'] ?? null;
                                                $joinTs = $joinRaw ? strtotime($joinRaw) : false;
                                                echo $joinTs ? date('M d, Y', $joinTs) : 'N/A';
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="flex flex-col">
                                                <?php 
                                                    $expiryRaw = $member['expiry_date'] ?? null;
                                                    $expiryTs = $expiryRaw ? strtotime($expiryRaw) : false;
                                                    echo $expiryTs ? date('M d, Y', $expiryTs) : 'N/A';
                                                    $today = strtotime('today');
                                                    $days_left = ($expiryTs !== false) ? (int)round(($expiryTs - $today) / (60 * 60 * 24)) : null;
                                                    if ($days_left !== null) {
                                                        if ($days_left <= 0) {
                                                            echo '<span class="badge badge-error mt-1">Expired</span>';
                                                        } elseif ($days_left <= 30) {
                                                            echo '<span class="badge badge-warning mt-1">' . $days_left . ' days left</span>';
                                                        }
                                                    }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($member['status'] == 'Active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php elseif ($member['status'] == 'Inactive'): ?>
                                                <span class="badge badge-warning">Inactive</span>
                                            <?php else: ?>
                                                <span class="badge badge-error">Expired</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="table-actions">
                                                <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-outline btn-sm" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/views/admin/edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-primary btn-sm" title="Edit Member">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($days_left !== null && $days_left <= 0): ?>
                                                    <a href="<?php echo BASE_URL; ?>/views/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>" class="btn btn-secondary btn-sm" title="Renew Membership">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button onclick="confirmDeleteMember(<?php echo $member['member_id']; ?>, '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'], ENT_QUOTES); ?>')" class="btn btn-outline btn-sm text-error border-error hover:bg-error-bg" title="Delete Member">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-users text-3xl mb-2 text-gray-300"></i>
                                        <p>No members found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                        
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-light">
                        <nav class="pagination" aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&membership_type=<?php echo urlencode($membership_type); ?>" aria-label="Previous">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&membership_type=<?php echo urlencode($membership_type); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&membership_type=<?php echo urlencode($membership_type); ?>" aria-label="Next">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
                
        </main>
    </div>
    
    <!-- Include Footer -->
    <?php include '../../views/includes/footer.php'; ?>
    
    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Import New Members</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeImportModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="importFile" class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                        <input type="file" id="importFile" name="import_file" accept=".csv" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                        <p class="text-xs text-gray-500 mt-1">Only CSV files are supported. Maximum file size: 10MB</p>
                    </div>
                    
                    <!-- Hidden input for insert_only mode -->
                    <input type="hidden" id="importMode" name="import_mode" value="insert_only">
                    
                    <div class="mb-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <span class="text-sm text-blue-800">This import will only create new member profiles and accounts. Existing members will be skipped.</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="createAccounts" name="create_accounts" value="true" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded" checked>
                            <label for="createAccounts" class="ml-2 block text-sm text-gray-700">Create login accounts for new members</label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Automatically generates username and password for each new member</p>
                    </div>
                    
                    <div class="mb-4" id="credentialsOptions" style="display: block;">
                        <div class="flex items-center">
                            <input type="checkbox" id="sendCredentials" name="send_credentials" value="true" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="sendCredentials" class="ml-2 block text-sm text-gray-700">Send credentials via email</label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Email login details to new members automatically</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CSV Format Requirements:</label>
                        <div class="text-xs text-gray-600 bg-gray-50 p-3 rounded">
                            <p class="font-medium mb-1">Required columns:</p>
                            <p>first_name, last_name, email</p>
                            <p class="font-medium mb-1 mt-2">Optional columns:</p>
                            <p>phone, gender, date_of_birth, address, membership_type_id, ippis_no, username, password</p>
                            <p class="mt-2"><strong>Note:</strong> First row should contain column headers</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeImportModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>Import New Members
                        </button>
                    </div>
                </form>
                
                <div id="importProgress" class="hidden mt-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-3"></div>
                            <span class="text-sm text-blue-800">Processing import...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" onerror="console.log('jQuery failed to load')"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js" onerror="console.log('DataTables failed to load')"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js" onerror="console.log('Custom script failed to load')"></script>
    
    <script>
        // Check if jQuery loaded successfully
        if (typeof $ !== 'undefined') {
            $(document).ready(function() {
                // Initialize DataTable with pagination disabled (we're using our own)
                $('#membersTable').DataTable({
                    "paging": false,
                    "ordering": true,
                    "info": false,
                    "searching": false
                });
            });
        } else {
            console.log('jQuery not available, skipping DataTable initialization');
        }
        
        // Import Modal Functions
        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importForm').reset();
            document.getElementById('importProgress').classList.add('hidden');
        }
        
        // Show/hide credentials options based on create accounts checkbox
        document.getElementById('createAccounts').addEventListener('change', function() {
            const credentialsOptions = document.getElementById('credentialsOptions');
            if (this.checked) {
                credentialsOptions.style.display = 'block';
            } else {
                credentialsOptions.style.display = 'none';
                document.getElementById('sendCredentials').checked = false;
            }
        });
        
        // Handle Import Form Submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('importFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file to import.');
                return;
            }
            
            // Show progress indicator
            document.getElementById('importProgress').classList.remove('hidden');
            
            // Create FormData object
            const formData = new FormData();
            formData.append('import_file', file);
            formData.append('import_mode', document.getElementById('importMode').value);
            formData.append('create_accounts', document.getElementById('createAccounts').checked ? 'true' : 'false');
            formData.append('send_credentials', document.getElementById('sendCredentials').checked ? 'true' : 'false');
            
            // Send AJAX request
            fetch('<?php echo BASE_URL; ?>/controllers/member_import_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('importProgress').classList.add('hidden');
                
                if (data.success) {
                    let message = data.message;
                    
                    // Show credentials if not sent via email
                    if (data.credentials && data.credentials.length > 0) {
                        message += '\n\nNew Account Credentials:';
                        data.credentials.forEach(function(cred) {
                            message += '\n' + cred.name + ' - Username: ' + cred.username + ', Password: ' + cred.password;
                        });
                        message += '\n\nPlease save these credentials and share them securely with the members.';
                    }
                    
                    alert('Import completed successfully! ' + message);
                    closeImportModal();
                    location.reload(); // Refresh the page to show new/updated members
                } else {
                    alert('Import failed: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('importProgress').classList.add('hidden');
                console.error('Error:', error);
                alert('An error occurred during import. Please try again.');
            });
        });
        
        // Export Members Function
        function exportMembers() {
            const search = document.querySelector('input[name="search"]')?.value || '';
            const status = document.querySelector('select[name="status"]')?.value || '';
            const membership_type = document.querySelector('select[name="membership_type"]')?.value || '';
            const per_page = document.querySelector('select[name="per_page"]')?.value || '15';
            
            // Build export URL with current filters
            let exportUrl = '<?php echo BASE_URL; ?>/controllers/member_export_controller.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (status) params.push('status=' + encodeURIComponent(status));
            if (membership_type) params.push('membership_type=' + encodeURIComponent(membership_type));
            if (per_page) params.push('per_page=' + encodeURIComponent(per_page));
            
            exportUrl += params.join('&');
            
            // Open export URL in new window
            window.open(exportUrl, '_blank');
        }
        
        // Print Members Function
        function printMembers() {
            // Create a new window with printable content
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Get current filters
            const search = document.querySelector('input[name="search"]')?.value || '';
            const status = document.querySelector('select[name="status"]')?.value || '';
            const membership_type = document.querySelector('select[name="membership_type"]')?.value || '';
            
            // Build print URL
            let printUrl = '<?php echo BASE_URL; ?>/views/admin/print_members.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (status) params.push('status=' + encodeURIComponent(status));
            if (membership_type) params.push('membership_type=' + encodeURIComponent(membership_type));
            
            printUrl += params.join('&');
            
            printWindow.location.href = printUrl;
        }
        
        // Delete Member Confirmation
        let memberIdToDelete = null;
        
        function confirmDeleteMember(memberId, memberName) {
            memberIdToDelete = memberId;
            const message = `Are you sure you want to delete ${memberName}?\n\nThis action cannot be undone. The member will be marked as deleted.`;
            
            if (confirm(message)) {
                // Redirect to delete confirmation page
                window.location.href = '<?php echo BASE_URL; ?>/views/admin/delete_member.php?id=' + memberId;
            }
        }
        
        // Handle delete buttons with data attributes for confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    if (href) {
                        const url = new URL(href, window.location.origin);
                        const memberId = url.searchParams.get('id');
                        if (memberId) {
                            // Try to get member name from the row
                            const row = this.closest('tr');
                            const nameCell = row?.querySelector('td:nth-child(2) .text-sm.font-medium');
                            const memberName = nameCell?.textContent.trim() || 'this member';
                            confirmDeleteMember(memberId, memberName);
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
