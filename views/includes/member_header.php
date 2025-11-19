<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/member_auth_check.php';
// Member header include centralizes assets and navbar for member-facing pages
$memberName = isset($_SESSION['member_name']) ? $_SESSION['member_name'] : 'Member';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!-- Assets: Bootstrap, Font Awesome, CSIMS color system, and site styles -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/csims-colors.css">
<link rel="stylesheet" href="../assets/css/style.css">

<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container-fluid">
        <!-- Left: Offcanvas Toggler and Brand -->
        <button class="btn btn-outline-secondary me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberOffcanvas" aria-controls="memberOffcanvas">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center" href="member_dashboard.php">
            <span class="d-inline-flex align-items-center justify-content-center bg-white border rounded px-2 me-2" style="height: 40px;">
                <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                    <img src="<?php echo APP_LOGO_URL; ?>" alt="<?php echo APP_SHORT_NAME; ?> Logo" style="height: 36px; width: auto; object-fit: contain;" />
                <?php else: ?>
                    <i class="fas fa-users"></i>
                <?php endif; ?>
            </span>
            NPC CTLStaff Loan Society
        </a>

        <!-- Right: Profile Dropdown -->
        <div class="ms-auto d-flex align-items-center">
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($memberName) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="member_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="member_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Offcanvas: Collapsible Left Menu -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="memberOffcanvas" aria-labelledby="memberOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="memberOffcanvasLabel"><i class="fas fa-bars me-2"></i> Member Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="nav flex-column">
            <a class="nav-link <?= $currentPage === 'member_dashboard.php' ? 'active' : '' ?>" href="member_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
            <a class="nav-link <?= $currentPage === 'member_loans.php' ? 'active' : '' ?>" href="member_loans.php"><i class="fas fa-money-bill-wave me-2"></i> Loans</a>
            <a class="nav-link <?= $currentPage === 'member_savings.php' ? 'active' : '' ?>" href="member_savings.php"><i class="fas fa-piggy-bank me-2"></i> Savings</a>
            <a class="nav-link <?= $currentPage === 'member_notifications.php' ? 'active' : '' ?>" href="member_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link <?= $currentPage === 'member_messages.php' ? 'active' : '' ?>" href="member_messages.php"><i class="fas fa-envelope me-2"></i> Messages</a>
            <a class="nav-link <?= in_array($currentPage, ['member_loan_application.php','member_loan_application_enhanced.php','member_loan_application_integrated.php']) ? 'active' : '' ?>" href="member_loan_application.php"><i class="fas fa-plus-circle me-2"></i> Apply for Loan</a>
        </nav>
        <hr>
        <div class="mt-2">
            <a class="btn btn-outline-secondary w-100" href="member_profile.php"><i class="fas fa-user me-2"></i> Profile</a>
            <a class="btn btn-outline-danger w-100 mt-2" href="member_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>
    </div>
</div>