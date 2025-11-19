<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/communication_controller.php';
require_once '../../controllers/member_controller.php';

// Use centralized auth
$auth = new AuthController();
if (!$auth->isLoggedIn() || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}
$current_user = $auth->getCurrentUser();
$admin_id = $current_user['admin_id'];

$communicationController = new CommunicationController();
$memberController = new MemberController();

// Handle form submissions
$errors = [];
$success = false;
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_announcement':
            $announcement_data = [
                'title' => $_POST['title'],
                'content' => $_POST['content'],
                'priority' => $_POST['priority'],
                'target_audience' => $_POST['target_audience'],
                'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                'created_by' => $admin_id,
                'status' => $_POST['status']
            ];
            
            $announcement_id = $communicationController->createAnnouncement($announcement_data);
            if ($announcement_id) {
                $success = true;
                $action_message = 'Announcement created successfully!';
            } else {
                $errors[] = 'Failed to create announcement.';
            }
            break;
            
        case 'create_template':
            $template_data = [
                'name' => $_POST['name'],
                'subject' => $_POST['subject'],
                'content' => $_POST['content'],
                'message_type' => $_POST['message_type'],
                'variables' => $_POST['variables'],
                'created_by' => $admin_id,
                'status' => 'active'
            ];
            
            $template_id = $communicationController->createMessageTemplate($template_data);
            if ($template_id) {
                $success = true;
                $action_message = 'Message template created successfully!';
            } else {
                $errors[] = 'Failed to create template.';
            }
            break;
            
        case 'send_bulk_message':
            $recipient_criteria = [
                'status' => $_POST['recipient_status'] ?? null,
                'membership_type' => $_POST['membership_type'] ?? null
            ];
            
            $recipients = $communicationController->getRecipientList($recipient_criteria);
            $recipient_ids = array_column($recipients, 'member_id');
            
            if (!empty($recipient_ids)) {
                $message_ids = $communicationController->sendBulkMessage(
                    $admin_id,
                    $recipient_ids,
                    $_POST['subject'],
                    $_POST['message'],
                    $_POST['priority'] ?? 'normal'
                );
                
                if ($message_ids) {
                    $success = true;
                    $action_message = 'Bulk message sent to ' . count($recipient_ids) . ' members successfully!';
                } else {
                    $errors[] = 'Failed to send bulk message.';
                }
            } else {
                $errors[] = 'No recipients found matching the criteria.';
            }
            break;
    }
}

