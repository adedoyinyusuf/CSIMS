<?php
// Centralize session and security via config
require_once '../config/config.php';
require_once '../config/member_auth_check.php';
require_once '../config/database.php';
require_once '../controllers/message_controller.php';
require_once '../controllers/member_controller.php';

$messageController = new MessageController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'] ?? $_SESSION['user_id'];
$member = $memberController->getMemberById($member_id);

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // $errors and $success handling preserved
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
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd',
                            300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9',
                            600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e'
                        },
                        secondary: {
                            50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0',
                            300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b',
                            600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Modal Transitions */
        .modal { transition: opacity 0.25s ease; }
        .modal-active { overflow-y: hidden; }
    </style>
</head>
<body class="bg-secondary-50 text-secondary-900">
    <?php include __DIR__ . '/includes/member_header.php'; ?>
    
    <div class="flex min-h-screen">
        <main class="flex-1 overflow-x-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                
                <!-- Page Header -->
                <div class="md:flex md:items-center md:justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-secondary-900">Messages</h1>
                        <p class="text-secondary-500 mt-1">Communications with the administration</p>
                    </div>
                    <div class="mt-4 md:mt-0 flex items-center space-x-3">
                         <!-- Stats Pill -->
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white text-secondary-600 shadow-sm border border-secondary-200">
                            <i class="fas fa-inbox mr-2 text-primary-500"></i> 
                            <?php echo $pagination['total_items']; ?> total
                        </span>
                        <?php if ($unread_count > 0): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-50 text-amber-700 shadow-sm border border-amber-200">
                                <i class="fas fa-envelope mr-2 text-amber-500"></i>
                                <?php echo $unread_count; ?> unread
                            </span>
                        <?php endif; ?>
                        
                        <!-- Compose Button -->
                        <button onclick="openComposeModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                            <i class="fas fa-plus mr-2"></i> New Message
                        </button>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if (isset($success) && $success): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-md shadow-sm animate-fade-in-down">
                        <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-check-circle text-green-500"></i></div>
                            <div class="ml-3"><p class="text-sm font-medium text-green-800"><?php echo $successMessage; ?></p></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-md shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-exclamation-circle text-red-500"></i></div>
                            <div class="ml-3">
                                <?php foreach ($errors as $error): ?>
                                    <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filter & Search Bar -->
                <div class="bg-white rounded-xl shadow-sm border border-secondary-100 p-4 mb-6">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-5 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-secondary-400"></i>
                            </div>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="block w-full pl-10 pr-3 py-2 border-secondary-300 rounded-lg text-sm placeholder-secondary-400 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Search subject or content...">
                        </div>
                        <div class="md:col-span-3">
                            <select name="filter" 
                                    class="block w-full pl-3 pr-10 py-2 text-base border-secondary-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-lg">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Messages</option>
                                <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                                <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read Only</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                             <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-secondary-300 shadow-sm text-sm font-medium rounded-lg text-secondary-700 bg-white hover:bg-secondary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Messages List -->
                <div class="bg-white rounded-xl shadow-sm border border-secondary-100 overflow-hidden">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-12 px-4">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-secondary-100 mb-4">
                                <i class="fas fa-envelope-open text-secondary-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-secondary-900">No messages found</h3>
                            <p class="mt-1 text-sm text-secondary-500">
                                <?php echo $search ? 'Try adjusting your search or filters.' : 'Get in touch with the administration using the button above.'; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <ul class="divide-y divide-secondary-100">
                            <?php foreach ($messages as $message): ?>
                                <?php 
                                    $isUnread = ($message['message_direction'] === 'received' && !$message['is_read']);
                                    $rowClass = $isUnread ? 'bg-primary-50/50' : 'hover:bg-secondary-50';
                                    $iconClass = $message['message_direction'] === 'sent' ? 'bg-secondary-100 text-secondary-500' : 'bg-primary-100 text-primary-600';
                                    $icon = $message['message_direction'] === 'sent' ? 'fa-paper-plane' : 'fa-inbox';
                                ?>
                                <li>
                                    <div onclick="viewMessage(<?php echo $message['message_id']; ?>)" class="block cursor-pointer <?php echo $rowClass; ?> transition duration-150 ease-in-out">
                                        <div class="px-4 py-4 sm:px-6">
                                            <div class="flex items-start">
                                                <!-- Icon/Avatar -->
                                                <div class="flex-shrink-0">
                                                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full <?php echo $iconClass; ?>">
                                                        <i class="fas <?php echo $icon; ?> text-sm"></i>
                                                    </span>
                                                </div>
                                                
                                                <!-- Content -->
                                                <div class="ml-4 flex-1">
                                                    <div class="flex items-center justify-between">
                                                        <p class="text-sm font-semibold text-secondary-900 truncate">
                                                            <?php echo htmlspecialchars($message['subject']); ?>
                                                            <?php if ($isUnread): ?>
                                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800">
                                                                    New
                                                                </span>
                                                            <?php endif; ?>
                                                        </p>
                                                        <div class="ml-2 flex-shrink-0 flex">
                                                            <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $message['message_direction'] === 'sent' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                                <?php echo ucfirst($message['message_direction']); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="mt-1 flex justify-between">
                                                        <div class="sm:flex">
                                                            <p class="flex items-center text-sm text-secondary-500">
                                                                <?php if ($message['message_direction'] === 'sent'): ?>
                                                                    <i class="fas fa-arrow-right text-xs mr-1.5 text-secondary-400"></i> To: <?php echo htmlspecialchars($message['recipient_name'] ?? 'Admin'); ?>
                                                                <?php else: ?>
                                                                    <i class="fas fa-user-circle text-xs mr-1.5 text-secondary-400"></i> From: <?php echo htmlspecialchars($message['sender_name'] ?? 'Admin'); ?>
                                                                <?php endif; ?>
                                                                <span class="mx-2 text-secondary-300">&bull;</span>
                                                                <span class="truncate max-w-xs sm:max-w-md">
                                                                    <?php echo htmlspecialchars(substr($message['message'], 0, 100)) . (strlen($message['message']) > 100 ? '...' : ''); ?>
                                                                </span>
                                                            </p>
                                                        </div>
                                                        <div class="mt-2 flex items-center text-xs text-secondary-500 sm:mt-0">
                                                            <i class="fas fa-clock flex-shrink-0 mr-1.5 text-secondary-400"></i>
                                                            <p>
                                                                <?php echo date('M j, Y', strtotime($message['created_at'])); ?> 
                                                                <span class="hidden sm:inline">at <?php echo date('g:i A', strtotime($message['created_at'])); ?></span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <div class="bg-white px-4 py-3 border-t border-secondary-200 flex items-center justify-between sm:px-6">
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-secondary-700">
                                            Showing <span class="font-medium"><?php echo $pagination['offset'] + 1; ?></span> to <span class="font-medium"><?php echo min($pagination['offset'] + $pagination['items_per_page'], $pagination['total_items']); ?></span> of <span class="font-medium"><?php echo $pagination['total_items']; ?></span> results
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <?php
                                            // Simple Pagination Logic for Tailwind
                                            $qs = $_GET; 
                                            unset($qs['page']);
                                            $baseLink = '?' . http_build_query($qs) . '&page=';
                                            
                                            if ($pagination['current_page'] > 1) {
                                                echo '<a href="' . $baseLink . ($pagination['current_page'] - 1) . '" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-secondary-300 bg-white text-sm font-medium text-secondary-500 hover:bg-secondary-50"><i class="fas fa-chevron-left"></i></a>';
                                            }
                                            
                                            // Show limited page numbers
                                            for ($i = 1; $i <= $pagination['total_pages']; $i++) {
                                                if ($i == $pagination['current_page']) {
                                                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-primary-500 bg-primary-50 text-sm font-medium text-primary-600 z-10">' . $i . '</span>';
                                                } else {
                                                    echo '<a href="' . $baseLink . $i . '" class="relative inline-flex items-center px-4 py-2 border border-secondary-300 bg-white text-sm font-medium text-secondary-700 hover:bg-secondary-50">' . $i . '</a>';
                                                }
                                            }
                                            
                                            if ($pagination['current_page'] < $pagination['total_pages']) {
                                                echo '<a href="' . $baseLink . ($pagination['current_page'] + 1) . '" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-secondary-300 bg-white text-sm font-medium text-secondary-500 hover:bg-secondary-50"><i class="fas fa-chevron-right"></i></a>';
                                            }
                                            ?>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- Compose Modal (Tailwind) -->
    <div id="composeModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-secondary-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeComposeModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-pen text-primary-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-secondary-900" id="modal-title">
                                    New Message
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="subject" class="block text-sm font-medium text-secondary-700">Subject</label>
                                        <input type="text" name="subject" id="subject" class="mt-1 block w-full border-secondary-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" required>
                                    </div>
                                    <div>
                                        <label for="message" class="block text-sm font-medium text-secondary-700">Message</label>
                                        <textarea id="message" name="message" rows="5" class="mt-1 block w-full border-secondary-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm" placeholder="Write your message here..." required></textarea>
                                    </div>
                                    <div class="rounded-md bg-blue-50 p-3">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-info-circle text-blue-400"></i>
                                            </div>
                                            <div class="ml-3 flex-1 md:flex md:justify-between">
                                                <p class="text-sm text-blue-700">Replies will appear in your inbox.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-secondary-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Send Message
                        </button>
                        <button type="button" onclick="closeComposeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-secondary-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-secondary-700 hover:bg-secondary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Message Modal (Tailwind) -->
    <div id="viewMessageModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-secondary-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeViewModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-semibold text-secondary-900 flex items-center justify-between">
                                <span id="viewSubject">Subject</span>
                                <span id="viewDate" class="text-xs font-normal text-secondary-500"></span>
                            </h3>
                            <div class="mt-2 text-sm text-secondary-500 mb-4 border-b border-secondary-100 pb-2">
                                <span id="viewSender" class="font-medium text-secondary-700">Sender</span>
                            </div>
                            <div class="mt-4">
                                <p id="viewContent" class="text-sm text-secondary-700 whitespace-pre-wrap leading-relaxed"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-secondary-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="replyToMessage()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-reply mr-2"></i> Reply
                    </button>
                    <button type="button" onclick="closeViewModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-secondary-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-secondary-700 hover:bg-secondary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Data for JS access
        const messages = <?php echo json_encode($messages); ?>;
        let currentMessageId = null;

        function openComposeModal() {
            document.getElementById('composeModal').classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function closeComposeModal() {
            document.getElementById('composeModal').classList.add('hidden');
            document.body.classList.remove('modal-active');
        }

        function viewMessage(messageId) {
            const message = messages.find(m => m.message_id == messageId);
            if (message) {
                currentMessageId = messageId;
                
                document.getElementById('viewSubject').textContent = message.subject;
                document.getElementById('viewContent').textContent = message.message;
                document.getElementById('viewDate').textContent = new Date(message.created_at).toLocaleString();
                
                const sender = message.message_direction === 'sent' 
                    ? `To: ${message.recipient_name || 'Administration'}` 
                    : `From: ${message.sender_name || 'Administration'}`;
                document.getElementById('viewSender').textContent = sender;

                document.getElementById('viewMessageModal').classList.remove('hidden');
                document.body.classList.add('modal-active');

                // Mark as read API call if needed and unread
                if (message.message_direction === 'received' && !message.is_read) {
                    markAsRead(messageId);
                }
            }
        }
        
        function closeViewModal() {
            document.getElementById('viewMessageModal').classList.add('hidden');
            document.body.classList.remove('modal-active');
        }

        function replyToMessage() {
            closeViewModal();
            const message = messages.find(m => m.message_id == currentMessageId);
            if (message) {
                const subject = message.subject.startsWith('Re: ') ? message.subject : 'Re: ' + message.subject;
                document.getElementById('subject').value = subject;
                // Add reference to body
                const ref = `\n\n--- On ${new Date(message.created_at).toLocaleString()}, ${message.sender_name || 'Admin'} wrote: ---\n${message.message.substring(0, 100)}...`;
                document.getElementById('message').value = ref;
                openComposeModal();
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
                // Optional: visual update or slight delay refresh
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
