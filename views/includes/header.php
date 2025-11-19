<?php
require_once __DIR__ . '/../../src/bootstrap.php';

$container = CSIMS\bootstrap();
$authService = $container->resolve(\CSIMS\Services\AuthenticationService::class);

if (!isset($current_user)) {
    $userModel = $authService->getCurrentUser();
    $current_user = $userModel ? [
        'first_name' => $userModel->getFirstName(),
        'last_name' => $userModel->getLastName()
    ] : ['first_name' => 'User', 'last_name' => ''];
}
?>

<?php
// Notifications: load controller and fetch live data for header
require_once __DIR__ . '/../../controllers/notification_controller.php';
$notificationController = new NotificationController();
// If a member context is present (e.g., view_member.php sets $member_id), scope notifications
$scopedMemberId = isset($member_id) ? (int)$member_id : null;
if ($scopedMemberId) {
    $recentNotifications = $notificationController->getMemberNotifications($scopedMemberId, 5);
    $recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];
    $unreadCount = (int)$notificationController->getMemberUnreadCount($scopedMemberId);
} else {
    $notificationStats = $notificationController->getNotificationStats();
    $unreadCount = (int)($notificationStats['unread_notifications'] ?? 0);
    $recentData = $notificationController->getAllNotifications(1, 5);
    $recentNotifications = is_array($recentData) ? ($recentData['notifications'] ?? []) : [];
}
?>
<!-- Tailwind CSS Local Build -->
<link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">
<!-- CSIMS Color System -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
<!-- Font Awesome (centralized) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Custom CSS -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
<!-- Shared Components and Icon Utilities -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/icons.css">

