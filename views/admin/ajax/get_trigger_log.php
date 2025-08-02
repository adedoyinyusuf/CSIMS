<?php
/**
 * AJAX endpoint to get trigger execution log details
 */

header('Content-Type: application/json');

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    // Allow direct access for testing
}

require_once '../../../config/auth_check.php';
require_once '../../../controllers/notification_trigger_controller.php';

try {
    $executionId = isset($_GET['execution_id']) ? (int)$_GET['execution_id'] : 0;
    
    if (!$executionId) {
        throw new Exception('Invalid execution ID');
    }
    
    $triggerController = new NotificationTriggerController();
    $logDetails = $triggerController->getExecutionLogDetails($executionId);
    
    if (!$logDetails) {
        throw new Exception('Log details not found');
    }
    
    echo json_encode([
        'success' => true,
        'log' => $logDetails
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>