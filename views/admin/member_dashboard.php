<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize member controller
$memberController = new MemberController();

// Get member statistics
$stats = $memberController->getMemberStatistics();

// Get expiring memberships
$expiring_memberships = $memberController->getExpiringMemberships(30);

// Get recent members
$recent_members = $memberController->getAllMembers(1, '', '', '');

// Get membership types for chart
$membership_types = $memberController->getMembershipTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - <?php echo APP_NAME; ?></title>
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
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Member Dashboard</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-users me-2"></i>Member Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="<?php echo BASE_URL; ?>/admin/add_member.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i> Add Member
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/members.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-list me-1"></i> View All
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/member_import.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-upload me-1"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Members</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_members']); ?></div>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Members</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['active_members']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Expiring Soon</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($expiring_memberships); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">New This Month</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['new_members_this_month']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Membership Types Chart -->
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Members by Membership Type</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="membershipTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gender Distribution Chart -->
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Gender Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="genderChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tables Row -->
                <div class="row">
                    <!-- Expiring Memberships -->
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Expiring Memberships (Next 30 Days)</h6>
                                <a href="<?php echo BASE_URL; ?>/admin/expiring_memberships.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($expiring_memberships) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Member</th>
                                                    <th>Type</th>
                                                    <th>Expires</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $count = 0;
                                                foreach ($expiring_memberships as $member): 
                                                    if ($count >= 5) break;
                                                    $count++;
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($member['membership_type']); ?></td>
                                                        <td>
                                                            <span class="badge bg-warning">
                                                                <?php echo date('M d, Y', strtotime($member['expiry_date'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="<?php echo BASE_URL; ?>/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <p class="text-muted">No memberships expiring in the next 30 days</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Members -->
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Members</h6>
                                <a href="<?php echo BASE_URL; ?>/admin/members.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Join Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $count = 0;
                                            foreach ($recent_members['members'] as $member): 
                                                if ($count >= 5) break;
                                                $count++;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($member['membership_type']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                                    <td>
                                                        <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <script>
        // Membership Type Chart
        const membershipTypeData = {
            labels: [<?php foreach ($stats['members_by_type'] as $type => $count): ?>'<?php echo addslashes($type); ?>',<?php endforeach; ?>],
            datasets: [{
                data: [<?php foreach ($stats['members_by_type'] as $type => $count): ?><?php echo $count; ?>,<?php endforeach; ?>],
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#f4b619', '#e02d1b', '#5a5c69'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        };
        
        const membershipTypeCtx = document.getElementById('membershipTypeChart').getContext('2d');
        new Chart(membershipTypeCtx, {
            type: 'doughnut',
            data: membershipTypeData,
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Gender Chart
        const genderData = {
            labels: [<?php foreach ($stats['members_by_gender'] as $gender => $count): ?>'<?php echo addslashes($gender); ?>',<?php endforeach; ?>],
            datasets: [{
                data: [<?php foreach ($stats['members_by_gender'] as $gender => $count): ?><?php echo $count; ?>,<?php endforeach; ?>],
                backgroundColor: ['#4e73df', '#e74a3b', '#f6c23e'],
                hoverBackgroundColor: ['#2e59d9', '#e02d1b', '#f4b619'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        };
        
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'doughnut',
            data: genderData,
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
    
    <style>
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        .text-xs {
            font-size: 0.7rem;
        }
        .chart-pie {
            position: relative;
            height: 15rem;
        }
    </style>
</body>
</html>
