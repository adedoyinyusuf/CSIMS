<?php
// Setup script to create and seed the `system_config` table for SystemConfigService
// Usage: open in browser at `/setup/database/create_system_config_table.php`

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/config/database.php';

function respond($status, $message, $details = []) {
    header('Content-Type: text/html; charset=utf-8');
    $title = $status === 'success' ? 'System Config Setup Complete' : 'System Config Setup Error';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;max-width:880px;margin:40px auto;padding:0 20px;color:#222}h1{font-size:22px;margin:0 0 12px}code{background:#f5f5f7;padding:2px 6px;border-radius:4px}pre{background:#f5f5f7;padding:12px;border-radius:6px;overflow:auto} .ok{color:#0a7} .err{color:#c00} .muted{color:#666} ul{line-height:1.6} .box{border:1px solid #eee;padding:14px;border-radius:6px;margin:16px 0}</style>';
    echo '</head><body>';
    echo '<h1>' . ($status === 'success' ? '✅ ' : '⚠️ ') . htmlspecialchars($title) . '</h1>';
    echo '<p>' . htmlspecialchars($message) . '</p>';
    if (!empty($details)) {
        echo '<div class="box"><pre>' . htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)) . '</pre></div>';
    }
    echo '<p class="muted">If you re-run this, it will ensure the table exists and seed any missing keys.</p>';
    echo '<p><a href="/views/admin/settings.php">Go to Admin Settings</a></p>';
    echo '</body></html>';
    exit;
}

