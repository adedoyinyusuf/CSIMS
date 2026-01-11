<?php
require_once __DIR__ . '/../../src/bootstrap.php';

$container = CSIMS\bootstrap();
$authService = $container->resolve(\CSIMS\Services\AuthenticationService::class);
$auth = $authService; // Expose as $auth for views checking permissions

if (!isset($current_user)) {
    $userModel = $authService->getCurrentUser();
    $current_user = $userModel ? [
        'id' => $userModel->getId(),
        'first_name' => $userModel->getFirstName(),
        'last_name' => $userModel->getLastName()
    ] : ['id' => 0, 'first_name' => 'User', 'last_name' => ''];
}

// Get User ID for permission checks
$userId = $current_user['admin_id'] ?? $current_user['user_id'] ?? $current_user['id'] ?? 0;
?>

<?php
// Notifications: load controller and fetch live data for header
require_once __DIR__ . '/../../controllers/notification_controller.php';
$notificationController = new NotificationController();
// If a member context is present (e.g., view_member.php sets $member_id), scope notifications
$scopedMemberId = isset($member_id) ? (int)$member_id : null;
if ($scopedMemberId) {
    try {
        $recentNotifications = $notificationController->getMemberNotifications($scopedMemberId, 5);
        $recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];
        $unreadCount = (int)$notificationController->getMemberUnreadCount($scopedMemberId);
    } catch (Exception $e) {
        // Fallback if error occurs
        $recentNotifications = [];
        $unreadCount = 0;
    }
} else {
    try {
        $notificationStats = $notificationController->getNotificationStats();
        $unreadCount = (int)($notificationStats['unread_notifications'] ?? 0);
        $recentData = $notificationController->getAllNotifications(1, 5);
        $recentNotifications = is_array($recentData) ? ($recentData['notifications'] ?? []) : [];
    } catch (Exception $e) {
        $recentNotifications = [];
        $unreadCount = 0;
    }
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

<header class="navbar sticky top-0 z-50 bg-white border-b border-gray-100 shadow-sm transition-all duration-300">
    <div class="flex items-center justify-between px-6 py-3">
        <!-- Brand & Sidebar Toggle -->
        <div class="flex items-center space-x-4">
            <!-- Mobile Sidebar Toggle -->
            <button type="button" class="p-2 -ml-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors md:hidden" onclick="toggleSidebar()">
                <i class="fas fa-bars text-xl"></i>
            </button>
            
            <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="navbar-brand flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl overflow-hidden bg-primary-600 flex items-center justify-center text-white shadow-md transform group-hover:scale-105 transition-transform duration-300">
                    <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                        <img src="<?php echo APP_LOGO_URL; ?>" alt="<?php echo APP_SHORT_NAME; ?> Logo" class="w-full h-full object-contain" />
                    <?php else: ?>
                        <i class="fas fa-university text-lg"></i>
                    <?php endif; ?>
                </div>
                <span class="hidden sm:block font-bold text-gray-800 text-lg tracking-tight group-hover:text-primary-600 transition-colors"><?php echo APP_SHORT_NAME; ?></span>
            </a>
            
            <!-- Desktop Sidebar Toggle -->
            <button type="button" class="hidden md:flex p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all" onclick="toggleSidebar()" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Search Bar -->
        <div class="flex-1 max-w-xl mx-8 hidden md:block">
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400 group-focus-within:text-primary-500 transition-colors"></i>
                </div>
                <input type="text" 
                       class="w-full pl-11 pr-4 py-2.5 bg-gray-50 border border-transparent text-gray-900 rounded-xl focus:bg-white focus:border-primary-100 focus:ring-4 focus:ring-primary-50 placeholder-gray-400 transition-all duration-300 text-sm" 
                       placeholder="Search members, loans, savings..." 
                       aria-label="Search">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <kbd class="hidden sm:inline-block px-2 py-0.5 bg-gray-100 text-gray-400 text-xs rounded-md border border-gray-200 shadow-sm">K</kbd>
                </div>
            </div>
        </div>
        
        <!-- Right Navigation -->
        <div class="flex items-center space-x-3 md:space-x-5">
            <!-- Notifications -->
            <div class="relative">
                <button type="button" class="group p-2.5 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-primary-600 transition-all duration-200 relative" onclick="toggleNotifications()">
                    <i class="fas fa-bell text-xl group-hover:animate-swing"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge absolute top-1.5 right-1.5 h-2.5 w-2.5 rounded-full bg-red-500 border-2 border-white animate-pulse"></span>
                    <?php endif; ?>
                </button>
                
                <!-- Notifications Dropdown -->
                <div id="notificationsDropdown" class="dropdown-menu absolute right-0 mt-4 w-96 hidden z-50 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden transform origin-top-right transition-all duration-200">
                    <div class="p-4 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-800">Notifications</h3>
                        <?php if ($unreadCount > 0): ?>
                            <span class="px-2.5 py-0.5 rounded-full bg-primary-100 text-primary-700 text-xs font-bold"><?php echo $unreadCount; ?> New</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="max-h-[28rem] overflow-y-auto custom-scrollbar">
                        <?php if (!empty($recentNotifications)): ?>
                            <?php foreach ($recentNotifications as $n): ?>
                                <?php 
                                    $isUnread = (int)($n['is_read'] ?? 0) === 0;
                                    $bgClass = $isUnread ? 'bg-blue-50/50' : 'hover:bg-gray-50';
                                    $iconBg = $isUnread ? 'bg-primary-100 text-primary-600' : 'bg-gray-100 text-gray-500';
                                    $title = htmlspecialchars($n['title'] ?? ($n['notification_type'] ?? 'Notification'));
                                    $message = htmlspecialchars($n['message'] ?? '');
                                    $messageShort = strlen($message) > 60 ? substr($message, 0, 60) . '...' : $message;
                                    $timeAgo = isset($n['created_at']) ? time_elapsed_string($n['created_at']) : '';
                                    $id = (int)($n['notification_id'] ?? 0);
                                    $link = $id > 0 ? (BASE_URL . '/views/admin/view_notification.php?id=' . $id) : '#';
                                    
                                    // Determine icon based on type (simple heuristic)
                                    $icon = 'fa-bell';
                                    if (stripos($title, 'loan') !== false) $icon = 'fa-hand-holding-usd';
                                    elseif (stripos($title, 'member') !== false) $icon = 'fa-user';
                                    elseif (stripos($title, 'saving') !== false) $icon = 'fa-piggy-bank';
                                    elseif (stripos($title, 'security') !== false) $icon = 'fa-shield-alt';
                                ?>
                                <a href="<?php echo $link; ?>" class="block p-4 transition-colors border-b border-gray-50 <?php echo $bgClass; ?>">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-10 h-10 rounded-full <?php echo $iconBg; ?> flex items-center justify-center flex-shrink-0">
                                            <i class="fas <?php echo $icon; ?> text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-800 truncate"><?php echo $title; ?></p>
                                            <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?php echo $messageShort; ?></p>
                                            <div class="flex items-center mt-2 space-x-2">
                                                <i class="far fa-clock text-[10px] text-gray-400"></i>
                                                <span class="text-[10px] text-gray-400 font-medium"><?php echo $timeAgo; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($isUnread): ?>
                                            <div class="w-2 h-2 rounded-full bg-primary-500 mt-2"></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center bg-gray-50/30">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400">
                                    <i class="far fa-bell-slash text-2xl"></i>
                                </div>
                                <p class="text-gray-500 text-sm font-medium">No system notifications</p>
                                <p class="text-gray-400 text-xs mt-1">We'll let you know when something important happens.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3 bg-gray-50 border-t border-gray-100 text-center">
                        <?php if (!empty($scopedMemberId)): ?>
                            <a href="<?php echo BASE_URL; ?>/views/admin/member_notifications.php?id=<?php echo (int)$scopedMemberId; ?>" class="text-sm font-semibold text-primary-600 hover:text-primary-800 transition-colors inline-flex items-center group">
                                View all member activity <i class="fas fa-arrow-right ml-1 transform group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/views/admin/notifications.php" class="text-sm font-semibold text-primary-600 hover:text-primary-800 transition-colors inline-flex items-center group">
                                View all notifications <i class="fas fa-arrow-right ml-1 transform group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="relative border-l border-gray-200 pl-3 md:pl-5">
                <button type="button" class="group flex items-center space-x-3 p-1 rounded-full hover:bg-gray-50 transition-all duration-200 ring-2 ring-transparent focus:ring-primary-100" onclick="toggleUserMenu()">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white shadow-sm group-hover:shadow-md transition-shadow">
                        <span class="font-bold text-sm">
                            <?php 
                            $initials = substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1);
                            echo strtoupper($initials ?: 'U'); 
                            ?>
                        </span>
                    </div>
                    <div class="hidden md:flex flex-col items-start">
                        <span class="text-sm font-semibold text-gray-800 group-hover:text-primary-700 transition-colors"><?php echo $current_user['first_name']; ?></span>
                        <span class="text-[10px] uppercase tracking-wide font-medium text-gray-500">Administrator</span>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200 group-hover:text-gray-600"></i>
                </button>
                
                <!-- User Dropdown -->
                <div id="userDropdown" class="dropdown-menu absolute right-0 mt-4 w-64 hidden z-50 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden transform origin-top-right transition-all duration-200">
                    <!-- User Header -->
                    <div class="p-5 bg-gradient-to-br from-primary-600 to-primary-700 text-white">
                        <div class="flex items-center space-x-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-white/30 flex items-center justify-center backdrop-blur-sm ring-2 ring-white/50">
                                <span class="font-bold"><?php echo strtoupper($initials ?: 'U'); ?></span>
                            </div>
                            <div>
                                <p class="font-bold text-sm"><?php echo $current_user['first_name'] . ' ' . $current_user['last_name']; ?></p>
                                <p class="text-xs text-primary-100">Super Admin</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="py-2">
                        <a href="<?php echo BASE_URL; ?>/views/admin/admin_profile.php" class="flex items-center px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">
                            <i class="fas fa-user-circle w-6 text-gray-400 group-hover:text-primary-500"></i>
                            My Profile
                        </a>
                        <?php if (isset($auth) && $auth->hasPermission($userId, 'settings.manage')): ?>
                        <a href="<?php echo BASE_URL; ?>/views/admin/settings.php" class="flex items-center px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">
                            <i class="fas fa-cog w-6 text-gray-400 group-hover:text-primary-500"></i>
                            System Settings
                        </a>
                        <?php endif; ?>
                        <?php if (isset($auth) && $auth->hasPermission($userId, 'system.admin')): ?>
                        <!-- Security Dashboard is in Sidebar -->
                        <?php endif; ?>

                        <?php if (isset($auth) && $auth->hasPermission($userId, 'system.admin')): ?>
                        <a href="<?php echo BASE_URL; ?>/views/admin/two_factor_setup.php" class="flex items-center px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">
                            <i class="fas fa-mobile-alt w-6 text-gray-400 group-hover:text-primary-500"></i>
                            Two-Factor Auth
                        </a>
                        <?php endif; ?>
                        
                        <!-- Administration Section -->
                        <div class="border-t border-gray-100 my-2"></div>
                        
                        <div class="px-5 py-2">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Administration</p>
                        </div>
                        
                        <a href="<?php echo BASE_URL; ?>/views/admin/users.php" class="flex items-center px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">
                            <i class="fas fa-users-cog w-6 text-gray-400 group-hover:text-primary-500"></i>
                            User Management
                        </a>
                        
                        <a href="<?php echo BASE_URL; ?>/views/admin/administration.php" class="flex items-center px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">
                            <i class="fas fa-user-shield w-6 text-gray-400 group-hover:text-primary-500"></i>
                            System Administration
                        </a>
                        
                        <a href="<?php echo BASE_URL; ?>/views/admin/audit_logs.php" class="flex items-center px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">
                            <i class="fas fa-clipboard-list w-6 text-gray-400 group-hover:text-primary-500"></i>
                            Audit Logs
                        </a>
                        
                        <a href="<?php echo BASE_URL; ?>/views/admin/security_dashboard.php" class="flex items-center px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">
                            <i class="fas fa-shield-alt w-6 text-gray-400 group-hover:text-primary-500"></i>
                            Security Dashboard
                        </a>
                        
                        <div class="border-t border-gray-100 my-2"></div>
                        
                        <a href="<?php echo BASE_URL; ?>/views/auth/logout.php" class="flex items-center px-5 py-3 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors font-medium">
                            <i class="fas fa-sign-out-alt w-6"></i>
                            Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// Helper function for time elapsed if not in PHP
