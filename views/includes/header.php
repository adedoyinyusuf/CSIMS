<?php
// Include required files
require_once __DIR__ . '/../../controllers/auth_controller.php';

// Get current user
if (!isset($current_user)) {
    $authController = new AuthController();
    $current_user = $authController->getCurrentUser();
}
?>

<!-- Tailwind CSS Local Build -->
<link href="<?php echo BASE_URL; ?>/assets/css/tailwind.css" rel="stylesheet">

<header class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
    <div class="flex items-center justify-between px-4 py-3">
        <!-- Brand -->
        <div class="flex items-center space-x-4">
            <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="flex items-center space-x-2 text-primary-600 hover:text-primary-700 transition-colors">
                <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-primary-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-university text-white text-sm"></i>
                </div>
                <span class="font-bold text-xl hidden sm:block"><?php echo APP_SHORT_NAME; ?></span>
            </a>
            
            <!-- Sidebar toggle button (mobile and desktop) -->
            <button type="button" class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors" onclick="toggleSidebar()">
                <i class="fas fa-bars text-lg"></i>
            </button>
        </div>
        
        <!-- Search Bar -->
        <div class="flex-1 max-w-lg mx-4 hidden md:block">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors" placeholder="Search members, loans, contributions..." aria-label="Search">
            </div>
        </div>
        
        <!-- Right Navigation -->
        <div class="flex items-center space-x-2">
            <!-- Notifications -->
            <div class="relative">
                <button type="button" class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors relative" onclick="toggleNotifications()">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-medium">
                        3
                    </span>
                </button>
                
                <!-- Notifications Dropdown -->
                <div id="notificationsDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <a href="#" class="block p-4 hover:bg-gray-50 transition-colors border-b border-gray-100">
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-red-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">Membership expiring soon</p>
                                    <p class="text-xs text-gray-500 mt-1">5 members have memberships expiring within 30 days</p>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="block p-4 hover:bg-gray-50 transition-colors border-b border-gray-100">
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-green-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">New member registration</p>
                                    <p class="text-xs text-gray-500 mt-1">John Doe has submitted a membership application</p>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="block p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">Loan application received</p>
                                    <p class="text-xs text-gray-500 mt-1">New loan application for $5,000 requires review</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="p-4 border-t border-gray-200">
                        <a href="#" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="relative">
                <button type="button" class="flex items-center space-x-2 p-2 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors" onclick="toggleUserMenu()">
                    <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-primary-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <span class="hidden sm:block font-medium"><?php echo $current_user['first_name']; ?></span>
                    <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                </button>
                
                <!-- User Dropdown -->
                <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50">
                    <div class="py-2">
                        <a href="<?php echo BASE_URL; ?>/views/admin/profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-user w-4 h-4 mr-3 text-gray-400"></i>
                            Profile
                        </a>
                        <a href="<?php echo BASE_URL; ?>/views/admin/settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-cog w-4 h-4 mr-3 text-gray-400"></i>
                            Settings
                        </a>
                        <hr class="my-2 border-gray-200">
                        <a href="<?php echo BASE_URL; ?>/views/auth/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <i class="fas fa-sign-out-alt w-4 h-4 mr-3 text-red-500"></i>
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