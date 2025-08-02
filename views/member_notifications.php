<?php
session_start();
require_once '../config/database.php';
require_once '../controllers/notification_controller.php';
require_once '../controllers/member_controller.php';

// Check if member is logged in
if (!isset($_SESSION['member_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: member_login.php');
    exit();
}

$notificationController = new NotificationController();
$memberController = new MemberController();

$member_id = $_SESSION['member_id'];
$member = $memberController->getMemberById($member_id);

// Get member's notifications
$notifications = $notificationController->getMemberNotifications($member_id, 50);

// Group notifications by date
$grouped_notifications = [];
foreach ($notifications as $notification) {
    $date = date('Y-m-d', strtotime($notification['created_at']));
    $grouped_notifications[$date][] = $notification;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - NPC CTLStaff Loan Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .notification-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.15);
        }
        .notification-type {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .type-payment { background-color: #d4edda; color: #155724; }
        .type-meeting { background-color: #cce7ff; color: #004085; }
        .type-policy { background-color: #fff3cd; color: #856404; }
        .type-general { background-color: #e2e3e5; color: #383d41; }
        .date-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin: 1.5rem 0 1rem 0;
            border-left: 4px solid #007bff;
        }
        .notification-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar d-flex flex-column p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-university"></i> Member Portal
                    </h4>
                    
                    <div class="mb-3">
                        <small class="text-white-50">Welcome,</small>
                        <div class="text-white fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="member_dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="member_profile.php">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a class="nav-link" href="member_loans.php">
                            <i class="fas fa-money-bill-wave me-2"></i> My Loans
                        </a>
                        <a class="nav-link" href="member_contributions.php">
                            <i class="fas fa-piggy-bank me-2"></i> My Contributions
                        </a>
                        <a class="nav-link active" href="member_notifications.php">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </a>
                        <a class="nav-link" href="member_loan_application.php">
                            <i class="fas fa-plus-circle me-2"></i> Apply for Loan
                        </a>
                    </nav>
                    
                    <div class="mt-auto">
                        <a class="nav-link" href="member_logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-bell me-2"></i> My Notifications</h2>
                        <div class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <?php echo count($notifications); ?> notification(s)
                        </div>
                    </div>
                    
                    <?php if (empty($notifications)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No notifications</h5>
                                <p class="text-muted">You don't have any notifications at the moment. Check back later for updates from the society.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped_notifications as $date => $date_notifications): ?>
                            <div class="date-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-calendar-day me-2"></i>
                                    <?php 
                                        $today = date('Y-m-d');
                                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                                        
                                        if ($date === $today) {
                                            echo 'Today';
                                        } elseif ($date === $yesterday) {
                                            echo 'Yesterday';
                                        } else {
                                            echo date('F j, Y', strtotime($date));
                                        }
                                    ?>
                                    <span class="badge bg-primary ms-2"><?php echo count($date_notifications); ?></span>
                                </h6>
                            </div>
                            
                            <?php foreach ($date_notifications as $notification): ?>
                                <div class="card notification-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <i class="fas fa-envelope me-2"></i>
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </h6>
                                            <div class="d-flex align-items-center">
                                                <span class="notification-type type-<?php echo strtolower($notification['notification_type']); ?> me-2">
                                                    <?php echo htmlspecialchars($notification['notification_type']); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                        
                                        <div class="notification-meta">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <i class="fas fa-user me-1"></i>
                                                    From: <?php echo htmlspecialchars($notification['created_by_name'] ?? 'System'); ?>
                                                </div>
                                                <div class="col-md-6 text-md-end">
                                                    <i class="fas fa-users me-1"></i>
                                                    To: <?php echo $notification['recipient_type'] === 'All' ? 'All Members' : 'You'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <!-- Load More Button (if needed) -->
                        <?php if (count($notifications) >= 50): ?>
                            <div class="text-center mt-4">
                                <button class="btn btn-outline-primary" onclick="loadMoreNotifications()">
                                    <i class="fas fa-chevron-down me-2"></i> Load More Notifications
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadMoreNotifications() {
            // This would be implemented to load more notifications via AJAX
            alert('Load more functionality would be implemented here');
        }
        
        // Auto-refresh notifications every 5 minutes
        setInterval(function() {
            // This would refresh the notifications via AJAX
            console.log('Auto-refreshing notifications...');
        }, 300000); // 5 minutes
    </script>
</body>
</html>