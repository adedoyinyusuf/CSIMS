<?php
require_once '../../config/config.php';
require_once '../../config/security.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../src/autoload.php';
require_once '../../includes/utilities.php';
require_once '../../includes/session.php';
$session = Session::getInstance();

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
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

// Initialize controllers
$memberController = new MemberController();
$loanController = new LoanController();

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

// Calculate days until membership expiry (guard against null/invalid dates)
$now = new DateTime();
$days_until_expiry = null;
$is_expired = false;
if (!empty($member['expiry_date']) && strtotime($member['expiry_date']) !== false) {
    $expiry_date = new DateTime($member['expiry_date']);
    $days_until_expiry = $now->diff($expiry_date)->days;
    $is_expired = $now > $expiry_date;
}

// Initialize related data for savings and loans
$database = Database::getInstance();
$conn = $database->getConnection();
// Initialize schema-aware savings columns for display fallbacks
$schema = Utilities::getSavingsSchema($conn);
$statusCol = $schema['transaction_status'] ?? 'transaction_status';
$typeCol = $schema['transaction_type'] ?? 'transaction_type';
$dateCol = $schema['transaction_date'] ?? 'transaction_date';

$accountRepo = new \CSIMS\Repositories\SavingsAccountRepository($conn);
$savingsRepo = new \CSIMS\Repositories\SavingsTransactionRepository($conn);

// Member total savings balance across accounts
$member_total_savings = $accountRepo->getTotalBalanceByMember($member_id);

$savings_transactions = array_map(fn($t) => $t->toArray(), $savingsRepo->findByMemberId($member_id));
// Sort by date desc and limit 50
usort($savings_transactions, function($a, $b) { return strtotime($b['transaction_date'] ?? '1970-01-01') - strtotime($a['transaction_date'] ?? '1970-01-01'); });
$savings_transactions = array_slice($savings_transactions, 0, 50);

// Fallback: if no member-scoped transactions, aggregate account histories
if (empty($savings_transactions)) {
    $accounts = $accountRepo->findByMemberId($member_id);
    $aggregated = [];
    foreach ($accounts as $account) {
        $history = $savingsRepo->getAccountHistory((int)$account->getAccountId(), 50, 0);
        foreach ($history as $tx) {
            $aggregated[] = $tx->toArray();
        }
    }
    if (!empty($aggregated)) {
        usort($aggregated, function($a, $b) { return strtotime($b['transaction_date'] ?? '1970-01-01') - strtotime($a['transaction_date'] ?? '1970-01-01'); });
        $savings_transactions = array_slice($aggregated, 0, 50);
    }
}

// Fetch member loans using active LoanController methods
// Note: getLoansByMemberId is a shim to getMemberLoans; both are supported
$member_loans = [];
try {
    if (method_exists($loanController, 'getLoansByMemberId')) {
        $member_loans = $loanController->getLoansByMemberId($member_id) ?? [];
    } elseif (method_exists($loanController, 'getMemberLoans')) {
        $member_loans = $loanController->getMemberLoans($member_id) ?? [];
    }
    // Limit to 50 for display consistency if large
    if (is_array($member_loans)) {
        $member_loans = array_slice($member_loans, 0, 50);
    } else {
        $member_loans = [];
    }
} catch (Throwable $e) {
    error_log('Error fetching member loans: ' . $e->getMessage());
    $member_loans = [];
}