function timeAgo(date) {
    // Basic JS implementation if needed on client side updates
}

// Toggle functions for dropdowns
function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    const userDropdown = document.getElementById('userDropdown');
    
    // Close other dropdown
    if (!userDropdown.classList.contains('hidden')) {
        userDropdown.classList.add('hidden');
    }
    
    dropdown.classList.toggle('hidden');
    
    // Add animation classes
    if (!dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('opacity-0', 'scale-95');
        dropdown.classList.add('opacity-100', 'scale-100');
    } else {
        dropdown.classList.remove('opacity-100', 'scale-100');
        dropdown.classList.add('opacity-0', 'scale-95');
    }
}

function toggleUserMenu() {
    console.log('toggleUserMenu called');
    const dropdown = document.getElementById('userDropdown');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    
    console.log('dropdown element:', dropdown);
    console.log('dropdown hidden?', dropdown ? dropdown.classList.contains('hidden') : 'element not found');
    
    // Close other dropdown
    if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) {
        notificationsDropdown.classList.add('hidden');
    }
    
    if (dropdown) {
        dropdown.classList.toggle('hidden');
        console.log('After toggle, hidden?', dropdown.classList.contains('hidden'));
        
        // Animation logic
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('opacity-0', 'scale-95');
            dropdown.classList.add('opacity-100', 'scale-100');
            console.log('Dropdown opened with animation');
        } else {
            dropdown.classList.remove('opacity-100', 'scale-100');
            dropdown.classList.add('opacity-0', 'scale-95');
            console.log('Dropdown closed with animation');
        }
    } else {
        console.error('userDropdown element not found!');
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');
    const mainContent = document.querySelector('.main-content'); // Use querySelector for flexibility
    
    if (sidebar) {
        // Check if we're on mobile or desktop based on window width
        const isMobile = window.innerWidth < 768;
        
        if (isMobile) {
            // Mobile behavior: slide in/out
            sidebar.classList.toggle('-translate-x-full');
            if (overlay) overlay.classList.toggle('hidden');
        } else {
            // Desktop behavior: collapse/expand
            sidebar.classList.toggle('sidebar-collapsed');
            if (mainContent) {
                mainContent.classList.toggle('sidebar-collapsed');
            }
        }
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const userDropdown = document.getElementById('userDropdown');
    const notificationBtn = event.target.closest('[onclick="toggleNotifications()"]');
    const userBtn = event.target.closest('[onclick="toggleUserMenu()"]');
    
    if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden') && !notificationBtn && !notificationsDropdown.contains(event.target)) {
        notificationsDropdown.classList.add('hidden');
    }
    
    if (userDropdown && !userDropdown.classList.contains('hidden') && !userBtn && !userDropdown.contains(event.target)) {
        userDropdown.classList.add('hidden');
    }
});

