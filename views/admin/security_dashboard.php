<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/security_controller.php';

// Initialize session and auth
$session = Session::getInstance();
$authController = new AuthController();

// Check if user is logged in and is admin
if (!$authController->isLoggedIn() || !$authController->hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize security controller
$securityController = new SecurityController();

// Get period from request
$period = $_GET['period'] ?? 'week';
$valid_periods = ['week', 'month', 'quarter', 'year'];
if (!in_array($period, $valid_periods)) {
    $period = 'week';
}

// Handle actions
if ($_POST['action'] ?? false) {
    switch ($_POST['action']) {
        case 'unlock_account':
            if (isset($_POST['username'])) {
                $result = $securityController->unlockAccount($_POST['username'], $session->get('user_id'));
                if ($result) {
                    $session->setFlash('success', 'Account unlocked successfully.');
                } else {
                    $session->setFlash('error', 'Failed to unlock account.');
                }
            }
            break;
            
        case 'run_audit':
            $audit_results = $securityController->performSecurityAudit();
            $session->setFlash('success', 'Security audit completed.');
            break;
    }
    
    header('Location: security_dashboard.php?period=' . $period);
    exit();
}

// Handle export requests
if (isset($_GET['export'])) {
    $securityController->exportSecurityReport($_GET['export']);
    exit;
}

// Get dashboard data
$dashboard_data = $securityController->getSecurityDashboard($period);
$audit_results = $securityController->performSecurityAudit();

$page_title = 'Security Dashboard';
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
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .security-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .security-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }
        
        .security-card.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        
        .security-card.warning {
            background: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%);
        }
        
        .security-card.success {
            background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%);
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
        
        .severity-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .severity-low { background: #d4edda; color: #155724; }
        .severity-medium { background: #fff3cd; color: #856404; }
        .severity-high { background: #f8d7da; color: #721c24; }
        .severity-critical { background: #721c24; color: white; }
        
        .security-score {
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
        }
        
        .score-excellent { color: var(--success); }
        .score-good { color: var(--info); }
        .score-fair { color: var(--warning); }
        .score-poor { color: var(--error); }
        
        .event-item {
            border-left: 4px solid var(--true-blue);
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: var(--primary-50);
            border-radius: 0 8px 8px 0;
        }
        
        .event-item.danger { border-left-color: var(--error); background: var(--error-bg); }
        .event-item.warning { border-left-color: var(--warning); background: var(--warning-bg); }
        .event-item.success { border-left-color: var(--success); background: var(--success-bg); }
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
                    <h1 class="h2"><i class="fas fa-shield-alt me-2"></i>Security Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <select class="form-select" id="periodSelector" onchange="changePeriod()">
                                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                            </select>
                        </div>
                        <div class="btn-group me-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="run_audit">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Run Audit
                                </button>
                            </form>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?export=csv&period=<?php echo $period; ?>">Security Report (CSV)</a></li>
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

                <?php if ($session->getFlash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($session->getFlash('error')); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Security Overview Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="security-card <?php echo (isset($audit_results['security_score']) && $audit_results['security_score'] >= 80) ? 'success' : ((isset($audit_results['security_score']) && $audit_results['security_score'] >= 60) ? 'warning' : 'danger'); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value"><?php echo $audit_results['security_score'] ?? 0; ?>%</div>
                                    <div class="metric-label">Security Score</div>
                                </div>
                                <i class="fas fa-shield-alt fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="security-card <?php echo (isset($dashboard_data['locked_accounts']) && count($dashboard_data['locked_accounts']) > 0) ? 'warning' : 'success'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value"><?php echo isset($dashboard_data['locked_accounts']) ? count($dashboard_data['locked_accounts']) : 0; ?></div>
                                    <div class="metric-label">Locked Accounts</div>
                                </div>
                                <i class="fas fa-lock fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="security-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value"><?php echo isset($dashboard_data['failed_logins']) ? count($dashboard_data['failed_logins']) : 0; ?></div>
                                    <div class="metric-label">Failed Login IPs</div>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="security-card success">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-value"><?php echo (isset($dashboard_data['tfa_stats']['users_with_2fa']) && isset($dashboard_data['tfa_stats']['total_users'])) ? round(($dashboard_data['tfa_stats']['users_with_2fa'] / max($dashboard_data['tfa_stats']['total_users'], 1)) * 100) : 0; ?>%</div>
                                    <div class="metric-label">2FA Adoption</div>
                                </div>
                                <i class="fas fa-mobile-alt fa-2x" style="opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Events & Failed Logins -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Security Events</h5>
                            <canvas id="securityEventsChart" height="200"></canvas>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-ban me-2"></i>Top Failed Login IPs</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Attempts</th>
                                            <th>Last Attempt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($dashboard_data['failed_logins'])): foreach (array_slice($dashboard_data['failed_logins'], 0, 8) as $login): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($login['ip_address']); ?></code></td>
                                                <td><span class="badge bg-danger"><?php echo $login['failed_attempts']; ?></span></td>
                                                <td><small><?php echo date('M j, H:i', strtotime($login['last_attempt'])); ?></small></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        <?php if (!isset($dashboard_data['failed_logins']) || empty($dashboard_data['failed_logins'])): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No failed login attempts</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Locked Accounts & Security Audit -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-user-lock me-2"></i>Locked Accounts</h5>
                            <?php if (isset($dashboard_data['locked_accounts']) && !empty($dashboard_data['locked_accounts'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Locked At</th>
                                                <th>Reason</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dashboard_data['locked_accounts'] as $account): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($account['username']); ?></td>
                                                    <td><?php echo date('M j, Y H:i', strtotime($account['locked_at'])); ?></td>
                                                    <td><small><?php echo htmlspecialchars($account['lock_reason']); ?></small></td>
                                                    <td>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="action" value="unlock_account">
                                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($account['username']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Unlock this account?')">
                                                                <i class="fas fa-unlock"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                                    <p>No locked accounts</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>Security Audit Results</h5>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded mb-3">
                                        <h4 class="text-warning"><?php echo $audit_results['weak_passwords'] ?? 0; ?></h4>
                                        <small class="text-muted">Weak Passwords</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded mb-3">
                                        <h4 class="text-info"><?php echo $audit_results['users_without_2fa'] ?? 0; ?></h4>
                                        <small class="text-muted">No 2FA</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded mb-3">
                                        <h4 class="text-danger"><?php echo $audit_results['inactive_admins'] ?? 0; ?></h4>
                                        <small class="text-muted">Inactive Admins</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded mb-3">
                                        <h4 class="text-warning"><?php echo $audit_results['suspicious_logins'] ?? 0; ?></h4>
                                        <small class="text-muted">Suspicious Logins</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Security Events -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container">
                            <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Security Events</h5>
                            <div class="row">
                                <?php if (isset($dashboard_data['recent_events'])): foreach (array_slice($dashboard_data['recent_events'], 0, 12) as $event): ?>
                                    <div class="col-lg-6 mb-2">
                                        <div class="event-item <?php echo $event['severity'] === 'high' || $event['severity'] === 'critical' ? 'danger' : ($event['severity'] === 'medium' ? 'warning' : 'success'); ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($event['event_type']); ?></h6>
                                                    <p class="mb-1 small"><?php echo htmlspecialchars($event['description']); ?></p>
                                                    <small class="text-muted">
                                                        <?php if ($event['username']): ?>
                                                            User: <?php echo htmlspecialchars($event['username']); ?> | 
                                                        <?php endif; ?>
                                                        IP: <?php echo htmlspecialchars($event['ip_address']); ?> | 
                                                        <?php echo date('M j, H:i', strtotime($event['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <span class="severity-badge severity-<?php echo $event['severity']; ?>">
                                                    <?php echo $event['severity']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                                <?php if (!isset($dashboard_data['recent_events']) || empty($dashboard_data['recent_events'])): ?>
                                    <div class="col-12">
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                                            <p>No recent security events</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
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

        // Security Events Chart
        const securityEventsData = <?php echo json_encode($dashboard_data['security_events'] ?? []); ?>;
        
        if (securityEventsData.length > 0) {
            const eventTypes = [...new Set(securityEventsData.map(item => item.event_type))];
            const eventCounts = eventTypes.map(type => {
                return securityEventsData
                    .filter(item => item.event_type === type)
                    .reduce((sum, item) => sum + parseInt(item.event_count), 0);
            });
            
            const ctx = document.getElementById('securityEventsChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: eventTypes,
                    datasets: [{
                        data: eventCounts,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 205, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)'
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
        } else {
            document.getElementById('securityEventsChart').style.display = 'none';
            document.querySelector('#securityEventsChart').parentElement.innerHTML += '<div class="text-center text-muted py-4"><i class="fas fa-info-circle fa-3x mb-3"></i><p>No security events to display</p></div>';
        }
    </script>
</body>
</html>
