<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/member_auth_check.php';
// Member header include centralizes assets and navbar for member-facing pages
$memberName = isset($_SESSION['member_name']) ? $_SESSION['member_name'] : 'Member';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
    <!-- Premium Assets & Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/csims-colors.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --bs-font-sans-serif: 'Inter', system-ui, -apple-system, sans-serif;
            --primary-gradient: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            --sidebar-active-bg: #f0f9ff;
            --sidebar-active-color: #0369a1;
            --sidebar-active-border: #0ea5e9;
        }

        body {
            font-family: var(--bs-font-sans-serif);
        }

        /* Navbar Styling */
        .premium-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }
        
        .premium-brand {
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.025em;
            font-size: 1.1rem;
        }

        .menu-toggler {
            border: 1px solid #e2e8f0;
            color: #64748b;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .menu-toggler:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        /* Offcanvas / Sidebar Premium Styling */
        .premium-offcanvas {
            border-right: none;
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
        }

        .premium-offcanvas .offcanvas-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
        }

        .premium-offcanvas .offcanvas-title {
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.01em;
        }

        .premium-offcanvas .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
        }
        .premium-offcanvas .btn-close:hover {
            opacity: 1;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.5rem;
            color: #475569; /* Slate 600 */
            font-weight: 500;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-link i {
            width: 24px;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1.1rem;
            color: #94a3b8; /* Slate 400 */
            transition: color 0.15s;
        }

        .sidebar-link:hover {
            background-color: #f8fafc;
            color: #334155;
            border-left-color: #cbd5e1;
        }
        .sidebar-link:hover i {
            color: #64748b;
        }

        .sidebar-link.active {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-active-color);
            border-left-color: var(--sidebar-active-border);
        }
        .sidebar-link.active i {
            color: var(--sidebar-active-border);
        }

        .sidebar-divider {
            border-top: 1px solid #e2e8f0;
            margin: 1rem 1.5rem;
        }

        .logout-link:hover {
            background-color: #fef2f2;
            color: #ef4444;
            border-left-color: #fca5a5;
        }
        .logout-link:hover i {
            color: #ef4444;
        }

        /* User Dropdown */
        .user-dropdown-btn {
            font-weight: 500;
            color: #334155 !important;
        }
        .user-dropdown-btn i {
            color: #0ea5e9;
        }
    </style>

    <nav class="navbar navbar-expand-lg navbar-light premium-navbar fixed-top">
        <div class="container-fluid px-4">
            <!-- Left: Offcanvas Toggler and Brand -->
            <div class="d-flex align-items-center">
                <button class="menu-toggler me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberOffcanvas" aria-controls="memberOffcanvas">
                    <i class="fas fa-bars"></i>
                </button>
                <a class="navbar-brand d-flex align-items-center premium-brand" href="member_dashboard.php">
                    <span class="d-inline-flex align-items-center justify-content-center bg-white border border-slate-200 rounded-circle me-2 shadow-sm" style="width: 40px; height: 40px;">
                        <?php if (defined('APP_LOGO_URL') && APP_LOGO_URL): ?>
                            <img src="<?php echo APP_LOGO_URL; ?>" alt="Logo" style="height: 24px; width: auto;" />
                        <?php else: ?>
                            <i class="fas fa-shield-alt text-primary"></i>
                        <?php endif; ?>
                    </span>
                    <span class="d-none d-sm-inline">NPC CTLStaff Loan Society</span>
                    <span class="d-inline d-sm-none">CSIMS</span>
                </a>
            </div>

            <!-- Right: Profile Dropdown (Desktop) -->
            <div class="ms-auto d-flex align-items-center">
                <div class="d-none d-lg-block">
                    <a class="nav-link user-dropdown-btn" href="member_profile.php">
                        <i class="fas fa-user-circle me-2 fa-lg"></i>
                        <?= htmlspecialchars($memberName) ?>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Spacer for fixed navbar -->
    <div style="height: 70px;"></div>

    <!-- Offcanvas: Collapsible Left Menu -->
    <div class="offcanvas offcanvas-start premium-offcanvas" tabindex="-1" id="memberOffcanvas" aria-labelledby="memberOffcanvasLabel">
        <div class="offcanvas-header shadow-sm">
            <h5 class="offcanvas-title" id="memberOffcanvasLabel">
                <i class="fas fa-th-large me-2 opacity-75"></i> Member Menu
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <nav class="sidebar-menu">
                <a class="sidebar-link <?= $currentPage === 'member_dashboard.php' ? 'active' : '' ?>" href="member_dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a class="sidebar-link <?= $currentPage === 'member_loans.php' || strpos($currentPage, 'loan') !== false ? 'active' : '' ?>" href="member_loans.php">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Loans</span>
                </a>
                <a class="sidebar-link <?= $currentPage === 'member_savings.php' ? 'active' : '' ?>" href="member_savings.php">
                    <i class="fas fa-piggy-bank"></i>
                    <span>Savings</span>
                </a>
                <a class="sidebar-link <?= $currentPage === 'member_messages.php' ? 'active' : '' ?>" href="member_messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>

                <div class="sidebar-divider"></div>

                <a class="sidebar-link <?= $currentPage === 'member_profile.php' ? 'active' : '' ?>" href="member_profile.php">
                    <i class="fas fa-user-cog"></i>
                    <span>My Profile</span>
                </a>
                
                <a class="sidebar-link logout-link mt-1" href="member_logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
    </div>
