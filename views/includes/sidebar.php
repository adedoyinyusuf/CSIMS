<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize auth and userId for permission checks if not already set
if (!isset($auth) || !isset($userId)) {
    // Try to get from existing context or controller
    if (!isset($auth) && class_exists('AuthController')) {
        $auth = new AuthController();
    }
    if (!isset($userId)) {
        $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
    }
}
?>

<!-- Ensure CSS is loaded for sidebar styles -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">

<nav id="sidebarMenu" class="sidebar fixed left-0 top-16 bottom-0 w-64 transform -translate-x-full md:translate-x-0 transition-all duration-300 ease-in-out z-40 overflow-y-auto sidebar-nav bg-white shadow-lg border-r border-gray-100 pb-24">
    <div class="p-4">
        <!-- Main Navigation -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title text-gray-400">Main Navigation</h3>
            <div class="space-y-1">
                <a href="<?php echo BASE_URL; ?>/views/admin/dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo ($current_page == 'dashboard.php') ? 'bg-primary-50 text-primary-700 border-l-4 border-primary-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'dashboard.php') ? 'bg-white text-primary-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <span class="font-medium sidebar-text">Dashboard</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo (in_array($current_page, ['members.php', 'add_member.php', 'edit_member.php', 'view_member.php'])) ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['members.php', 'add_member.php', 'edit_member.php', 'view_member.php'])) ? 'bg-white text-indigo-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="font-medium sidebar-text">Members</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/member_approvals.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo ($current_page == 'member_approvals.php') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'member_approvals.php') ? 'bg-white text-emerald-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <span class="font-medium sidebar-text">Member Approvals</span>
                    <?php
                    // Show pending count badge
                    try {
                        if (isset($memberController)) {
                            $pendingCount = count($memberController->getPendingMembers());
                            if ($pendingCount > 0) {
                                echo '<span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full sidebar-text shadow-sm">' . $pendingCount . '</span>';
                            }
                        }
                    } catch (Exception $e) {
                        // Silently fail if memberController not available
                    }
                    ?>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/memberships.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo (in_array($current_page, ['memberships.php', 'add_membership_type.php', 'edit_membership_type.php', 'view_membership_type.php'])) ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['memberships.php', 'add_membership_type.php', 'edit_membership_type.php', 'view_membership_type.php'])) ? 'bg-white text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <span class="font-medium sidebar-text">Memberships</span>
                </a>
            </div>
        </div>

        <!-- Financial Management -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title text-gray-400">Financial Management</h3>
            <div class="space-y-1">
                <a href="<?php echo BASE_URL; ?>/views/admin/loans.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo (in_array($current_page, ['loans.php', 'loan_applications.php', 'view_loan.php'])) ? 'bg-orange-50 text-orange-700 border-l-4 border-orange-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['loans.php', 'loan_applications.php', 'view_loan.php'])) ? 'bg-white text-orange-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <span class="font-medium sidebar-text">Loans</span>
                </a>

                <a href="<?php echo BASE_URL; ?>/views/admin/savings_accounts.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo (in_array($current_page, ['savings_accounts.php', 'savings.php', 'create_savings_account.php', 'view_savings_account.php', 'savings_details.php', 'savings_ippis_upload.php'])) ? 'bg-teal-50 text-teal-700 border-l-4 border-teal-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['savings_accounts.php', 'savings.php', 'create_savings_account.php', 'view_savings_account.php', 'savings_details.php', 'savings_ippis_upload.php'])) ? 'bg-white text-teal-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <span class="font-medium sidebar-text">Savings</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/savings_withdrawal_approvals.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo ($current_page == 'savings_withdrawal_approvals.php') ? 'bg-purple-50 text-purple-700 border-l-4 border-purple-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'savings_withdrawal_approvals.php') ? 'bg-white text-purple-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <span class="font-medium sidebar-text">Withdrawal Approvals</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/savings_post_interest.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo ($current_page == 'savings_post_interest.php') ? 'bg-cyan-50 text-cyan-700 border-l-4 border-cyan-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'savings_post_interest.php') ? 'bg-white text-cyan-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <span class="font-medium sidebar-text">Post Interest</span>
                </a>
            </div>
        </div>

        <!-- Communication -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title text-gray-400">Communication</h3>
            <div class="space-y-1">
                <a href="<?php echo BASE_URL; ?>/views/admin/messages.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo (in_array($current_page, ['messages.php', 'send_message.php', 'view_message.php'])) ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['messages.php', 'send_message.php', 'view_message.php'])) ? 'bg-white text-indigo-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <span class="font-medium sidebar-text">Messages</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/views/admin/reports.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo (in_array($current_page, ['reports.php', 'generate_report.php'])) ? 'bg-sky-50 text-sky-700 border-l-4 border-sky-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['reports.php', 'generate_report.php'])) ? 'bg-white text-sky-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <span class="font-medium sidebar-text">Reports</span>
                </a>
            </div>
        </div>

        <!-- Analytics & Security -->
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title text-gray-400">Analytics & Security</h3>
            <div class="space-y-1">
                <?php if (isset($auth) && $auth->hasPermission($userId, 'reports.generate')): ?>
                <a href="<?php echo BASE_URL; ?>/views/admin/financial_dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo ($current_page == 'financial_dashboard.php') ? 'bg-amber-50 text-amber-700 border-l-4 border-amber-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'financial_dashboard.php') ? 'bg-white text-amber-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <span class="font-medium sidebar-text">Financial Analytics</span>
                </a>
                <?php endif; ?>
                
                <?php if (isset($auth) && $auth->hasPermission($userId, 'system.admin')): ?>
                <a href="<?php echo BASE_URL; ?>/views/admin/security_dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo ($current_page == 'security_dashboard.php') ? 'bg-red-50 text-red-700 border-l-4 border-red-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'security_dashboard.php') ? 'bg-white text-red-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <span class="font-medium sidebar-text">Security Dashboard</span>
                </a>
                <?php endif; ?>
                
                <!-- Audit Logs Viewer -->
                <a href="<?php echo BASE_URL; ?>/views/admin/member_activity_log.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo ($current_page == 'member_activity_log.php') ? 'bg-lime-50 text-lime-700 border-l-4 border-lime-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo ($current_page == 'member_activity_log.php') ? 'bg-white text-lime-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <span class="font-medium sidebar-text">Audit Logs</span>
                </a>
            </div>
        </div>

        <!-- Administration -->
        <?php if (isset($auth) && $auth->hasPermission($userId, 'users.read')): ?>
        <div class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 px-3 sidebar-section-title text-gray-400">Administration</h3>
            <div class="space-y-1">                
                <!-- System Administration - Super Admin Only -->
                <a href="<?php echo BASE_URL; ?>/views/admin/users.php" class="flex items-center space-x-3 p-3 rounded-lg transition-all duration-200 sidebar-link <?php echo (in_array($current_page, ['users.php', 'add_user.php', 'edit_user.php'])) ? 'bg-slate-50 text-slate-700 border-l-4 border-slate-600 shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'; ?>">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center sidebar-icon <?php echo (in_array($current_page, ['users.php', 'add_user.php', 'edit_user.php'])) ? 'bg-white text-slate-600 shadow-sm' : 'bg-gray-100 text-gray-500'; ?>">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <span class="font-medium sidebar-text">Users</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
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
