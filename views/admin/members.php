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

// Get stats
$member_stats = $memberController->getMemberStatistics();

// Calculate statistics based on the CURRENT filtered results for real-time stats
$db = Database::getInstance()->getConnection();
$where = [];
if (!empty($search)) {
    $like = '%' . $db->real_escape_string($search) . '%';
    $where[] = "(m.first_name LIKE '$like' OR m.last_name LIKE '$like' OR m.email LIKE '$like' OR m.phone LIKE '$like' OR m.ippis_no LIKE '$like')";
}
if (!empty($status)) {
    $statusEsc = $db->real_escape_string($status);
    $where[] = "m.status = '$statusEsc'";
}
if (!empty($membership_type)) {
    $typeEsc = $db->real_escape_string($membership_type);
    $where[] = "(m.membership_type_id = '$typeEsc' OR mt.membership_type_id = '$typeEsc' OR mt.name = '$typeEsc')";
}
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$filtered_stats = [
    'active_members' => 0,
];
$activeSql = "SELECT COUNT(*) AS c FROM members m 
              LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id 
              $whereSql AND (m.status = 'Active')";
if ($res = $db->query($activeSql)) {
    $row = $res->fetch_assoc();
    $filtered_stats['active_members'] = (int)($row['c'] ?? 0);
    $res->free();
}

