<?php
/**
 * Notification Trigger History
 * 
 * View execution history and logs for notification triggers
 */

require_once '../../config/auth_check.php';
require_once '../../controllers/notification_trigger_controller.php';

$triggerController = new NotificationTriggerController();

// Get trigger ID from URL
$triggerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$triggerId) {
    header('Location: notification_triggers.php');
    exit;
}

// Get trigger details
$trigger = $triggerController->getTriggerById($triggerId);
if (!$trigger) {
    header('Location: notification_triggers.php');
    exit;
}

// Get execution history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$historyData = $triggerController->getTriggerHistory($triggerId, $page, 20, $status, $dateFrom, $dateTo);
$history = $historyData['history'];
$totalPages = $historyData['total_pages'];

// Get trigger statistics
$stats = $triggerController->getTriggerExecutionStats($triggerId);

include '../../views/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <a href="notification_triggers.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    Trigger History: <?php echo htmlspecialchars($trigger['name']); ?>
                </h1>
            </div>
            
            <!-- Trigger Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Type:</strong> <?php echo ucfirst($trigger['trigger_type']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?php echo $trigger['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($trigger['status']); ?>
                            </span>
                        </div>
                        <div class="col-md-3">
                            <strong>Recipients:</strong> <?php echo htmlspecialchars($trigger['recipient_group']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Next Run:</strong> 
                            <?php 
                            $nextRun = new DateTime($trigger['next_run']);
                            echo $nextRun->format('M j, Y g:i A');
                            ?>
                        </div>
                    </div>
                    <?php if ($trigger['description']): ?>
                        <div class="row mt-2">
                            <div class="col-12">
                                <strong>Description:</strong> <?php echo htmlspecialchars($trigger['description']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h4><?php echo $stats['total_executions'] ?? 0; ?></h4>
                            <p class="mb-0">Total Executions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h4><?php echo $stats['successful_executions'] ?? 0; ?></h4>
                            <p class="mb-0">Successful</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h4><?php echo $stats['failed_executions'] ?? 0; ?></h4>
                            <p class="mb-0">Failed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h4><?php echo $stats['total_recipients'] ?? 0; ?></h4>
                            <p class="mb-0">Total Recipients</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?php echo $triggerId; ?>">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                                <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="partial" <?php echo $status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="?id=<?php echo $triggerId; ?>" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- History Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Execution History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($history)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5>No execution history found</h5>
                            <p class="text-muted">This trigger hasn't been executed yet or no records match your filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Execution Time</th>
                                        <th>Status</th>
                                        <th>Recipients</th>
                                        <th>Sent</th>
                                        <th>Failed</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $execution): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $executionTime = new DateTime($execution['executed_at']);
                                                echo $executionTime->format('M j, Y g:i:s A');
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusClass = [
                                                    'success' => 'success',
                                                    'failed' => 'danger',
                                                    'partial' => 'warning'
                                                ];
                                                $class = $statusClass[$execution['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>">
                                                    <?php echo ucfirst($execution['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($execution['recipients_count']); ?></td>
                                            <td>
                                                <span class="text-success"><?php echo number_format($execution['sent_count']); ?></span>
                                                <?php if ($execution['email_sent'] > 0): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-envelope"></i> <?php echo number_format($execution['email_sent']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if ($execution['sms_sent'] > 0): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-sms"></i> <?php echo number_format($execution['sms_sent']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($execution['failed_count'] > 0): ?>
                                                    <span class="text-danger"><?php echo number_format($execution['failed_count']); ?></span>
                                                    <?php if ($execution['email_failed'] > 0): ?>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-envelope"></i> <?php echo number_format($execution['email_failed']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if ($execution['sms_failed'] > 0): ?>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-sms"></i> <?php echo number_format($execution['sms_failed']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($execution['execution_time']) {
                                                    echo number_format($execution['execution_time'], 2) . 's';
                                                } else {
                                                    echo '<span class="text-muted">-</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#logModal" 
                                                        data-execution-id="<?php echo $execution['id']; ?>"
                                                        data-execution-time="<?php echo $executionTime->format('M j, Y g:i:s A'); ?>">
                                                    <i class="fas fa-eye"></i> View Log
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="History pagination">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?id=<?php echo $triggerId; ?>&page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logModalLabel">Execution Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="logContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handle log modal
document.getElementById('logModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var executionId = button.getAttribute('data-execution-id');
    var executionTime = button.getAttribute('data-execution-time');
    
    var modalTitle = this.querySelector('.modal-title');
    var logContent = this.querySelector('#logContent');
    
    modalTitle.textContent = 'Execution Log - ' + executionTime;
    
    // Show loading spinner
    logContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    // Fetch log details
    fetch('ajax/get_trigger_log.php?execution_id=' + executionId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var html = '<div class="row mb-3">';
                html += '<div class="col-md-6"><strong>Status:</strong> <span class="badge bg-' + (data.log.status === 'success' ? 'success' : 'danger') + '">' + data.log.status + '</span></div>';
                html += '<div class="col-md-6"><strong>Duration:</strong> ' + data.log.execution_time + 's</div>';
                html += '</div>';
                
                html += '<div class="row mb-3">';
                html += '<div class="col-md-3"><strong>Recipients:</strong> ' + data.log.recipients_count + '</div>';
                html += '<div class="col-md-3"><strong>Sent:</strong> ' + data.log.sent_count + '</div>';
                html += '<div class="col-md-3"><strong>Failed:</strong> ' + data.log.failed_count + '</div>';
                html += '<div class="col-md-3"><strong>Skipped:</strong> ' + (data.log.recipients_count - data.log.sent_count - data.log.failed_count) + '</div>';
                html += '</div>';
                
                if (data.log.error_message) {
                    html += '<div class="alert alert-danger"><strong>Error:</strong> ' + data.log.error_message + '</div>';
                }
                
                if (data.log.log_data) {
                    html += '<h6>Execution Log:</h6>';
                    html += '<pre class="bg-light p-3" style="max-height: 300px; overflow-y: auto;">' + data.log.log_data + '</pre>';
                }
                
                logContent.innerHTML = html;
            } else {
                logContent.innerHTML = '<div class="alert alert-danger">Error loading log details: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            logContent.innerHTML = '<div class="alert alert-danger">Error loading log details: ' + error.message + '</div>';
        });
});
</script>

<?php include '../../views/includes/footer.php'; ?>