try {
    $db = new PdoDatabase();
    $pdo = $db->getConnection();

    // Create table if not exists
    $createSql = <<<SQL
CREATE TABLE IF NOT EXISTS `system_config` (
  `config_key` VARCHAR(64) NOT NULL,
  `config_value` TEXT NULL,
  `config_type` VARCHAR(16) NOT NULL DEFAULT 'string',
  `description` TEXT NULL,
  `category` VARCHAR(64) NOT NULL DEFAULT 'general',
  `is_editable` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_restart` TINYINT(1) NOT NULL DEFAULT 0,
  `validation_regex` VARCHAR(255) NULL,
  `min_value` DECIMAL(16,2) NULL,
  `max_value` DECIMAL(16,2) NULL,
  `updated_by` INT NULL,
  `updated_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $pdo->exec($createSql);

    // Seed baseline configuration values used across the app
    $seeds = [
        // General
        ['SYSTEM_NAME', 'CSIMS', 'string', 'System display name', 'general', 1, 0, null, null, null],
        ['SYSTEM_EMAIL', 'admin@example.com', 'string', 'System contact email', 'general', 1, 0, null, null, null],
        ['SYSTEM_PHONE', '+0000000000', 'string', 'System contact phone', 'general', 1, 0, null, null, null],
        ['SYSTEM_ADDRESS', 'Organization Address', 'string', 'System address', 'general', 1, 0, null, null, null],

        // Membership
        ['DEFAULT_MEMBERSHIP_FEE', '1000.00', 'decimal', 'Default membership fee', 'membership', 1, 0, null, 0, 1000000],
        ['MEMBERSHIP_DURATION', '12', 'integer', 'Membership duration in months', 'membership', 1, 0, null, 1, 120],
        ['LATE_PAYMENT_PENALTY', '2.00', 'decimal', 'Late payment penalty percentage', 'membership', 1, 0, null, 0, 100],

        // Savings
        ['MIN_MANDATORY_SAVINGS', '5000.00', 'decimal', 'Minimum mandatory savings', 'savings', 1, 0, null, 0, 100000000],
        ['MAX_MANDATORY_SAVINGS', '200000.00', 'decimal', 'Maximum mandatory savings', 'savings', 1, 0, null, 0, 100000000],
        ['SAVINGS_INTEREST_RATE', '6.00', 'decimal', 'Savings annual interest rate (%)', 'savings', 1, 0, null, 0, 100],
        ['WITHDRAWAL_MAX_PERCENTAGE', '80.00', 'decimal', 'Max withdrawal as % of balance', 'savings', 1, 0, null, 0, 100],
        ['WITHDRAWAL_PROCESSING_DAYS', '5', 'integer', 'Withdrawal processing time in days', 'savings', 1, 0, null, 0, 365],

        // Loans
        ['LOAN_TO_SAVINGS_MULTIPLIER', '3.00', 'decimal', 'Loan to savings multiplier', 'loan', 1, 0, null, 0, 100],
        ['MIN_MEMBERSHIP_MONTHS', '6', 'integer', 'Min months of membership before loans', 'loan', 1, 0, null, 0, 240],
        ['MAX_ACTIVE_LOANS_PER_MEMBER', '3', 'integer', 'Max active loans per member', 'loan', 1, 0, null, 0, 20],
        ['LOAN_PENALTY_RATE', '2.00', 'decimal', 'Loan penalty rate (%)', 'loan', 1, 0, null, 0, 100],
        ['DEFAULT_GRACE_PERIOD', '7', 'integer', 'Default grace period in days', 'loan', 1, 0, null, 0, 365],
        ['MAX_LOAN_AMOUNT', '5000000.00', 'decimal', 'Maximum loan amount', 'loan', 1, 0, null, 0, 1000000000],
        ['GUARANTOR_REQUIREMENT_THRESHOLD', '500000.00', 'decimal', 'Amount threshold requiring guarantors', 'loan', 1, 0, null, 0, 1000000000],
        ['MIN_GUARANTORS_REQUIRED', '2', 'integer', 'Minimum guarantors required', 'loan', 1, 0, null, 0, 10],
        ['DEFAULT_INTEREST_RATE', '12.00', 'decimal', 'Default loan interest rate (%)', 'loan', 1, 0, null, 0, 100],
        ['MAX_LOAN_DURATION', '24', 'integer', 'Maximum loan duration in months', 'loan', 1, 0, null, 1, 120],
        ['MIN_CONTRIBUTION_MONTHS', '3', 'integer', 'Min contribution months for loan eligibility', 'loan', 1, 0, null, 0, 240],

        // Workflow/System Ops
        ['ADMIN_APPROVAL_WORKFLOW', '3', 'integer', 'Number of approval levels', 'workflow', 1, 0, null, 1, 10],
        ['AUTO_APPROVAL_LIMIT', '100000.00', 'decimal', 'Auto-approval loan limit', 'workflow', 1, 0, null, 0, 100000000],
        ['APPROVAL_TIMEOUT_DAYS', '7', 'integer', 'Approval timeout in days', 'workflow', 1, 0, null, 0, 365],
        ['AUTO_DEDUCTION_DAY', '28', 'integer', 'Day of month for auto-deduction', 'system', 1, 0, null, 1, 28],
        ['INTEREST_POSTING_DAY', '1', 'integer', 'Day of month to post interest', 'system', 1, 0, null, 1, 28],
        ['PENALTY_CALCULATION_DAY', '2', 'integer', 'Day of month to calculate penalties', 'system', 1, 0, null, 1, 28],
        ['DEFAULT_FLAG_AFTER_MISSED_PAYMENTS', '3', 'integer', 'Missed payments before default flag', 'system', 1, 0, null, 0, 24],
    ];

    $insertSql = $pdo->prepare(
        "INSERT INTO system_config (config_key, config_value, config_type, description, category, is_editable, requires_restart, validation_regex, min_value, max_value, updated_by, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), config_type = VALUES(config_type), description = VALUES(description), category = VALUES(category), is_editable = VALUES(is_editable), requires_restart = VALUES(requires_restart), validation_regex = VALUES(validation_regex), min_value = VALUES(min_value), max_value = VALUES(max_value)"
    );

    $created = 0; $updated = 0; $errors = [];
    foreach ($seeds as $row) {
        try {
            $ok = $insertSql->execute($row);
            if ($ok) {
                // Detect if it was insert or update by checking rowCount (MySQL returns 2 on update)
                $rc = $insertSql->rowCount();
                if ($rc === 1) { $created++; } else { $updated++; }
            }
        } catch (Throwable $e) {
            $errors[] = 'Seed ' . $row[0] . ' failed: ' . $e->getMessage();
        }
    }

    // Count total keys available
    $countStmt = $pdo->query('SELECT COUNT(*) AS cnt FROM system_config');
    $count = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    respond('success', 'system_config table is ready. Seeds applied.', [
        'created' => $created,
        'updated' => $updated,
        'total_keys' => $count,
    ]);
} catch (Throwable $e) {
    respond('error', 'Failed to setup system_config: ' . $e->getMessage());
}