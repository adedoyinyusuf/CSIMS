<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/notification_controller.php';
require_once '../../includes/session.php';
$session = Session::getInstance();
// Check if user is logged in
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

// Initialize notification controller
$notificationController = new NotificationController();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $recipient_type = $_POST['recipient_type'];
    $recipient_id = $_POST['recipient_id'] ?? null;
    $notification_type = $_POST['notification_type'];
    $created_by = $_SESSION['admin_id'];
    
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
            'notification_type' => $notification_type,
            'created_by' => $created_by
        ];
        
        $notification_id = $notificationController->createNotification($data);
        
        if ($notification_id) {
            $session->setFlash('success', 'Notification created successfully.');
            header('Location: notifications.php');
            exit;
        } else {
            $errors[] = 'Failed to create notification. Please try again.';
        }
    }
}

// Get data for form dropdowns
$notification_types = $notificationController->getNotificationTypes();
$recipient_types = $notificationController->getRecipientTypes();
$members = $notificationController->getMembers();
$admins = $notificationController->getAdmins();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Notification - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    
</head>
<body>
    <!-- Include Header/Navbar -->
    <?php include '../../views/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content mt-16">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Create New Notification</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="notifications.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Notifications
                        </a>
                    </div>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if ($session->hasFlash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $session->getFlash('success'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($session->hasFlash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $session->getFlash('error'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

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
                            <h6 class="m-0 font-weight-bold text-primary">Notification Details</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="notificationForm">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                           placeholder="Enter notification title" required>
                                </div>

                                <div class="mb-3">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="5" 
                                              placeholder="Enter notification message" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
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
                                                            <?php echo (($_POST['notification_type'] ?? '') === $key) ? 'selected' : ''; ?>>
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
                                                            <?php echo (($_POST['recipient_type'] ?? '') === $key) ? 'selected' : ''; ?>>
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
                                    <a href="notifications.php" class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send Notification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Notification Guidelines</h6>
                        </div>
                        <div class="card-body">
                            <h6>Notification Types:</h6>
                            <ul class="small">
                                <li><strong>Payment:</strong> Payment reminders, confirmations</li>
                                <li><strong>Meeting:</strong> Meeting announcements, schedules</li>
                                <li><strong>Policy:</strong> Policy updates, rule changes</li>
                                <li><strong>General:</strong> General information, announcements</li>
                            </ul>

                            <h6 class="mt-3">Recipient Types:</h6>
                            <ul class="small">
                                <li><strong>All Users:</strong> Broadcast to everyone</li>
                                <li><strong>Specific Member:</strong> Send to one member</li>
                                <li><strong>Specific Admin:</strong> Send to one admin</li>
                            </ul>

                            <h6 class="mt-3">Tips:</h6>
                            <ul class="small">
                                <li>Keep titles concise and descriptive</li>
                                <li>Use clear, professional language</li>
                                <li>Include relevant dates and deadlines</li>
                                <li>Double-check recipient selection</li>
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
                                    <h6 id="preview_title">Notification Title</h6>
                                    <p id="preview_message" class="mb-0">Notification message will appear here...</p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> Just now
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>

<script>
// Store member and admin data for JavaScript
const members = <?php echo json_encode($members); ?>;
const admins = <?php echo json_encode($admins); ?>;

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
                recipientIdSelect.appendChild(option);
            });
        } else if (recipientType === 'Admin') {
            admins.forEach(admin => {
                const option = document.createElement('option');
                option.value = admin.admin_id;
                option.textContent = admin.name;
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
</style>

</body>
</html>
