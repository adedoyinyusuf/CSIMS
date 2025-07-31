<?php
require_once '../config/config.php';
require_once '../controllers/auth_controller.php';
require_once '../controllers/member_controller.php';

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access the dashboard');
    header("Location: <?php echo BASE_URL; ?>/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Get member statistics
$memberController = new MemberController();
$stats = $memberController->getMemberStatistics();

// Get expiring memberships
$expiring_memberships = $memberController->getExpiringMemberships(30);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Print</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar"></i> This Month
                        </button>
                    </div>
                </div>
                
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
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Members</h6>
                                        <h2 class="card-text"><?php echo $stats['total_members']; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="<?php echo BASE_URL; ?>/admin/members.php" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-arrow-circle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Active Members</h6>
                                        <h2 class="card-text"><?php echo $stats['active_members']; ?></h2>
                                    </div>
                                    <i class="fas fa-user-check fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="<?php echo BASE_URL; ?>/admin/members.php?status=Active" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-arrow-circle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-warning h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Expiring Soon</h6>
                                        <h2 class="card-text"><?php echo count($expiring_memberships); ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="<?php echo BASE_URL; ?>/admin/expiring_memberships.php" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-arrow-circle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-info h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">New This Month</h6>
                                        <h2 class="card-text"><?php echo $stats['new_members_this_month']; ?></h2>
                                    </div>
                                    <i class="fas fa-user-plus fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="<?php echo BASE_URL; ?>/admin/members.php?filter=new" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-arrow-circle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title">Members by Gender</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title">Members by Membership Type</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="membershipTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Members and Expiring Memberships -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Members</h5>
                                <a href="<?php echo BASE_URL; ?>/admin/members.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Membership</th>
                                                <th>Join Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Get recent members (this would be implemented in the MemberController)
                                            $recentMembers = $memberController->getAllMembers(1, '', '', '');
                                            $count = 0;
                                            foreach ($recentMembers['members'] as $member): 
                                                if ($count >= 5) break; // Show only 5 recent members
                                                $count++;
                                            ?>
                                                <tr>
                                                    <td><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></td>
                                                    <td><?php echo $member['membership_type']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                                    <td>
                                                        <a href="<?php echo BASE_URL; ?>/admin/view_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($count == 0): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No members found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Expiring Memberships</h5>
                                <a href="<?php echo BASE_URL; ?>/admin/expiring_memberships.php" class="btn btn-sm btn-warning">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Membership</th>
                                                <th>Expiry Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $count = 0;
                                            foreach ($expiring_memberships as $member): 
                                                if ($count >= 5) break; // Show only 5 expiring memberships
                                                $count++;
                                            ?>
                                                <tr>
                                                    <td><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></td>
                                                    <td><?php echo $member['membership_type']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($member['expiry_date'])); ?></td>
                                                    <td>
                                                        <a href="<?php echo BASE_URL; ?>/admin/renew_membership.php?id=<?php echo $member['member_id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-sync-alt"></i> Renew</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($count == 0): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No expiring memberships found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
    
    <script>
        // Gender Chart
        const genderData = {
            labels: <?php echo json_encode(array_keys($stats['members_by_gender'] ?? [])); ?>,
            datasets: [{
                label: 'Members by Gender',
                data: <?php echo json_encode(array_values($stats['members_by_gender'] ?? [])); ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(255, 206, 86, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
                borderWidth: 1
            }]
        };
        
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(genderCtx, {
            type: 'pie',
            data: genderData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Gender Distribution'
                    }
                }
            }
        });
        
        // Membership Type Chart
        const membershipTypeData = {
            labels: <?php echo json_encode(array_keys($stats['members_by_type'] ?? [])); ?>,
            datasets: [{
                label: 'Members by Type',
                data: <?php echo json_encode(array_values($stats['members_by_type'] ?? [])); ?>,
                backgroundColor: [
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)'
                ],
                borderWidth: 1
            }]
        };
        
        const membershipTypeCtx = document.getElementById('membershipTypeChart').getContext('2d');
        const membershipTypeChart = new Chart(membershipTypeCtx, {
            type: 'bar',
            data: membershipTypeData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Membership Type Distribution'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
