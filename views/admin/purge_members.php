<?php
require_once '../../config/config.php';
require_once '../../config/auth_check.php';
require_once '../../controllers/auth_controller.php';
require_once '../../includes/db.php';

$auth = new AuthController();
$current_user = isset($current_user) ? $current_user : $auth->getCurrentUser();

function tableExists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_row();
    return (int)$row[0] > 0;
}

function countRows(mysqli $conn, string $table): int {
    if (!tableExists($conn, $table)) return 0;
    $sql = "SELECT COUNT(*) AS c FROM `" . $conn->real_escape_string($table) . "`";
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

$database = Database::getInstance();
$conn = $database->getConnection();

$tables = [
    // Savings
    'savings_transactions', 'savings_accounts',
    // Loans
    'loan_payment_schedule', 'loan_interest_postings', 'loans',
    // Membership and member-related
    'memberships', 'share_capital', 'member_messages',
    // Notifications and queues
    'notifications', 'email_queue', 'sms_queue'
];

$dryRunResult = [];
$purgeResult = ['deleted' => [], 'errors' => []];
$didPurge = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $confirm = trim($_POST['confirm'] ?? '');
    if ($action === 'dry_run') {
        foreach ($tables as $t) {
            $dryRunResult[$t] = tableExists($conn, $t) ? countRows($conn, $t) : null;
        }
        $dryRunResult['members'] = tableExists($conn, 'members') ? countRows($conn, 'members') : null;
    } elseif ($action === 'purge' && $confirm === 'CONFIRM') {
        $didPurge = true;
        $conn->begin_transaction();
        try {
            $conn->query('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $t) {
                if (tableExists($conn, $t)) {
                    $sql = "DELETE FROM `" . $conn->real_escape_string($t) . "`";
                    if ($conn->query($sql) === true) {
                        $purgeResult['deleted'][$t] = $conn->affected_rows;
                    } else {
                        $purgeResult['errors'][] = 'Failed deleting from ' . $t . ': ' . $conn->error;
                    }
                }
            }
            if (tableExists($conn, 'members')) {
                if ($conn->query('DELETE FROM `members`') === true) {
                    $purgeResult['deleted']['members'] = $conn->affected_rows;
                } else {
                    $purgeResult['errors'][] = 'Failed deleting members: ' . $conn->error;
                }
            }
            $conn->query('SET FOREIGN_KEY_CHECKS=1');
            if (empty($purgeResult['errors'])) {
                $conn->commit();
                $_SESSION['success_message'] = 'Purge completed successfully.';
            } else {
                $conn->rollback();
                $_SESSION['error_message'] = 'Purge encountered errors. No changes were committed.';
            }
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['error_message'] = 'Purge failed: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Invalid action or confirmation text.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Purge Members and Data - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css" />
</head>
<body>
<?php require_once '../includes/header.php'; ?>
<?php require_once '../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="container">
        <h1 class="text-danger">Danger Zone: Purge Members and Related Data</h1>
        <p class="text-muted">This operation will remove ALL member records and related data (savings, loans, notifications, queues, etc.). It cannot be undone. Consider backing up the database first.</p>
        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <section class="card">
            <h2>Dry Run</h2>
            <p>Preview how many rows exist in each table before deleting.</p>
            <form method="post">
                <input type="hidden" name="action" value="dry_run" />
                <?php echo CSRFProtection::getTokenField(); ?>
                <button type="submit" class="btn btn-warning">Run Dry-Run</button>
            </form>
            <?php if (!empty($dryRunResult)): ?>
                <table class="table">
                    <thead><tr><th>Table</th><th>Row Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($dryRunResult as $table => $count): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($table); ?></td>
                                <td><?php echo $count === null ? 'Missing' : (int)$count; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Execute Purge</h2>
            <p class="text-danger">Type <strong>CONFIRM</strong> to proceed. This will attempt deletion in a transaction with foreign key checks disabled, then re-enabled. If any table delete fails, the entire purge is rolled back.</p>
            <form method="post">
                <input type="hidden" name="action" value="purge" />
                <?php echo CSRFProtection::getTokenField(); ?>
                <input type="text" name="confirm" placeholder="Type CONFIRM" required />
                <button type="submit" class="btn btn-danger">Delete All Members and Data</button>
            </form>
        </section>

        <?php if ($didPurge): ?>
            <section class="card">
                <h3>Purge Result</h3>
                <?php if (!empty($purgeResult['deleted'])): ?>
                    <h4>Deleted Rows</h4>
                    <table class="table">
                        <thead><tr><th>Table</th><th>Affected Rows</th></tr></thead>
                        <tbody>
                            <?php foreach ($purgeResult['deleted'] as $table => $affected): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table); ?></td>
                                    <td><?php echo (int)$affected; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <?php if (!empty($purgeResult['errors'])): ?>
                    <h4>Errors</h4>
                    <ul class="text-danger">
                        <?php foreach ($purgeResult['errors'] as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
</body>
</html>