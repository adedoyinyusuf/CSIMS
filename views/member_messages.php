<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/message_controller.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$messageController = new MessageController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $errors = [];
    $success = false;
    
    if ($action === 'send_message') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // Validation
        if (empty($subject)) {
            $errors[] = "Subject is required";
        }
        if (empty($message)) {
            $errors[] = "Message is required";
        }
        
        if (empty($errors)) {
            $messageData = [
                'sender_type' => 'Member',
                'sender_id' => $member_id,
                'recipient_type' => 'Admin',
                'recipient_id' => 1, // Send to primary admin
                'subject' => $subject,
                'message' => $message
            ];
            
            if ($messageController->createMessage($messageData)) {
                $success = true;
                $successMessage = "Message sent successfully to administration";
            } else {
                $errors[] = "Failed to send message";
            }
        }
    } elseif ($action === 'mark_read') {
        $message_id = $_POST['message_id'] ?? 0;
        $messageController->markAsRead($message_id);
    }
}

// Get member's messages (both sent and received)
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

// Get messages where member is sender or recipient
$sql = "SELECT m.*, 
        CASE 
            WHEN m.sender_type = 'Admin' THEN CONCAT(sa.first_name, ' ', sa.last_name)
            WHEN m.sender_type = 'Member' THEN CONCAT(sm.first_name, ' ', sm.last_name)
        END as sender_name,
        CASE 
            WHEN m.recipient_type = 'Admin' THEN CONCAT(ra.first_name, ' ', ra.last_name)
            WHEN m.recipient_type = 'Member' THEN CONCAT(rm.first_name, ' ', rm.last_name)
        END as recipient_name,
        CASE 
            WHEN m.sender_type = 'Member' AND m.sender_id = ? THEN 'sent'
            WHEN m.recipient_type = 'Member' AND m.recipient_id = ? THEN 'received'
        END as message_direction
        FROM messages m 
        LEFT JOIN admins sa ON m.sender_type = 'Admin' AND m.sender_id = sa.admin_id
        LEFT JOIN members sm ON m.sender_type = 'Member' AND m.sender_id = sm.member_id
        LEFT JOIN admins ra ON m.recipient_type = 'Admin' AND m.recipient_id = ra.admin_id
        LEFT JOIN members rm ON m.recipient_type = 'Member' AND m.recipient_id = rm.member_id
        WHERE (m.sender_type = 'Member' AND m.sender_id = ?) 
           OR (m.recipient_type = 'Member' AND m.recipient_id = ?)
        ORDER BY m.created_at DESC
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $member_id, $member_id, $member_id, $member_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unread count
$unread_sql = "SELECT COUNT(*) as unread_count FROM messages 
               WHERE recipient_type = 'Member' AND recipient_id = ? AND is_read = 0";
