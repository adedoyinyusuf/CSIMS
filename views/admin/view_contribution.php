<?php
/**
 * View Contribution Page
 * 
 * This page displays detailed information about a specific contribution.
 */

// Include required files
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/contribution_controller.php';
require_once '../../controllers/member_controller.php';

// Initialize controllers
$authController = new AuthController();
$contributionController = new ContributionController();
$memberController = new MemberController();

// Check if user is logged in
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get current user
$currentUser = $authController->getCurrentUser();

// Check if contribution ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = "No contribution specified.";
    $_SESSION['flash_message_type'] = "danger";
    header('Location: contributions.php');
    exit;
}

$contribution_id = (int)$_GET['id'];

// Get contribution details
$contribution = $contributionController->getContributionById($contribution_id);

if (!$contribution) {
    $_SESSION['flash_message'] = "Contribution not found.";
    $_SESSION['flash_message_type'] = "danger";
    header('Location: contributions.php');
    exit;
}

// Get member details
$member = $memberController->getMemberById($contribution['member_id']);

// Page title
$pageTitle = "View Contribution";

// Include header
include_once '../includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">View Contribution</h1>
        <div>
            <a href="edit_contribution.php?id=<?php echo htmlspecialchars($contribution_id); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button class="btn btn-info btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="contributions.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Contributions
            </a>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="contributions.php">Contributions</a></li>
            <li class="breadcrumb-item active" aria-current="page">View Contribution</li>
        </ol>
    </nav>

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

    <div class="row">
        <!-- Contribution Details Card -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Contribution Details</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                            <a class="dropdown-item" href="edit_contribution.php?id=<?php echo htmlspecialchars($contribution_id); ?>">
                                <i class="fas fa-edit fa-sm fa-fw mr-2 text-gray-400"></i> Edit
                            </a>
                            <a class="dropdown-item" href="#" onclick="confirmDelete(<?php echo htmlspecialchars($contribution_id); ?>); return false;">
                                <i class="fas fa-trash fa-sm fa-fw mr-2 text-gray-400"></i> Delete
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" onclick="window.print(); return false;">
                                <i class="fas fa-print fa-sm fa-fw mr-2 text-gray-400"></i> Print
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th width="30%">Contribution ID</th>
                                    <td><?php echo htmlspecialchars($contribution['contribution_id']); ?></td>
                                </tr>
                                <tr>
                                    <th>Amount</th>
                                    <td>
                                        <span class="font-weight-bold text-success">
                                            $<?php echo htmlspecialchars(number_format($contribution['amount'], 2)); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Contribution Date</th>
                                    <td><?php echo htmlspecialchars(date('F d, Y', strtotime($contribution['contribution_date']))); ?></td>
                                </tr>
                                <tr>
                                    <th>Contribution Type</th>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo htmlspecialchars($contribution['contribution_type']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Payment Method</th>
                                    <td><?php echo htmlspecialchars($contribution['payment_method']); ?></td>
                                </tr>
                                <tr>
                                    <th>Receipt Number</th>
                                    <td>
                                        <?php if (!empty($contribution['receipt_number'])): ?>
                                            <?php echo htmlspecialchars($contribution['receipt_number']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Notes</th>
                                    <td>
                                        <?php if (!empty($contribution['notes'])): ?>
                                            <?php echo nl2br(htmlspecialchars($contribution['notes'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No notes</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td><?php echo htmlspecialchars(date('F d, Y h:i A', strtotime($contribution['created_at']))); ?></td>
                                </tr>
                                <?php if (!empty($contribution['updated_at'])): ?>
                                <tr>
                                    <th>Last Updated</th>
                                    <td><?php echo htmlspecialchars(date('F d, Y h:i A', strtotime($contribution['updated_at']))); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Member Information Card -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Member Information</h6>
                </div>
                <div class="card-body">
                    <?php if ($member): ?>
                        <div class="text-center mb-3">
                            <?php if (!empty($member['photo'])): ?>
                                <img class="img-profile rounded-circle" src="../../uploads/members/<?php echo htmlspecialchars($member['photo']); ?>" 
                                     alt="Member Photo" style="width: 100px; height: 100px; object-fit: cover;">
                            <?php else: ?>
                                <img class="img-profile rounded-circle" src="../../assets/img/undraw_profile.svg" 
                                     alt="Default Profile" style="width: 100px; height: 100px; object-fit: cover;">
                            <?php endif; ?>
                        </div>
                        <h5 class="text-center mb-3">
                            <a href="view_member.php?id=<?php echo htmlspecialchars($member['member_id']); ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </a>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th>Member ID</th>
                                        <td><?php echo htmlspecialchars($member['member_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone</th>
                                        <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Membership Type</th>
                                        <td><?php echo htmlspecialchars($member['membership_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php if ($member['status'] === 'active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php elseif ($member['status'] === 'inactive'): ?>
                                                <span class="badge badge-warning">Inactive</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"><?php echo ucfirst(htmlspecialchars($member['status'])); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="view_member.php?id=<?php echo htmlspecialchars($member['member_id']); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-user"></i> View Full Profile
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            Member information not available. The member may have been deleted.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

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

<script>
    // Function to confirm deletion
    function confirmDelete(id) {
        document.getElementById('confirmDeleteBtn').href = 'contributions.php?delete=1&id=' + id;
        $('#deleteModal').modal('show');
    }
</script>

<!-- Print Styles -->
<style media="print">
    @page {
        size: auto;
        margin: 10mm;
    }
    
    body {
        background-color: #fff !important;
    }
    
    .no-print, .no-print * {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #fff !important;
        border-bottom: 2px solid #000 !important;
    }
    
    .container-fluid {
        padding: 0 !important;
    }
    
    .dropdown, .btn, .breadcrumb, .alert {
        display: none !important;
    }
    
    .table-bordered {
        border: 1px solid #000 !important;
    }
    
    .table-bordered td, .table-bordered th {
        border: 1px solid #000 !important;
    }
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
