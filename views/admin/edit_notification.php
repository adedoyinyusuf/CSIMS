<?php
session_start();
require_once '../config/auth_check.php';
require_once '../../controllers/notification_controller.php';

// Initialize notification controller
$notificationController = new NotificationController();

// Get notification ID from URL
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$notification_id) {
    $_SESSION['flash_message'] = 'Invalid notification ID.';
    $_SESSION['flash_type'] = 'error';
    header('Location: notifications.php');
    exit;
}

// Get notification details
$notification = $notificationController->getNotificationById($notification_id);

if (!$notification) {
    $_SESSION['flash_message'] = 'Notification not found.';
    $_SESSION['flash_type'] = 'error';
    header('Location: notifications.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $recipient_type = $_POST['recipient_type'];
    $recipient_id = $_POST['recipient_id'] ?? null;
    $notification_type = $_POST['notification_type'];
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required.';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required.';
    }
    
    if (empty($notification_type)) {
        $errors[] = 'Notification type is required.';
    }
    
    if (empty($recipient_type)) {
        $errors[] = 'Recipient type is required.';
    }
    
    if ($recipient_type !== 'All' && empty($recipient_id)) {
        $errors[] = 'Recipient ID is required for specific recipients.';
    }
    
    if (empty($errors)) {
        $data = [
            'title' => $title,
            'message' => $message,
            'recipient_type' => $recipient_type,
            'recipient_id' => $recipient_id,
            'notification_type' => $notification_type
        ];
        
        $result = $notificationController->updateNotification($notification_id, $data);
        
        if ($result) {
            $_SESSION['flash_message'] = 'Notification updated successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: view_notification.php?id=' . $notification_id);
            exit;
        } else {
            $errors[] = 'Failed to update notification. Please try again.';
        }
    }
    
    // If there are errors, update the notification array with posted values
    if (!empty($errors)) {
        $notification['title'] = $title;
        $notification['message'] = $message;
        $notification['recipient_type'] = $recipient_type;
        $notification['recipient_id'] = $recipient_id;
        $notification['notification_type'] = $notification_type;
    }
}

// Get data for form dropdowns
$notification_types = $notificationController->getNotificationTypes();
$recipient_types = $notificationController->getRecipientTypes();
$members = $notificationController->getMembers();
$admins = $notificationController->getAdmins();