// Get dashboard data
$stats = $communicationController->getCommunicationStats();
$announcements = $communicationController->getAllAnnouncements(20);
$templates = $communicationController->getMessageTemplates();
$pending_notifications = $communicationController->getPendingNotifications(10);
$scheduled_messages = $communicationController->getPendingScheduledMessages(10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Portal - NPC CTLStaff Loan Society</title>
    <!-- Tailwind CSS and Font Awesome are provided by the shared header include -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="bg-gray-50 font-sans">
    <div class="wrapper">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        <!-- Content Wrapper -->
        <div class="main-content" id="mainContent">
            <?php include_once __DIR__ . '/../includes/header.php'; ?>
            <div class="p-6">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-bullhorn mr-3 text-primary-600"></i> Communication Portal
                        </h1>
                        <p class="text-gray-600 mt-2">Manage announcements, messages, and member communications</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="showAnnouncementModal()" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 transition-colors duration-200 shadow-lg">
                            <i class="fas fa-plus mr-2"></i> New Announcement
                        </button>
                        <button onclick="showBulkMessageModal()" class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors duration-200 shadow-lg">
                            <i class="fas fa-envelope mr-2"></i> Bulk Message
                        </button>
                    </div>
                </div>
                
                <!-- Flash Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Success!</h3>
                                <p class="mt-2 text-sm text-green-700"><?php echo htmlspecialchars($action_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Announcements</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['announcements']['total_announcements']; ?></p>
                                <p class="text-sm text-gray-500"><?php echo $stats['announcements']['active_announcements']; ?> active</p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-bullhorn text-2xl icon-lapis"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Messages Sent</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['messages']['total_messages']; ?></p>
                                <p class="text-sm text-gray-500"><?php echo $stats['messages']['read_messages']; ?> read</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-envelope text-2xl icon-success"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-slate-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Notifications</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['notifications']['total_notifications']; ?></p>
                                <p class="text-sm text-gray-500"><?php echo $stats['notifications']['sent_notifications']; ?> delivered</p>
                            </div>
                            <div class="bg-slate-100 p-3 rounded-full">
                                <i class="fas fa-bell text-2xl text-slate-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Recipients Reached</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['messages']['unique_recipients']; ?></p>
                                <p class="text-sm text-gray-500">Unique members</p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-users text-2xl icon-error"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button onclick="showTab('announcements')" class="tab-button active border-b-2 border-primary-500 py-2 px-1 text-sm font-medium text-primary-600">
                                <i class="fas fa-bullhorn mr-2"></i> Announcements (<?php echo count($announcements); ?>)
                            </button>
                            <button onclick="showTab('templates')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-file-alt mr-3 icon-primary"></i> Templates (<?php echo count($templates); ?>)
                            </button>
                            <button onclick="showTab('scheduled')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-clock mr-3 icon-primary"></i> Scheduled (<?php echo count($scheduled_messages); ?>)
                            </button>
                            <button onclick="showTab('notifications')" class="tab-button border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-bell mr-2"></i> Notifications (<?php echo count($pending_notifications); ?>)
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Tab Contents -->
                
                <!-- Announcements Tab -->
                <div id="announcements-tab" class="tab-content">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-bullhorn mr-3 text-primary-600"></i> Recent Announcements
                                </h3>
                                <button onclick="showAnnouncementModal()" class="text-primary-600 hover:text-primary-700 flex items-center text-sm">
                                    <i class="fas fa-plus mr-2"></i> New Announcement
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <?php if (empty($announcements)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-bullhorn text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No announcements found.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-3">
                                                <div class="flex-1">
                                                    <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                                    <p class="text-gray-600 mt-2"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                                </div>
                                                <div class="ml-4 flex flex-col items-end space-y-2">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                        echo match($announcement['priority']) {
                                                            'high' => 'bg-red-100 text-red-800',
                                                            'medium' => 'bg-yellow-100 text-yellow-800',
                                                            'normal' => 'bg-green-100 text-green-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($announcement['priority']); ?> Priority
                                                    </span>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                        echo match($announcement['status']) {
                                                            'active' => 'bg-green-100 text-green-800',
                                                            'inactive' => 'bg-gray-100 text-gray-800',
                                                            'expired' => 'bg-red-100 text-red-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($announcement['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-center text-sm text-gray-500">
                                                <div>
                                                    <span class="font-medium">Target:</span> <?php echo ucfirst($announcement['target_audience']); ?> |
                                                    <span class="font-medium">Created:</span> <?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?>
                                                    <?php if ($announcement['expiry_date']): ?>
                                                        | <span class="font-medium">Expires:</span> <?php echo date('M d, Y', strtotime($announcement['expiry_date'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex space-x-2">
                                                    <button class="text-primary-600 hover:text-primary-700">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="text-red-600 hover:text-red-700">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Templates Tab -->
                <div id="templates-tab" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                    <i class="fas fa-file-alt mr-3 icon-primary"></i> Message Templates
                                </h3>
                                <button onclick="showTemplateModal()" class="text-primary-600 hover:text-primary-700 flex items-center text-sm">
                                    <i class="fas fa-plus mr-2"></i> New Template
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <?php if (empty($templates)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No templates found.</p>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($templates as $template): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-3">
                                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($template['name']); ?></h4>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo ucfirst(str_replace('_', ' ', $template['message_type'])); ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($template['subject']); ?></p>
                                            <div class="text-xs text-gray-500">
                                                <?php 
                                                $variables = json_decode($template['variables'] ?? '[]', true);
                                                if (!empty($variables)): 
                                                ?>
                                                    <span class="font-medium">Variables:</span> <?php echo implode(', ', array_map(function($v) { return '{' . $v . '}'; }, $variables)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-4 flex justify-end space-x-2">
                                                <button class="text-primary-600 hover:text-primary-700">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="useTemplate(<?php echo $template['id']; ?>)" class="text-green-600 hover:text-green-700">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-700">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Scheduled Tab -->
                <div id="scheduled-tab" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-clock mr-3 text-primary-600"></i> Scheduled Messages
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($scheduled_messages)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-clock text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No scheduled messages found.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Recipients</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Scheduled</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                                <th class="px-4 py-3 text-left font-medium text-gray-500">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($scheduled_messages as $message): ?>
                                                <tr>
                                                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($message['subject']); ?></td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <?php echo ucfirst(str_replace('_', ' ', $message['message_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3"><?php echo count(json_decode($message['recipient_ids'], true)); ?> members</td>
                                                    <td class="px-4 py-3"><?php echo date('M d, Y H:i', strtotime($message['scheduled_at'])); ?></td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                            echo match($message['status']) {
                                                                'sent' => 'bg-green-100 text-green-800',
                                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                                'failed' => 'bg-red-100 text-red-800',
                                                                default => 'bg-gray-100 text-gray-800'
                                                            };
                                                        ?>">
                                                            <?php echo ucfirst($message['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <button class="text-primary-600 hover:text-primary-700 mr-2">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($message['status'] === 'pending'): ?>
                                                            <button class="text-red-600 hover:text-red-700">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div id="notifications-tab" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-bell mr-3 text-primary-600"></i> Pending Notifications
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($pending_notifications)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-bell text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No pending notifications found.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($pending_notifications as $notification): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-3">
                                                <div class="flex-1">
                                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                    <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                </div>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                                    echo match($notification['priority']) {
                                                        'urgent' => 'bg-red-100 text-red-800',
                                                        'high' => 'bg-orange-100 text-orange-800',
                                                        'normal' => 'bg-green-100 text-green-800',
                                                        'low' => 'bg-gray-100 text-gray-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($notification['priority']); ?>
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center text-sm text-gray-500">
                                                <div>
                                                    <span class="font-medium">Type:</span> <?php echo ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?> |
                                                    <span class="font-medium">Recipient:</span> <?php echo ucfirst($notification['recipient_type']); ?> |
                                                    <span class="font-medium">Methods:</span> <?php echo implode(', ', json_decode($notification['delivery_methods'], true)); ?>
                                                </div>
                                                <div>
                                                    <span class="font-medium">Scheduled:</span> <?php echo date('M d, Y H:i', strtotime($notification['scheduled_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Announcement Modal -->
    <div id="announcementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-90vh overflow-y-auto">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_announcement">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Create New Announcement</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" id="title" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                            <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority <span class="text-red-500">*</span></label>
                                <select name="priority" id="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                    <option value="normal">Normal</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="target_audience" class="block text-sm font-medium text-gray-700 mb-2">Target Audience <span class="text-red-500">*</span></label>
                                <select name="target_audience" id="target_audience" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                    <option value="all">All Members</option>
                                    <option value="active">Active Members</option>
                                    <option value="expired">Expired Members</option>
                                </select>
                            </div>
                            <div>
                                <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-2">Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" id="expiry_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            </div>
                        </div>
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content <span class="text-red-500">*</span></label>
                            <textarea name="content" id="content" rows="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required></textarea>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                            <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeAnnouncementModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            Create Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Message Modal -->
    <div id="bulkMessageModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-90vh overflow-y-auto">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_bulk_message">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Send Bulk Message</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="recipient_status" class="block text-sm font-medium text-gray-700 mb-2">Recipient Status</label>
                                <select name="recipient_status" id="recipient_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                    <option value="">All Members</option>
                                    <option value="Active">Active Members</option>
                                    <option value="Expired">Expired Members</option>
                                    <option value="Suspended">Suspended Members</option>
                                </select>
                            </div>
                            <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                    <option value="normal">Normal</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" id="subject" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message <span class="text-red-500">*</span></label>
                            <textarea name="message" id="message" rows="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeBulkMessageModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Template Modal -->
    <div id="templateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-90vh overflow-y-auto">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_template">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900">Create Message Template</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="template_name" class="block text-sm font-medium text-gray-700 mb-2">Template Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="template_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                            </div>
                            <div>
                                <label for="template_type" class="block text-sm font-medium text-gray-700 mb-2">Template Type <span class="text-red-500">*</span></label>
                                <select name="message_type" id="template_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                                    <option value="general">General</option>
                                    <option value="reminder">Reminder</option>
                                    <option value="announcement">Announcement</option>
                                    <option value="welcome">Welcome</option>
                                    <option value="renewal">Renewal</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="template_subject" class="block text-sm font-medium text-gray-700 mb-2">Subject <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" id="template_subject" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required>
                        </div>
                        <div>
                            <label for="template_content" class="block text-sm font-medium text-gray-700 mb-2">Content <span class="text-red-500">*</span></label>
                            <textarea name="content" id="template_content" rows="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" required></textarea>
                        </div>
                        <div>
                            <label for="template_variables" class="block text-sm font-medium text-gray-700 mb-2">Available Variables (JSON Array)</label>
                            <input type="text" name="variables" id="template_variables" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder='["first_name", "last_name", "member_id"]'>
                            <p class="text-xs text-gray-500 mt-1">Use variables in content like {first_name}, {last_name}, etc.</p>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeTemplateModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            Create Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-primary-500', 'text-primary-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            const activeButton = document.querySelector(`[onclick="showTab('${tabName}')"]`);
            activeButton.classList.add('active', 'border-primary-500', 'text-primary-600');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        // Modal functions
        function showAnnouncementModal() {
            document.getElementById('announcementModal').classList.remove('hidden');
        }

        function closeAnnouncementModal() {
            document.getElementById('announcementModal').classList.add('hidden');
        }

        function showBulkMessageModal() {
            document.getElementById('bulkMessageModal').classList.remove('hidden');
        }

        function closeBulkMessageModal() {
            document.getElementById('bulkMessageModal').classList.add('hidden');
        }

        function showTemplateModal() {
            document.getElementById('templateModal').classList.remove('hidden');
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').classList.add('hidden');
        }

        function useTemplate(templateId) {
            // This would open a modal to use the template for bulk messaging
            alert('Use Template functionality would be implemented here');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showTab('announcements');
        });
    </script>
</body>
</html>
