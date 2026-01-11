<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/membership_controller.php';

$session = Session::getInstance();
if (!$session->isLoggedIn() || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Please login to continue';
    header('Location: ../../index.php');
    exit();
}

$auth = new AuthController();
$current_user = $auth->getCurrentUser();

$membershipController = new MembershipController();

// Get success message from session
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle pagination and search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 15;

// Get membership types data
$result = $membershipController->getAllMembershipTypes($page, $limit, $search);
$membership_types = $result['membership_types'];
$total_pages = $result['total_pages'];
$current_page = $result['current_page'];
$total_records = $result['total_records'];

// Get membership statistics
$stats = $membershipController->getMembershipStats();
$expiring = $membershipController->getExpiringMemberships(30);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memberships - CSIMS</title>
    <!-- Premium Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/premium-design-system.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
                    <h1 class="text-3xl font-bold mb-2 text-gray-900">
                        <i class="fas fa-id-card mr-3 text-indigo-600"></i>
                        Membership Management
                    </h1>
                    <p class="text-gray-600">Manage membership types and requirements</p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <a href="add_membership_type.php" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i> Add Membership Type
                    </a>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Premium Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Types - Teal Gradient -->
                <div class="stat-card-gradient gradient-teal">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Total Types</p>
                        <h3 class="text-4xl font-bold text-white mb-2"><?php echo number_format($stats['total_types']); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-layer-group mr-1"></i> Membership categories
                        </div>
                    </div>
                    <i class="fas fa-users absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>

                <!-- Average Fee - Green Gradient -->
                <div class="stat-card-gradient gradient-green">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Average Fee</p>
                        <h3 class="text-4xl font-bold text-white mb-2">₦<?php echo number_format($stats['average_fee'], 2); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-chart-line mr-1"></i> Per membership
                        </div>
                    </div>
                    <i class="fas fa-dollar-sign absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>

                <!-- Highest Fee - Blue Gradient -->
                <div class="stat-card-gradient gradient-blue">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Highest Fee</p>
                        <h3 class="text-4xl font-bold text-white mb-2">₦<?php echo number_format($stats['highest_fee'], 2); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-arrow-up mr-1"></i> Premium tier
                        </div>
                    </div>
                    <i class="fas fa-arrow-up absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>

                <!-- Expiring Soon - Orange Gradient -->
                <div class="stat-card-gradient gradient-orange">
                    <div class="relative z-10">
                        <p class="text-white text-sm font-medium opacity-90 mb-1">Expiring Soon</p>
                        <h3 class="text-4xl font-bold text-white mb-2"><?php echo count($expiring); ?></h3>
                        <div class="flex items-center text-white text-xs opacity-90">
                            <i class="fas fa-clock mr-1"></i> Within 30 days
                        </div>
                    </div>
                    <i class="fas fa-exclamation-triangle absolute bottom-4 right-4 text-white opacity-10" style="font-size: 5rem;"></i>
                </div>
            </div>

            <!-- Expiring Memberships Alert -->
            <?php if (!empty($expiring)): ?>
                <div class="card card-admin mb-6 border-l-4 border-orange-500">
                    <div class="card-body">
                        <h5 class="font-bold text-orange-700 mb-3 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Expiring Memberships
                        </h5>
                        <p class="text-gray-600 mb-3">The following memberships will expire within 30 days:</p>
                        <ul class="list-disc list-inside text-gray-700 space-y-1">
                            <?php foreach (array_slice($expiring, 0, 5) as $exp): ?>
                                <li><?php echo htmlspecialchars($exp['member_name']); ?> - <?php echo $exp['membership_type']; ?> 
                                    <span class="text-orange-600">(Expires: <?php echo date('M d, Y', strtotime($exp['expiry_date'])); ?>)</span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($expiring) > 5): ?>
                                <li class="font-semibold">... and <?php echo count($expiring) - 5; ?> more</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Enhanced Premium Search -->
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-search mr-2 text-indigo-600"></i>
                        Search Membership Types
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-4">
                            <label for="search" class="form-label">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" class="form-control pl-10" id="search" name="search" 
                                       placeholder="Search by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="md:col-span-2 flex items-end space-x-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Search
                            </button>
                            <a href="memberships.php" class="btn btn-outline">
                                <i class="fas fa-times mr-2"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Membership Types Table -->
            <div class="card card-admin animate-fade-in">
                <div class="card-header flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Membership Types</h3>
                    <span class="badge bg-indigo-600 text-white"><?php echo $total_records; ?> Total</span>
                </div>
                <div class="card-body">
                    <?php if (empty($membership_types)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-users text-gray-400 text-5xl mb-4"></i>
                            <h5 class="text-xl font-semibold text-gray-900 mb-2">No membership types found</h5>
                            <p class="text-gray-600 mb-4">Start by adding your first membership type.</p>
                            <a href="add_membership_type.php" class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i> Add Membership Type
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-premium" id="membershipTypesTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Monthly Contribution</th>
                                        <th>Duration</th>
                                        <th>Fee (₦)</th>
                                        <th>Members</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membership_types as $type): ?>
                                        <tr>
                                            <td><?php echo $type['membership_type_id']; ?></td>
                                            <td>
                                                <div>
                                                    <strong class="text-gray-900"><?php echo htmlspecialchars($type['name']); ?></strong>
                                                    <?php if ($type['description']): ?>
                                                        <br><small class="text-gray-500"><?php echo htmlspecialchars(substr($type['description'], 0, 50)) . '...'; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo isset($type['monthly_contribution']) ? '₦' . number_format($type['monthly_contribution'], 2) : '-'; ?></td>
                                            <td>
                                                <span class="badge bg-blue-100 text-blue-700"><?php echo $type['duration']; ?> months</span>
                                            </td>
                                            <td class="font-semibold text-gray-900">₦<?php echo number_format($type['fee'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-indigo-100 text-indigo-700"><?php echo $type['member_count']; ?> members</span>
                                            </td>
                                            <td class="text-gray-600"><?php echo date('M d, Y', strtotime($type['created_at'])); ?></td>
                                            <td>
                                                <div class="flex space-x-1">
                                                    <a href="view_membership_type.php?id=<?php echo $type['membership_type_id']; ?>" 
                                                       class="btn btn-sm btn-outline" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_membership_type.php?id=<?php echo $type['membership_type_id']; ?>" 
                                                       class="btn btn-sm btn-outline" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($type['member_count'] == 0): ?>
                                                        <a href="delete_membership_type.php?id=<?php echo $type['membership_type_id']; ?>" 
                                                           class="btn btn-sm btn-outline text-red-600 hover:bg-red-50" title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this membership type?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline opacity-50 cursor-not-allowed" disabled title="Cannot delete - has active members">
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

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-6" aria-label="Membership types pagination">
                                <ul class="flex justify-center space-x-2">
                                    <?php if ($current_page > 1): ?>
                                        <li>
                                            <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" 
                                               class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                        <li>
                                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                                               class="px-4 py-2 border rounded-lg <?php echo $i == $current_page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($current_page < $total_pages): ?>
                                        <li>
                                            <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" 
                                               class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php include '../../views/includes/footer.php'; ?>
    
    <script>
        // Real-time search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const tableBody = document.querySelector('#membershipTypesTable tbody');
            const tableRows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];

            function filterTable() {
                const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
                let visibleCount = 0;

                tableRows.forEach(row => {
                    const name = row.cells[1]?.textContent.toLowerCase() || '';
                    const matchesSearch = searchTerm === '' || name.includes(searchTerm);

                    if (matchesSearch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                console.log(`Showing ${visibleCount} membership types`);
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterTable);
                searchInput.addEventListener('keyup', filterTable);
            }

            // Prevent form submission for real-time search
            const filterForm = searchInput?.closest('form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    filterTable();
                    return false;
                });
            }
        });
    </script>
</body>
</html>
