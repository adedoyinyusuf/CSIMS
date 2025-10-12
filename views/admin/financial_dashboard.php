<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/financial_analytics_controller.php';

// Initialize session and auth
$session = Session::getInstance();
$authController = new AuthController();

// Check if user is logged in
if (!$authController->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize financial analytics controller
$financialController = new FinancialAnalyticsController();

// Get period from request
$period = $_GET['period'] ?? 'month';
$valid_periods = ['week', 'month', 'quarter', 'year'];
if (!in_array($period, $valid_periods)) {
    $period = 'month';
}

// Handle export requests
if (isset($_GET['export'])) {
    $dashboard_data = $financialController->getFinancialDashboard($period);
    $financialController->exportAnalytics($dashboard_data, $_GET['export']);
    exit;
}

// Get dashboard data
$dashboard_data = $financialController->getFinancialDashboard($period);

$page_title = 'Financial Analytics Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - NPC CTLStaff Loan Society</title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <?php 
    $auth = $authController;
    require_once '../includes/header.php'; 
    ?>

    <div class="flex">
        <?php require_once '../includes/sidebar.php'; ?>
        
        <main class="flex-1 md:ml-64 mt-16 p-6">
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div class="mb-4 sm:mb-0">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">
                            <i class="fas fa-chart-line mr-3 text-primary-600"></i>Financial Analytics Dashboard
                        </h1>
                        <p class="text-gray-600">Comprehensive financial insights and analytics</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <select class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="periodSelector" onchange="changePeriod()">
                            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                        </select>
                        <div class="relative">
                            <button type="button" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" onclick="toggleDropdown()">
                                <i class="fas fa-download mr-2"></i> Export
                                <i class="fas fa-chevron-down ml-2"></i>
                            </button>
                            <div id="exportDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                <div class="py-1">
                                    <a href="?export=overview&period=<?php echo $period; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Overview Data</a>
                                    <a href="?export=cash_flow&period=<?php echo $period; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cash Flow Analysis</a>
                                    <a href="?export=member_health&period=<?php echo $period; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Member Financial Health</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($session->getFlash('success')): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-3 text-green-600"></i>
                        <?php echo htmlspecialchars($session->getFlash('success')); ?>
                    </div>
                    <button type="button" class="text-green-600 hover:text-green-800" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Financial Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div style="background: linear-gradient(135deg, var(--lapis-lazuli) 0%, var(--true-blue) 100%);" class="rounded-xl p-6 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold mb-2">₦<?php echo number_format($dashboard_data['overview']['total_assets'], 0); ?></div>
                            <div class="text-blue-100 text-sm">Total Assets</div>
                        </div>
                        <i class="fas fa-wallet text-4xl text-blue-200"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-pink-500 to-red-500 rounded-xl p-6 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold mb-2">₦<?php echo number_format($dashboard_data['overview']['outstanding_loans'], 0); ?></div>
                            <div class="text-pink-100 text-sm">Outstanding Loans</div>
                        </div>
                        <i class="fas fa-hand-holding-usd text-4xl text-pink-200"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-6 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold mb-2"><?php echo number_format($dashboard_data['overview']['financial_health_score'], 0); ?>%</div>
                            <div class="text-blue-100 text-sm">Financial Health Score</div>
                        </div>
                        <i class="fas fa-heartbeat text-4xl text-blue-200"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl p-6 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-3xl font-bold mb-2"><?php echo number_format($dashboard_data['overview']['loan_to_asset_ratio'], 1); ?>%</div>
                            <div class="text-yellow-100 text-sm">Loan-to-Asset Ratio</div>
                        </div>
                        <i class="fas fa-chart-pie text-4xl text-yellow-200"></i>
                    </div>
                </div>
            </div>

            <!-- Cash Flow Analysis -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-exchange-alt mr-2 text-primary-600"></i>Cash Flow Analysis
                        </h3>
                        <canvas id="cashFlowChart" height="100"></canvas>
                    </div>
                </div>
                
                <div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-tachometer-alt mr-2 text-primary-600"></i>Key Metrics
                        </h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Net Cash Flow:</span>
                                <span class="font-semibold <?php echo $dashboard_data['cash_flow']['net_cash_flow'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    ₦<?php echo number_format($dashboard_data['cash_flow']['net_cash_flow'], 0); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Cash Flow Ratio:</span>
                                <span class="font-semibold text-gray-900"><?php echo number_format($dashboard_data['cash_flow']['cash_flow_ratio'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Liquidity Position:</span>
                                <span class="font-semibold <?php echo $dashboard_data['overview']['liquidity_ratio'] >= 0 ? 'text-green-600' : 'text-yellow-600'; ?>">
                                    ₦<?php echo number_format($dashboard_data['overview']['liquidity_ratio'], 0); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loan Performance & Savings Performance -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-chart-bar mr-2 text-primary-600"></i>Loan Performance
                    </h3>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="text-center p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600"><?php echo number_format($dashboard_data['loan_performance']['collection_rate'], 1); ?>%</div>
                            <div class="text-sm text-blue-700">Collection Rate</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600"><?php echo number_format($dashboard_data['loan_performance']['default_rate'], 1); ?>%</div>
                            <div class="text-sm text-yellow-700">Default Rate</div>
                        </div>
                    </div>
                    <canvas id="loanPerformanceChart" height="150"></canvas>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-piggy-bank mr-2 text-primary-600"></i>Savings Performance
                    </h3>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="text-center p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="text-2xl font-bold text-green-600"><?php echo number_format($dashboard_data['savings_performance']['avg_interest_rate'] ?? 0, 1); ?>%</div>
                            <div class="text-sm text-green-700">Average Interest Rate</div>
                        </div>
                        <div class="text-center p-4 bg-cyan-50 border border-cyan-200 rounded-lg">
                            <div class="text-2xl font-bold text-cyan-600"><?php echo number_format($dashboard_data['savings_performance']['growth_rate'] ?? 0, 1); ?>%</div>
                            <div class="text-sm text-cyan-700">Growth Rate</div>
                        </div>
                    </div>
                    <canvas id="savingsChart" height="150"></canvas>
                </div>
            </div>

            <!-- Financial Trends -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-trending-up mr-2 text-primary-600"></i>Financial Trends Over Time
                </h3>
                <canvas id="trendsChart" height="80"></canvas>
            </div>

            <!-- Member Financial Health & Forecasts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-users mr-2 text-primary-600"></i>Top Members by Financial Health
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Savings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Loans</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Score</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach (array_slice($dashboard_data['member_financial_health'], 0, 10) as $member): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($member['member_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₦<?php echo number_format($member['total_savings'] ?? 0, 0); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₦<?php echo number_format($member['active_loans'], 0); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php 
                                                $score = $member['financial_health_score'];
                                                $class = $score >= 80 ? 'bg-green-100 text-green-800' : ($score >= 60 ? 'bg-blue-100 text-blue-800' : ($score >= 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'));
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $class; ?>"><?php echo number_format($score, 0); ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-crystal-ball mr-2 text-primary-600"></i>Financial Forecasts
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($dashboard_data['forecasts'] as $category => $forecast): ?>
                                <div class="p-4 border border-gray-200 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-3"><?php echo $category; ?></h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600">Next Period:</span>
                                            <span class="font-semibold text-gray-900">₦<?php echo number_format($forecast['next_period_forecast'], 0); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600">Trend:</span>
                                            <span class="inline-flex items-center text-sm font-medium <?php echo $forecast['trend_direction'] === 'increasing' ? 'text-green-600' : ($forecast['trend_direction'] === 'decreasing' ? 'text-red-600' : 'text-yellow-600'); ?>">
                                                <i class="fas fa-arrow-<?php echo $forecast['trend_direction'] === 'increasing' ? 'up' : ($forecast['trend_direction'] === 'decreasing' ? 'down' : 'right'); ?> mr-1"></i>
                                                <?php echo ucfirst($forecast['trend_direction']); ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600">Confidence:</span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $forecast['confidence_level'] === 'high' ? 'bg-green-100 text-green-800' : ($forecast['confidence_level'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                <?php echo ucfirst($forecast['confidence_level']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../../views/includes/footer.php'; ?>
        </main>
    </div>
    
    <script>
        // Dropdown toggle
        function toggleDropdown() {
            const dropdown = document.getElementById('exportDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('exportDropdown');
            const button = event.target.closest('button');
            if (!button || !button.onclick) {
                dropdown.classList.add('hidden');
            }
        });

        // Period selector
        function changePeriod() {
            const period = document.getElementById('periodSelector').value;
            window.location.href = `?period=${period}`;
        }

        // Cash Flow Chart
        const cashFlowCtx = document.getElementById('cashFlowChart').getContext('2d');
        const cashFlowChart = new Chart(cashFlowCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($dashboard_data['cash_flow']['inflows'] as $inflow): ?>
                        '<?php echo $inflow['source']; ?>',
                    <?php endforeach; ?>
                    <?php foreach ($dashboard_data['cash_flow']['outflows'] as $outflow): ?>
                        '<?php echo $outflow['source']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Cash Flow',
                    data: [
                        <?php foreach ($dashboard_data['cash_flow']['inflows'] as $inflow): ?>
                            <?php echo $inflow['amount']; ?>,
                        <?php endforeach; ?>
                        <?php foreach ($dashboard_data['cash_flow']['outflows'] as $outflow): ?>
                            -<?php echo $outflow['amount']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: function(context) {
                        return context.parsed.y >= 0 ? 'rgba(75, 192, 192, 0.8)' : 'rgba(255, 99, 132, 0.8)';
                    },
                    borderColor: function(context) {
                        return context.parsed.y >= 0 ? 'rgba(75, 192, 192, 1)' : 'rgba(255, 99, 132, 1)';
                    },
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
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

        // Loan Performance Chart
        const loanCtx = document.getElementById('loanPerformanceChart').getContext('2d');
        const loanChart = new Chart(loanCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Loans', 'Paid Loans'],
                datasets: [{
                    data: [
                        <?php echo $dashboard_data['loan_performance']['active_loan_amount']; ?>,
                        <?php echo $dashboard_data['loan_performance']['paid_loan_amount']; ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
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

        // Savings Chart
        const savingsCtx = document.getElementById('savingsChart').getContext('2d');
        const savingsChart = new Chart(savingsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Savings', 'Interest Earned'],
                datasets: [{
                    data: [
                        <?php echo $dashboard_data['savings_performance']['total_savings'] ?? 0; ?>,
                        <?php echo $dashboard_data['savings_performance']['total_interest_earned'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)'
                    ],
                    borderColor: [
                        'rgba(34, 197, 94, 1)',
                        'rgba(59, 130, 246, 1)'
                    ],
                    borderWidth: 1
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

        // Trends Chart
        const trendsData = <?php echo json_encode($dashboard_data['trends']); ?>;
        const periods = [...new Set(trendsData.map(item => item.period))].sort();
        const categories = ['Savings', 'Loans'];
        
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: periods,
                datasets: categories.map((category, index) => {
                    const categoryData = trendsData.filter(item => item.category === category);
                    const colors = [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)'
                    ];
                    
                    return {
                        label: category,
                        data: periods.map(period => {
                            const item = categoryData.find(d => d.period === period);
                            return item ? item.amount : 0;
                        }),
                        borderColor: colors[index],
                        backgroundColor: colors[index].replace('1)', '0.1)'),
                        tension: 0.4,
                        fill: false
                    };
                })
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
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
    </script>
</body>
</html>
