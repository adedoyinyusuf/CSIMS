<?php
require_once '../config/config.php';
require_once '../controllers/auth_controller.php';
require_once '../controllers/member_controller.php';

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

// Calculate age from date of birth
$age = '';
if (!empty($member['date_of_birth'])) {
    $dob = new DateTime($member['date_of_birth']);
    $now = new DateTime();
    $interval = $now->diff($dob);
    $age = $interval->y;
}

// Calculate days until membership expiry
$expiry_date = new DateTime($member['expiry_date']);
$now = new DateTime();
$days_until_expiry = $now->diff($expiry_date)->days;
$is_expired = $now > $expiry_date;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Member - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Include Header/Navbar -->
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/members.php">Members</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View Member</li>
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
                <div class="profile-header mb-4">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <?php if (!empty($member['photo'])): ?>
                                    <img src="<?php echo BASE_URL; ?>/assets/images/members/<?php echo $member['photo']; ?>" alt="Profile" class="profile-img">
                                <?php else: ?>
                                    <div class="profile-img bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user fa-4x text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-7">
                                <h2 class="mb-1"><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></h2>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-id-card me-2"></i> Member ID: <?php echo $member['member_id']; ?>
                                    <?php if (!empty($member['occupation'])): ?>
                                        <span class="ms-3"><i class="fas fa-briefcase me-2"></i> <?php echo $member['occupation']; ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-envelope me-2"></i> <?php echo $member['email']; ?>
                                    <span class="ms-3"><i class="fas fa-phone me-2"></i> <?php echo $member['phone']; ?></span>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i> Joined: <?php echo date('M d, Y', strtotime($member['join_date'])); ?>
                                    <span class="ms-3">
                                        <i class="fas fa-clock me-2"></i> Expires: <?php echo date('M d, Y', strtotime($member['expiry_date'])); ?>
                                        <?php if ($is_expired): ?>
                                            <span class="badge bg-danger ms-2">Expired</span>
                                        <?php elseif ($days_until_expiry <= 30): ?>
                                            <span class="badge bg-warning ms-2"><?php echo $days_until_expiry; ?> days left</span>
                                        <?php else: ?>
                                            <span class="badge bg-success ms-2">Active</span>
                                        <?php endif; ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="btn-group">
                                    <a href="<?php echo BASE_URL; ?>/admin/edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-2"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($is_expired): ?>
                                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>"><i class="fas fa-sync-alt me-2"></i> Renew Membership</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/add_contribution.php?member_id=<?php echo $member['member_id']; ?>"><i class="fas fa-money-bill-wave me-2"></i> Add Contribution</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="window.print();"><i class="fas fa-print me-2"></i> Print Profile</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger btn-delete" href="<?php echo BASE_URL; ?>/admin/delete_member.php?id=<?php echo $member['member_id']; ?>"><i class="fas fa-trash me-2"></i> Delete Member</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Member Details -->
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i> Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <tbody>
                                        <tr>
                                            <th width="35%">Full Name</th>
                                            <td><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Gender</th>
                                            <td><?php echo $member['gender']; ?></td>
                                        </tr>
                                        <?php if (!empty($member['date_of_birth'])): ?>
                                            <tr>
                                                <th>Date of Birth</th>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($member['date_of_birth'])); ?>
                                                    <?php if ($age): ?>
                                                        <span class="text-muted">(<?php echo $age; ?> years)</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($member['occupation'])): ?>
                                            <tr>
                                                <th>Occupation</th>
                                                <td><?php echo $member['occupation']; ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <?php if ($member['status'] == 'Active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($member['status'] == 'Inactive'): ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><i class="fas fa-address-card me-2"></i> Contact Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <tbody>
                                        <tr>
                                            <th width="35%">Email</th>
                                            <td><?php echo $member['email']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phone</th>
                                            <td><?php echo $member['phone']; ?></td>
                                        </tr>
                                        <?php if (!empty($member['address'])): ?>
                                            <tr>
                                                <th>Address</th>
                                                <td><?php echo $member['address']; ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($member['city']) || !empty($member['state']) || !empty($member['postal_code'])): ?>
                                            <tr>
                                                <th>City/State/Postal</th>
                                                <td>
                                                    <?php 
                                                    $location = [];
                                                    if (!empty($member['city'])) $location[] = $member['city'];
                                                    if (!empty($member['state'])) $location[] = $member['state'];
                                                    if (!empty($member['postal_code'])) $location[] = $member['postal_code'];
                                                    echo implode(', ', $location);
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($member['country'])): ?>
                                            <tr>
                                                <th>Country</th>
                                                <td><?php echo $member['country']; ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Membership Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><i class="fas fa-id-card me-2"></i> Membership Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <tbody>
                                        <tr>
                                            <th width="35%">Membership Type</th>
                                            <td><?php echo $member['membership_type']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Join Date</th>
                                            <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Expiry Date</th>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($member['expiry_date'])); ?>
                                                <?php if ($is_expired): ?>
                                                    <span class="badge bg-danger ms-2">Expired</span>
                                                <?php elseif ($days_until_expiry <= 30): ?>
                                                    <span class="badge bg-warning ms-2"><?php echo $days_until_expiry; ?> days left</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success ms-2">Active</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Membership Duration</th>
                                            <td>
                                                <?php 
                                                $join_date = new DateTime($member['join_date']);
                                                $interval = $now->diff($join_date);
                                                $years = $interval->y;
                                                $months = $interval->m;
                                                
                                                if ($years > 0) {
                                                    echo $years . ' year' . ($years > 1 ? 's' : '');
                                                    if ($months > 0) {
                                                        echo ', ' . $months . ' month' . ($months > 1 ? 's' : '');
                                                    }
                                                } else {
                                                    echo $months . ' month' . ($months > 1 ? 's' : '');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i> Additional Information</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($member['notes'])): ?>
                                    <h6>Notes:</h6>
                                    <p><?php echo nl2br($member['notes']); ?></p>
                                <?php else: ?>
                                    <p class="text-muted">No additional information available.</p>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Created:</h6>
                                        <p><?php echo date('M d, Y H:i', strtotime($member['created_at'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Last Updated:</h6>
                                        <p><?php echo date('M d, Y H:i', strtotime($member['updated_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs for Contributions, Loans, etc. -->
                <div class="card mb-4">
                    <div class="card-header bg-light p-0">
                        <ul class="nav nav-tabs" id="memberTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="contributions-tab" data-bs-toggle="tab" data-bs-target="#contributions" type="button" role="tab" aria-controls="contributions" aria-selected="true">
                                    <i class="fas fa-money-bill-wave me-2"></i> Contributions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="loans-tab" data-bs-toggle="tab" data-bs-target="#loans" type="button" role="tab" aria-controls="loans" aria-selected="false">
                                    <i class="fas fa-hand-holding-usd me-2"></i> Loans
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="investments-tab" data-bs-toggle="tab" data-bs-target="#investments" type="button" role="tab" aria-controls="investments" aria-selected="false">
                                    <i class="fas fa-chart-line me-2"></i> Investments
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                                    <i class="fas fa-history me-2"></i> Activity Log
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="memberTabsContent">
                            <!-- Contributions Tab -->
                            <div class="tab-pane fade show active" id="contributions" role="tabpanel" aria-labelledby="contributions-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Contribution History</h5>
                                    <a href="<?php echo BASE_URL; ?>/admin/add_contribution.php?member_id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Add Contribution
                                    </a>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Type</th>
                                                <th>Receipt #</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- This would be populated from the database -->
                                            <tr>
                                                <td colspan="6" class="text-center">No contribution records found</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Loans Tab -->
                            <div class="tab-pane fade" id="loans" role="tabpanel" aria-labelledby="loans-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Loan History</h5>
                                    <a href="<?php echo BASE_URL; ?>/admin/add_loan.php?member_id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Add Loan
                                    </a>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                                <th>Due Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- This would be populated from the database -->
                                            <tr>
                                                <td colspan="6" class="text-center">No loan records found</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Investments Tab -->
                            <div class="tab-pane fade" id="investments" role="tabpanel" aria-labelledby="investments-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Investment History</h5>
                                    <a href="<?php echo BASE_URL; ?>/admin/add_investment.php?member_id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Add Investment
                                    </a>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Return</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- This would be populated from the database -->
                                            <tr>
                                                <td colspan="6" class="text-center">No investment records found</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Activity Log Tab -->
                            <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                                <h5 class="mb-3">Activity Log</h5>
                                
                                <div class="timeline">
                                    <!-- This would be populated from the database -->
                                    <div class="timeline-item">
                                        <div class="timeline-date"><?php echo date('M d, Y', strtotime($member['created_at'])); ?></div>
                                        <div class="timeline-content">
                                            <h6>Member Created</h6>
                                            <p>Member account was created in the system.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Include Footer -->
                <?php include '../includes/footer.php'; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <style>
        /* Timeline styling */
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            left: 20px;
            height: 100%;
            width: 2px;
            background-color: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 50px;
            margin-bottom: 20px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #007bff;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #007bff;
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
        }
        
        .timeline-content h6 {
            margin-top: 0;
        }
        
        .timeline-content p {
            margin-bottom: 0;
        }
        
        /* Print styles */
        @media print {
            .nav-tabs, .tab-content {
                display: none !important;
            }
        }
    </style>
</body>
</html>
