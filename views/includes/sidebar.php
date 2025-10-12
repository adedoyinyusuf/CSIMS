<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebarMenu" class="sidebar fixed left-0 top-16 h-full w-64 transform -translate-x-full md:translate-x-0 transition-all duration-300 ease-in-out z-40 overflow-y-auto sidebar-nav">
    <div class="p-4">
        <!-- Main Navigation -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title">Main Navigation</h3>
            <div class="space-y-1">
                <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="sidebar-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo ($current_page == 'dashboard.php') ? 'glass-dark' : 'bg-white bg-opacity-10'; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <span class="font-medium sidebar-text">Dashboard</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="sidebar-item <?php echo (in_array($current_page, ['members.php', 'add_member.php', 'edit_member.php', 'view_member.php'])) ? 'active' : ''; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (in_array($current_page, ['members.php', 'add_member.php', 'edit_member.php', 'view_member.php'])) ? 'glass-dark' : 'bg-white bg-opacity-10'; ?>">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="font-medium sidebar-text">Members</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/member_approvals.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo ($current_page == 'member_approvals.php') ? 'bg-green-50 text-green-700 border-l-4 border-green-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'member_approvals.php') ? 'bg-green-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-user-check <?php echo ($current_page == 'member_approvals.php') ? 'text-green-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Member Approvals</span>
                    <?php
                    // Show pending count badge
                    if (isset($memberController)) {
                        $pendingCount = count($memberController->getPendingMembers());
                        if ($pendingCount > 0) {
                            echo '<span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full sidebar-text">' . $pendingCount . '</span>';
                        }
                    }
                    ?>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/memberships.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo (in_array($current_page, ['memberships.php', 'add_membership_type.php', 'edit_membership_type.php', 'view_membership_type.php'])) ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['memberships.php', 'add_membership_type.php', 'edit_membership_type.php', 'view_membership_type.php'])) ? 'bg-blue-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-id-card <?php echo (in_array($current_page, ['memberships.php', 'add_membership_type.php', 'edit_membership_type.php', 'view_membership_type.php'])) ? 'text-blue-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Memberships</span>
                </a>
            </div>
        </div>

        <!-- Financial Management -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title">Financial Management</h3>
            <div class="space-y-1">
                <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo (in_array($current_page, ['savings_accounts.php', 'create_savings_account.php', 'view_savings_account.php'])) ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['SavingsController.php', 'savings_accounts.php', 'create_savings_account.php', 'view_savings_account.php'])) ? 'bg-emerald-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-piggy-bank <?php echo (in_array($current_page, ['SavingsController.php', 'savings_accounts.php', 'create_savings_account.php', 'view_savings_account.php'])) ? 'text-emerald-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Savings</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo (in_array($current_page, ['loans.php', 'loan_applications.php', 'view_loan.php'])) ? 'bg-orange-50 text-orange-700 border-l-4 border-orange-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['loans.php', 'loan_applications.php', 'view_loan.php'])) ? 'bg-orange-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-hand-holding-usd <?php echo (in_array($current_page, ['loans.php', 'loan_applications.php', 'view_loan.php'])) ? 'text-orange-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Loans</span>
                </a>
            </div>
        </div>

        <!-- Communication -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title">Communication</h3>
            <div class="space-y-1">
                <a href="<?php echo BASE_URL; ?>/views/admin/messages.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo (in_array($current_page, ['messages.php', 'send_message.php', 'view_message.php'])) ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['messages.php', 'send_message.php', 'view_message.php'])) ? 'bg-indigo-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-envelope <?php echo (in_array($current_page, ['messages.php', 'send_message.php', 'view_message.php'])) ? 'text-indigo-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Messages</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/notifications.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo (in_array($current_page, ['notifications.php', 'send_notification.php'])) ? 'bg-pink-50 text-pink-700 border-l-4 border-pink-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['notifications.php', 'send_notification.php'])) ? 'bg-pink-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-bell <?php echo (in_array($current_page, ['notifications.php', 'send_notification.php'])) ? 'text-pink-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Notifications</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/reports.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo (in_array($current_page, ['reports.php', 'generate_report.php'])) ? 'bg-cyan-50 text-cyan-700 border-l-4 border-cyan-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['reports.php', 'generate_report.php'])) ? 'bg-cyan-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-file-alt <?php echo (in_array($current_page, ['reports.php', 'generate_report.php'])) ? 'text-cyan-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Reports</span>
                </a>
            </div>
        </div>

        <!-- Analytics & Security -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title">Analytics & Security</h3>
            <div class="space-y-1">
                <?php if (isset($auth) && $auth->hasPermission('view_financial_analytics')): ?>
                <a href="<?php echo BASE_URL; ?>/views/admin/financial_dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo ($current_page == 'financial_dashboard.php') ? 'bg-amber-50 text-amber-700 border-l-4 border-amber-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'financial_dashboard.php') ? 'bg-amber-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-chart-pie <?php echo ($current_page == 'financial_dashboard.php') ? 'text-amber-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Financial Analytics</span>
                </a>
                <?php endif; ?>
                
                <?php if (isset($auth) && $auth->hasPermission('view_security_dashboard')): ?>
                <a href="<?php echo BASE_URL; ?>/views/admin/security_dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo ($current_page == 'security_dashboard.php') ? 'bg-red-50 text-red-700 border-l-4 border-red-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'security_dashboard.php') ? 'bg-red-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-shield-alt <?php echo ($current_page == 'security_dashboard.php') ? 'text-red-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Security Dashboard</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Administration -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title">Administration</h3>
            <div class="space-y-1">
                <!-- Profile Management - Available to all admins -->
                <a href="<?php echo BASE_URL; ?>/views/admin/admin_profile.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo ($current_page == 'admin_profile.php') ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'admin_profile.php') ? 'bg-blue-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-user-edit <?php echo ($current_page == 'admin_profile.php') ? 'text-blue-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">My Profile</span>
                </a>
                

                
                <!-- System Administration - Super Admin Only -->
                <?php if (isset($auth) && $auth->hasPermission('manage_users')): ?>
                <a href="<?php echo BASE_URL; ?>/views/admin/users.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo (in_array($current_page, ['users.php', 'add_user.php', 'edit_user.php'])) ? 'bg-slate-50 text-slate-700 border-l-4 border-slate-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['users.php', 'add_user.php', 'edit_user.php'])) ? 'bg-slate-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-user-shield <?php echo (in_array($current_page, ['users.php', 'add_user.php', 'edit_user.php'])) ? 'text-slate-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Users</span>
                </a>
                <?php endif; ?>
                
                <?php if (isset($auth) && $auth->hasPermission('manage_settings')): ?>
                <a href="<?php echo BASE_URL; ?>/views/admin/settings.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo ($current_page == 'settings.php') ? 'bg-slate-50 text-slate-700 border-l-4 border-slate-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'settings.php') ? 'bg-slate-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-cog <?php echo ($current_page == 'settings.php') ? 'text-slate-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Settings</span>
                </a>
                <?php endif; ?>
                
                <?php if (isset($auth) && $auth->hasPermission('manage_two_factor')): ?>
                <a href="<?php echo BASE_URL; ?>/views/admin/two_factor_setup.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link <?php echo ($current_page == 'two_factor_setup.php') ? 'bg-yellow-50 text-yellow-700 border-l-4 border-yellow-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'two_factor_setup.php') ? 'bg-yellow-100' : 'bg-gray-100'; ?>">
                        <i class="fas fa-mobile-alt <?php echo ($current_page == 'two_factor_setup.php') ? 'text-yellow-600' : 'text-gray-600'; ?>"></i>
                    </div>
                    <span class="font-medium sidebar-text">Two-Factor Auth</span>
                </a>
                <?php endif; ?>
                
                <a href="<?php echo BASE_URL; ?>/views/auth/logout.php" class="flex items-center space-x-3 p-3 rounded-lg transition-colors sidebar-link text-gray-700 hover:bg-red-50 hover:text-red-700">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 group-hover:bg-red-100 sidebar-icon">
                        <i class="fas fa-sign-out-alt text-gray-600 group-hover:text-red-600"></i>
                    </div>
                    <span class="font-medium sidebar-text">Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile sidebar overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden hidden" onclick="toggleMobileSidebar()"></div>

<style>
/* Sidebar collapse styles */
.sidebar-nav.sidebar-collapsed {
    width: 4rem;
}

.sidebar-nav.sidebar-collapsed .sidebar-text {
    display: none;
}

.sidebar-nav.sidebar-collapsed .sidebar-section-title {
    display: none;
}

.sidebar-nav.sidebar-collapsed .sidebar-link {
    justify-content: center;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

.sidebar-nav.sidebar-collapsed .sidebar-icon {
    margin-right: 0;
}

/* Main content adjustment */
.main-content {
    margin-left: 16rem; /* 64 * 0.25rem = 16rem for w-64 */
    transition: margin-left 0.3s ease-in-out;
}

.main-content.sidebar-collapsed {
    margin-left: 4rem;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
    }
    
    .main-content.sidebar-collapsed {
        margin-left: 0;
    }
}
</style>

<script>
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
}

// Close sidebar when clicking overlay
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            toggleMobileSidebar();
        });
    }
});
</script>