<?php
/**
 * Admin - Audit Logs Viewer
 * Displays recent audit entries with simple filtering.
 */

require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';

// Require admin login
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Discover available audit log files (current and rotated archives)
$logsDir = realpath(__DIR__ . '/../../storage/logs');
$availableLogs = [];
if ($logsDir && is_dir($logsDir)) {
    $files = glob($logsDir . DIRECTORY_SEPARATOR . 'audit*.log');
    // Sort by modification time descending
    usort($files, function($a, $b){ return filemtime($b) <=> filemtime($a); });
    $availableLogs = array_map('realpath', $files ?: []);
}

// Pick selected file (default to audit.log)
$selectedFileParam = isset($_GET['file']) ? trim($_GET['file']) : '';
$defaultLogPath = $logsDir ? ($logsDir . DIRECTORY_SEPARATOR . 'audit.log') : '';
$selectedLogPath = $defaultLogPath;
if ($selectedFileParam) {
    // Map by basename to avoid path traversal; find matching in availableLogs
    foreach ($availableLogs as $p) {
        if (basename($p) === $selectedFileParam) { $selectedLogPath = $p; break; }
    }
}
$logFile = $selectedLogPath ? realpath($selectedLogPath) : false;
$hasLog = $logFile && file_exists($logFile);

// Filters
$actor = isset($_GET['actor']) ? trim($_GET['actor']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$entity = isset($_GET['entity']) ? trim($_GET['entity']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(10, min(1000, (int)$_GET['limit'])) : 200;
// Date range (YYYY-MM-DD)
$fromDate = isset($_GET['from']) ? trim($_GET['from']) : '';
$toDate = isset($_GET['to']) ? trim($_GET['to']) : '';
$fromTs = $fromDate ? strtotime($fromDate . ' 00:00:00') : null;
$toTs = $toDate ? strtotime($toDate . ' 23:59:59') : null;

// Read last N lines (with a cap) from log
$entries = [];
if ($hasLog) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) { $lines = []; }
    $maxRead = min(count($lines), max($limit * 5, $limit));
    $slice = array_slice($lines, -$maxRead);
    foreach ($slice as $line) {
        $data = json_decode($line, true);
        if (!is_array($data)) { continue; }
        // Date filter
        if (($fromTs !== null || $toTs !== null) && isset($data['timestamp'])) {
            $ts = strtotime($data['timestamp']);
            if ($ts !== false) {
                if ($fromTs !== null && $ts < $fromTs) { continue; }
                if ($toTs !== null && $ts > $toTs) { continue; }
            }
        }
        // Simple filtering
        if ($actor && (!isset($data['actor_name']) || stripos($data['actor_name'], $actor) === false)) continue;
        if ($action && (strcasecmp($data['action'] ?? '', $action) !== 0)) continue;
        if ($entity && (strcasecmp($data['entity'] ?? '', $entity) !== 0)) continue;
        if ($q) {
            $hay = json_encode($data);
            if (stripos($hay, $q) === false) continue;
        }
        $entries[] = $data;
        if (count($entries) >= $limit) break;
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pageTitle = 'Audit Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> - <?php echo h(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/csims-colors.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .filters { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #e5e7eb; padding: 8px; font-size: 14px; }
        .table th { background: #f3f4f6; text-align: left; }
        .muted { color: #6b7280; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 6px; font-size: 12px; }
        .badge-admin { background: #dbeafe; color: #1e3a8a; }
        .badge-member { background: #dcfce7; color: #166534; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .actions { display: flex; gap: 8px; }
    </style>
    <?php include_once __DIR__ . '/../../views/includes/header.php'; ?>
    <?php include_once __DIR__ . '/../../views/includes/sidebar.php'; ?>
    <script>
      function resetFilters(){
        const url = new URL(window.location.href);
        ['actor','action','entity','q','limit'].forEach(k=>url.searchParams.delete(k));
        window.location.href = url.toString();
      }
    </script>
  </head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="text-primary-800"><?php echo h($pageTitle); ?></h1>
            <div class="actions">
                <button class="btn" onclick="location.reload()">Refresh</button>
                <button class="btn" onclick="resetFilters()">Reset Filters</button>
            </div>
        </div>
        <div class="filters">
            <form method="get" class="grid grid-cols-4 gap-3" style="width:100%">
                <input type="text" name="actor" value="<?php echo h($actor); ?>" placeholder="Actor" class="form-control" />
                <input type="text" name="action" value="<?php echo h($action); ?>" placeholder="Action" class="form-control" />
                <input type="text" name="entity" value="<?php echo h($entity); ?>" placeholder="Entity" class="form-control" />
                <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Search in JSON" class="form-control" />
                <input type="date" name="from" value="<?php echo h($fromDate); ?>" placeholder="From (YYYY-MM-DD)" class="form-control" />
                <input type="date" name="to" value="<?php echo h($toDate); ?>" placeholder="To (YYYY-MM-DD)" class="form-control" />
                <div style="grid-column: span 2;">
                    <label class="muted">Log file</label>
                    <select name="file" class="form-control">
                        <?php
                        $selectedBase = $logFile ? basename($logFile) : '';
                        if (empty($availableLogs)) {
                            echo '<option value="">(none found)</option>';
                        } else {
                            foreach ($availableLogs as $p) {
                                $base = basename($p);
                                $sel = ($base === $selectedBase) ? 'selected' : '';
                                echo '<option value="' . h($base) . '" ' . $sel . '>' . h($base) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="muted" style="grid-column: span 4;">
                    File: <?php echo h($logFile ? basename($logFile) : '(none)'); ?> — Showing last <?php echo h($limit); ?> matched entries (reading up to <?php echo h(isset($maxRead) ? $maxRead : $limit); ?> lines)
                </div>
                <div style="grid-column: span 4;">
                    <label class="muted">Limit</label>
                    <input type="number" name="limit" value="<?php echo h($limit); ?>" min="10" max="1000" class="form-control" />
                    <button type="submit" class="btn btn-primary" style="margin-left:8px">Apply</button>
                </div>
            </form>
        </div>

        <?php if (!$hasLog): ?>
            <div class="alert alert-warning">Audit log file not found at: <?php echo h($logFile ?: '(unknown)'); ?></div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Actor</th>
                        <th>Type</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Entity ID</th>
                        <th>IP</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td><?php echo h($e['timestamp'] ?? ''); ?></td>
                            <td><?php echo h($e['actor_name'] ?? ''); ?>
                                <span class="muted">(<?php echo h($e['actor_source'] ?? ''); ?>)</span>
                            </td>
                            <td>
                                <?php $ut = strtolower($e['user_type'] ?? ''); ?>
                                <span class="badge <?php echo $ut==='admin'?'badge-admin':'badge-member'; ?>">
                                    <?php echo h($e['user_type'] ?? ''); ?>
                                </span>
                            </td>
                            <td><?php echo h($e['action'] ?? ''); ?></td>
                            <td><?php echo h($e['entity'] ?? ''); ?></td>
                            <td><?php echo h($e['entity_id'] ?? ''); ?></td>
                            <td><?php echo h($e['ip_address'] ?? ''); ?></td>
                            <td class="muted" title="<?php echo h($e['user_agent'] ?? ''); ?>">
                                <?php echo h(substr((string)($e['user_agent'] ?? ''), 0, 48)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="8" class="muted">No matching entries</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php include_once __DIR__ . '/../../views/includes/footer.php'; ?>
</body>
</html>