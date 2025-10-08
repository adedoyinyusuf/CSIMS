<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/contribution_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get member ID from URL
$member_id = $_GET['id'] ?? null;

if (!$member_id) {
    $session->setFlash('error', 'Member ID is required');
    header("Location: " . BASE_URL . "/views/admin/members.php");
    exit();
}

// Initialize controllers
$memberController = new MemberController();
$contributionController = new ContributionController();

// Get member details
$member = $memberController->getMemberById($member_id);

if (!$member) {
    $session->setFlash('error', 'Member not found');
    header("Location: " . BASE_URL . "/views/admin/members.php");
    exit();
}

// Calculate age
$age = '';
if (!empty($member['date_of_birth'])) {
    $dob = new DateTime($member['date_of_birth']);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
}

// Calculate membership status
$expiry_date = new DateTime($member['expiry_date']);
$today = new DateTime();
$is_expired = $today > $expiry_date;
$days_to_expiry = $today->diff($expiry_date)->days;

// Get member contributions
$contributions = $contributionController->getMemberContributions($member_id, 1, 10);

// Get member statistics
$member_stats = [
    'total_contributions' => $contributionController->getMemberTotalContributions($member_id),
    'contribution_count' => $contributionController->getMemberContributionCount($member_id),
    'last_contribution' => $contributionController->getMemberLastContribution($member_id)
];

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'activate':
            $result = $memberController->updateMember($member_id, ['status' => 'Active']);
            if ($result['success']) {
                $session->setFlash('success', 'Member activated successfully');
            } else {
                $session->setFlash('error', $result['message']);
            }
            break;
            
        case 'deactivate':
            $result = $memberController->updateMember($member_id, ['status' => 'Inactive']);
            if ($result['success']) {
                $session->setFlash('success', 'Member deactivated successfully');
            } else {
                $session->setFlash('error', $result['message']);
            }
            break;
            
        case 'suspend':
            $result = $memberController->updateMember($member_id, ['status' => 'Suspended']);
            if ($result['success']) {
                $session->setFlash('success', 'Member suspended successfully');
            } else {
                $session->setFlash('error', $result['message']);
            }
            break;
    }
    
    // Refresh member data
    $member = $memberController->getMemberById($member_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> - Profile - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/members.php">Members</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></li>
                    </ol>
                </nav>
                
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
                
                <!-- Member Profile Header -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <?php if (!empty($member['photo'])): ?>
                                    <?php 
                                    // Handle different photo path formats
                                    $photo_url = $member['photo'];
                                    if (strpos($photo_url, 'assets/') === 0) {
                                        // Photo path already includes assets/ directory
                                        $photo_url = BASE_URL . '/' . $photo_url;
                                    } else {
                                        // Photo path is just filename, use default directory
                                        $photo_url = BASE_URL . '/assets/images/members/' . $photo_url;
                                    }
                                    ?>
                                    <img src="<?php echo $photo_url; ?>" alt="Profile Photo" class="rounded-circle img-fluid" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px;">
                                        <i class="fas fa-user fa-3x text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h2 class="mb-1"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h2>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-id-card me-2"></i>Member ID: <?php echo $member['member_id']; ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($member['email']); ?><br>
                                    <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($member['phone']); ?>
                                </p>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-info me-2"><?php echo htmlspecialchars($member['membership_type']); ?></span>
                                    <?php if ($member['status'] == 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($member['status'] == 'Inactive'): ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php elseif ($member['status'] == 'Expired'): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Suspended</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="btn-group mb-2" role="group">
                                    <a href="<?php echo BASE_URL; ?>/views/admin/edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/views/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>" class="btn btn-success">
                                        <i class="fas fa-sync-alt me-1"></i> Renew
                                    </a>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if ($member['status'] !== 'Active'): ?>
                                                <li><a class="dropdown-item" href="#" onclick="quickAction('activate')"><i class="fas fa-check me-2"></i>Activate</a></li>
                                            <?php endif; ?>
                                            <?php if ($member['status'] !== 'Inactive'): ?>
                                                <li><a class="dropdown-item" href="#" onclick="quickAction('deactivate')"><i class="fas fa-times me-2"></i>Deactivate</a></li>
                                            <?php endif; ?>
                                            <?php if ($member['status'] !== 'Suspended'): ?>
                                                <li><a class="dropdown-item" href="#" onclick="quickAction('suspend')"><i class="fas fa-ban me-2"></i>Suspend</a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/member_export.php?ids=<?php echo $member['member_id']; ?>&format=pdf"><i class="fas fa-file-pdf me-2"></i>Export PDF</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="printProfile()"><i class="fas fa-print me-2"></i>Print Profile</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-calendar-alt fa-2x text-primary mb-2"></i>
                                <h5 class="card-title">Member Since</h5>
                                <p class="card-text"><?php echo date('M d, Y', strtotime($member['join_date'])); ?></p>
                                <small class="text-muted"><?php echo floor((time() - strtotime($member['join_date'])) / (365*24*60*60)); ?> years</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x <?php echo $is_expired ? 'text-danger' : ($days_to_expiry <= 30 ? 'text-warning' : 'text-success'); ?> mb-2"></i>
                                <h5 class="card-title">Membership Expiry</h5>
                                <p class="card-text"><?php echo date('M d, Y', strtotime($member['expiry_date'])); ?></p>
                                <small class="text-muted">
                                    <?php if ($is_expired): ?>
                                        Expired <?php echo $days_to_expiry; ?> days ago
                                    <?php else: ?>
                                        <?php echo $days_to_expiry; ?> days remaining
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                                <h5 class="card-title">Total Contributions</h5>
                                <p class="card-text">$<?php echo number_format($member_stats['total_contributions'], 2); ?></p>
                                <small class="text-muted"><?php echo $member_stats['contribution_count']; ?> transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-birthday-cake fa-2x text-info mb-2"></i>
                                <h5 class="card-title">Age</h5>
                                <p class="card-text"><?php echo $age; ?> years</p>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($member['date_of_birth'])); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabbed Content -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="memberTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                                    <i class="fas fa-user me-1"></i> Personal Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contributions-tab" data-bs-toggle="tab" data-bs-target="#contributions" type="button" role="tab">
                                    <i class="fas fa-money-bill-wave me-1"></i> Contributions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                    <i class="fas fa-history me-1"></i> Activity Log
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                                    <i class="fas fa-file-alt me-1"></i> Documents
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="memberTabsContent">
                            <!-- Personal Details Tab -->
                            <div class="tab-pane fade show active" id="details" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Personal Information</h5>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Full Name:</strong></td>
                                                <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Gender:</strong></td>
                                                <td><?php echo htmlspecialchars($member['gender']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Date of Birth:</strong></td>
                                                <td><?php echo date('M d, Y', strtotime($member['date_of_birth'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Age:</strong></td>
                                                <td><?php echo $age; ?> years</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Occupation:</strong></td>
                                                <td><?php echo htmlspecialchars($member['occupation']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Contact Information</h5>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><a href="mailto:<?php echo $member['email']; ?>"><?php echo htmlspecialchars($member['email']); ?></a></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td><a href="tel:<?php echo $member['phone']; ?>"><?php echo htmlspecialchars($member['phone']); ?></a></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Address:</strong></td>
                                                <td><?php echo htmlspecialchars($member['address']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>City:</strong></td>
                                                <td><?php echo htmlspecialchars($member['city']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>State:</strong></td>
                                                <td><?php echo htmlspecialchars($member['state']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Postal Code:</strong></td>
                                                <td><?php echo htmlspecialchars($member['postal_code']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Country:</strong></td>
                                                <td><?php echo htmlspecialchars($member['country']); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h5>Membership Information</h5>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Membership Type:</strong></td>
                                                <td><span class="badge bg-info"><?php echo htmlspecialchars($member['membership_type']); ?></span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
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
                                            </tr>
                                            <tr>
                                                <td><strong>Join Date:</strong></td>
                                                <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Expiry Date:</strong></td>
                                                <td>
                                                    <span class="badge <?php echo $is_expired ? 'bg-danger' : ($days_to_expiry <= 30 ? 'bg-warning' : 'bg-success'); ?>">
                                                        <?php echo date('M d, Y', strtotime($member['expiry_date'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <!-- Member Type Display -->
                                            <tr>
                                                <th>Member Type</th>
                                                <td><?php echo isset($member['member_type']) ? ucfirst($member['member_type']) : 'Member'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Additional Information</h5>
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Notes:</strong></td>
                                                <td><?php echo nl2br(htmlspecialchars($member['notes'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Created:</strong></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($member['created_at'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Last Updated:</strong></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($member['updated_at'])); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contributions Tab -->
                            <div class="tab-pane fade" id="contributions" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>Contribution History</h5>
                                    <a href="<?php echo BASE_URL; ?>/admin/add_contribution.php?member_id=<?php echo $member['member_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Add Contribution
                                    </a>
                                </div>
                                
                                <?php if (!empty($contributions['contributions'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Description</th>
                                                    <th>Received By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($contributions['contributions'] as $contribution): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?></td>
                                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($contribution['contribution_type']); ?></span></td>
                                                        <td>$<?php echo number_format($contribution['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($contribution['description']); ?></td>
                                                        <td><?php echo htmlspecialchars($contribution['received_by_name'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <a href="<?php echo BASE_URL; ?>/admin/member_contributions.php?member_id=<?php echo $member['member_id']; ?>" class="btn btn-outline-primary">
                                            View All Contributions
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Contributions Yet</h5>
                                        <p class="text-muted">This member hasn't made any contributions yet.</p>
                                        <a href="<?php echo BASE_URL; ?>/admin/add_contribution.php?member_id=<?php echo $member['member_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i> Add First Contribution
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Activity Log Tab -->
                            <div class="tab-pane fade" id="activity" role="tabpanel">
                                <h5>Recent Activity</h5>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-primary"></div>
                                        <div class="timeline-content">
                                            <h6 class="timeline-title">Member Joined</h6>
                                            <p class="timeline-text">Became a member of the cooperative</p>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($member['join_date'])); ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($member_stats['last_contribution']): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-success"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">Last Contribution</h6>
                                                <p class="timeline-text">Made a contribution of $<?php echo number_format($member_stats['last_contribution']['amount'], 2); ?></p>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($member_stats['last_contribution']['contribution_date'])); ?></small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-info"></div>
                                        <div class="timeline-content">
                                            <h6 class="timeline-title">Profile Updated</h6>
                                            <p class="timeline-text">Member information was last updated</p>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($member['updated_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Documents Tab -->
                            <div class="tab-pane fade" id="documents" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>Member Documents</h5>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                        <i class="fas fa-upload me-1"></i> Upload Document
                                    </button>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-id-card fa-3x text-primary mb-3"></i>
                                                <h6>ID Copy</h6>
                                                <p class="text-muted small">Government issued ID</p>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-file-signature fa-3x text-success mb-3"></i>
                                                <h6>Application Form</h6>
                                                <p class="text-muted small">Membership application</p>
                                                <button class="btn btn-sm btn-outline-success">View</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-camera fa-3x text-info mb-3"></i>
                                                <h6>Profile Photo</h6>
                                                <p class="text-muted small">Member photograph</p>
                                                <?php if (!empty($member['photo'])): ?>
                                                    <button class="btn btn-sm btn-outline-info">View</button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled>Not Available</button>
                                                <?php endif; ?>
                                            </div>
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
    
    <!-- Quick Action Form (Hidden) -->
    <form id="quickActionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionInput">
    </form>
    
    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label for="documentType" class="form-label">Document Type</label>
                            <select class="form-select" id="documentType">
                                <option value="">Select document type...</option>
                                <option value="id">ID Copy</option>
                                <option value="application">Application Form</option>
                                <option value="photo">Profile Photo</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="documentFile" class="form-label">Choose File</label>
                            <input type="file" class="form-control" id="documentFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                        <div class="mb-3">
                            <label for="documentDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="documentDescription" rows="3" placeholder="Optional description..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Upload Document</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -23px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px #dee2e6;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #007bff;
        }
        
        .timeline-title {
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .timeline-text {
            margin-bottom: 5px;
            color: #6c757d;
        }
    </style>
    
    <script>
        function quickAction(action) {
            if (confirm(`Are you sure you want to ${action} this member?`)) {
                document.getElementById('actionInput').value = action;
                document.getElementById('quickActionForm').submit();
            }
        }
        
        function printProfile() {
            window.print();
        }
        
        // Print styles
        const printStyles = `
            @media print {
                .btn, .dropdown, .nav-tabs, .breadcrumb, .alert {
                    display: none !important;
                }
                .card {
                    border: none !important;
                    box-shadow: none !important;
                }
                .tab-content .tab-pane {
                    display: block !important;
                    opacity: 1 !important;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>
