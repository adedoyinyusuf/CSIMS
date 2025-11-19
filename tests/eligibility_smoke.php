<?php
// Lightweight eligibility smoke test
// Usage (web): /tests/eligibility_smoke.php?member_id=123&amount=100000&loan_type_id=1
// Usage (CLI): php tests/eligibility_smoke.php member_id=123 amount=100000 loan_type_id=1

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/config/database.php';
require_once __DIR__ . '/../includes/config/SystemConfigService.php';
require_once __DIR__ . '/../includes/services/BusinessRulesService.php';

// Parse inputs
$inputs = [];
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', $arg, 2);
            $inputs[$k] = $v;
        }
    }
}
$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : (int)($inputs['member_id'] ?? 0);
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : (float)($inputs['amount'] ?? 100000);
$loanTypeId = isset($_GET['loan_type_id']) ? (int)$_GET['loan_type_id'] : (int)($inputs['loan_type_id'] ?? 1);

$result = [
    'ok' => false,
    'error' => null,
    'data' => []
];

try {
    $db = new PdoDatabase();
    $pdo = $db->getConnection();
    $config = SystemConfigService::getInstance($pdo);
    $rules = new BusinessRulesService($pdo);

    // Derive memberId if not provided via session
    if ($memberId <= 0 && isset($_SESSION['member_id'])) {
        $memberId = (int)$_SESSION['member_id'];
    }
    if ($memberId <= 0) {
        throw new InvalidArgumentException('member_id is required for this test');
    }

    // Gather settings and checks
    $interestRate = (float)$config->get('DEFAULT_INTEREST_RATE', 12.0);
    $minMembershipMonths = (int)$config->getMinMembershipMonths();
    $hasOverdue = $rules->hasOverdueLoans($memberId);
    $eligibilityErrors = $rules->validateLoanEligibility($memberId, $amount, $loanTypeId);

    $result['ok'] = true;
    $result['data'] = [
        'member_id' => $memberId,
        'requested_amount' => $amount,
        'loan_type_id' => $loanTypeId,
        'interest_rate' => $interestRate,
        'min_membership_months' => $minMembershipMonths,
        'has_overdue_loans' => $hasOverdue,
        'eligibility_errors_count' => count($eligibilityErrors),
        'eligibility_errors' => $eligibilityErrors
    ];
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);