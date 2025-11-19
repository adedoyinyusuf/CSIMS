<?php
// Admin AJAX: Return loan statistics as JSON
header('Content-Type: application/json');

try {
    require_once '../../config/config.php';
    require_once '../../controllers/auth_controller.php';
    require_once '../../controllers/loan_controller.php';

    $auth = new AuthController();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $loanController = new LoanController();
    $stats = $loanController->getLoanStatistics();

    // Normalize/ensure keys expected by loans.php
    $response = [
        'success' => true,
        'data' => [
            'total_loans' => (int)($stats['total_loans'] ?? 0),
            'total_amount' => (float)($stats['total_amount'] ?? 0),
            'pending_count' => (int)($stats['pending_count'] ?? ($stats['pending_loans']['count'] ?? 0)),
            'approved_count' => (int)($stats['approved_count'] ?? ($stats['approved_loans']['count'] ?? 0)),
            'approved_amount' => (float)($stats['approved_amount'] ?? 0),
            'overdue_count' => (int)($stats['overdue_count'] ?? ($stats['overdue_loans']['count'] ?? 0)),
        ]
    ];

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
?>