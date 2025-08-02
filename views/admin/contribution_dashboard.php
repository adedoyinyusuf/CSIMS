<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/contribution_controller.php';
require_once '../../controllers/member_controller.php';

$contributionController = new ContributionController();
$memberController = new MemberController();

// Get contribution statistics
$stats = $contributionController->getContributionStatistics();

// Get recent contributions
$recentContributions = $contributionController->getAllContributions(1, 5)['contributions'];

// Get top contributors this month
$topContributors = $contributionController->getAllContributions(1, 5, '', 'amount', 'DESC', '', date('Y-m-01'), date('Y-m-t'))['contributions'];

// Calculate monthly growth
$currentMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));
$currentMonthTotal = 0;
$lastMonthTotal = 0;

foreach ($stats['monthly_contributions'] as $monthData) {
    if ($monthData['month'] === $currentMonth) {
        $currentMonthTotal = $monthData['monthly_amount'];
    }
    if ($monthData['month'] === $lastMonth) {
        $lastMonthTotal = $monthData['monthly_amount'];
    }
}

$growthPercentage = $lastMonthTotal > 0 ? (($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contribution Dashboard - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
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
                        <li class="breadcrumb-item active">Contribution Dashboard</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-line me-2"></i>Contribution Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="add_contribution.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Add Contribution
                            </a>
                            <a href="contributions.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-1"></i>View All
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
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Contributions
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            $<?= number_format($stats['total_amount'], 2) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                            This Month
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            $<?= number_format($stats['month_amount'], 2) ?>
                                        </div>
                                        <div class="text-xs">
                                            <span class="<?= $growthPercentage >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <i class="fas fa-arrow-<?= $growthPercentage >= 0 ? 'up' : 'down' ?>"></i>
                                                <?= abs(number_format($growthPercentage, 1)) ?>%
                                            </span>
                                            vs last month
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                                            Average Contribution
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            $<?= number_format(count($stats['recent_contributions']) > 0 ? $stats['total_amount'] / count($stats['recent_contributions']) : 0, 2) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
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
                                            Total Transactions
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= array_sum(array_column($stats['contributions_by_type'], 'count')) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Monthly Contributions Chart -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Monthly Contributions Trend</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contribution Types Chart -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Contributions by Type</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="typeChart" width="400" height="400"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tables Row -->
                <div class="row">
                    <!-- Recent Contributions -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Contributions</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                                <th>Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentContributions)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No recent contributions</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recentContributions as $contribution): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="view_member.php?id=<?= $contribution['member_id'] ?>">
                                                                <?= htmlspecialchars($contribution['first_name'] . ' ' . $contribution['last_name']) ?>
                                                            </a>
                                                        </td>
                                                        <td>$<?= number_format($contribution['amount'], 2) ?></td>
                                                        <td><?= date('M d, Y', strtotime($contribution['contribution_date'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-primary">
                                                                <?= htmlspecialchars($contribution['contribution_type']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center">
                                    <a href="contributions.php" class="btn btn-primary btn-sm">
                                        View All Contributions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Contributors This Month -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top Contributors This Month</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Member</th>
                                                <th>Amount</th>
                                                <th>Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($topContributors)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No contributions this month</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($topContributors as $index => $contributor): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-<?= $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'info') ?>">
                                                                #<?= $index + 1 ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="view_member.php?id=<?= $contributor['member_id'] ?>">
                                                                <?= htmlspecialchars($contributor['first_name'] . ' ' . $contributor['last_name']) ?>
                                                            </a>
                                                        </td>
                                                        <td>$<?= number_format($contributor['amount'], 2) ?></td>
                                                        <td>
                                                            <span class="badge bg-success">
                                                                <?= htmlspecialchars($contributor['contribution_type']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center">
                                    <a href="contributions.php?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-t') ?>" class="btn btn-success btn-sm">
                                        View All This Month
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3 mb-3">
                                        <a href="add_contribution.php" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                                            Add Contribution
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="contributions.php" class="btn btn-info btn-lg w-100">
                                            <i class="fas fa-list fa-2x d-block mb-2"></i>
                                            View All
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="#" onclick="exportContributions()" class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-download fa-2x d-block mb-2"></i>
                                            Export Data
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="#" onclick="generateReport()" class="btn btn-warning btn-lg w-100">
                                            <i class="fas fa-chart-pie fa-2x d-block mb-2"></i>
                                            Generate Report
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Contributions Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($stats['monthly_contributions'], 'month')) ?>,
                datasets: [{
                    label: 'Monthly Contributions',
                    data: <?= json_encode(array_column($stats['monthly_contributions'], 'monthly_amount')) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Contributions Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Contribution Types Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($stats['contributions_by_type'], 'contribution_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($stats['contributions_by_type'], 'type_amount')) ?>,
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': $' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Quick action functions
        function exportContributions() {
            window.location.href = 'contributions.php?export=csv';
        }
        
        function generateReport() {
            window.open('reports.php?type=contributions', '_blank');
        }
    </script>
</body>
</html>
