<?php
require_once '../../config/config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/message_controller.php';

$auth = new AuthController();
$current_user = $auth->getCurrentUser();

if (!$current_user) {
    header('Location: ../auth/login.php');
    exit();
}

$messageController = new MessageController();

// Handle pagination and search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$limit = 10;

// Get messages data
$result = $messageController->getAllMessages($page, $limit, $search, $filter);
$messages = $result['messages'];
$total_pages = $result['total_pages'];
$current_page = $result['current_page'];
$total_records = $result['total_records'];

// Get message statistics
$stats = $messageController->getMessageStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Messages Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="compose_message.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Compose Message
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Messages</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_messages']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-envelope fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Unread Messages</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['unread_messages']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-envelope-open fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Read Messages</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['read_messages']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-envelope-open-text fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="filter" class="form-select">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Messages</option>
                                    <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                    <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="messages.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Messages Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Messages List (<?php echo $total_records; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                                <h5>No messages found</h5>
                                <p class="text-muted">Start by composing your first message.</p>
                                <a href="compose_message.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Compose Message
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Subject</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($messages as $message): ?>
                                            <tr class="<?php echo !$message['is_read'] ? 'table-warning' : ''; ?>">
                                                <td><?php echo $message['message_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($message['subject']); ?></strong>
                                                    <?php if (!$message['is_read']): ?>
                                                        <span class="badge bg-warning ms-2">New</span>
                                                    <?php endif; ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($message['message'], 0, 50)) . '...'; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $message['sender_type'] === 'Admin' ? 'primary' : 'info'; ?>">
                                                        <?php echo $message['sender_type']; ?>
                                                    </span>
                                                    <br><?php echo htmlspecialchars($message['sender_name']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $message['recipient_type'] === 'Admin' ? 'primary' : 'info'; ?>">
                                                        <?php echo $message['recipient_type']; ?>
                                                    </span>
                                                    <br><?php echo htmlspecialchars($message['recipient_name']); ?>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($message['is_read']): ?>
                                                        <span class="badge bg-success">Read</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Unread</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view_message.php?id=<?php echo $message['message_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if (!$message['is_read']): ?>
                                                            <a href="mark_read.php?id=<?php echo $message['message_id']; ?>" 
                                                               class="btn btn-sm btn-outline-success" title="Mark as Read">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="delete_message.php?id=<?php echo $message['message_id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger" title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this message?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Messages pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($current_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
