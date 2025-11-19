<?php
/**
 * CSIMS Reports & Analytics Page
 * Updated to use the CSIMS admin template system with Phase 1&2 integrations
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/report_controller.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';
require_once '_admin_template_config.php';

// Ensure unified session initialization and cookie usage
$session = Session::getInstance();

// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize common services
$businessRulesService = new SimpleBusinessRulesService();

// Initialize report controller
$reportController = new ReportController();

// Handle report generation
$report_data = null;
$report_type = '';
$start_date = '';
$end_date = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['generate'])) {
    $report_type = $_POST['report_type'] ?? $_GET['report_type'] ?? '';
    $date_range = $_POST['date_range'] ?? $_GET['date_range'] ?? '';
    $start_date = $_POST['start_date'] ?? $_GET['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? $_GET['end_date'] ?? '';
    
    // Handle date range presets
    if ($date_range && $date_range !== 'custom') {
        $dates = getDateRangeFromPreset($date_range);
        $start_date = $dates['start'];
        $end_date = $dates['end'];
    }
    
    // Generate report based on type
    switch ($report_type) {
        case 'member':
            $report_data = $reportController->getMemberReport($start_date, $end_date);
            break;
        case 'financial':
            $report_data = $reportController->getFinancialReport($start_date, $end_date);
            break;
        case 'loan':
            $report_data = $reportController->getLoanReport($start_date, $end_date);
            break;
        case 'activity':
            $report_data = $reportController->getActivityReport($start_date, $end_date);
            break;
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $report_data) {
    $filename = $report_type . '_report_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate CSV based on report type
    switch ($report_type) {
        case 'member':
            echo "Member Status Report\n";
            echo "Status,Count\n";
            foreach ($report_data['member_status'] as $status => $count) {
                echo "$status,$count\n";
            }
            break;
        case 'financial':
            echo "Financial Summary Report\n";
            echo "Category,Amount,Count\n";
            // Update CSV header label
            echo "Total Savings," . $report_data['contributions']['total_contributions'] . "," . $report_data['contributions']['total_transactions'] . "\n";
            echo "Total Investments," . $report_data['investments']['total_investments'] . "," . $report_data['investments']['total_investment_count'] . "\n";
            echo "Total Loans," . $report_data['loans']['total_loans'] . "," . $report_data['loans']['total_loan_count'] . "\n";
            break;
    }
    exit;
}

// Get available options
$report_types = $reportController->getReportTypes();
$date_presets = $reportController->getDateRangePresets();

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Page configuration
$pageConfig = AdminTemplateConfig::getPageConfig('reports');
$pageTitle = $pageConfig['title'];
$pageDescription = $pageConfig['description'];
$pageIcon = $pageConfig['icon'];

// Helper function for date range presets
function getDateRangeFromPreset($preset) {
    $today = date('Y-m-d');
    $dates = ['start' => $today, 'end' => $today];
    
    switch ($preset) {
        case 'yesterday':
            $dates['start'] = $dates['end'] = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'this_week':
            $dates['start'] = date('Y-m-d', strtotime('monday this week'));
            $dates['end'] = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'last_week':
            $dates['start'] = date('Y-m-d', strtotime('monday last week'));
            $dates['end'] = date('Y-m-d', strtotime('sunday last week'));
            break;
        case 'this_month':
            $dates['start'] = date('Y-m-01');
            $dates['end'] = date('Y-m-t');
            break;
        case 'last_month':
            $dates['start'] = date('Y-m-01', strtotime('last month'));
            $dates['end'] = date('Y-m-t', strtotime('last month'));
            break;
        case 'this_quarter':
            $quarter = ceil(date('n') / 3);
            $dates['start'] = date('Y-' . sprintf('%02d', ($quarter - 1) * 3 + 1) . '-01');
            $dates['end'] = date('Y-m-t', strtotime($dates['start'] . ' +2 months'));
            break;
        case 'this_year':
            $dates['start'] = date('Y-01-01');
            $dates['end'] = date('Y-12-31');
            break;
    }
    
    return $dates;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <!-- Font Awesome -->
    
    <!-- CSIMS Color System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <!-- Tailwind CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
    <!-- Chart.js for report visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-admin">
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="flex">
        <!-- Include Sidebar -->
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 mt-16 p-6" id="mainContent">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div class="animate-slide-in">
                    <h1 class="text-3xl font-bold mb-2" style="color: var(--text-primary);">
                        <i class="<?php echo $pageIcon; ?> mr-3" style="color: #cb0b0a;"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <p style="color: var(--text-muted);"><?php echo $pageDescription; ?></p>
                </div>
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <?php if ($report_data): ?>
                        <a href="?export=csv&report_type=<?php echo urlencode($report_type); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
                           class="btn btn-standard btn-primary">
                            <i class="fas fa-download mr-2"></i> Export CSV
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-standard btn-outline" onclick="exportData()">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button type="button" class="btn btn-standard btn-outline" onclick="printData()">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Enhanced Flash Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 icon-success"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error flex items-center justify-between animate-slide-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 icon-error"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <button type="button" class="text-current opacity-75 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Enhanced Report Generation Form -->
            <div class="card card-admin animate-fade-in mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-chart-bar mr-2 icon-lapis"></i>
                        Generate Report
                    </h3>
                </div>
                <div class="card-body p-6">
                    <form method="POST" id="reportForm" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-control" id="report_type" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <?php if (isset($report_types)): ?>
                                    <?php foreach ($report_types as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $report_type === $key ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_range" class="form-label">Date Range</label>
                            <select class="form-control" id="date_range" name="date_range" onchange="toggleCustomDates()">
                                <?php if (isset($date_presets)): ?>
                                    <?php foreach ($date_presets as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (isset($_POST['date_range']) && $_POST['date_range'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div id="start_date_group" style="display: none;">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div id="end_date_group" style="display: none;">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="btn btn-primary w-full">
                                <i class="fas fa-chart-bar mr-2"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($report_data): ?>
                <!-- Report Results -->
                <?php if ($report_type === 'member'): ?>
                    <!-- Member Report -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Members</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($report_data['total_members']); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php foreach ($report_data['member_status'] as $status => $count): ?>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-<?php echo $status === 'Active' ? 'success' : ($status === 'Inactive' ? 'warning' : 'info'); ?> shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-<?php echo $status === 'Active' ? 'success' : ($status === 'Inactive' ? 'warning' : 'info'); ?> text-uppercase mb-1"><?php echo $status; ?> Members</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($count); ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-user fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Registration Trends</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($report_data['registration_trends'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Month</th>
                                                        <th>Registrations</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($report_data['registration_trends'] as $trend): ?>
                                                        <tr>
                                                            <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                                                            <td><?php echo number_format($trend['registrations']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No registration data available for the selected period.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Age Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($report_data['age_distribution'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Age Group</th>
                                                        <th>Count</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_with_age = array_sum(array_column($report_data['age_distribution'], 'count'));
                                                    foreach ($report_data['age_distribution'] as $age_group): 
                                                        $percentage = $total_with_age > 0 ? ($age_group['count'] / $total_with_age) * 100 : 0;
                                                    ?>
                                                        <tr>
                                                            <td><?php echo $age_group['age_group']; ?></td>
                                                            <td><?php echo number_format($age_group['count']); ?></td>
                                                            <td><?php echo number_format($percentage, 1); ?>%</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No age data available for the selected period.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'financial'): ?>
                    <!-- Financial Report -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Savings</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₦<?php echo number_format($report_data['contributions']['total_contributions'], 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
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
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Investments</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₦<?php echo number_format($report_data['investments']['total_investments'], 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Loans</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₦<?php echo number_format($report_data['loans']['total_loans'], 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Expected Returns</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₦<?php echo number_format($report_data['investments']['total_expected_returns'], 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Financial Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Total Amount</th>
                                            <th>Transaction Count</th>
                                            <th>Average Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Savings</td>
                                            <td>₦<?php echo number_format($report_data['contributions']['total_contributions'], 2); ?></td>
                                            <td><?php echo number_format($report_data['contributions']['total_transactions']); ?></td>
                                            <td>₦<?php echo number_format($report_data['contributions']['average_contribution'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Investments</td>
                                            <td>₦<?php echo number_format($report_data['investments']['total_investments'], 2); ?></td>
                                            <td><?php echo number_format($report_data['investments']['total_investment_count']); ?></td>
                                            <td>₦<?php echo $report_data['investments']['total_investment_count'] > 0 ? number_format($report_data['investments']['total_investments'] / $report_data['investments']['total_investment_count'], 2) : '0.00'; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Loans</td>
                                            <td>₦<?php echo number_format($report_data['loans']['total_loans'], 2); ?></td>
                                            <td><?php echo number_format($report_data['loans']['total_loan_count']); ?></td>
                                            <td>₦<?php echo $report_data['loans']['total_loan_count'] > 0 ? number_format($report_data['loans']['total_loans'] / $report_data['loans']['total_loan_count'], 2) : '0.00'; ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'loan'): ?>
                    <!-- Loan Report -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Loan Status Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Status</th>
                                                    <th>Count</th>
                                                    <th>Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data['loan_status'] as $status): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-<?php echo $status['status'] === 'Active' ? 'warning' : ($status['status'] === 'Paid' ? 'success' : 'secondary'); ?>">
                                                                <?php echo $status['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo number_format($status['count']); ?></td>
                                                        <td>₦<?php echo number_format($status['total_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Loan Amount Ranges</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Amount Range</th>
                                                    <th>Count</th>
                                                    <th>Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data['amount_ranges'] as $range): ?>
                                                    <tr>
                                                        <td><?php echo $range['amount_range']; ?></td>
                                                        <td><?php echo number_format($range['count']); ?></td>
                                                        <td>₦<?php echo number_format($range['total_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top Borrowers</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Member ID</th>
                                            <th>Loan Count</th>
                                            <th>Total Borrowed</th>
                                            <th>Active Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['top_borrowers'] as $borrower): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($borrower['member_name']); ?></td>
                                                <td><?php echo $borrower['member_id']; ?></td>
                                                <td><?php echo number_format($borrower['loan_count']); ?></td>
                                                <td>₦<?php echo number_format($borrower['total_borrowed'], 2); ?></td>
                                                <td>₦<?php echo number_format($borrower['active_amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($report_type === 'activity'): ?>
                    <!-- Activity Report -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">New Members</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($report_data['new_members']); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
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
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">New Savings Records</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($report_data['new_contributions']); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
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
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">New Loans</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($report_data['new_loans']); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">New Investments</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($report_data['new_investments']); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">System Activity Summary</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Activity summary for the selected period:</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            New Member Registrations
                                            <span class="badge bg-primary rounded-pill"><?php echo number_format($report_data['new_members']); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            New Savings Records
                                            <span class="badge bg-success rounded-pill"><?php echo number_format($report_data['new_contributions']); ?></span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            New Loan Applications
                                            <span class="badge bg-warning rounded-pill"><?php echo number_format($report_data['new_loans']); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            New Investment Records
                                            <span class="badge bg-info rounded-pill"><?php echo number_format($report_data['new_investments']); ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Report Info -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Report Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Report Type:</strong> <?php echo $report_types[$report_type]; ?></p>
                                <p><strong>Generated On:</strong> <?php echo date('F j, Y g:i A'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date Range:</strong> 
                                    <?php 
                                    if ($start_date && $end_date) {
                                        echo date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
                                    } else {
                                        echo 'All Time';
                                    }
                                    ?>
                                </p>
                                <p><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Report Generated -->
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chart-bar fa-3x text-gray-300 mb-3"></i>
                        <h5 class="text-gray-600">No Report Generated</h5>
                        <p class="text-muted">Select a report type and date range above to generate a report.</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Include Footer -->
    <?php include '../../views/includes/footer.php'; ?>
    
    <!-- JavaScript -->

<script>
<?php echo AdminTemplateConfig::getCommonJavaScript(); ?>

// Reports-specific functions
function exportData() {
    showAlert('Exporting report data...', 'success');
    // Implementation for general export functionality
}

function printData() {
    window.print();
}

function toggleCustomDates() {
    const dateRange = document.getElementById('date_range').value;
    const startDateGroup = document.getElementById('start_date_group');
    const endDateGroup = document.getElementById('end_date_group');
    
    if (dateRange === 'custom') {
        startDateGroup.style.display = 'block';
        endDateGroup.style.display = 'block';
        document.getElementById('start_date').required = true;
        document.getElementById('end_date').required = true;
    } else {
        startDateGroup.style.display = 'none';
        endDateGroup.style.display = 'none';
        document.getElementById('start_date').required = false;
        document.getElementById('end_date').required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleCustomDates();
});

// Form validation
document.getElementById('reportForm').addEventListener('submit', function(e) {
    const reportType = document.getElementById('report_type').value;
    const dateRange = document.getElementById('date_range').value;
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    if (!reportType) {
        e.preventDefault();
        alert('Please select a report type.');
        return false;
    }
    
    if (dateRange === 'custom' && (!startDate || !endDate)) {
        e.preventDefault();
        alert('Please select both start and end dates for custom range.');
        return false;
    }
    
    if (dateRange === 'custom' && startDate > endDate) {
        e.preventDefault();
        alert('Start date cannot be later than end date.');
        return false;
    }
});
</script>

<style>
/* border-left-* classes provided globally in assets/css/style.css */

.text-xs {
    font-size: 0.7rem;
}
.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

@media print {
    .btn-toolbar,
    .card:first-child {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    
    .card-header {
        background: none !important;
        border: none !important;
    }
}
</style>

</body>
</html>
