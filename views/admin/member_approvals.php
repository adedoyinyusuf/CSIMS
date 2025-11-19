<?php
require_once '../../config/config.php';
require_once '../../config/auth_check.php';
require_once '../../controllers/member_controller.php';
// Add missing AuthController include
require_once '../../controllers/auth_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
-    $session->setFlash('error', 'Please login to access the member approvals page');
-    header("Location: " . BASE_URL . "index.php");
+    $session->setFlash('error', 'Please login to access the member approvals page');
+    header("Location: " . BASE_URL . "/index.php");
     exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

$memberController = new MemberController();

$success = '';
$error = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $member_id = (int)$_POST['member_id'];
    
    if ($action === 'approve') {
        $result = $memberController->approveMember($member_id);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'reject') {
        $result = $memberController->rejectMember($member_id);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get pending members
$pendingMembers = $memberController->getPendingMembers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Approvals - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    
</head>
<body>
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content mt-16">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-user-check"></i> Member Approvals</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                        <span class="badge bg-warning text-dark fs-6">
                            <i class="fas fa-clock"></i> <?php echo count($pendingMembers); ?> Pending
                        </span>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Pending Members Table -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users"></i> Pending Member Registrations
                        </h5>
                        <span class="badge bg-primary"><?php echo count($pendingMembers); ?> Total Pending</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingMembers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                <h4 class="text-muted">No Pending Approvals</h4>
                                <p class="text-muted">All member registrations have been processed.</p>
                                <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="btn btn-primary">
                                    <i class="fas fa-users"></i> View All Members
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Contact</th>
                                            <th>Membership Type</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingMembers as $member): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                        <div class="ms-3">
                                                            <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                            <div class="text-muted">ID: <?php echo htmlspecialchars($member['member_id']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($member['email']); ?></div>
                                                    <div class="text-muted"><?php echo htmlspecialchars($member['phone']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['membership_type'] ?? 'Standard'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($member['registration_date'] ?? $member['join_date'] ?? 'now')); ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="member_id" value="<?php echo (int)$member['member_id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline ms-2">
                                                        <input type="hidden" name="member_id" value="<?php echo (int)$member['member_id']; ?>">
                                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
</body>
</html>