<?php
/**
 * Admin - Loan Approvals / Business Rule Alerts
 * 
 * Displays loans flagged by business rules for review.
 */
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/loan_controller.php';
require_once '../../includes/services/SimpleBusinessRulesService.php';

$auth = new AuthController();
// Lightweight auth diagnostics (trigger with ?auth_debug=1)
$__debug_auth = isset($_GET['auth_debug']) || isset($_GET['debug']);
$__diag = [
  'isLoggedIn' => (bool)$auth->isLoggedIn(),
  'session_id' => $_SESSION['session_id'] ?? null,
  'admin_id' => $_SESSION['admin_id'] ?? null,
  'user_type' => $_SESSION['user_type'] ?? null,
  'role' => $_SESSION['role'] ?? null,
  'php_session_id' => session_id(),
  'request_host' => $_SERVER['HTTP_HOST'] ?? null,
  'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
];
if ($__debug_auth) {
  header('Content-Type: text/plain');
  echo "loan_approvals auth diagnostics\n\n";
  foreach ($__diag as $k => $v) {
    if (is_array($v)) { $v = json_encode($v); }
    echo $k . ': ' . (isset($v) ? (is_string($v) ? $v : (string)$v) : 'null') . "\n";
  }
  exit();
}
// Robust admin auth check: accept legacy admin session as well
if (!$auth->isLoggedIn() && !isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = 'Please login to access loan approvals';
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit();
}

$current_user = $auth->getCurrentUser();
$loanController = new LoanController();
$businessRulesService = new SimpleBusinessRulesService();

// Fetch loan alerts flagged by business rules
$loan_alerts = [];
try {
    $loan_alerts = $businessRulesService->getLoanAlerts();
} catch (Exception $e) {
    $loan_alerts = [];
}

// Hydrate alert items with basic loan details (best-effort)
$alert_items = [];
if (!empty($loan_alerts)) {
    foreach ($loan_alerts as $alert) {
      $loanId = (int)($alert['loan_id'] ?? ($alert['loanId'] ?? 0));
      $reason = $alert['reason'] ?? ($alert['message'] ?? 'Requires review');
      $loan = false;
      if ($loanId > 0) {
        try { $loan = $loanController->getLoanById($loanId); } catch (Exception $e) { $loan = false; }
      }
      $memberName = '';
      if ($loan && isset($loan['first_name'], $loan['last_name'])) {
        $memberName = $loan['first_name'] . ' ' . $loan['last_name'];
      }
      $amount = $loan['amount'] ?? ($loan['principal_amount'] ?? null);
      $status = $loan['status'] ?? '';
      $alert_items[] = [
        'loan_id' => $loanId,
        'member_name' => $memberName,
        'amount' => $amount,
        'status' => $status,
        'reason' => $reason,
      ];
    }
}

$pageTitle = "Loan Approvals";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
  <link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-admin">
  <?php include '../../views/includes/header.php'; ?>
  <div class="flex">
    <?php include '../../views/includes/sidebar.php'; ?>
    <main class="flex-1 md:ml-64 mt-16 p-6">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-clipboard-check mr-3" style="color: #07beb8;"></i>
            Loan Approvals / Alerts
          </h1>
          <p style="color: var(--text-muted);">Review loans flagged by business rules and take action</p>
        </div>
        <div>
          <a href="<?php echo BASE_URL; ?>/admin/workflow_approvals.php" class="btn btn-standard btn-outline">
            <i class="fas fa-project-diagram mr-2"></i>Open Workflow Approvals
          </a>
        </div>
      </div>

      <?php if (empty($alert_items)): ?>
        <div class="alert alert-info">
          <i class="fas fa-info-circle mr-2"></i>
          No loans currently require attention according to business rules.
        </div>
      <?php else: ?>
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center">
              <i class="fas fa-exclamation-triangle mr-3 icon-warning"></i>
              <div>
                <strong><?php echo count($alert_items); ?> loan(s)</strong> require review
              </div>
            </div>
            <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="text-sm underline">Back to Loans</a>
          </div>
          <div class="p-6">
            <div class="table-responsive">
              <table class="table table-hover w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loan ID</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php foreach ($alert_items as $item): ?>
                    <tr>
                      <td class="px-3 py-3">#<?php echo htmlspecialchars($item['loan_id']); ?></td>
                      <td class="px-3 py-3"><?php echo htmlspecialchars($item['member_name'] ?: '—'); ?></td>
                      <td class="px-3 py-3"><?php echo isset($item['amount']) ? '₦' . number_format((float)$item['amount'], 2) : '—'; ?></td>
                      <td class="px-3 py-3">
                        <?php if (!empty($item['status'])): ?>
                          <span class="badge badge-warning"><?php echo htmlspecialchars(ucfirst($item['status'])); ?></span>
                        <?php else: ?>
                          <span class="badge">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-3 py-3 text-sm" style="color: var(--text-muted);">
                        <?php echo htmlspecialchars($item['reason']); ?>
                      </td>
                      <td class="px-3 py-3 text-center">
                        <?php if (!empty($item['loan_id'])): ?>
                          <a href="<?php echo BASE_URL; ?>/views/admin/view_loan.php?id=<?php echo (int)$item['loan_id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye mr-1"></i>View
                          </a>
                          <a href="<?php echo BASE_URL; ?>/views/admin/edit_loan.php?id=<?php echo (int)$item['loan_id']; ?>" class="btn btn-sm btn-outline-secondary ml-2">
                            <i class="fas fa-edit mr-1"></i>Edit
                          </a>
                        <?php else: ?>
                          <span class="text-xs text-gray-500">No loan reference</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>