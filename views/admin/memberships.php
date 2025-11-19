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
$limit = 10;

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Membership Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_membership_type.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Membership Type
                        </a>
                    </div>
                </div>

                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Types</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_types']); ?>
                                        </div>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Average Fee</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            ₦<?php echo number_format($stats['average_fee'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Highest Fee</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            ₦<?php echo number_format($stats['highest_fee'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Expiring Soon</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo count($expiring); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expiring Memberships Alert -->
                <?php if (!empty($expiring)): ?>
                    <div class="alert alert-warning" role="alert">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Expiring Memberships</h5>
                        <p>The following memberships will expire within 30 days:</p>
                        <ul class="mb-0">
                            <?php foreach (array_slice($expiring, 0, 5) as $exp): ?>
                                <li><?php echo htmlspecialchars($exp['member_name']); ?> - <?php echo $exp['membership_type']; ?> (Expires: <?php echo date('M d, Y', strtotime($exp['expiry_date'])); ?>)</li>
                            <?php endforeach; ?>
                            <?php if (count($expiring) > 5): ?>
                                <li>... and <?php echo count($expiring) - 5; ?> more</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search membership types..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="memberships.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Membership Types Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Membership Types (<?php echo $total_records; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($membership_types)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5>No membership types found</h5>
                                <p class="text-muted">Start by adding your first membership type.</p>
                                <a href="add_membership_type.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Membership Type
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
<th>Monthly Savings Requirement</th>
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
                                                    <strong><?php echo htmlspecialchars($type['name']); ?></strong>
<td><?php echo isset($type['monthly_contribution']) ? '₦' . number_format($type['monthly_contribution'], 2) : '-'; ?></td>
                                                    <?php if ($type['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($type['description'], 0, 50)) . '...'; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $type['duration']; ?> months</span>
                                                </td>
                                                <td>₦<?php echo number_format($type['fee'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $type['member_count']; ?> members</span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($type['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view_membership_type.php?id=<?php echo $type['membership_type_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_membership_type.php?id=<?php echo $type['membership_type_id']; ?>" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($type['member_count'] == 0): ?>
                                                            <a href="delete_membership_type.php?id=<?php echo $type['membership_type_id']; ?>" 
                                                               class="btn btn-sm btn-outline-danger" title="Delete"
                                                               onclick="return confirm('Are you sure you want to delete this membership type?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-danger" disabled title="Cannot delete - has active members">
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
                                <nav aria-label="Membership types pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($current_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
