<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: <?php echo BASE_URL; ?>/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Check if member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $session->setFlash('error', 'Member ID is required');
    header("Location: members.php");
    exit();
}

$member_id = (int)$_GET['id'];

// Initialize member controller
$memberController = new MemberController();

// Get member details
$member = $memberController->getMemberById($member_id);

if (!$member) {
    $session->setFlash('error', 'Member not found');
    header("Location: members.php");
    exit();
}

// Check for confirmation
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// If confirmed, delete the member
if ($confirmed) {
    if ($memberController->deleteMember($member_id)) {
        $session->setFlash('success', 'Member deleted successfully');
        header("Location: members.php");
        exit();
    } else {
        $session->setFlash('error', 'Failed to delete member');
        header("Location: view_member.php?id=$member_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Member - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
                        <li class="breadcrumb-item"><a href="view_member.php?id=<?php echo $member_id; ?>">View Member</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Delete Member</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Delete Member</h1>
                </div>
                
                <!-- Flash Messages -->
                <?php if ($session->hasFlash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $session->getFlash('error'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4>Are you sure you want to delete this member?</h4>
                                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. The member will be marked as deleted in the system.</p>
                                
                                <p>You are about to delete the following member:</p>
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th width="30%">Member ID</th>
                                            <td><?php echo $member['member_id']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Name</th>
                                            <td><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td><?php echo $member['email']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phone</th>
                                            <td><?php echo $member['phone']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Membership Type</th>
                                            <td><?php echo $member['membership_type']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Join Date</th>
                                            <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle me-2"></i> Note: This will perform a soft delete. The member's information will be retained in the database but marked as deleted. They will no longer appear in active member lists.
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="view_member.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">Cancel</a>
                                    <a href="delete_member.php?id=<?php echo $member_id; ?>&confirm=yes" class="btn btn-danger">
                                        <i class="fas fa-trash me-2"></i> Yes, Delete Member
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="text-center mb-4">
                                    <?php if (!empty($member['photo'])): ?>
                                        <img src="../../assets/images/members/<?php echo $member['photo']; ?>" alt="Profile" class="img-thumbnail" style="max-width: 150px;">
                                    <?php else: ?>
                                        <div class="profile-img bg-secondary d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; margin: 0 auto;">
                                            <i class="fas fa-user fa-5x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title">Alternative Actions</h5>
                                        <p>Instead of deleting this member, you might consider:</p>
                                        <ul>
                                            <li>Setting their status to "Inactive"</li>
                                            <li>Updating their information</li>
                                            <li>Renewing their membership</li>
                                        </ul>
                                        <div class="d-grid gap-2">
                                            <a href="edit_member.php?id=<?php echo $member_id; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit me-2"></i> Edit Member
                                            </a>
                                            <a href="renew_membership.php?id=<?php echo $member_id; ?>" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-sync-alt me-2"></i> Renew Membership
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Include Footer -->
                <?php include '../../views/includes/footer.php'; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../../assets/js/script.js"></script>
</body>
</html>
