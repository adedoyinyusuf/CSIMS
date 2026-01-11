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
$auth = $authController;
require_once __DIR__ . '/../includes/header.php';
?>

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

<!-- Main Content -->
<div class="flex-1 main-content bg-gray-50 mt-16">
    <div class="p-8">
        <!-- Page Heading -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><i class="fas fa-shield-alt mr-2 text-primary-600"></i>Security Dashboard</h1>
                <p class="text-gray-600 mt-2">Monitor system security and audit trails</p>
            </div>
            <div class="flex space-x-3">
                <select class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="periodSelector" onchange="changePeriod()">
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                </select>
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="run_audit">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <i class="fas fa-search mr-2"></i> Run Audit
                    </button>
                </form>
            </div>
        </div>

        <?php if ($session->getFlash('success')): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                <?php echo htmlspecialchars($session->getFlash('success')); ?>
            </div>
        <?php endif; ?>

        <?php if ($session->getFlash('error')): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <?php echo htmlspecialchars($session->getFlash('error')); ?>
            </div>
        <?php endif; ?>

        <!-- Security Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Security Score</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo $audit_results['security_score'] ?? 0; ?>%</p>
                    </div>
                    <div class="p-3 rounded-full <?php echo ($audit_results['security_score'] ?? 0) >= 80 ? 'bg-green-100' : (($audit_results['security_score'] ?? 0) >= 60 ? 'bg-yellow-100' : 'bg-red-100'); ?>">
                        <i class="fas fa-shield-alt text-xl <?php echo ($audit_results['security_score'] ?? 0) >= 80 ? 'text-green-600' : (($audit_results['security_score'] ?? 0) >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Locked Accounts</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo isset($dashboard_data['locked_accounts']) ? count($dashboard_data['locked_accounts']) : 0; ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-lock text-xl text-purple-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Failed Login IPs</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo isset($dashboard_data['failed_logins']) ? count($dashboard_data['failed_logins']) : 0; ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-exclamation-triangle text-xl text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">2FA Adoption</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2"><?php echo (isset($dashboard_data['tfa_stats']['users_with_2fa']) && isset($dashboard_data['tfa_stats']['total_users'])) ? round(($dashboard_data['tfa_stats']['users_with_2fa'] / max($dashboard_data['tfa_stats']['total_users'], 1)) * 100) : 0; ?>%</p>
                    </div>
                    <div class="p-3 rounded-full bg-teal-100">
                        <i class="fas fa-mobile-alt text-xl text-teal-600"></i>
                    </div>
                </div>
            </div>
        </div>

                <!-- Security Events & Failed Logins -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fas fa-chart-pie mr-2 text-primary-600"></i>Security Events</h3>
                        <canvas id="securityEventsChart" height="200"></canvas>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fas fa-ban mr-2 text-red-600"></i>Top Failed Login IPs</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Attempt</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (isset($dashboard_data['failed_logins'])): foreach (array_slice($dashboard_data['failed_logins'], 0, 8) as $login): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-sm"><code class="bg-gray-100 px-2 py-1 rounded text-gray-800"><?php echo htmlspecialchars($login['ip_address']); ?></code></td>
                                            <td class="px-4 py-3 text-sm"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><?php echo $login['failed_attempts']; ?></span></td>
                                            <td class="px-4 py-3 text-sm text-gray-500"><?php echo date('M j, H:i', strtotime($login['last_attempt'])); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    <?php if (!isset($dashboard_data['failed_logins']) || empty($dashboard_data['failed_logins'])): ?>
                                        <tr>
                                            <td colspan="3" class="px-4 py-8 text-center text-gray-500">No failed login attempts</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Locked Accounts & Security Audit -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fas fa-user-lock mr-2 text-purple-600"></i>Locked Accounts</h3>
                        <?php if (isset($dashboard_data['locked_accounts']) && !empty($dashboard_data['locked_accounts'])): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Locked At</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($dashboard_data['locked_accounts'] as $account): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($account['username']); ?></td>
                                                <td class="px-4 py-3 text-sm text-gray-500"><?php echo date('M j, Y H:i', strtotime($account['locked_at'])); ?></td>
                                                <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($account['lock_reason']); ?></td>
                                                <td class="px-4 py-3 text-sm">
                                                    <form method="post" class="inline">
                                                        <input type="hidden" name="action" value="unlock_account">
                                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($account['username']); ?>">
                                                        <button type="submit" class="text-green-600 hover:text-green-900" onclick="return confirm('Unlock this account?')">
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
                            <div class="text-center py-8">
                                <i class="fas fa-check-circle text-5xl text-green-500 mb-3"></i>
                                <p class="text-gray-500">No locked accounts</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fas fa-clipboard-check mr-2 text-blue-600"></i>Security Audit Results</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-4 border border-gray-200 rounded-lg">
                                <p class="text-3xl font-bold text-yellow-600"><?php echo $audit_results['weak_passwords'] ?? 0; ?></p>
                                <p class="text-sm text-gray-600 mt-1">Weak Passwords</p>
                            </div>
                            <div class="text-center p-4 border border-gray-200 rounded-lg">
                                <p class="text-3xl font-bold text-blue-600"><?php echo $audit_results['users_without_2fa'] ?? 0; ?></p>
                                <p class="text-sm text-gray-600 mt-1">No 2FA</p>
                            </div>
                            <div class="text-center p-4 border border-gray-200 rounded-lg">
                                <p class="text-3xl font-bold text-red-600"><?php echo $audit_results['inactive_admins'] ?? 0; ?></p>
                                <p class="text-sm text-gray-600 mt-1">Inactive Admins</p>
                            </div>
                            <div class="text-center p-4 border border-gray-200 rounded-lg">
                                <p class="text-3xl font-bold text-orange-600"><?php echo $audit_results['suspicious_logins'] ?? 0; ?></p>
                                <p class="text-sm text-gray-600 mt-1">Suspicious Logins</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Security Events -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fas fa-history mr-2 text-gray-600"></i>Recent Security Events</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <?php if (isset($dashboard_data['recent_events'])): foreach (array_slice($dashboard_data['recent_events'], 0, 12) as $event): ?>
                            <div class="border-l-4 <?php echo $event['severity'] === 'high' || $event['severity'] === 'critical' ? 'border-red-500 bg-red-50' : ($event['severity'] === 'medium' ? 'border-yellow-500 bg-yellow-50' : 'border-green-500 bg-green-50'); ?> p-4 rounded-r-lg">
                                <div class="flex justify-between items-start">
                                    <div class="flex-grow">
                                        <h4 class="font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($event['event_type']); ?></h4>
                                        <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($event['description']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php if ($event['username']): ?>
                                                User: <?php echo htmlspecialchars($event['username']); ?> | 
                                            <?php endif; ?>
                                            IP: <?php echo htmlspecialchars($event['ip_address']); ?> | 
                                            <?php echo date('M j, H:i', strtotime($event['created_at'])); ?>
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                                        <?php echo $event['severity'] === 'low' ? 'bg-green-200 text-green-800' : 
                                                   ($event['severity'] === 'medium' ? 'bg-yellow-200 text-yellow-800' : 
                                                   ($event['severity'] === 'high' ? 'bg-red-200 text-red-800' : 'bg-red-800 text-white')); ?>">
                                        <?php echo $event['severity']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                        <?php if (!isset($dashboard_data['recent_events']) || empty($dashboard_data['recent_events'])): ?>
                            <div class="col-span-2 text-center py-8">
                                <i class="fas fa-info-circle text-5xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500">No recent security events</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
