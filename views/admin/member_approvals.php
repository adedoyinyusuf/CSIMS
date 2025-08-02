<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access the member approvals page');
    header("Location: " . BASE_URL . "index.php");
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
                                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                            <i class="fas fa-user text-white"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($member['username']); ?></small>
                                                            <?php if ($member['gender']): ?>
                                                                <small class="text-muted"> • <?php echo htmlspecialchars($member['gender']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($member['email']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($member['phone'] ?: 'N/A'); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($member['membership_type']); ?></span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" 
                                                                onclick="showMemberDetails(<?php echo $member['member_id']; ?>)" 
                                                                data-bs-toggle="modal" data-bs-target="#memberModal">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to approve this member?')">
                                                            <input type="hidden" name="action" value="approve">
                                                            <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to reject this member registration?')">
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Member Details Modal -->
    <div class="modal fade" id="memberModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user"></i> Member Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="memberDetails">
                    <!-- Member details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function showMemberDetails(memberId) {
        // Find member data from the pending members array
        const members = <?php echo json_encode($pendingMembers); ?>;
        const member = members.find(m => m.member_id == memberId);
        
        if (member) {
            const detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-user"></i> Personal Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${member.first_name} ${member.last_name}</td></tr>
                            <tr><td><strong>Username:</strong></td><td>${member.username}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${member.email}</td></tr>
                            <tr><td><strong>Phone:</strong></td><td>${member.phone || 'N/A'}</td></tr>
                            <tr><td><strong>Date of Birth:</strong></td><td>${member.dob || 'N/A'}</td></tr>
                            <tr><td><strong>Gender:</strong></td><td>${member.gender || 'N/A'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle"></i> Additional Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Occupation:</strong></td><td>${member.occupation || 'N/A'}</td></tr>
                            <tr><td><strong>Address:</strong></td><td>${member.address || 'N/A'}</td></tr>
                            <tr><td><strong>Membership Type:</strong></td><td><span class="badge bg-info">${member.membership_type}</span></td></tr>
                            <tr><td><strong>Registration Date:</strong></td><td>${new Date(member.join_date).toLocaleDateString()}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-warning">${member.status}</span></td></tr>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('memberDetails').innerHTML = detailsHtml;
        }
    }
    </script>

    <?php include '../../views/includes/footer.php'; ?>
</body>
</html>