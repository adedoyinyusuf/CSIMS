<?php
/**
 * Notification Triggers Management Interface
 * 
 * Allows administrators to create, edit, and manage automated notification triggers
 */

require_once '../../config/auth_check.php';
require_once '../../controllers/notification_trigger_controller.php';
require_once '../../config/notification_config.php';

$triggerController = new NotificationTriggerController();
$config = require '../../config/notification_config.php';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'],
                    'trigger_type' => $_POST['trigger_type'],
                    'trigger_condition' => $_POST['trigger_condition'] ?? [],
                    'recipient_group' => $_POST['recipient_group'],
                    'notification_template' => $_POST['notification_template'],
                    'schedule_pattern' => $_POST['schedule_pattern'],
                    'status' => $_POST['status'] ?? 'active',
                    'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
                    'sms_enabled' => isset($_POST['sms_enabled']) ? 1 : 0,
                    'created_by' => $_SESSION['user_id']
                ];
                
                $result = $triggerController->createTrigger($data);
                if ($result) {
                    $message = 'Notification trigger created successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error creating notification trigger.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $data = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'],
                    'trigger_type' => $_POST['trigger_type'],
                    'trigger_condition' => $_POST['trigger_condition'] ?? [],
                    'recipient_group' => $_POST['recipient_group'],
                    'notification_template' => $_POST['notification_template'],
                    'schedule_pattern' => $_POST['schedule_pattern'],
                    'status' => $_POST['status'],
                    'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
                    'sms_enabled' => isset($_POST['sms_enabled']) ? 1 : 0
                ];
                
                $result = $triggerController->updateTrigger($_POST['trigger_id'], $data);
                if ($result) {
                    $message = 'Notification trigger updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating notification trigger.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $result = $triggerController->deleteTrigger($_POST['trigger_id']);
                if ($result) {
                    $message = 'Notification trigger deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error deleting notification trigger.';
                    $messageType = 'error';
                }
                break;
                
            case 'test':
                $result = $triggerController->testTrigger($_POST['trigger_id']);
                if ($result['success']) {
                    $message = 'Test completed. Total recipients: ' . $result['total_recipients'] . ', Filtered: ' . $result['filtered_recipients'];
                    $messageType = 'info';
                } else {
                    $message = 'Test failed: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'execute':
                $result = $triggerController->executeTrigger($_POST['trigger_id']);
                if ($result) {
                    $message = 'Trigger executed successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error executing trigger.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get triggers with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$triggersData = $triggerController->getAllTriggers($page, 20, $search, $status);
$triggers = $triggersData['triggers'];
$totalPages = $triggersData['total_pages'];

// Get trigger statistics
$stats = $triggerController->getTriggerStats();

// Get trigger for editing if specified
$editTrigger = null;
if (isset($_GET['edit'])) {
    $editTrigger = $triggerController->getTriggerById($_GET['edit']);
}

include '../../views/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../views/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Notification Triggers</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#triggerModal">
                        <i class="fas fa-plus"></i> Create Trigger
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $stats['total_triggers'] ?? 0; ?></h4>
                                    <p class="card-text">Total Triggers</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-cogs fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $stats['active_triggers'] ?? 0; ?></h4>
                                    <p class="card-text">Active Triggers</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-play fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $stats['due_triggers'] ?? 0; ?></h4>
                                    <p class="card-text">Due Now</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $stats['executions_today'] ?? 0; ?></h4>
                                    <p class="card-text">Executions Today</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-paper-plane fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name or description">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="paused" <?php echo $status === 'paused' ? 'selected' : ''; ?>>Paused</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="notification_triggers.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Triggers Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Notification Triggers</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($triggers)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>No triggers found</h5>
                            <p class="text-muted">Create your first notification trigger to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Recipients</th>
                                        <th>Schedule</th>
                                        <th>Next Run</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($triggers as $trigger): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($trigger['name']); ?></strong>
                                                <?php if ($trigger['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($trigger['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo ucfirst($trigger['trigger_type']); ?></span>
                                                <br>
                                                <?php if ($trigger['email_enabled']): ?>
                                                    <i class="fas fa-envelope text-primary" title="Email enabled"></i>
                                                <?php endif; ?>
                                                <?php if ($trigger['sms_enabled']): ?>
                                                    <i class="fas fa-sms text-success" title="SMS enabled"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($trigger['recipient_group']); ?></td>
                                            <td>
                                                <?php 
                                                $schedule = json_decode($trigger['schedule_pattern'], true);
                                                echo ucfirst($schedule['type'] ?? 'Unknown');
                                                if (isset($schedule['time'])) {
                                                    echo ' at ' . $schedule['time'];
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $nextRun = new DateTime($trigger['next_run']);
                                                $now = new DateTime();
                                                if ($nextRun <= $now && $trigger['status'] === 'active') {
                                                    echo '<span class="badge bg-warning">Due Now</span>';
                                                } else {
                                                    echo $nextRun->format('M j, Y g:i A');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusClass = [
                                                    'active' => 'success',
                                                    'inactive' => 'secondary',
                                                    'paused' => 'warning',
                                                    'due' => 'danger'
                                                ];
                                                $currentStatus = $trigger['current_status'] ?? $trigger['status'];
                                                $class = $statusClass[$currentStatus] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>"><?php echo ucfirst($currentStatus); ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="?edit=<?php echo $trigger['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Test this trigger?');">
                                                        <input type="hidden" name="action" value="test">
                                                        <input type="hidden" name="trigger_id" value="<?php echo $trigger['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-info" title="Test">
                                                            <i class="fas fa-vial"></i>
                                                        </button>
                                                    </form>
                                                    <?php if ($trigger['current_status'] === 'due'): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Execute this trigger now?');">
                                                            <input type="hidden" name="action" value="execute">
                                                            <input type="hidden" name="trigger_id" value="<?php echo $trigger['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Execute Now">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <a href="notification_trigger_history.php?id=<?php echo $trigger['id']; ?>" class="btn btn-sm btn-outline-secondary" title="History">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this trigger?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="trigger_id" value="<?php echo $trigger['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Triggers pagination">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">
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

<!-- Create/Edit Trigger Modal -->
<div class="modal fade" id="triggerModal" tabindex="-1" aria-labelledby="triggerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="triggerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="triggerModalLabel">
                        <?php echo $editTrigger ? 'Edit' : 'Create'; ?> Notification Trigger
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editTrigger ? 'update' : 'create'; ?>">
                    <?php if ($editTrigger): ?>
                        <input type="hidden" name="trigger_id" value="<?php echo $editTrigger['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $editTrigger ? htmlspecialchars($editTrigger['name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="trigger_type" class="form-label">Trigger Type *</label>
                                <select class="form-select" id="trigger_type" name="trigger_type" required>
                                    <option value="">Select Type</option>
                                    <option value="membership_expiry" <?php echo ($editTrigger && $editTrigger['trigger_type'] === 'membership_expiry') ? 'selected' : ''; ?>>Membership Expiry</option>
                                    <option value="payment_overdue" <?php echo ($editTrigger && $editTrigger['trigger_type'] === 'payment_overdue') ? 'selected' : ''; ?>>Payment Overdue</option>
                                    <option value="welcome" <?php echo ($editTrigger && $editTrigger['trigger_type'] === 'welcome') ? 'selected' : ''; ?>>Welcome Message</option>
                                    <option value="birthday" <?php echo ($editTrigger && $editTrigger['trigger_type'] === 'birthday') ? 'selected' : ''; ?>>Birthday Wishes</option>
                                    <option value="custom" <?php echo ($editTrigger && $editTrigger['trigger_type'] === 'custom') ? 'selected' : ''; ?>>Custom</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?php echo $editTrigger ? htmlspecialchars($editTrigger['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recipient_group" class="form-label">Recipient Group *</label>
                                <select class="form-select" id="recipient_group" name="recipient_group" required>
                                    <option value="">Select Group</option>
                                    <?php foreach ($config['recipients'] as $key => $group): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($editTrigger && $editTrigger['recipient_group'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="notification_template" class="form-label">Template *</label>
                                <select class="form-select" id="notification_template" name="notification_template" required>
                                    <option value="">Select Template</option>
                                    <?php foreach ($config['templates'] as $key => $template): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($editTrigger && $editTrigger['notification_template'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($key); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Schedule Pattern *</label>
                        <div class="row">
                            <div class="col-md-4">
                                <select class="form-select" id="schedule_type" name="schedule_pattern[type]" required>
                                    <option value="">Select Schedule</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="schedule_day" style="display: none;">
                                <select class="form-select" name="schedule_pattern[day_of_week]">
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                    <option value="7">Sunday</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="schedule_date" style="display: none;">
                                <input type="number" class="form-control" name="schedule_pattern[day_of_month]" min="1" max="31" placeholder="Day of month">
                            </div>
                            <div class="col-md-4">
                                <input type="time" class="form-control" name="schedule_pattern[time]" value="09:00">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (!$editTrigger || $editTrigger['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($editTrigger && $editTrigger['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="paused" <?php echo ($editTrigger && $editTrigger['status'] === 'paused') ? 'selected' : ''; ?>>Paused</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" 
                                           <?php echo (!$editTrigger || $editTrigger['email_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_enabled">
                                        Enable Email
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sms_enabled" name="sms_enabled" 
                                           <?php echo ($editTrigger && $editTrigger['sms_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_enabled">
                                        Enable SMS
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editTrigger ? 'Update' : 'Create'; ?> Trigger
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show modal if editing
<?php if ($editTrigger): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('triggerModal'));
        modal.show();
    });
<?php endif; ?>

// Handle schedule type changes
document.getElementById('schedule_type').addEventListener('change', function() {
    var scheduleDay = document.getElementById('schedule_day');
    var scheduleDate = document.getElementById('schedule_date');
    
    scheduleDay.style.display = 'none';
    scheduleDate.style.display = 'none';
    
    if (this.value === 'weekly') {
        scheduleDay.style.display = 'block';
    } else if (this.value === 'monthly') {
        scheduleDate.style.display = 'block';
    }
});

// Trigger the change event on page load to set initial state
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('schedule_type').dispatchEvent(new Event('change'));
});
</script>

<?php include '../../views/includes/footer.php'; ?>
