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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .health-score {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        
        .health-excellent { background: linear-gradient(45deg, #4CAF50, #8BC34A); color: white; }
        .health-good { background: linear-gradient(45deg, #2196F3, #03DAC6); color: white; }
        .health-fair { background: linear-gradient(45deg, #FF9800, #FFC107); color: white; }
        .health-poor { background: linear-gradient(45deg, #F44336, #E91E63); color: white; }
        
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .trend-up { color: #4CAF50; }
        .trend-down { color: #F44336; }
        .trend-stable { color: #FF9800; }
    </style>
</head>
<body>
    <?php 
    $auth = $authController;
    require_once '../includes/header.php'; 
    ?>

    <div class="container-fluid">
        <div class="row">
            <?php require_once '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-line me-2"></i>Financial Analytics Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <select class="form-select" id="periodSelector" onchange="changePeriod()">
                                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                            </select>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?export=overview&period=<?php echo $period; ?>">Overview Data</a></li>
                                <li><a class="dropdown-item" href="?export=cash_flow&period=<?php echo $period; ?>">Cash Flow Analysis</a></li>
                                <li><a class="dropdown-item" href="?export=member_health&period=<?php echo $period; ?>">Member Financial Health</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($session->getFlash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($session->getFlash('success')); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Financial Overview Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value">₦<?php echo number_format($dashboard_data['overview']['total_assets'], 0); ?></div>
                                    <div class="metric-label">Total Assets</div>
                                </div>
                                <i class="fas fa-wallet fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value">₦<?php echo number_format($dashboard_data['overview']['outstanding_loans'], 0); ?></div>
                                    <div class="metric-label">Outstanding Loans</div>
                                </div>
                                <i class="fas fa-hand-holding-usd fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value"><?php echo number_format($dashboard_data['overview']['financial_health_score'], 0); ?>%</div>
                                    <div class="metric-label">Financial Health Score</div>
                                </div>
                                <i class="fas fa-heartbeat fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value"><?php echo number_format($dashboard_data['overview']['loan_to_asset_ratio'], 1); ?>%</div>
                                    <div class="metric-label">Loan-to-Asset Ratio</div>
                                </div>
                                <i class="fas fa-chart-pie fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cash Flow Analysis -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-exchange-alt me-2"></i>Cash Flow Analysis</h5>
                            <canvas id="cashFlowChart" height="100"></canvas>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-tachometer-alt me-2"></i>Key Metrics</h5>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Net Cash Flow:</span>
                                    <span class="fw-bold <?php echo $dashboard_data['cash_flow']['net_cash_flow'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        ₦<?php echo number_format($dashboard_data['cash_flow']['net_cash_flow'], 0); ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Cash Flow Ratio:</span>
                                    <span class="fw-bold"><?php echo number_format($dashboard_data['cash_flow']['cash_flow_ratio'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Liquidity Position:</span>
                                    <span class="fw-bold <?php echo $dashboard_data['overview']['liquidity_ratio'] >= 0 ? 'text-success' : 'text-warning'; ?>">
                                        ₦<?php echo number_format($dashboard_data['overview']['liquidity_ratio'], 0); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loan Performance & Investment Returns -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Loan Performance</h5>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded">
                                        <h4 class="text-primary"><?php echo number_format($dashboard_data['loan_performance']['collection_rate'], 1); ?>%</h4>
                                        <small class="text-muted">Collection Rate</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded">
                                        <h4 class="text-warning"><?php echo number_format($dashboard_data['loan_performance']['default_rate'], 1); ?>%</h4>
                                        <small class="text-muted">Default Rate</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <canvas id="loanPerformanceChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Investment Returns</h5>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded">
                                        <h4 class="text-success"><?php echo number_format($dashboard_data['investment_returns']['avg_roi_percentage'], 1); ?>%</h4>
                                        <small class="text-muted">Average ROI</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded">
                                        <h4 class="text-info"><?php echo number_format($dashboard_data['investment_returns']['realization_rate'], 1); ?>%</h4>
                                        <small class="text-muted">Realization Rate</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <canvas id="investmentChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Trends -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-trending-up me-2"></i>Financial Trends Over Time</h5>
                            <canvas id="trendsChart" height="80"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Member Financial Health & Forecasts -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-users me-2"></i>Top Members by Financial Health</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Contributions</th>
                                            <th>Active Loans</th>
                                            <th>Health Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($dashboard_data['member_financial_health'], 0, 10) as $member): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                                                <td>₦<?php echo number_format($member['total_contributions'], 0); ?></td>
                                                <td>₦<?php echo number_format($member['active_loans'], 0); ?></td>
                                                <td>
                                                    <?php 
                                                    $score = $member['financial_health_score'];
                                                    $class = $score >= 80 ? 'health-excellent' : ($score >= 60 ? 'health-good' : ($score >= 40 ? 'health-fair' : 'health-poor'));
                                                    ?>
                                                    <span class="health-score <?php echo $class; ?>"><?php echo number_format($score, 0); ?>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-crystal-ball me-2"></i>Financial Forecasts</h5>
                            <?php foreach ($dashboard_data['forecasts'] as $category => $forecast): ?>
                                <div class="mb-3 p-3 border rounded">
                                    <h6 class="mb-2"><?php echo $category; ?></h6>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>Next Period:</span>
                                        <span class="fw-bold">₦<?php echo number_format($forecast['next_period_forecast'], 0); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>Trend:</span>
                                        <span class="trend-indicator trend-<?php echo $forecast['trend_direction']; ?>">
                                            <i class="fas fa-arrow-<?php echo $forecast['trend_direction'] === 'increasing' ? 'up' : ($forecast['trend_direction'] === 'decreasing' ? 'down' : 'right'); ?>"></i>
                                            <?php echo ucfirst($forecast['trend_direction']); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Confidence:</span>
                                        <span class="badge bg-<?php echo $forecast['confidence_level'] === 'high' ? 'success' : ($forecast['confidence_level'] === 'medium' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($forecast['confidence_level']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php include '../../views/includes/footer.php'; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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

        // Investment Chart
        const investCtx = document.getElementById('investmentChart').getContext('2d');
        const investChart = new Chart(investCtx, {
            type: 'doughnut',
            data: {
                labels: ['Realized Returns', 'Unrealized Returns'],
                datasets: [{
                    data: [
                        <?php echo $dashboard_data['investment_returns']['realized_returns']; ?>,
                        <?php echo $dashboard_data['investment_returns']['unrealized_returns']; ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(153, 102, 255, 1)'
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
        const categories = ['Contributions', 'Loans', 'Investments'];
        
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
