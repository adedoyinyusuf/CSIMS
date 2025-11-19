<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/member_controller.php';


$memberController = new MemberController();


// Get member statistics
$memberStats = $memberController->getMemberStatistics();

// Include SavingsController for savings statistics without auto handleRequest
if (!defined('SAVINGS_CONTROLLER_INCLUDED')) {
    define('SAVINGS_CONTROLLER_INCLUDED', true);
}
require_once '../../controllers/SavingsController.php';
$savingsController = new SavingsController();
$savingsStats = $savingsController->getSavingsStatistics();

// Handle report generation
if (isset($_POST['generate_report'])) {
    $reportType = $_POST['report_type'];
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    $format = $_POST['format'] ?? 'html';
    
    // Redirect to report generation with parameters
    $params = http_build_query([
        'type' => $reportType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'format' => $format
    ]);
    
    if ($format === 'pdf') {
        header("Location: generate_report.php?$params");
        exit;
    }
}

// Get membership types for filtering
$membershipTypes = $memberController->getMembershipTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Reports - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Member Reports</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>Member Reports & Analytics</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportModal">
                                <i class="fas fa-file-alt me-1"></i>Generate Report
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="printReport()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Members
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $memberStats['total_members'] ?>
                                        </div>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Members
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $memberStats['active_members'] ?>
                                        </div>
                                        <div class="text-xs">
                                            <?= number_format(($memberStats['active_members'] / max($memberStats['total_members'], 1)) * 100, 1) ?>% of total
                                        </div>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Expiring Soon
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= count($memberController->getExpiringMemberships(30)) ?>
                                        </div>
                                        <div class="text-xs">
                                            Next 30 days
                                        </div>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            New This Month
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $memberStats['new_members_this_month'] ?>
                                        </div>
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
                    <!-- Membership Types Distribution -->
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Membership Types Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="membershipChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gender Distribution -->
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Gender Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="genderChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Reports Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Membership Status Analysis</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><span class="badge bg-success">Active</span></td>
                                                <td><?= $memberStats['active_members'] ?></td>
                                                <td><?= number_format(($memberStats['active_members'] / max($memberStats['total_members'], 1)) * 100, 1) ?>%</td>
                                                <td>
                                                    <a href="member_search.php?status=active" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-secondary">Inactive</span></td>
                                                <td><?= $memberStats['inactive_members'] ?></td>
                                                <td><?= number_format(($memberStats['inactive_members'] / max($memberStats['total_members'], 1)) * 100, 1) ?>%</td>
                                                <td>
                                                    <a href="member_search.php?status=inactive" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-danger">Expired</span></td>
                                                <td><?= $memberStats['expired_members'] ?></td>
                                                <td><?= number_format(($memberStats['expired_members'] / max($memberStats['total_members'], 1)) * 100, 1) ?>%</td>
                                                <td>
                                                    <a href="member_search.php?status=expired" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Savings Summary -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Savings Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-primary">$<?= number_format($savingsStats['total_balance'], 2) ?></h4>
                                        <p class="text-muted">Total Savings Balance</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-success">$<?= number_format($savingsStats['total_interest'], 2) ?></h4>
                                        <p class="text-muted">Deposits This Month</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-info"><?= (int)$savingsStats['active_members'] ?></h4>
                                        <p class="text-muted">Active Members</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h4 class="text-warning"><?= (int)$savingsStats['total_accounts'] ?></h4>
                                        <p class="text-muted">Total Accounts</p>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="savings.php" class="btn btn-primary">
                                        <i class="fas fa-chart-line me-1"></i>View Detailed Analytics
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Report Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Reports</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3 mb-3">
                                        <button onclick="generateQuickReport('membership_summary')" class="btn btn-outline-primary btn-lg w-100">
                                            <i class="fas fa-users fa-2x d-block mb-2"></i>
                                            Membership Summary
                                        </button>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <button onclick="generateQuickReport('expiring_members')" class="btn btn-outline-warning btn-lg w-100">
                                            <i class="fas fa-clock fa-2x d-block mb-2"></i>
                                            Expiring Members
                                        </button>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <button onclick="generateQuickReport('new_members')" class="btn btn-outline-success btn-lg w-100">
                                            <i class="fas fa-user-plus fa-2x d-block mb-2"></i>
                                            New Members
                                        </button>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="savings.php" class="btn btn-outline-info btn-lg w-100">
                                            <i class="fas fa-dollar-sign fa-2x d-block mb-2"></i>
                                            Savings Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Report Generation Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Custom Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="report_type" class="form-label">Report Type</label>
                                    <select class="form-select" id="report_type" name="report_type" required>
                                        <option value="">Select Report Type</option>
                                        <option value="membership_summary">Membership Summary</option>
                                        <option value="member_list">Complete Member List</option>
                                        <option value="expiring_members">Expiring Members</option>
                                        <option value="new_members">New Members</option>
                                        <option value="inactive_members">Inactive Members</option>
                                        <option value="savings_summary">Savings Summary</option>
                                        <option value="member_savings">Member Savings</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="format" class="form-label">Format</label>
                                    <select class="form-select" id="format" name="format" required>
                                        <option value="html">HTML (View)</option>
                                        <option value="pdf">PDF Download</option>
                                        <option value="csv">CSV Export</option>
                                        <option value="excel">Excel Export</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Filters</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <select class="form-select" name="membership_type">
                                        <option value="">All Membership Types</option>
                                        <?php foreach ($membershipTypes as $type): ?>
                                            <option value="<?= htmlspecialchars($type['type_name']) ?>">
                                                <?= htmlspecialchars($type['type_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="expired">Expired</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_report" class="btn btn-primary">
                            <i class="fas fa-file-alt me-1"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Membership Types Chart
        const membershipCtx = document.getElementById('membershipChart').getContext('2d');
        const membershipChart = new Chart(membershipCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($memberStats['members_by_type'], 'membership_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($memberStats['members_by_type'], 'count')) ?>,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Gender Distribution Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($memberStats['members_by_gender'], 'gender')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($memberStats['members_by_gender'], 'count')) ?>,
                    backgroundColor: [
                        '#36A2EB',
                        '#FF6384',
                        '#FFCE56'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Quick report functions
        function generateQuickReport(type) {
            const params = new URLSearchParams({
                type: type,
                format: 'html'
            });
            window.open('generate_report.php?' + params.toString(), '_blank');
        }
        
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