// Compute member outstanding loans across schema variants
$member_loan_outstanding = 0.0;
try {
    $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
    $has_amount_paid = $col && $col->num_rows > 0;
    $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'remaining_balance'");
    $has_remaining_balance = $col && $col->num_rows > 0;
    $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
    $has_total_repaid = $col && $col->num_rows > 0;

    if ($has_amount_paid) {
        $q = $conn->query("SELECT SUM(amount - amount_paid) AS total FROM loans WHERE member_id = {$member_id} AND LOWER(status) IN ('active','disbursed','approved')");
    } elseif ($has_remaining_balance) {
        $q = $conn->query("SELECT SUM(remaining_balance) AS total FROM loans WHERE member_id = {$member_id} AND LOWER(status) IN ('active','disbursed','approved')");
    } elseif ($has_total_repaid) {
        $q = $conn->query("SELECT SUM(amount - total_repaid) AS total FROM loans WHERE member_id = {$member_id} AND LOWER(status) IN ('active','disbursed','approved')");
    } else {
        $q = $conn->query("SELECT SUM(amount) AS total FROM loans WHERE member_id = {$member_id} AND LOWER(status) IN ('active','disbursed','approved')");
    }
    if ($q) { $member_loan_outstanding = (float)($q->fetch_assoc()['total'] ?? 0); }
} catch (Exception $e) { /* ignore and keep default */ }

// Compute member total paid (schema-aware)
$member_loan_paid_total = 0.0;
try {
    // Prefer loan_repayments if available
    $has_repayments = false;
    $col = $conn->query("SHOW TABLES LIKE 'loan_repayments'");
    if ($col && $col->num_rows > 0) { $has_repayments = true; }

    if ($has_repayments) {
        $q = $conn->query("SELECT SUM(lp.amount) AS total FROM loan_repayments lp JOIN loans l ON lp.loan_id = l.loan_id WHERE l.member_id = {$member_id}");
    } else {
        $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
        $has_amount_paid = $col && $col->num_rows > 0;
        $col = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
        $has_total_repaid = $col && $col->num_rows > 0;
        if ($has_amount_paid) {
            $q = $conn->query("SELECT SUM(amount_paid) AS total FROM loans WHERE member_id = {$member_id}");
        } elseif ($has_total_repaid) {
            $q = $conn->query("SELECT SUM(total_repaid) AS total FROM loans WHERE member_id = {$member_id}");
        } else {
            $q = false;
        }
    }
    if ($q) { $member_loan_paid_total = (float)($q->fetch_assoc()['total'] ?? 0); }
} catch (Exception $e) { /* ignore */ }

