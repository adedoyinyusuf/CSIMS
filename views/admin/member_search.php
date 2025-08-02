<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize member controller
$memberController = new MemberController();

// Get search parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$membership_type = $_GET['membership_type'] ?? '';
$gender = $_GET['gender'] ?? '';
$age_min = $_GET['age_min'] ?? '';
$age_max = $_GET['age_max'] ?? '';
$join_date_from = $_GET['join_date_from'] ?? '';
$join_date_to = $_GET['join_date_to'] ?? '';
$expiry_date_from = $_GET['expiry_date_from'] ?? '';
$expiry_date_to = $_GET['expiry_date_to'] ?? '';
$city = $_GET['city'] ?? '';
$state = $_GET['state'] ?? '';
$page = $_GET['page'] ?? 1;

// Handle bulk operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected_members = $_POST['selected_members'] ?? [];
    $bulk_action = $_POST['bulk_action'];
    
    if (!empty($selected_members)) {
        switch ($bulk_action) {
            case 'activate':
                foreach ($selected_members as $member_id) {
                    $memberController->updateMember($member_id, ['status' => 'Active']);
                }
                $session->setFlash('success', count($selected_members) . ' members activated successfully');
                break;
                
            case 'deactivate':
                foreach ($selected_members as $member_id) {
                    $memberController->updateMember($member_id, ['status' => 'Inactive']);
                }
                $session->setFlash('success', count($selected_members) . ' members deactivated successfully');
                break;
                
            case 'delete':
                foreach ($selected_members as $member_id) {
                    $memberController->deleteMember($member_id);
                }
                $session->setFlash('success', count($selected_members) . ' members deleted successfully');
                break;
                
            case 'export':
                // Redirect to export with selected IDs
                $ids = implode(',', $selected_members);
                header("Location: member_export.php?ids=$ids");
                exit();
                break;
        }
    } else {
        $session->setFlash('error', 'Please select at least one member');
    }
}

// Get members with advanced search
$members_data = $memberController->searchMembersAdvanced([
    'search' => $search,
    'status' => $status,
    'membership_type' => $membership_type,
    'gender' => $gender,
    'age_min' => $age_min,
    'age_max' => $age_max,
    'join_date_from' => $join_date_from,
    'join_date_to' => $join_date_to,
    'expiry_date_from' => $expiry_date_from,
    'expiry_date_to' => $expiry_date_to,
    'city' => $city,
    'state' => $state,
    'page' => $page
]);

$members = $members_data['members'];
$total_pages = $members_data['total_pages'];
$total_members = $members_data['total_members'];