$stmt = $conn->prepare($unread_sql);
$stmt->bind_param('i', $member_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - NPC CTLStaff Loan Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .message-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.15);
        }
        .message-card.sent {
            border-left-color: #28a745;
        }
        .message-card.received {
            border-left-color: #007bff;
        }
        .message-card.unread {
            background-color: #f8f9ff;
            border-left-color: #ffc107;
        }
        .message-direction {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .direction-sent { background-color: #d4edda; color: #155724; }
        .direction-received { background-color: #cce7ff; color: #004085; }
        .compose-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        .compose-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-university"></i> Member Portal
                    </h4>
                    
                    <div class="mb-3">
                        <small class="text-white-50">Welcome,</small>
                        <div class="text-white fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="member_dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="member_profile.php">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a class="nav-link" href="member_loans.php">
                            <i class="fas fa-money-bill-wave me-2"></i> My Loans
                        </a>
                        <a class="nav-link" href="member_contributions.php">
                            <i class="fas fa-piggy-bank me-2"></i> My Contributions
                        </a>
                        <a class="nav-link" href="member_notifications.php">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </a>
                        <a class="nav-link active" href="member_messages.php">
                            <i class="fas fa-envelope me-2"></i> Messages
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="member_loan_application.php">
                            <i class="fas fa-plus-circle me-2"></i> Apply for Loan
                        </a>
                    </nav>
                    
                    <div class="mt-auto">
                        <a class="nav-link" href="member_logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-envelope me-2"></i> Messages</h2>
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-3">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php echo count($messages); ?> message(s)
                            </span>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-warning text-dark">
                                    <?php echo $unread_count; ?> unread
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Success/Error Messages -->
                    <?php if (isset($success) && $success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Messages List -->
                    <?php if (empty($messages)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-envelope-open fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No messages</h5>
                                <p class="text-muted">You don't have any messages yet. Click the compose button to send a message to the administration.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="card message-card <?php echo $message['message_direction']; ?> <?php echo ($message['message_direction'] === 'received' && !$message['is_read']) ? 'unread' : ''; ?>" 
                                 onclick="viewMessage(<?php echo $message['message_id']; ?>)">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-<?php echo $message['message_direction'] === 'sent' ? 'paper-plane' : 'inbox'; ?> me-2"></i>
                                            <?php echo htmlspecialchars($message['subject']); ?>
                                            <?php if ($message['message_direction'] === 'received' && !$message['is_read']): ?>
                                                <span class="badge bg-warning text-dark ms-2">New</span>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="d-flex align-items-center">
                                            <span class="message-direction direction-<?php echo $message['message_direction']; ?> me-2">
                                                <?php echo ucfirst($message['message_direction']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <p class="card-text text-truncate">
                                        <?php echo htmlspecialchars(substr($message['message'], 0, 150)) . (strlen($message['message']) > 150 ? '...' : ''); ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php if ($message['message_direction'] === 'sent'): ?>
                                                To: <?php echo htmlspecialchars($message['recipient_name'] ?? 'Administration'); ?>
                                            <?php else: ?>
                                                From: <?php echo htmlspecialchars($message['sender_name'] ?? 'Administration'); ?>
                                            <?php endif; ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Compose Button -->
    <button class="compose-btn" data-bs-toggle="modal" data-bs-target="#composeModal">
        <i class="fas fa-plus"></i>
    </button>
    
    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Compose Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="8" required placeholder="Type your message here..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Your message will be sent to the administration team. They will respond as soon as possible.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Message Modal -->
    <div class="modal fade" id="viewMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageSubject"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>From:</strong> <span id="messageSender"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Date:</strong> <span id="messageDate"></span>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div id="messageContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="replyToMessage()">
                        <i class="fas fa-reply me-1"></i>Reply
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentMessageId = null;
        
        function viewMessage(messageId) {
            // Find message data
            const messages = <?php echo json_encode($messages); ?>;
            const message = messages.find(m => m.message_id == messageId);
            
            if (message) {
                currentMessageId = messageId;
                
                document.getElementById('messageSubject').textContent = message.subject;
                document.getElementById('messageSender').textContent = 
                    message.message_direction === 'sent' ? 
                    (message.recipient_name || 'Administration') : 
                    (message.sender_name || 'Administration');
                document.getElementById('messageDate').textContent = 
                    new Date(message.created_at).toLocaleString();
                document.getElementById('messageContent').innerHTML = 
                    message.message.replace(/\n/g, '<br>');
                
                // Show modal
                new bootstrap.Modal(document.getElementById('viewMessageModal')).show();
                
                // Mark as read if it's a received unread message
                if (message.message_direction === 'received' && !message.is_read) {
                    markAsRead(messageId);
                }
            }
        }
        
        function markAsRead(messageId) {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('message_id', messageId);
            
            fetch('', {
                method: 'POST',
                body: formData
            }).then(() => {
                // Refresh page to update unread count
                setTimeout(() => location.reload(), 1000);
            });
        }
        
        function replyToMessage() {
            // Close view modal and open compose modal with subject prefilled
            bootstrap.Modal.getInstance(document.getElementById('viewMessageModal')).hide();
            
            const messages = <?php echo json_encode($messages); ?>;
            const message = messages.find(m => m.message_id == currentMessageId);
            
            if (message) {
                const subject = message.subject.startsWith('Re: ') ? 
                    message.subject : 'Re: ' + message.subject;
                
                document.getElementById('subject').value = subject;
                
                setTimeout(() => {
                    new bootstrap.Modal(document.getElementById('composeModal')).show();
                }, 300);
            }
        }
    </script>
</body>
</html>