// Compute member repayments this month (if loan_repayments exists)
$member_loan_repayments_this_month = 0.0;
$member_loan_repayments_count_this_month = 0;
try {
    $col = $conn->query("SHOW TABLES LIKE 'loan_repayments'");
    if ($col && $col->num_rows > 0) {
        $q = $conn->query("SELECT SUM(lp.amount) AS total FROM loan_repayments lp JOIN loans l ON lp.loan_id = l.loan_id WHERE l.member_id = {$member_id} AND YEAR(lp.payment_date) = YEAR(CURDATE()) AND MONTH(lp.payment_date) = MONTH(CURDATE())");
        if ($q) { $member_loan_repayments_this_month = (float)($q->fetch_assoc()['total'] ?? 0); }
        $q2 = $conn->query("SELECT COUNT(*) AS cnt FROM loan_repayments lp JOIN loans l ON lp.loan_id = l.loan_id WHERE l.member_id = {$member_id} AND YEAR(lp.payment_date) = YEAR(CURDATE()) AND MONTH(lp.payment_date) = MONTH(CURDATE())");
        if ($q2) { $member_loan_repayments_count_this_month = (int)($q2->fetch_assoc()['cnt'] ?? 0); }
    }
} catch (Exception $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Member - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            object-fit: cover;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.2s ease-in-out;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            border-bottom: 1px solid #e9ecef;
        }
        .table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .stats-card h4 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }
        .timeline-item {
            border-left: 3px solid #667eea;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .timeline-date {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background-color: #667eea;
            color: white;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
    
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
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/admin/members.php">Members</a></li>
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
                                    <i class="fas fa-calendar-alt me-2"></i> Joined: <?php echo !empty($member['join_date']) && strtotime($member['join_date']) !== false ? date('M d, Y', strtotime($member['join_date'])) : 'N/A'; ?>
                                    <span class="ms-3">
                                        <i class="fas fa-clock me-2"></i> Expires: <?php echo !empty($member['expiry_date']) && strtotime($member['expiry_date']) !== false ? date('M d, Y', strtotime($member['expiry_date'])) : 'N/A'; ?>
                                        <?php if ($is_expired): ?>
                                            <span class="badge bg-danger ms-2">Expired</span>
                                        <?php elseif ($days_until_expiry !== null && $days_until_expiry <= 30): ?>
                                            <span class="badge bg-warning ms-2"><?php echo $days_until_expiry; ?> days left</span>
                                        <?php elseif ($days_until_expiry !== null): ?>
                                            <span class="badge bg-success ms-2">Active</span>
                                        <?php endif; ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="btn-group">
                                    <a href="<?php echo BASE_URL; ?>/views/admin/edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-2"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($is_expired): ?>
                                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/views/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>"><i class="fas fa-sync-alt me-2"></i> Renew Membership</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/views/admin/savings.php?member_id=<?php echo $member['member_id']; ?>"><i class="fas fa-money-bill-wave me-2"></i> Manage Savings</a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"><i class="fas fa-key me-2"></i> Reset Password</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="window.print();"><i class="fas fa-print me-2"></i> Print Profile</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger btn-delete" href="<?php echo BASE_URL; ?>/views/admin/delete_member.php?id=<?php echo $member['member_id']; ?>"><i class="fas fa-trash me-2"></i> Delete Member</a></li>
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
                                            <th>Member Type</th>
                                            <td><?php echo isset($member['member_type_label']) && !empty($member['member_type_label']) ? ucfirst($member['member_type_label']) : (isset($member['member_type']) ? ucfirst($member['member_type']) : 'Member'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Join Date</th>
                                            <td><?php echo !empty($member['join_date']) && strtotime($member['join_date']) !== false ? date('M d, Y', strtotime($member['join_date'])) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Expiry Date</th>
                                            <td>
                                                <?php echo !empty($member['expiry_date']) && strtotime($member['expiry_date']) !== false ? date('M d, Y', strtotime($member['expiry_date'])) : 'N/A'; ?>
                                                <?php if ($is_expired): ?>
                                                    <span class="badge bg-danger ms-2">Expired</span>
                                                <?php elseif (!is_null($days_until_expiry) && $days_until_expiry <= 30): ?>
                                                    <span class="badge bg-warning ms-2"><?php echo (int)$days_until_expiry; ?> days left</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success ms-2">Active</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Total Savings Balance</th>
                                            <td>₦<?php echo number_format($member_total_savings ?? 0, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Membership Duration</th>
                                            <td>
                                                <?php 
                                                if (!empty($member['join_date']) && strtotime($member['join_date']) !== false) {
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
                                                } else {
                                                    echo 'N/A';
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
                                        <p><?php echo !empty($member['created_at']) && strtotime($member['created_at']) !== false ? date('M d, Y H:i', strtotime($member['created_at'])) : 'N/A'; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Last Updated:</h6>
                                        <p><?php echo !empty($member['updated_at']) && strtotime($member['updated_at']) !== false ? date('M d, Y H:i', strtotime($member['updated_at'])) : 'N/A'; ?></p>
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
                                <button class="nav-link" id="savings-tab" data-bs-toggle="tab" data-bs-target="#savings" type="button" role="tab" aria-controls="savings" aria-selected="false">
                                <i class="fas fa-piggy-bank me-2"></i> Savings
                            </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="loans-tab" data-bs-toggle="tab" data-bs-target="#loans" type="button" role="tab" aria-controls="loans" aria-selected="true">
                                    <i class="fas fa-hand-holding-usd me-2"></i> Loans
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
                            <!-- Savings Tab -->
                            <div class="tab-pane fade" id="savings" role="tabpanel" aria-labelledby="savings-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Savings History</h5>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge bg-success">Total Savings: ₦<?php echo number_format($member_total_savings ?? 0, 2); ?></span>
                                        <a href="<?php echo BASE_URL; ?>/views/admin/savings.php" class="btn btn-sm btn-primary">
                                            <i class="fas fa-plus"></i> Manage Savings
                                        </a>
                                    </div>
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
<?php if (empty($savings_transactions)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No savings records found</td>
                                            </tr>
<?php else: ?>
<?php foreach ($savings_transactions as $tx): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($tx['transaction_date'] ?? ($tx[$dateCol] ?? '1970-01-01'))); ?></td>
                                                <td>₦<?php echo number_format($tx['amount'] ?? 0, 2); ?></td>
                                                <td><?php echo htmlspecialchars($tx['transaction_type'] ?? ($tx[$typeCol] ?? '')); ?></td>
                                                <td><?php echo !empty($tx['reference_number']) ? htmlspecialchars($tx['reference_number']) : (!empty($tx['receipt_number']) ? htmlspecialchars($tx['receipt_number']) : '-'); ?></td>
                                                <td><?php echo !empty($tx['notes']) ? htmlspecialchars($tx['notes']) : '-'; ?></td>
                                                <td>-</td>
                                            </tr>
<?php endforeach; ?>
<?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Loans Tab -->
                            <div class="tab-pane fade show active" id="loans" role="tabpanel" aria-labelledby="loans-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Loan History</h5>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge bg-warning text-dark">Outstanding: ₦<?php echo number_format($member_loan_outstanding ?? 0, 2); ?></span>
                                        <span class="badge bg-success">Paid Total: ₦<?php echo number_format($member_loan_paid_total ?? 0, 2); ?></span>
                                        <span class="badge bg-primary">Repayments This Month: ₦<?php echo number_format($member_loan_repayments_this_month ?? 0, 2); ?></span>
                                        <span class="badge bg-info text-dark">Repayments Count: <?php echo number_format($member_loan_repayments_count_this_month ?? 0); ?></span>
                                        <a href="<?php echo BASE_URL; ?>/views/admin/add_loan.php?member_id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-plus"></i> Add Loan
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <?php
                                    // Detect schema for per-loan paid/remaining calculations once
                                    $has_repayments = false;
                                    $has_amount_paid = false;
                                    $has_total_repaid = false;
                                    try {
                                        $chk = $conn->query("SHOW TABLES LIKE 'loan_repayments'");
                                        if ($chk && $chk->num_rows > 0) { $has_repayments = true; }
                                        $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'amount_paid'");
                                        if ($chk && $chk->num_rows > 0) { $has_amount_paid = true; }
                                        $chk = $conn->query("SHOW COLUMNS FROM loans LIKE 'total_repaid'");
                                        if ($chk && $chk->num_rows > 0) { $has_total_repaid = true; }
                                    } catch (Exception $e) { /* ignore */ }
                                    
                                    // Prefetch repayment sums for this member's loans to avoid per-row queries
                                    $repayment_sums_by_loan = [];
                                    if ($has_repayments && !empty($member_loans)) {
                                        $loanIds = array_map(function($l){ return (int)$l['loan_id']; }, $member_loans);
                                        $loanIds = array_filter($loanIds, function($id){ return $id > 0; });
                                        if (!empty($loanIds)) {
                                            $in = implode(',', $loanIds);
                                            try {
                                                $rs = $conn->query("SELECT loan_id, SUM(amount) AS total FROM loan_repayments WHERE loan_id IN ($in) GROUP BY loan_id");
                                                if ($rs) {
                                                    while ($row = $rs->fetch_assoc()) {
                                                        $repayment_sums_by_loan[(int)$row['loan_id']] = (float)($row['total'] ?? 0);
                                                    }
                                                }
                                            } catch (Exception $e) { /* ignore */ }
                                        }
                                    }
                                    ?>
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Paid</th>
                                                <th>Remaining</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                                <th>Due Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- This would be populated from the database -->
<?php if (empty($member_loans)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No loan records found</td>
                                            </tr>
<?php else: ?>
<?php foreach ($member_loans as $loan): ?>
                                            <?php
                                                $loanAmount = (float)($loan['amount'] ?? 0);
                                                $loanPaid = 0.0;
                                                $loanId = (int)($loan['loan_id'] ?? 0);
                                                if ($has_repayments && $loanId > 0 && isset($repayment_sums_by_loan[$loanId])) {
                                                    $loanPaid = (float)$repayment_sums_by_loan[$loanId];
                                                } elseif ($has_amount_paid && isset($loan['amount_paid'])) {
                                                    $loanPaid = (float)$loan['amount_paid'];
                                                } elseif ($has_total_repaid && isset($loan['total_repaid'])) {
                                                    $loanPaid = (float)$loan['total_repaid'];
                                                }
                                                $loanRemaining = max(0.0, $loanAmount - $loanPaid);
                                            ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                                <td>₦<?php echo number_format($loanAmount, 2); ?></td>
                                                <td>₦<?php echo number_format($loanPaid, 2); ?></td>
                                                <td>₦<?php echo number_format($loanRemaining, 2); ?></td>
                                                <td><?php echo htmlspecialchars($loan['purpose'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($loan['status'] ?? ''); ?></td>
                                                <td><?php echo !empty($loan['due_date']) ? date('M d, Y', strtotime($loan['due_date'])) : '-'; ?></td>
                                                <td><a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo $loan['loan_id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                            </tr>
<?php endforeach; ?>
<?php endif; ?>
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
                                    <div class="timeline-date"><?php echo !empty($member['created_at']) && strtotime($member['created_at']) !== false ? date('M d, Y', strtotime($member['created_at'])) : 'N/A'; ?></div>
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
                <?php include '../../views/includes/footer.php'; ?>
            </main>
        </div>
    </div>
    
    <!-- Password Reset Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset Member Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo BASE_URL; ?>/views/admin/reset_member_password.php" method="POST" id="resetPasswordForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::generateToken(); ?>">
                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You are about to reset the password for <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>.
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="generate_password" name="generate_password" value="1" checked>
                                <label class="form-check-label" for="generate_password">
                                    <strong>Auto-generate secure password</strong> (Recommended)
                                </label>
                            </div>
                            <div class="form-text">When enabled, a secure random password will be generated and displayed to you.</div>
                        </div>
                        
                        <div id="manual_password_section" style="display: none;">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                                <div class="form-text">Password must be at least 8 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> The new password will be displayed on the next page. Please copy it securely and share it with the member through a secure channel.
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="send_email" name="send_email" value="1" checked>
                            <label class="form-check-label" for="send_email">
                                <i class="fas fa-envelope me-1"></i> Send new password to member via email
                            </label>
                            <div class="form-text">Member's email: <strong><?php echo htmlspecialchars($member['email'] ?? 'No email on file'); ?></strong></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="resetPasswordBtn">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <script>
        // Toggle between auto-generate and manual password
        document.getElementById('generate_password').addEventListener('change', function() {
            const manualSection = document.getElementById('manual_password_section');
            const newPasswordField = document.getElementById('new_password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            if (this.checked) {
                manualSection.style.display = 'none';
                newPasswordField.required = false;
                confirmPasswordField.required = false;
            } else {
                manualSection.style.display = 'block';
                newPasswordField.required = true;
                confirmPasswordField.required = true;
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form submission validation and loading state management
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            console.log('Form submission started');
            console.log('Form element:', this);
            console.log('Form action:', this.action);
            console.log('Form method:', this.method);
            
            const generatePassword = document.getElementById('generate_password').checked;
            console.log('Generate password mode:', generatePassword);
            
            const submitButton = document.getElementById('resetPasswordBtn');
            console.log('Submit button found:', submitButton);
            
            if (!submitButton) {
                console.error('Submit button not found!');
                return;
            }
            
            const originalText = submitButton.innerHTML;
            
            // Validate manual password if not auto-generating
            if (!generatePassword) {
                const password = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (!password || !confirmPassword) {
                    e.preventDefault();
                    alert('Please enter and confirm the password.');
                    return;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return;
                }
            }
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            
            console.log('Form validation passed, submitting...');
            // Form will submit normally after this
        });
    </script>
    
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
            background-color: var(--true-blue);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px var(--true-blue);
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
