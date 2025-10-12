<?php
session_start();
require_once '../../config/database.php';
require_once '../../controllers/reports_controller.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$reportsController = new ReportsController();

// Get filter parameters
$period = $_GET['period'] ?? '1_year';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$export = $_GET['export'] ?? null;

// Handle export requests
if ($export) {
    $data = [];
    $filename = '';
    
    switch ($export) {
        case 'financial_summary':
            $data = $reportsController->getFinancialSummary($start_date, $end_date);
            $filename = 'financial_summary_' . date('Y-m-d');
            break;
        case 'loan_portfolio':
            $data = $reportsController->getLoanPortfolioAnalysis($period);
            $filename = 'loan_portfolio_' . date('Y-m-d');
            break;
        case 'member_statistics':
            $data = $reportsController->getMemberStatistics($period);
            $filename = 'member_statistics_' . date('Y-m-d');
            break;
        case 'contribution_performance':
            $data = $reportsController->getContributionPerformance($period);
            $filename = 'contribution_performance_' . date('Y-m-d');
            break;
    }
    
    if ($data) {
        $reportsController->exportToCSV($data, $filename);
        exit();
    }
}

// Get dashboard data
$kpis = $reportsController->getKPIs();
$financial_summary = $reportsController->getFinancialSummary($start_date, $end_date);
$loan_portfolio = $reportsController->getLoanPortfolioAnalysis($period);
$member_stats = $reportsController->getMemberStatistics($period);
$contribution_performance = $reportsController->getContributionPerformance($period);
$recent_transactions = $reportsController->getRecentTransactions(10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard - NPC CTLStaff Loan Society</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64" style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);" class="shadow-xl">
            <div class="flex flex-col h-full p-6">
                <h4 class="text-white text-xl font-bold mb-6">
                    <i class="fas fa-university mr-2"></i> Admin Portal
                </h4>
                
                <nav class="flex-1 space-y-2">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="manage_members.php">
                        <i class="fas fa-users mr-3"></i> Members
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="manage_loans.php">
                        <i class="fas fa-money-bill-wave mr-3"></i> Loans
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="manage_contributions.php">
                        <i class="fas fa-piggy-bank mr-3"></i> Contributions
                    </a>
                    <a class="flex items-center px-4 py-3 text-white bg-white/20 rounded-lg font-medium" href="reports_dashboard.php">
                        <i class="fas fa-chart-bar mr-3"></i> Reports
                    </a>
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="approvals_dashboard.php">
                        <i class="fas fa-check-circle mr-3"></i> Approvals
                    </a>
                </nav>
                
                <div class="mt-auto">
                    <a class="flex items-center px-4 py-3 text-primary-200 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200" href="admin_logout.php">
                        <i class="fas fa-sign-out-alt mr-3"></i> Logout
                    </a>
                </div>
            </div>
        </div>
            
        <!-- Main Content -->
        <div class="flex-1 overflow-hidden">
            <div class="p-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-chart-bar mr-3 text-primary-600"></i> Reports Dashboard
                        </h1>
                        <p class="text-gray-600 mt-2">Comprehensive analytics and reporting for cooperative society management</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <select id="periodFilter" onchange="updatePeriod()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="1_month" <?php echo $period === '1_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="3_months" <?php echo $period === '3_months' ? 'selected' : ''; ?>>Last 3 Months</option>
                            <option value="6_months" <?php echo $period === '6_months' ? 'selected' : ''; ?>>Last 6 Months</option>
                            <option value="1_year" <?php echo $period === '1_year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="2_years" <?php echo $period === '2_years' ? 'selected' : ''; ?>>Last 2 Years</option>
                        </select>
                    </div>
                </div>
                
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Active Members</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($kpis['total_active_members']); ?></p>
                                <p class="text-sm text-gray-500">+<?php echo $kpis['new_members_this_month']; ?> this month</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-users text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Outstanding Loans</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($kpis['outstanding_loans'], 2); ?></p>
                                <p class="text-sm text-gray-500">Portfolio balance</p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-money-bill-wave text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4" style="border-left-color: var(--lapis-lazuli);">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--lapis-lazuli);">Contributions YTD</p>
                                <p class="text-2xl font-bold text-gray-800">₦<?php echo number_format($kpis['contributions_this_year'], 2); ?></p>
                                <p class="text-sm text-gray-500">Year to date</p>
                            </div>
                            <div class="p-3 rounded-full" style="background-color: rgba(26, 85, 153, 0.1);">
                                <i class="fas fa-piggy-bank text-2xl" style="color: var(--lapis-lazuli);"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Portfolio at Risk</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $kpis['portfolio_at_risk']; ?>%</p>
                                <p class="text-sm text-gray-500"><?php echo $kpis['overdue_loans']; ?> overdue loans</p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Financial Summary Chart -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-900">Financial Overview</h3>
                            <button onclick="exportData('financial_summary')" class="text-primary-600 hover:text-primary-700 flex items-center text-sm">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                        </div>
                        <div class="h-80">
                            <canvas id="financialChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Loan Portfolio Chart -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-900">Loan Status Distribution</h3>
                            <button onclick="exportData('loan_portfolio')" class="text-primary-600 hover:text-primary-700 flex items-center text-sm">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                        </div>
                        <div class="h-80">
                            <canvas id="loanStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed Analytics Tabs -->
                <div class="bg-white rounded-2xl shadow-lg">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6">
                            <button onclick="showTab('financial')" class="tab-button active border-b-2 border-primary-500 py-4 px-1 text-sm font-medium text-primary-600">
                                <i class="fas fa-chart-line mr-2"></i> Financial Analysis
                            </button>
                            <button onclick="showTab('members')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                                <i class="fas fa-users mr-2"></i> Member Statistics
                            </button>
                            <button onclick="showTab('loans')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                                <i class="fas fa-money-bill-wave mr-2"></i> Loan Portfolio
                            </button>
                            <button onclick="showTab('contributions')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                                <i class="fas fa-piggy-bank mr-2"></i> Contributions
                            </button>
                            <button onclick="showTab('transactions')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">
                                <i class="fas fa-exchange-alt mr-2"></i> Recent Transactions
                            </button>
                        </nav>
                    </div>
                    
                    <!-- Financial Analysis Tab -->
                    <div id="financial-tab" class="tab-content p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Total Assets -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Total Assets</h4>
                                <p class="text-2xl font-bold text-green-600">₦<?php echo number_format($financial_summary['net_position']['total_assets'], 2); ?></p>
                                <p class="text-sm text-gray-500 mt-2">Contributions + Shares - Withdrawals</p>
                            </div>
                            
                            <!-- Outstanding Loans -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Outstanding Loans</h4>
                                <p class="text-2xl font-bold text-blue-600">₦<?php echo number_format($financial_summary['net_position']['outstanding_loans'], 2); ?></p>
                                <p class="text-sm text-gray-500 mt-2"><?php echo $financial_summary['loans']['total_loans']; ?> active loans</p>
                            </div>
                            
                            <!-- Liquid Reserves -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Liquid Reserves</h4>
                                <p class="text-2xl font-bold text-purple-600">₦<?php echo number_format($financial_summary['net_position']['liquid_reserves'], 2); ?></p>
                                <p class="text-sm text-gray-500 mt-2">Available for lending</p>
                            </div>
                        </div>
                        
                        <div class="mt-8">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Financial Summary Details</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Category</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500">Count</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500">Amount</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500">Average</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-3 font-medium">Total Contributions</td>
                                            <td class="px-4 py-3 text-right"><?php echo number_format($financial_summary['contributions']['total_contributions']); ?></td>
                                            <td class="px-4 py-3 text-right text-green-600">₦<?php echo number_format($financial_summary['contributions']['total_amount'], 2); ?></td>
                                            <td class="px-4 py-3 text-right">₦<?php echo $financial_summary['contributions']['total_contributions'] > 0 ? number_format($financial_summary['contributions']['total_amount'] / $financial_summary['contributions']['total_contributions'], 2) : '0.00'; ?></td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 font-medium">Share Capital</td>
                                            <td class="px-4 py-3 text-right"><?php echo number_format($financial_summary['shares']['total_share_purchases']); ?></td>
                                            <td class="px-4 py-3 text-right text-blue-600">₦<?php echo number_format($financial_summary['shares']['total_paid'], 2); ?></td>
                                            <td class="px-4 py-3 text-right">₦<?php echo $financial_summary['shares']['total_share_purchases'] > 0 ? number_format($financial_summary['shares']['total_paid'] / $financial_summary['shares']['total_share_purchases'], 2) : '0.00'; ?></td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 font-medium">Withdrawals</td>
                                            <td class="px-4 py-3 text-right"><?php echo number_format($financial_summary['withdrawals']['total_withdrawals']); ?></td>
                                            <td class="px-4 py-3 text-right text-red-600">₦<?php echo number_format($financial_summary['withdrawals']['net_amount'], 2); ?></td>
                                            <td class="px-4 py-3 text-right">₦<?php echo $financial_summary['withdrawals']['total_withdrawals'] > 0 ? number_format($financial_summary['withdrawals']['net_amount'] / $financial_summary['withdrawals']['total_withdrawals'], 2) : '0.00'; ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member Statistics Tab -->
                    <div id="members-tab" class="tab-content hidden p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <!-- Member Growth Chart -->
                            <div class="md:col-span-2">
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Member Growth Trend</h4>
                                <div class="h-64">
                                    <canvas id="memberGrowthChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Status Distribution -->
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Member Status</h4>
                                <div class="space-y-3">
                                    <?php foreach ($member_stats['status_distribution'] as $status): ?>
                                        <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg">
                                            <span class="font-medium"><?php echo ucfirst($status['status']); ?></span>
                                            <div class="text-right">
                                                <span class="text-lg font-bold"><?php echo $status['count']; ?></span>
                                                <span class="text-sm text-gray-500">(<?php echo $status['percentage']; ?>%)</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Contribution Participation -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Contribution Participation</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Active Contributors:</span>
                                        <span class="font-medium"><?php echo number_format($member_stats['contribution_participation']['active_contributors']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Average Contribution:</span>
                                        <span class="font-medium">₦<?php echo number_format($member_stats['contribution_participation']['avg_contributions'], 2); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Highest Contribution:</span>
                                        <span class="font-medium">₦<?php echo number_format($member_stats['contribution_participation']['max_contributions'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Loan Participation -->
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Loan Participation</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Members with Loans:</span>
                                        <span class="font-medium"><?php echo number_format($member_stats['loan_participation']['members_with_loans']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Avg Loans per Member:</span>
                                        <span class="font-medium"><?php echo number_format($member_stats['loan_participation']['avg_loans_per_member'], 1); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Avg Amount per Member:</span>
                                        <span class="font-medium">₦<?php echo number_format($member_stats['loan_participation']['avg_amount_per_member'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loan Portfolio Tab -->
                    <div id="loans-tab" class="tab-content hidden p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <!-- Term Analysis -->
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Loans by Term</h4>
                                <div class="space-y-4">
                                    <?php foreach ($loan_portfolio['term_analysis'] as $term): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="font-medium"><?php echo $term['term_category']; ?></span>
                                                <span class="text-sm text-gray-500"><?php echo $term['count']; ?> loans</span>
                                            </div>
                                            <div class="text-lg font-bold text-blue-600">₦<?php echo number_format($term['total_amount'], 2); ?></div>
                                            <div class="text-sm text-gray-500">
                                                Avg Rate: <?php echo number_format($term['avg_interest_rate'], 2); ?>% | 
                                                Outstanding: ₦<?php echo number_format($term['outstanding'], 2); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Overdue Analysis -->
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Overdue Analysis</h4>
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-red-700">Overdue Loans:</span>
                                            <span class="font-bold text-red-800"><?php echo $loan_portfolio['overdue_analysis']['overdue_count']; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-red-700">Overdue Amount:</span>
                                            <span class="font-bold text-red-800">₦<?php echo number_format($loan_portfolio['overdue_analysis']['overdue_amount'], 2); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-red-700">Avg Days Overdue:</span>
                                            <span class="font-bold text-red-800"><?php echo number_format($loan_portfolio['overdue_analysis']['avg_days_overdue']); ?> days</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <h5 class="font-semibold text-gray-900 mb-3">Top Borrowers</h5>
                                    <div class="space-y-2">
                                        <?php foreach (array_slice($loan_portfolio['top_borrowers'], 0, 5) as $borrower): ?>
                                            <div class="flex justify-between items-center p-2 border-b border-gray-200">
                                                <span class="text-sm font-medium"><?php echo $borrower['member_name']; ?></span>
                                                <span class="text-sm font-bold">₦<?php echo number_format($borrower['current_outstanding'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contributions Tab -->
                    <div id="contributions-tab" class="tab-content hidden p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Monthly Trends -->
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Monthly Contribution Trends</h4>
                                <div class="h-64">
                                    <canvas id="contributionTrendsChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Contribution Types -->
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Contribution Types</h4>
                                <div class="space-y-3">
                                    <?php foreach ($contribution_performance['type_analysis'] as $type): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="font-medium"><?php echo ucfirst($type['contribution_type']); ?></span>
                                                <span class="text-sm text-gray-500"><?php echo $type['transaction_count']; ?> transactions</span>
                                            </div>
                                            <div class="text-lg font-bold text-green-600">₦<?php echo number_format($type['total_amount'], 2); ?></div>
                                            <div class="text-sm text-gray-500">
                                                Avg: ₦<?php echo number_format($type['avg_amount'], 2); ?> | 
                                                Contributors: <?php echo $type['unique_contributors']; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Contributors -->
                        <div class="mt-8">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Top Contributors (Last Year)</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500">Member</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500">Transactions</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500">Total Amount</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500">Average</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($contribution_performance['top_contributors'] as $contributor): ?>
                                            <tr>
                                                <td class="px-4 py-3 font-medium"><?php echo $contributor['member_name']; ?></td>
                                                <td class="px-4 py-3 text-right"><?php echo $contributor['contribution_count']; ?></td>
                                                <td class="px-4 py-3 text-right text-green-600">₦<?php echo number_format($contributor['total_contributed'], 2); ?></td>
                                                <td class="px-4 py-3 text-right">₦<?php echo number_format($contributor['avg_contribution'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Transactions Tab -->
                    <div id="transactions-tab" class="tab-content hidden p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">Member</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500">Amount</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">Description</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($recent_transactions as $transaction): ?>
                                        <tr>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match($transaction['type']) {
                                                        'contribution' => 'bg-green-100 text-green-800',
                                                        'loan_payment' => 'bg-blue-100 text-blue-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-medium"><?php echo $transaction['member_name']; ?></td>
                                            <td class="px-4 py-3 text-right font-bold text-green-600">₦<?php echo number_format($transaction['amount'], 2); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo $transaction['description']; ?></td>
                                            <td class="px-4 py-3"><?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?></td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match($transaction['status']) {
                                                        'Confirmed' => 'bg-green-100 text-green-800',
                                                        'Pending' => 'bg-yellow-100 text-yellow-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo $transaction['status']; ?>
                                                </span>
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
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-primary-500', 'text-primary-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Add active class to selected button
            const activeButton = document.querySelector(`[onclick="showTab('${tabName}')"]`);
            activeButton.classList.add('active', 'border-primary-500', 'text-primary-600');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        // Period filter
        function updatePeriod() {
            const period = document.getElementById('periodFilter').value;
            const url = new URL(window.location);
            url.searchParams.set('period', period);
            window.location = url;
        }

        // Export functionality
        function exportData(type) {
            const period = document.getElementById('periodFilter').value;
            const url = new URL(window.location);
            url.searchParams.set('export', type);
            url.searchParams.set('period', period);
            window.open(url, '_blank');
        }

        // Chart data
        const financialData = <?php echo json_encode([
            'labels' => ['Contributions', 'Shares', 'Loan Payments', 'Withdrawals'],
            'data' => [
                $financial_summary['contributions']['total_amount'],
                $financial_summary['shares']['total_paid'],
                $financial_summary['loans']['total_paid'],
                $financial_summary['withdrawals']['net_amount']
            ]
        ]); ?>;

        const loanStatusData = <?php echo json_encode([
            'labels' => array_column($loan_portfolio['status_distribution'], 'status'),
            'data' => array_column($loan_portfolio['status_distribution'], 'count')
        ]); ?>;

        const memberGrowthData = <?php echo json_encode([
            'labels' => array_column($member_stats['growth_trend'], 'month'),
            'data' => array_column($member_stats['growth_trend'], 'cumulative_members')
        ]); ?>;

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Financial Overview Chart
            new Chart(document.getElementById('financialChart'), {
                type: 'bar',
                data: {
                    labels: financialData.labels,
                    datasets: [{
                        data: financialData.data,
                        backgroundColor: ['#10B981', '#3B82F6', '#8B5CF6', '#EF4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₦' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Loan Status Chart
            new Chart(document.getElementById('loanStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: loanStatusData.labels,
                    datasets: [{
                        data: loanStatusData.data,
                        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // Member Growth Chart
            new Chart(document.getElementById('memberGrowthChart'), {
                type: 'line',
                data: {
                    labels: memberGrowthData.labels,
                    datasets: [{
                        label: 'Total Members',
                        data: memberGrowthData.data,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>
