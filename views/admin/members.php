<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access the members page');
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize member controller
$memberController = new MemberController();

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$membership_type = isset($_GET['membership_type']) ? $_GET['membership_type'] : '';

// Get members with pagination
$result = $memberController->getAllMembers($page, $search, $status, $membership_type);
$members = $result['members'];
$pagination = $result['pagination'];
$total_pages = $pagination['total_pages'];
$total_members = $pagination['total_items'];

// Get membership types for filter
$membership_types = $memberController->getMembershipTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Members</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_member.php" class="btn btn-sm btn-primary me-2">
                            <i class="fas fa-user-plus"></i> Add New Member
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2 btn-export-csv" data-table="membersTable">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-print">
                            <i class="fas fa-print"></i> Print
                        </button>
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
                
                <!-- Filter and Search -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">Filter Members</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Name, Email, Phone, ID" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo ($status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Expired" <?php echo ($status == 'Expired') ? 'selected' : ''; ?>>Expired</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="membership_type" class="form-label">Membership Type</label>
                                <select class="form-select" id="membership_type" name="membership_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($membership_types as $type): ?>
                                        <option value="<?php echo $type['type_id']; ?>" <?php echo ($membership_type == $type['type_id']) ? 'selected' : ''; ?>>
                                            <?php echo $type['type_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Members Table -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Member List</h5>
                        <span class="badge bg-primary"><?php echo $total_members; ?> Total Members</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="membersTable">
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
                                            <tr>
                                                <td><?php echo $member['member_id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($member['photo'])): ?>
                                            <img src="<?php echo BASE_URL; ?>/assets/images/members/<?php echo $member['photo']; ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
                                                        <?php else: ?>
                                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-user text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold"><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></div>
                                                            <small class="text-muted"><?php echo $member['gender']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?php echo $member['email']; ?></div>
                                                    <small class="text-muted"><?php echo $member['phone']; ?></small>
                                                </td>
                                                <td><?php echo $member['membership_type']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $expiry_date = strtotime($member['expiry_date']);
                                                    $today = strtotime('today');
                                                    $days_left = round(($expiry_date - $today) / (60 * 60 * 24));
                                                    
                                                    echo date('M d, Y', $expiry_date);
                                                    
                                                    if ($days_left <= 0) {
                                                        echo '<span class="badge bg-danger ms-2">Expired</span>';
                                                    } elseif ($days_left <= 30) {
                                                        echo '<span class="badge bg-warning ms-2">' . $days_left . ' days left</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($member['status'] == 'Active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($member['status'] == 'Inactive'): ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Expired</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/views/admin/edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit Member">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($days_left <= 0): ?>
                                            <a href="<?php echo BASE_URL; ?>/views/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Renew Membership">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo BASE_URL; ?>/views/admin/delete_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Delete Member">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No members found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&membership_type=<?php echo urlencode($membership_type); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
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
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Include Footer -->
                <?php include '../../views/includes/footer.php'; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable with pagination disabled (we're using our own)
            $('#membersTable').DataTable({
                "paging": false,
                "ordering": true,
                "info": false,
                "searching": false
            });
        });
    </script>
</body>
</html>
