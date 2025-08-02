<?php
/**
 * Admin - Loans List View
 * 
 * This page displays a list of all loan applications with filtering, sorting,
 * and pagination capabilities.
 */

// Require authentication and controllers
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize controllers
$loanController = new LoanController();
$memberController = new MemberController();

// Get loan statuses for filter dropdown
$loanStatuses = $loanController->getLoanStatuses();

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'application_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page

// Get loans with pagination
$result = $loanController->getAllLoans($page, $limit, $search, $sort_by, $sort_order, $status_filter);
$loans = $result['loans'];
$pagination = $result['pagination'];

// Get loan statistics
$loanStats = $loanController->getLoanStatistics();

// Page title
$pageTitle = "Loan Applications";
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
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <?php include_once __DIR__ . '/../includes/header.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('loans-list.csv')">Export CSV</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Print</button>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/admin/add_loan.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle"></i> New Loan Application
                    </a>
                </div>
            </div>
            
            <!-- Flash messages -->
            <?php include_once __DIR__ . '/../includes/flash_messages.php'; ?>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Active Loans</h5>
                            <p class="card-text fs-4"><?php echo number_format($loanStats['active_loans']['count']); ?></p>
                            <p class="card-text"><?php echo number_format($loanStats['active_loans']['amount'], 2); ?> (Total Amount)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Pending Loans</h5>
                            <p class="card-text fs-4"><?php echo number_format($loanStats['pending_loans']['count']); ?></p>
                            <p class="card-text"><?php echo number_format($loanStats['pending_loans']['amount'], 2); ?> (Total Amount)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Paid Loans</h5>
                            <p class="card-text fs-4"><?php echo number_format($loanStats['paid_loans']['count']); ?></p>
                            <p class="card-text"><?php echo number_format($loanStats['paid_loans']['amount'], 2); ?> (Total Amount)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">This Month Repayments</h5>
                            <p class="card-text fs-4"><?php echo number_format($loanStats['month_repayment_amount'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search by name, purpose..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Statuses</option>
                                        <?php foreach ($loanStatuses as $key => $value): ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($status_filter === $key) ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="sort_by" class="form-label">Sort By</label>
                                    <select class="form-select" id="sort_by" name="sort_by">
                                        <option value="application_date" <?php echo ($sort_by === 'application_date') ? 'selected' : ''; ?>>Date</option>
                                        <option value="amount" <?php echo ($sort_by === 'amount') ? 'selected' : ''; ?>>Amount</option>
                                        <option value="status" <?php echo ($sort_by === 'status') ? 'selected' : ''; ?>>Status</option>
                                        <option value="term_months" <?php echo ($sort_by === 'term_months') ? 'selected' : ''; ?>>Term</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="sort_order" class="form-label">Order</label>
                                    <select class="form-select" id="sort_order" name="sort_order">
                                        <option value="ASC" <?php echo ($sort_order === 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                                        <option value="DESC" <?php echo ($sort_order === 'DESC') ? 'selected' : ''; ?>>Descending</option>
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Loans Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Term</th>
                            <th>Interest</th>
                            <th>Application Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No loan applications found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><?php echo $loan['loan_id']; ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $loan['member_id']; ?>">
                                            <?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo number_format($loan['amount'], 2); ?></td>
                                    <td><?php echo $loan['term_months']; ?> months</td>
                                    <td><?php echo $loan['interest_rate']; ?>%</td>
                                    <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($loan['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'info',
                                                'rejected' => 'danger',
                                                'disbursed' => 'primary',
                                                'active' => 'primary',
                                                'defaulted' => 'danger',
                                                'paid' => 'success',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo $loanStatuses[$loan['status']] ?? ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>/admin/view_loan.php?id=<?php echo $loan['loan_id']; ?>" class="btn btn-info" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($loan['status'] === 'pending'): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/edit_loan.php?id=<?php echo $loan['loan_id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/admin/process_loan.php?id=<?php echo $loan['loan_id']; ?>" class="btn btn-success" title="Process">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                            <?php elseif (in_array($loan['status'], ['approved', 'disbursed', 'active'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/add_repayment.php?loan_id=<?php echo $loan['loan_id']; ?>" class="btn btn-success" title="Add Repayment">
                                                    <i class="bi bi-cash"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (in_array($loan['status'], ['pending', 'rejected'])): ?>
                                                <button type="button" class="btn btn-danger" title="Delete" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                        data-loan-id="<?php echo $loan['loan_id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" aria-label="First">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        // Calculate range of page numbers to display
                        $start_page = max(1, $pagination['current_page'] - 2);
                        $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?php echo ($i === $pagination['current_page']) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $pagination['total_pages']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($sort_by) ? '&sort_by=' . urlencode($sort_by) : ''; ?><?php echo !empty($sort_order) ? '&sort_order=' . urlencode($sort_order) : ''; ?>" aria-label="Last">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
                        </main>
                    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->
            
            <?php include_once __DIR__ . '/../includes/footer.php'; ?>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this loan application? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Set up delete confirmation modal
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const loanId = button.getAttribute('data-loan-id');
                    const confirmDeleteLink = document.getElementById('confirmDelete');
                    confirmDeleteLink.href = '<?php echo BASE_URL; ?>/admin/delete_loan.php?id=' + loanId;
                });
            }
        });
    </script>
</body>
</html>
