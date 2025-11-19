<?php
// Centralize session and security via config
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../config/database.php';
require_once '../controllers/message_controller.php';
require_once '../controllers/member_controller.php';

// Remove manual session check; rely on member_auth_check.php
// if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
//     header('Location: member_login.php');
//     exit();
// }

$messageController = new MessageController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'] ?? $_SESSION['user_id'];
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

// Get member's messages (both sent and received) with pagination and optional search/filter
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'unread', 'read'])) { $filter = 'all'; }

$result = $messageController->getMessagesForMemberPaginated($member_id, $page, $limit, $search, $filter);
$messages = $result['messages'] ?? [];
$pagination = $result['pagination'] ?? ['total_items' => count($messages), 'items_per_page' => $limit, 'current_page' => $page, 'total_pages' => 1, 'offset' => 0];

// Get unread count
$unread_count = $messageController->getUnreadCountForMember($member_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - NPC CTLStaff Loan Society</title>
    <!-- Assets centralized via includes/member_header.php -->
    <style>
        .sidebar {
            min-height: 100vh;
            background: #ffffff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.06);
        }
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: var(--text-primary);
            background-color: var(--primary-50);
        }
        /* Ensure any legacy white text is readable on white sidebar */
        .sidebar .text-white, .sidebar .text-white-50 { color: var(--text-secondary) !important; }
        .sidebar h4 { color: var(--text-primary); }
        .sidebar .fw-bold { color: var(--text-primary); }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .message-card {
            border-left: 4px solid var(--true-blue);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.15);
        }
        .message-card.sent {
            border-left-color: var(--success);
        }
        .message-card.received {
            border-left-color: var(--true-blue);
        }
        .message-card.unread {
            background-color: var(--primary-50);
            border-left-color: var(--warning);
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
            background: var(--true-blue);
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
<?php include __DIR__ . '/includes/member_header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content (offcanvas handles navigation) -->
            <div class="col-12">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-envelope me-2" style="color: var(--true-blue);"></i> Messages</h2>
                        <div class="d-flex align-items-center">
                            <form class="d-flex me-3" method="GET" action="">
                                <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                                <input type="text" class="form-control form-control-sm me-2" name="search" placeholder="Search subject or message" value="<?php echo htmlspecialchars($search); ?>">
                                <select class="form-select form-select-sm me-2" name="filter">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                    <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read</option>
                                </select>
                                <button class="btn btn-standard btn-sm btn-outline-primary" type="submit"><i class="fas fa-search me-1" style="color: var(--accent-color);"></i>Filter</button>
                            </form>
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
                                <i class="fas fa-envelope-open fa-4x text-muted mb-3" style="color: var(--true-blue);"></i>
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
                        <button type="button" class="btn btn-standard btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-standard btn-outline">
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
                    <button type="button" class="btn btn-outline" onclick="replyToMessage()">
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
<?php if (!empty($messages)): ?>
    <nav aria-label="Message pages" class="px-4 pb-4">
        <?php 
            // Build base URL without page param
            $query = $_GET;
            unset($query['page']);
            $qs = http_build_query($query);
            // Use Utilities::paginationLinks if available, else render minimal controls
            if (method_exists('Utilities', 'paginationLinks')) {
                echo Utilities::paginationLinks($pagination, 'member_messages.php');
            } else {
                echo '<ul class="pagination">';
                // Previous
                if ($pagination['current_page'] > 1) {
                    $prev = $pagination['current_page'] - 1;
                    echo '<li class="page-item"><a class="page-link" href="member_messages.php?' . ($qs ? $qs . '&' : '') . 'page=' . $prev . '">&laquo; Previous</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">&laquo; Previous</span></li>';
                }
                // Current
                echo '<li class="page-item active"><span class="page-link">Page ' . (int)$pagination['current_page'] . ' of ' . (int)$pagination['total_pages'] . '</span></li>';
                // Next
                if ($pagination['current_page'] < $pagination['total_pages']) {
                    $next = $pagination['current_page'] + 1;
                    echo '<li class="page-item"><a class="page-link" href="member_messages.php?' . ($qs ? $qs . '&' : '') . 'page=' . $next . '">Next &raquo;</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
                }
                echo '</ul>';
            }
        ?>
    </nav>
<?php endif; ?>
    <!-- Bootstrap JS for offcanvas -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