// Search shortcut
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[aria-label="Search"]');
        if (searchInput) searchInput.focus();
    }
});
</script>

<?php
/**
 * Helper function for "Time ago"
 */
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
    
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
    
        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
    
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing dropdowns...');
    
    // Find the user menu button (the one with onclick="toggleUserMenu()")
    const userMenuButton = document.querySelector('button[onclick="toggleUserMenu()"]');
    const dropdown = document.getElementById('userDropdown');
    
    console.log('User button found:', userMenuButton !== null);
    console.log('Dropdown found:', dropdown !== null);
    
    if (userMenuButton && dropdown) {
        // Remove inline onclick and add proper event listener
        userMenuButton.removeAttribute('onclick');
        
        userMenuButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isHidden = dropdown.classList.contains('hidden');
            console.log('Button clicked! Current state - hidden:', isHidden);
            
            if (isHidden) {
                dropdown.classList.remove('hidden');
                console.log('Dropdown opened');
            } else {
                dropdown.classList.add('hidden');
                console.log('Dropdown closed');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!userMenuButton.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
        
        console.log('User menu initialized successfully');
    } else {
        console.error('Failed to initialize user menu');
    }
});

// Keep the function for backwards compatibility
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
        console.log('toggleUserMenu called');
    }
}

// Sidebar toggle function
function toggleSidebar() {
    const sidebar = document.querySelector('aside');
    if (sidebar) {
        sidebar.classList.toggle('-translate-x-full');
    }
}
</script>