// Handle AJAX Search Request
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    ob_end_clean(); 
    header('Content-Type: application/json');
    ob_start();
    
    // Render Table Rows
    if (count($members) > 0) {
        $serial_number = (($page - 1) * $per_page) + 1;
        foreach ($members as $member) {
            ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?php echo $serial_number++; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php echo htmlspecialchars($member['ippis_no'] ?? 'N/A'); ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <?php 
                        $photo_url = $member['photo'] ?? '';
                        if (!empty($photo_url)) {
                            if (strpos($photo_url, 'assets/') === false) {
                                $photo_url = BASE_URL . '/assets/images/members/' . $photo_url;
                            } else {
                                $photo_url = BASE_URL . '/' . $photo_url;
                            }
                        }
                        ?>
                        <?php if (!empty($photo_url)): ?>
                            <img src="<?php echo $photo_url; ?>" alt="Profile" class="w-10 h-10 rounded-full mr-3 object-cover">
                        <?php else: ?>
                            <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3 text-white">
                                <i class="fas fa-user"></i>
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
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <?php echo isset($member['member_type_label']) ? ucfirst($member['member_type_label']) : 'Member'; ?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?php echo !empty($member['join_date']) ? date('M d, Y', strtotime($member['join_date'])) : 'N/A'; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?php 
                        if (!empty($member['expiry_date'])) {
                            echo date('M d, Y', strtotime($member['expiry_date']));
                            $days = (int)round((strtotime($member['expiry_date']) - time()) / (86400));
                            if ($days <= 0) echo ' <span class="text-xs text-red-600 font-bold">(Expired)</span>';
                            elseif ($days <= 30) echo ' <span class="text-xs text-amber-600 font-bold">(' . $days . ' days)</span>';
                        } else {
                            echo 'N/A';
                        }
                    ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($member['status'] == 'Active'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                    <?php elseif ($member['status'] == 'Pending'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?php echo ucfirst($member['status']); ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative z-10">
                    <div class="flex items-center space-x-3">
                        <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="text-gray-400 hover:text-gray-600 relative z-20"><i class="fas fa-eye"></i></a>
                        <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" class="text-blue-400 hover:text-blue-600 relative z-20"><i class="fas fa-edit"></i></a>
                        <button onclick="confirmDeleteMember(<?php echo $member['member_id']; ?>)" class="text-red-400 hover:text-red-600 relative z-20"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="9" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-users text-3xl mb-2 text-gray-300"></i><p>No members found</p></td></tr>';
    }
    $rows_html = ob_get_clean();

    // Render Pagination
    ob_start();
    if ($total_pages > 1): ?>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <a href="#" onclick="fetchResults(<?php echo max(1, $page - 1); ?>); return false;" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Previous</span>
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php 
            $range = 2;
            for ($i = 1; $i <= $total_pages; $i++): 
                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                    $activeClass = $i == $page ? 'bg-indigo-50 border-indigo-500 text-indigo-600 z-10' : 'bg-white text-gray-700 hover:bg-gray-50';
            ?>
                <a href="#" onclick="fetchResults(<?php echo $i; ?>); return false;" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?php echo $activeClass; ?>">
                    <?php echo $i; ?>
                </a>
            <?php elseif ($i == $page - $range - 1 || $i == $page + $range + 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
            <?php endif; endfor; ?>
            <a href="#" onclick="fetchResults(<?php echo min($total_pages, $page + 1); ?>); return false;" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="fas fa-chevron-right"></i>
            </a>
        </nav>
    <?php endif;
    $pagination_html = ob_get_clean();

    echo json_encode(['rows' => $rows_html, 'pagination' => $pagination_html, 'total_members' => $total_members, 'active_members' => $filtered_stats['active_members']]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css?v=2.4">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css?v=2.4">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1' },
                    secondary: { 50: '#f8fafc', 500: '#64748b', 900: '#0f172a' }
                }
            }
        }
    }
    </script>
    <style>
        .icon-success { color: #10b981; } .icon-error { color: #ef4444; }
        .gradient-blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .gradient-teal { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); }
        .gradient-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .gradient-orange { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">
    <?php include '../../views/includes/header.php'; ?>
    <div class="flex h-screen overflow-hidden">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 md:ml-64 transition-all duration-300 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Members Management</h1>
                    <p class="text-gray-500">Manage and view all cooperative society members</p>
                </div>
                <div class="flex gap-3 mt-4 md:mt-0">
                    <a href="add_member.php" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg font-medium text-white hover:bg-primary-700 shadow-sm transition-colors">
                        <i class="fas fa-plus mr-2"></i> Add Member
                    </a>
                    <button onclick="openImportModal()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition-colors">
                        <i class="fas fa-file-import mr-2"></i> Import
                    </button>
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition-colors">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Stats Cards -->
                <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-blue text-white">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Total Members</p>
                        <h3 class="text-3xl font-bold mt-1" id="totalMembersCard"><?php echo number_format($total_members); ?></h3>
                        <p class="text-xs opacity-70 mt-1"><i class="fas fa-users mr-1"></i> All registered</p>
                    </div>
                    <i class="fas fa-users absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>
                <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-teal text-white">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Active Members</p>
                        <h3 class="text-3xl font-bold mt-1" id="activeMembersCard"><?php echo number_format($filtered_stats['active_members']); ?></h3>
                        <p class="text-xs opacity-70 mt-1"><i class="fas fa-check-circle mr-1"></i> Currently active</p>
                    </div>
                    <i class="fas fa-user-check absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>
                 <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-purple text-white">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">New This Month</p>
                        <h3 class="text-3xl font-bold mt-1"><?php echo number_format($member_stats['new_members_this_month'] ?? 0); ?></h3>
                        <p class="text-xs opacity-70 mt-1"><i class="fas fa-calendar-plus mr-1"></i> Recent joins</p>
                    </div>
                    <i class="fas fa-clock absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>
                 <div class="rounded-xl shadow-lg p-6 relative overflow-hidden gradient-orange text-white">
                    <div class="relative z-10">
                        <p class="text-sm font-medium opacity-80">Expiring Soon</p>
                        <h3 class="text-3xl font-bold mt-1">0</h3>
                        <p class="text-xs opacity-70 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i> Next 30 days</p>
                    </div>
                    <i class="fas fa-hourglass-half absolute -bottom-4 -right-4 text-9xl opacity-10"></i>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600 mr-2">
                        <i class="fas fa-filter"></i>
                    </div>
                    Filter & Search Members
                </h3>
                <form id="proFilterForm" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search Query</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i class="fas fa-search"></i></span>
                            <input type="text" id="search" class="pl-10 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" placeholder="Name, Email, Phone, IPPIS..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">All Statuses</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Pending">Pending</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select id="membership_type" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">All Types</option>
                            <?php foreach ($membership_types as $type): ?>
                                <option value="<?php echo $type['membership_type_id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rows</label>
                        <select id="per_page" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-primary-600 text-white rounded-lg px-4 py-2 hover:bg-primary-700 transition font-medium shadow-sm">
                            Apply
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h3 class="font-semibold text-gray-900">Member List</h3>
                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full" id="totalCountBadge"><?php echo $total_members; ?> Members</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IPPIS</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody" class="bg-white divide-y divide-gray-200">
                            <?php if (count($members) > 0): ?>
                                <?php 
                                    $serial_number = (($page - 1) * $per_page) + 1;
                                    foreach ($members as $member): 
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $serial_number++; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($member['ippis_no'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php 
                                                $photo_url = $member['photo'] ?? '';
                                                if (!empty($photo_url)) {
                                                    if (strpos($photo_url, 'assets/') === false) $photo_url = BASE_URL . '/assets/images/members/' . $photo_url;
                                                    else $photo_url = BASE_URL . '/' . $photo_url;
                                                }
                                                ?>
                                                <?php if (!empty($photo_url)): ?>
                                                    <img src="<?php echo $photo_url; ?>" alt="" class="w-10 h-10 rounded-full mr-3 object-cover">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3 text-white"><i class="fas fa-user"></i></div>
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
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo isset($member['member_type_label']) ? ucfirst($member['member_type_label']) : 'Member'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo !empty($member['join_date']) ? date('M d, Y', strtotime($member['join_date'])) : 'N/A'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo !empty($member['expiry_date']) ? date('M d, Y', strtotime($member['expiry_date'])) : 'N/A'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($member['status'] == 'Active'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            <?php elseif ($member['status'] == 'Pending'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?php echo ucfirst($member['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-3">
                                                <a href="view_member.php?id=<?php echo $member['member_id']; ?>" class="text-gray-400 hover:text-gray-600"><i class="fas fa-eye"></i></a>
                                                <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" class="text-blue-400 hover:text-blue-600"><i class="fas fa-edit"></i></a>
                                                <button onclick="confirmDeleteMember(<?php echo $member['member_id']; ?>)" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-users text-3xl mb-2 text-gray-300"></i><p>No members found</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                 <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between" id="paginationContainer">
                    <?php if ($total_pages > 1): ?>
                         <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <!-- Helper for initial page load -->
                            <a href="?page=<?php echo max(1, $page - 1); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                             <!-- Simplified for initial static load, JS takes over -->
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                             <a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script>
        let searchTimeout;

        function fetchResults(page = 1) {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status').value;
            const type = document.getElementById('membership_type').value;
            const perPage = document.getElementById('per_page').value;
            const tbody = document.getElementById('membersTableBody');

            tbody.style.opacity = '0.5';

            const params = new URLSearchParams({
                ajax: '1',
                page: page,
                search: search,
                status: status,
                membership_type: type,
                per_page: perPage
            });

            fetch('members.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = data.rows;
                    tbody.style.opacity = '1';
                    
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('totalCountBadge').textContent = data.total_members + ' Members';
                    document.getElementById('totalMembersCard').textContent = new Intl.NumberFormat().format(data.total_members);
                    document.getElementById('activeMembersCard').textContent = new Intl.NumberFormat().format(data.active_members);
                })
                .catch(err => {
                    console.error('Search failed', err);
                    tbody.style.opacity = '1';
                });
        }

        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => fetchResults(1), 300);
        });

        ['status', 'membership_type', 'per_page'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => fetchResults(1));
        });

        document.getElementById('proFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetchResults(1);
        });
        
        function confirmDeleteMember(id) {
            if(confirm('Are you sure you want to delete this member?')) {
                // Implement delete logic or redirect
                alert('Delete functionality not connected in this demo.');
            }
        }
    </script>
</body>
</html>