$page_title = 'Edit Notification';
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Notification</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="view_notification.php?id=<?php echo $notification_id; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to View
                        </a>
                        <a href="notifications.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list"></i> All Notifications
                        </a>
                    </div>
                </div>
            </div>

            <?php require_once '../includes/flash_messages.php'; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Edit Notification Details</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="notificationForm">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($notification['title']); ?>" 
                                           placeholder="Enter notification title" required>
                                </div>

                                <div class="mb-3">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="5" 
                                              placeholder="Enter notification message" required><?php echo htmlspecialchars($notification['message']); ?></textarea>
                                    <div class="form-text">You can use basic HTML tags for formatting.</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="notification_type" class="form-label">Notification Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="notification_type" name="notification_type" required>
                                                <option value="">Select Type</option>
                                                <?php foreach ($notification_types as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" 
                                                            <?php echo ($notification['notification_type'] === $key) ? 'selected' : ''; ?>>
                                                        <?php echo $value; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="recipient_type" class="form-label">Recipient Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="recipient_type" name="recipient_type" required onchange="toggleRecipientId()">
                                                <option value="">Select Recipient Type</option>
                                                <?php foreach ($recipient_types as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" 
                                                            <?php echo ($notification['recipient_type'] === $key) ? 'selected' : ''; ?>>
                                                        <?php echo $value; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3" id="recipient_id_group" style="display: none;">
                                    <label for="recipient_id" class="form-label">Select Recipient</label>
                                    <select class="form-select" id="recipient_id" name="recipient_id">
                                        <option value="">Select Recipient</option>
                                    </select>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="view_notification.php?id=<?php echo $notification_id; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Notification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Current Notification Info</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td><?php echo $notification['notification_id']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php if ($notification['is_read']): ?>
                                            <span class="badge bg-success">Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Created By:</strong></td>
                                    <td><?php echo htmlspecialchars($notification['created_by_name'] ?? 'System'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Editing Guidelines</h6>
                        </div>
                        <div class="card-body">
                            <h6>Important Notes:</h6>
                            <ul class="small">
                                <li>Changes will be saved immediately</li>
                                <li>Recipients who already read this notification will see the updated version</li>
                                <li>The creation date and author cannot be changed</li>
                                <li>Consider the impact on users who may have already acted on the original message</li>
                            </ul>

                            <h6 class="mt-3">Best Practices:</h6>
                            <ul class="small">
                                <li>Add an "Updated" note if making significant changes</li>
                                <li>Keep the original intent of the notification</li>
                                <li>Test recipient selection carefully</li>
                                <li>Review the message for clarity and accuracy</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Preview</h6>
                        </div>
                        <div class="card-body">
                            <div id="notification_preview">
                                <div class="alert alert-info">
                                    <h6 id="preview_title"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                    <p id="preview_message" class="mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> Updated just now
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Store member and admin data for JavaScript
const members = <?php echo json_encode($members); ?>;
const admins = <?php echo json_encode($admins); ?>;
const currentRecipientType = '<?php echo $notification['recipient_type']; ?>';
const currentRecipientId = '<?php echo $notification['recipient_id']; ?>';

function toggleRecipientId() {
    const recipientType = document.getElementById('recipient_type').value;
    const recipientIdGroup = document.getElementById('recipient_id_group');
    const recipientIdSelect = document.getElementById('recipient_id');
    
    if (recipientType === 'All') {
        recipientIdGroup.style.display = 'none';
        recipientIdSelect.required = false;
        recipientIdSelect.innerHTML = '<option value="">Select Recipient</option>';
    } else {
        recipientIdGroup.style.display = 'block';
        recipientIdSelect.required = true;
        
        // Clear existing options
        recipientIdSelect.innerHTML = '<option value="">Select Recipient</option>';
        
        if (recipientType === 'Member') {
            members.forEach(member => {
                const option = document.createElement('option');
                option.value = member.member_id;
                option.textContent = member.name;
                if (currentRecipientType === 'Member' && currentRecipientId == member.member_id) {
                    option.selected = true;
                }
                recipientIdSelect.appendChild(option);
            });
        } else if (recipientType === 'Admin') {
            admins.forEach(admin => {
                const option = document.createElement('option');
                option.value = admin.admin_id;
                option.textContent = admin.name;
                if (currentRecipientType === 'Admin' && currentRecipientId == admin.admin_id) {
                    option.selected = true;
                }
                recipientIdSelect.appendChild(option);
            });
        }
    }
}

// Live preview functionality
function updatePreview() {
    const title = document.getElementById('title').value || 'Notification Title';
    const message = document.getElementById('message').value || 'Notification message will appear here...';
    
    document.getElementById('preview_title').textContent = title;
    document.getElementById('preview_message').textContent = message;
}

// Add event listeners for live preview
document.getElementById('title').addEventListener('input', updatePreview);
document.getElementById('message').addEventListener('input', updatePreview);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleRecipientId();
    updatePreview();
});

// Form validation
document.getElementById('notificationForm').addEventListener('submit', function(e) {
    const recipientType = document.getElementById('recipient_type').value;
    const recipientId = document.getElementById('recipient_id').value;
    
    if (recipientType !== 'All' && !recipientId) {
        e.preventDefault();
        alert('Please select a recipient.');
        return false;
    }
    
    // Confirm update
    if (!confirm('Are you sure you want to update this notification?')) {
        e.preventDefault();
        return false;
    }
});
</script>

<style>
.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

#notification_preview .alert {
    margin-bottom: 0;
}

#notification_preview h6 {
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.form-text {
    font-size: 0.875em;
    color: #6c757d;
}

.text-danger {
    color: #dc3545 !important;
}

.table-borderless td {
    border: none;
    padding: 0.25rem 0;
}

.badge {
    font-size: 0.875em;
}
</style>
