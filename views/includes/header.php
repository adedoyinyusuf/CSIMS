<?php
// Get current user
$current_user = isset($current_user) ? $current_user : $auth->getCurrentUser();
?>

<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
        <?php echo APP_SHORT_NAME; ?>
    </a>
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <input class="form-control form-control-dark w-100" type="text" placeholder="Search" aria-label="Search">
    <div class="navbar-nav">
        <div class="nav-item text-nowrap">
            <a class="nav-link px-3" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    3
                    <span class="visually-hidden">unread notifications</span>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Membership expiring soon</a></li>
                <li><a class="dropdown-item" href="#">New member registration</a></li>
                <li><a class="dropdown-item" href="#">Loan application received</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">View all notifications</a></li>
            </ul>
        </div>
        <div class="nav-item text-nowrap">
            <a class="nav-link px-3" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle"></i> <?php echo $current_user['first_name']; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/profile.php"><i class="fas fa-user fa-fw"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings.php"><i class="fas fa-cog fa-fw"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>