<header class="navbar sticky top-0 z-50">
    <div class="flex items-center justify-between px-4 py-3">
        <!-- Brand -->
        <div class="flex items-center space-x-4">
            <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="navbar-brand flex items-center space-x-2 transition-all duration-300 hover:transform hover:scale-105">
                <div class="w-10 h-10 rounded-xl overflow-hidden bg-white flex items-center justify-center border border-gray-200">
                    <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                        <img src="<?php echo APP_LOGO_URL; ?>" alt="<?php echo APP_SHORT_NAME; ?> Logo" class="w-full h-full object-contain" />
                    <?php else: ?>
                        <i class="fas fa-university text-primary-800 text-lg"></i>
                    <?php endif; ?>
                </div>
                <span class="hidden sm:block font-bold text-lapis-lazuli"><?php echo APP_SHORT_NAME; ?></span>
            </a>
            
            <!-- Sidebar toggle button (mobile and desktop) -->
            <button type="button" class="p-3 rounded-xl transition-all duration-300" onclick="toggleSidebar()">
                <i class="fas fa-bars text-lg"></i>
            </button>
        </div>
        
        <!-- Search Bar -->
        <div class="flex-1 max-w-lg mx-4 hidden md:block">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" class="form-control w-full pl-12 pr-4 py-3" placeholder="Search members, loans, savings..." aria-label="Search">
            </div>
        </div>
        
        <!-- Right Navigation -->
        <div class="flex items-center space-x-2">
            <!-- Notifications -->
            <div class="relative">
                <button type="button" class="p-3 rounded-xl transition-all duration-300 relative" onclick="toggleNotifications()">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="badge absolute -top-1 -right-1 h-5 w-5 text-xs rounded-full flex items-center justify-center font-bold bg-red-500 text-white">
                        <?php echo max(0, $unreadCount); ?>
                    </span>
                </button>
                
                <!-- Notifications Dropdown -->
                <div id="notificationsDropdown" class="dropdown-menu absolute right-0 mt-3 w-80 hidden z-50">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-primary">Notifications</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <?php if (!empty($recentNotifications)): ?>
                            <?php foreach ($recentNotifications as $n): ?>
                                <?php 
                                    $isUnread = (int)($n['is_read'] ?? 0) === 0;
                                    $dotColor = $isUnread ? 'bg-red-500' : 'bg-gray-300';
                                    $title = htmlspecialchars($n['title'] ?? ($n['notification_type'] ?? 'Notification'));
                                    $message = htmlspecialchars($n['message'] ?? '');
                                    $createdAt = htmlspecialchars($n['created_at'] ?? '');
                                    $id = (int)($n['notification_id'] ?? 0);
                                    $link = $id > 0 ? (BASE_URL . '/views/admin/view_notification.php?id=' . $id) : '#';
                                ?>
                                <a href="<?php echo $link; ?>" class="block p-4 hover:bg-gray-50 transition-colors border-b border-gray-100">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-2 h-2 <?php echo $dotColor; ?> rounded-full mt-2 flex-shrink-0"></div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900"><?php echo $title; ?></p>
                                            <?php if (!empty($message)): ?>
                                                <p class="text-xs text-gray-500 mt-1"><?php echo $message; ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($createdAt)): ?>
                                                <p class="text-xs text-gray-400 mt-1"><?php echo $createdAt; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-sm text-gray-500">No notifications to show.</div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 border-t border-gray-200">
                        <?php if (!empty($scopedMemberId)): ?>
                            <a href="<?php echo BASE_URL; ?>/views/admin/member_notifications.php?id=<?php echo (int)$scopedMemberId; ?>" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all notifications</a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/views/admin/notifications.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all notifications</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="relative">
                <button type="button" class="user-menu flex items-center space-x-3 p-2 rounded-xl transition-all duration-300" onclick="toggleUserMenu()">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center glass-dark">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <span class="hidden sm:block user-name"><?php echo $current_user['first_name']; ?></span>
                    <i class="fas fa-chevron-down text-sm"></i>
                </button>
                
                <!-- User Dropdown -->
                <div id="userDropdown" class="dropdown-menu absolute right-0 mt-3 w-52 hidden z-50">
                    <div class="py-2">
                        <a href="<?php echo BASE_URL; ?>/views/admin/profile.php" class="dropdown-item flex items-center">
                            <i class="fas fa-user w-5 h-5 mr-3 text-primary-600"></i>
                            Profile
                        </a>
                        <a href="<?php echo BASE_URL; ?>/views/admin/settings.php" class="dropdown-item flex items-center">
                            <i class="fas fa-cog w-5 h-5 mr-3 text-primary-600"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo BASE_URL; ?>/views/auth/logout.php" class="dropdown-item flex items-center text-error hover:bg-error-bg">
                            <i class="fas fa-sign-out-alt w-5 h-5 mr-3 text-error"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// Toggle functions for dropdowns
function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    const userDropdown = document.getElementById('userDropdown');
    userDropdown.classList.add('hidden');
    dropdown.classList.toggle('hidden');
}

function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    notificationsDropdown.classList.add('hidden');
    dropdown.classList.toggle('hidden');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    
    if (sidebar && overlay) {
        // Check if we're on mobile or desktop
        const isMobile = window.innerWidth < 768;
        
        if (isMobile) {
            // Mobile behavior: slide in/out with overlay
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        } else {
            // Desktop behavior: collapse/expand sidebar
            sidebar.classList.toggle('sidebar-collapsed');
            if (mainContent) {
                mainContent.classList.toggle('sidebar-collapsed');
            }
        }
    }
}

// Legacy function for backward compatibility
function toggleMobileSidebar() {
    toggleSidebar();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const userDropdown = document.getElementById('userDropdown');
    
    if (!event.target.closest('[onclick="toggleNotifications()"]') && !event.target.closest('#notificationsDropdown')) {
        notificationsDropdown.classList.add('hidden');
    }
    
    if (!event.target.closest('[onclick="toggleUserMenu()"]') && !event.target.closest('#userDropdown')) {
        userDropdown.classList.add('hidden');
    }
});
</script>