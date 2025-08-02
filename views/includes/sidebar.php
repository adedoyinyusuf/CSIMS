<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="background: #f8f9fa; border-right: 1px solid #e0e0e0; box-shadow: 2px 0 4px rgba(0,0,0,0.1);">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/dashboard.php">
                    <i class="fas fa-tachometer-alt fa-fw me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['members.php', 'add_member.php', 'edit_member.php', 'view_member.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/members.php">
                    <i class="fas fa-users fa-fw me-2"></i>
                    Members
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'member_approvals.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/member_approvals.php">
                    <i class="fas fa-user-check fa-fw me-2"></i>
                    Member Approvals
                    <?php
                    // Show pending count badge
                    if (isset($memberController)) {
                        $pendingCount = count($memberController->getPendingMembers());
                        if ($pendingCount > 0) {
                            echo '<span class="badge bg-warning ms-2">' . $pendingCount . '</span>';
                        }
                    }
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['memberships.php', 'add_membership_type.php', 'edit_membership_type.php', 'view_membership_type.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/memberships.php">
                    <i class="fas fa-id-card fa-fw me-2"></i>
                    Memberships
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['contributions.php', 'add_contribution.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/contributions.php">
                    <i class="fas fa-money-bill-wave fa-fw me-2"></i>
                    Contributions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['loans.php', 'loan_applications.php', 'view_loan.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/loans.php">
                    <i class="fas fa-hand-holding-usd fa-fw me-2"></i>
                    Loans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['investments.php', 'add_investment.php', 'view_investment.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/investments.php">
                    <i class="fas fa-chart-line fa-fw me-2"></i>
                    Investments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['messages.php', 'send_message.php', 'view_message.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/messages.php">
                    <i class="fas fa-envelope fa-fw me-2"></i>
                    Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['notifications.php', 'send_notification.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/notifications.php">
                    <i class="fas fa-bell fa-fw me-2"></i>
                    Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['reports.php', 'generate_report.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/reports.php">
                    <i class="fas fa-file-alt fa-fw me-2"></i>
                    Reports
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1" style="color: #6c757d; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
            <span>Analytics & Security</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'financial_dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/financial_dashboard.php">
                    <i class="fas fa-chart-pie fa-fw me-2"></i>
                    Financial Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'security_dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/security_dashboard.php">
                    <i class="fas fa-shield-alt fa-fw me-2"></i>
                    Security Dashboard
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1" style="color: #6c757d; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
            <span>Administration</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['users.php', 'add_user.php', 'edit_user.php'])) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/users.php">
                    <i class="fas fa-user-shield fa-fw me-2"></i>
                    Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/settings.php">
                    <i class="fas fa-cog fa-fw me-2"></i>
                    Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'two_factor_setup.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/views/admin/two_factor_setup.php">
                    <i class="fas fa-mobile-alt fa-fw me-2"></i>
                    Two-Factor Auth
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/views/auth/logout.php">
                    <i class="fas fa-sign-out-alt fa-fw me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>