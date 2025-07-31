<?php
/**
 * Contributions Management Page
 * 
 * This page displays a list of all contributions with filtering, sorting, and pagination.
 * It also provides options to add, edit, view, and delete contributions.
 */

// Include required files
require_once '../config/config.php';
require_once '../controllers/auth_controller.php';
require_once '../controllers/contribution_controller.php';
require_once '../controllers/member_controller.php';

// Initialize controllers
$auth = new AuthController();
$contributionController = new ContributionController();
$memberController = new MemberController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: <?php echo BASE_URL; ?>/index.php');
    exit;
}

// Get current user
$current_user = $auth->getCurrentUser();

// Process pagination, search, and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'contribution_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get contributions with pagination
$result = $contributionController->getAllContributions(
    $page, $limit, $search, $sort_by, $sort_order, $filter_type, $date_from, $date_to
);

$contributions = $result['contributions'];
$pagination = $result['pagination'];

// Get contribution types for filter dropdown
$contributionTypes = $contributionController->getContributionTypes();

// Handle contribution deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $contribution_id = (int)$_GET['id'];
    if ($contributionController->deleteContribution($contribution_id)) {
        $_SESSION['flash_message'] = "Contribution deleted successfully.";
        $_SESSION['flash_message_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Failed to delete contribution.";
        $_SESSION['flash_message_type'] = "danger";
    }
    
    // Redirect to remove the delete parameter from URL
    header('Location: contributions.php');
    exit;
}

// Page title
$pageTitle = "Manage Contributions";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title><?php echo $pageTitle; ?> - CSIMS</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <?php include_once '../includes/header.php'; ?>

                <!-- Begin Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Contributions</h1>
        <div>
            <a href="<?php echo BASE_URL; ?>/admin/add_contribution.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add New Contribution
            </a>
            <button class="btn btn-success btn-sm" onclick="exportTableToCSV('contributions.csv')">
                <i class="fas fa-download"></i> Export to CSV
            </button>
            <button class="btn btn-info btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Flash Message -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_message_type']; ?> alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['flash_message']; 
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_message_type']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters and Search</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row">
                <!-- Search -->
                <div class="col-md-3 mb-3">
                    <label for="search">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Name, Receipt #, Notes" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <!-- Contribution Type Filter -->
                <div class="col-md-2 mb-3">
                    <label for="filter_type">Contribution Type</label>
                    <select class="form-control" id="filter_type" name="filter_type">
                        <option value="">All Types</option>
                        <?php foreach ($contributionTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                <?php echo ($filter_type === $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Date Range Filter -->
                <div class="col-md-2 mb-3">
                    <label for="date_from">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="date_to">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <!-- Sort Options -->
                <div class="col-md-2 mb-3">
                    <label for="sort_by">Sort By</label>
                    <select class="form-control" id="sort_by" name="sort_by">
                        <option value="contribution_date" <?php echo ($sort_by === 'contribution_date') ? 'selected' : ''; ?>>Date</option>
                        <option value="amount" <?php echo ($sort_by === 'amount') ? 'selected' : ''; ?>>Amount</option>
                        <option value="contribution_type" <?php echo ($sort_by === 'contribution_type') ? 'selected' : ''; ?>>Type</option>
                        <option value="payment_method" <?php echo ($sort_by === 'payment_method') ? 'selected' : ''; ?>>Payment Method</option>
                    </select>
                </div>
                
                <div class="col-md-1 mb-3">
                    <label for="sort_order">Order</label>
                    <select class="form-control" id="sort_order" name="sort_order">
                        <option value="ASC" <?php echo ($sort_order === 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo ($sort_order === 'DESC') ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>/admin/contributions.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contributions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Contributions List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="contributionsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Payment Method</th>
                            <th>Receipt #</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contributions)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No contributions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contributions as $contribution): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($contribution['contribution_id']); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo htmlspecialchars($contribution['member_id']); ?>">
                                            <?php echo htmlspecialchars($contribution['first_name'] . ' ' . $contribution['last_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars(number_format($contribution['amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($contribution['contribution_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($contribution['contribution_type']); ?></td>
                                    <td><?php echo htmlspecialchars($contribution['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($contribution['receipt_number']); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/admin/view_contribution.php?id=<?php echo htmlspecialchars($contribution['contribution_id']); ?>" 
                                           class="btn btn-info btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/admin/edit_contribution.php?id=<?php echo htmlspecialchars($contribution['contribution_id']); ?>" 
                                           class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn btn-danger btn-sm" title="Delete" 
                                           onclick="confirmDelete(<?php echo htmlspecialchars($contribution['contribution_id']); ?>)">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagination['current_page'] > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>">
                                    First
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        // Calculate range of page numbers to display
                        $start_page = max(1, $pagination['current_page'] - 2);
                        $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?php echo ($i == $pagination['current_page']) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>">
                                    Next
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $pagination['total_pages']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_type) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>">
                                    Last
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
            <!-- Showing entries info -->
            <div class="text-center mt-3">
                Showing <?php echo ($pagination['total_records'] == 0) ? 0 : (($pagination['current_page'] - 1) * $pagination['limit'] + 1); ?> to 
                <?php echo min($pagination['current_page'] * $pagination['limit'], $pagination['total_records']); ?> of 
                <?php echo $pagination['total_records']; ?> entries
            </div>
        </div>
    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
            
            <?php include_once '../includes/footer.php'; ?>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this contribution? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Function to confirm deletion
        function confirmDelete(id) {
            document.getElementById('confirmDeleteBtn').href = '<?php echo BASE_URL; ?>/admin/contributions.php?delete=1&id=' + id;
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html>