// Get membership types for filter
$membership_types = $memberController->getMembershipTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Member Search - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/members.php">Members</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Advanced Search</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-search me-2"></i>Advanced Member Search</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="<?php echo BASE_URL; ?>/admin/add_member.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i> Add Member
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/members.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-list me-1"></i> Simple View
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php if ($session->hasFlash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $session->getFlash('success'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($session->hasFlash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $session->getFlash('error'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Advanced Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Search Filters
                            <button class="btn btn-sm btn-outline-secondary float-end" type="button" data-bs-toggle="collapse" data-bs-target="#searchFilters" aria-expanded="true">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </h5>
                    </div>
                    <div class="collapse show" id="searchFilters">
                        <div class="card-body">
                            <form action="" method="GET" class="row g-3">
                                <!-- Basic Search -->
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Name, Email, Phone, ID" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                
                                <!-- Status Filter -->
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="Active" <?php echo ($status === 'Active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo ($status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="Expired" <?php echo ($status === 'Expired') ? 'selected' : ''; ?>>Expired</option>
                                        <option value="Suspended" <?php echo ($status === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                                
                                <!-- Membership Type Filter -->
                                <div class="col-md-3">
                                    <label for="membership_type" class="form-label">Membership Type</label>
                                    <select class="form-select" id="membership_type" name="membership_type">
                                        <option value="">All Types</option>
                                        <?php foreach ($membership_types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" <?php echo ($membership_type == $type['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Gender Filter -->
                                <div class="col-md-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">All Genders</option>
                                        <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <!-- Age Range -->
                                <div class="col-md-3">
                                    <label class="form-label">Age Range</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="age_min" placeholder="Min" value="<?php echo htmlspecialchars($age_min); ?>">
                                        <span class="input-group-text">to</span>
                                        <input type="number" class="form-control" name="age_max" placeholder="Max" value="<?php echo htmlspecialchars($age_max); ?>">
                                    </div>
                                </div>
                                
                                <!-- Join Date Range -->
                                <div class="col-md-3">
                                    <label class="form-label">Join Date Range</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" name="join_date_from" value="<?php echo htmlspecialchars($join_date_from); ?>">
                                        <span class="input-group-text">to</span>
                                        <input type="date" class="form-control" name="join_date_to" value="<?php echo htmlspecialchars($join_date_to); ?>">
                                    </div>
                                </div>
                                
                                <!-- Expiry Date Range -->
                                <div class="col-md-3">
                                    <label class="form-label">Expiry Date Range</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" name="expiry_date_from" value="<?php echo htmlspecialchars($expiry_date_from); ?>">
                                        <span class="input-group-text">to</span>
                                        <input type="date" class="form-control" name="expiry_date_to" value="<?php echo htmlspecialchars($expiry_date_to); ?>">
                                    </div>
                                </div>
                                
                                <!-- Location Filters -->
                                <div class="col-md-2">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>">
                                </div>
                                
                                <!-- Search Buttons -->
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-1"></i> Search
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>/admin/member_search.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Results Summary -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">Search Results: <?php echo number_format($total_members); ?> members found</h5>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportSelected()">
                                        <i class="fas fa-download me-1"></i> Export Selected
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="exportAll()">
                                        <i class="fas fa-file-excel me-1"></i> Export All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Operations Form -->
                <form method="POST" id="bulkForm">
                    <!-- Bulk Actions Bar -->
                    <div class="card mb-4" id="bulkActionsBar" style="display: none;">
                        <div class="card-body py-2">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <span id="selectedCount">0</span> members selected
                                </div>
                                <div class="col-md-6 text-end">
                                    <select name="bulk_action" class="form-select form-select-sm d-inline-block w-auto me-2">
                                        <option value="">Choose Action...</option>
                                        <option value="activate">Activate</option>
                                        <option value="deactivate">Deactivate</option>
                                        <option value="export">Export</option>
                                        <option value="delete">Delete</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary" onclick="return confirmBulkAction()">
                                        Apply
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Members Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="membersTable">
                                    <thead>
                                        <tr>
                                            <th width="30">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>ID</th>
                                            <th>Photo</th>
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
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_members[]" value="<?php echo $member['member_id']; ?>" class="form-check-input member-checkbox">
                                                </td>
                                                <td><?php echo $member['member_id']; ?></td>
                                                <td>
                                                    <?php if (!empty($member['photo'])): ?>
                                                        <img src="<?php echo BASE_URL; ?>/assets/images/members/<?php echo $member['photo']; ?>" alt="Photo" class="rounded-circle" width="40" height="40">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                            <i class="fas fa-user text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($member['gender']); ?></small>
                                                </td>
                                                <td>
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($member['email']); ?><br>
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($member['phone']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($member['membership_type']); ?></span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $expiry_date = new DateTime($member['expiry_date']);
                                                    $today = new DateTime();
                                                    $is_expired = $today > $expiry_date;
                                                    $days_to_expiry = $today->diff($expiry_date)->days;
                                                    ?>
                                                    <span class="badge <?php echo $is_expired ? 'bg-danger' : ($days_to_expiry <= 30 ? 'bg-warning' : 'bg-success'); ?>">
                                                        <?php echo date('M d, Y', strtotime($member['expiry_date'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($member['status'] == 'Active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($member['status'] == 'Inactive'): ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php elseif ($member['status'] == 'Expired'): ?>
                                                        <span class="badge bg-danger">Expired</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Suspended</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="<?php echo BASE_URL; ?>/admin/edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="<?php echo BASE_URL; ?>/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-outline-success" title="Renew">
                                                            <i class="fas fa-sync-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <!-- Include Footer -->
                <?php include '../../views/includes/footer.php'; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.member-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionsBar();
        });
        
        // Individual checkbox change
        document.querySelectorAll('.member-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsBar);
        });
        
        function updateBulkActionsBar() {
            const selectedCheckboxes = document.querySelectorAll('.member-checkbox:checked');
            const count = selectedCheckboxes.length;
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            if (count > 0) {
                bulkActionsBar.style.display = 'block';
                selectedCount.textContent = count;
            } else {
                bulkActionsBar.style.display = 'none';
            }
            
            // Update select all checkbox
            const selectAll = document.getElementById('selectAll');
            const totalCheckboxes = document.querySelectorAll('.member-checkbox').length;
            selectAll.checked = count === totalCheckboxes;
            selectAll.indeterminate = count > 0 && count < totalCheckboxes;
        }
        
        function clearSelection() {
            document.querySelectorAll('.member-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            updateBulkActionsBar();
        }
        
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const count = document.querySelectorAll('.member-checkbox:checked').length;
            
            if (!action) {
                alert('Please select an action');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${count} selected members? This action cannot be undone.`);
            }
            
            return confirm(`Are you sure you want to ${action} ${count} selected members?`);
        }
        
        function exportSelected() {
            const selectedIds = Array.from(document.querySelectorAll('.member-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                alert('Please select at least one member to export');
                return;
            }
            window.location.href = `member_export.php?ids=${selectedIds.join(',')}`;
        }
        
        function exportAll() {
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            window.location.href = `member_export.php?${params.toString()}`;
        }
    </script>
</body>
</html>
