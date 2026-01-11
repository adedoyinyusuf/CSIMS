<?php
require_once '../../config/config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/message_controller.php';
require_once '../../controllers/member_controller.php';

$auth = new AuthController();
$current_user = $auth->getCurrentUser();

if (!$current_user) {
    header('Location: ../auth/login.php');
    exit();
}

$messageController = new MessageController();
$memberController = new MemberController();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $recipient_type = $_POST['recipient_type'] ?? '';
    $recipient_id = $_POST['recipient_id'] ?? '';
    
    $errors = [];
    
    if (empty($subject)) {
        $errors[] = 'Subject is required.';
    }
    
    if (empty($message)) {
        $errors[] = 'Message content is required.';
    }
    
    if (empty($recipient_type) || empty($recipient_id)) {
        $errors[] = 'Please select a recipient.';
    }
    
    if (empty($errors)) {
        $data = [
            'sender_type' => 'Admin',
            'sender_id' => $current_user['admin_id'],
            'recipient_type' => $recipient_type,
            'recipient_id' => (int)$recipient_id,
            'subject' => $subject,
            'message' => $message
        ];
        
        $result = $messageController->createMessage($data);
        
        if ($result) {
            $_SESSION['success_message'] = 'Message sent successfully!';
            header('Location: messages.php');
            exit();
        } else {
            $errors[] = 'Failed to send message. Please try again.';
        }
    }
}

// Get all members for recipient selection
$members = $memberController->getAllActiveMembers();
$admins = $messageController->getAdmins();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Message - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Compose Message</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="messages.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Messages
                        </a>
                    </div>
                </div>

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
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">New Message</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="recipient_type" class="form-label">Recipient Type</label>
                                        <select class="form-select" id="recipient_type" name="recipient_type" required>
                                            <option value="">Select recipient type...</option>
                                            <option value="Member" <?php echo (isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'Member') ? 'selected' : ''; ?>>Member</option>
                                            <option value="Admin" <?php echo (isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="recipient_id" class="form-label">Recipient</label>
                                        <select class="form-select" id="recipient_id" name="recipient_id" required>
                                            <option value="">Select recipient...</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="subject" name="subject" 
                                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="8" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="messages.php" class="btn btn-secondary me-md-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Message
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Quick Tips</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-lightbulb text-warning me-2"></i>
                                        Keep your subject line clear and descriptive
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-users text-info me-2"></i>
                                        Select the appropriate recipient type first
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-spell-check text-success me-2"></i>
                                        Review your message before sending
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-clock text-primary me-2"></i>
                                        Recipients will be notified immediately
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dynamic recipient loading
        const recipientType = document.getElementById('recipient_type');
        const recipientId = document.getElementById('recipient_id');
        
        const members = <?php echo json_encode($members); ?>;
        const admins = <?php echo json_encode($admins); ?>;
        
        recipientType.addEventListener('change', function() {
            const selectedType = this.value;
            recipientId.innerHTML = '<option value="">Select recipient...</option>';
            
            if (selectedType === 'Member') {
                members.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.member_id;
                    option.textContent = `${member.first_name} ${member.last_name} (${member.ippis_no})`;
                    recipientId.appendChild(option);
                });
            } else if (selectedType === 'Admin') {
                admins.forEach(admin => {
                    const option = document.createElement('option');
                    option.value = admin.admin_id;
                    const displayName = admin.name ?? `${admin.first_name ?? ''} ${admin.last_name ?? ''}`.trim();
                    const usernameSuffix = admin.username ? ` (${admin.username})` : '';
                    option.textContent = `${displayName}${usernameSuffix}`;
                    recipientId.appendChild(option);
                });
            }
        });
        
        // Restore selected values if form was submitted with errors
        <?php if (isset($_POST['recipient_type']) && isset($_POST['recipient_id'])): ?>
            recipientType.dispatchEvent(new Event('change'));
            setTimeout(() => {
                recipientId.value = '<?php echo $_POST['recipient_id']; ?>';
            }, 100);
        <?php endif; ?>
    </script>
</body>
</html>