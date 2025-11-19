<?php
require_once __DIR__ . '/../includes/db.php';

class SecurityController
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getSecurityDashboard(string $period = 'week'): array
    {
        $range = $this->resolvePeriodRange($period);

        // Basic metrics with schema-aware fallbacks
        // Failed logins: prefer security_logs (event_type = 'failed_login'); fallback to login_attempts if present
        if ($this->hasTable('security_logs') && $this->hasColumn('security_logs', 'created_at')) {
            $failedLogins = (int)($this->scalar(
                "SELECT COUNT(*) FROM security_logs WHERE event_type = 'failed_login' AND created_at BETWEEN ? AND ?",
                [$range['start'], $range['end']], 'ss'
            ) ?? 0);
        } elseif ($this->hasTable('login_attempts') && $this->hasColumn('login_attempts', 'created_at')) {
            $failedLogins = (int)($this->scalar(
                "SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at BETWEEN ? AND ?",
                [$range['start'], $range['end']], 'ss'
            ) ?? 0);
        } else {
            $failedLogins = 0;
        }

        // Locked accounts: admins table
        $lockedAccounts = (int)($this->scalar("SELECT COUNT(*) FROM admins WHERE status = 'Locked'", [], '') ?? 0);

        // Password resets: prefer admins.reset_token/reset_expiry; fallback to password_resets.created_at if present
        if ($this->hasTable('admins') && $this->hasColumn('admins', 'reset_token') && $this->hasColumn('admins', 'reset_expiry')) {
            $passwordResets = (int)($this->scalar(
                "SELECT COUNT(*) FROM admins WHERE reset_token IS NOT NULL AND reset_expiry BETWEEN ? AND ?",
                [$range['start'], $range['end']], 'ss'
            ) ?? 0);
        } elseif ($this->hasTable('password_resets') && $this->hasColumn('password_resets', 'created_at')) {
            $passwordResets = (int)($this->scalar(
                "SELECT COUNT(*) FROM password_resets WHERE created_at BETWEEN ? AND ?",
                [$range['start'], $range['end']], 'ss'
            ) ?? 0);
        } else {
            $passwordResets = 0;
        }

        $securityScore = max(0, 100 - ($failedLogins * 2 + $lockedAccounts * 5));

        // Recent events: prefer security_logs; fallback to security_events if present
        if ($this->hasTable('security_logs') && $this->hasColumn('security_logs', 'created_at')) {
            // Choose a description column that exists
            if ($this->hasColumn('security_logs', 'event_description')) {
                $descCol = 'event_description';
            } elseif ($this->hasColumn('security_logs', 'description')) {
                $descCol = 'description';
            } elseif ($this->hasColumn('security_logs', 'message')) {
                $descCol = 'message';
            } elseif ($this->hasColumn('security_logs', 'details')) {
                $descCol = 'details';
            } else {
                $descCol = "'N/A'"; // fallback literal
            }

            $recentEvents = $this->rows(
                "SELECT event_type, severity, {$descCol} AS description, created_at FROM security_logs ORDER BY created_at DESC LIMIT 10"
            );
        } elseif ($this->hasTable('security_events') && $this->hasColumn('security_events', 'created_at')) {
            $recentEvents = $this->rows(
                "SELECT event_type, severity, description, created_at FROM security_events ORDER BY created_at DESC LIMIT 10"
            );
        } else {
            $recentEvents = [];
        }

        return [
            'metrics' => [
                'failed_logins' => $failedLogins,
                'locked_accounts' => $lockedAccounts,
                'password_resets' => $passwordResets,
                'security_score' => $securityScore,
            ],
            'recent_events' => $recentEvents,
        ];
    }

    public function performSecurityAudit(): array
    {
        // Weak password heuristic:
        // Count credentials that look like plaintext/legacy hashes (md5/sha1) or not bcrypt/argon2
        $weakPasswords = 0;
        if ($this->hasTable('admins') && $this->hasColumn('admins', 'password')) {
            // Detect MD5/legacy hashes or non-bcrypt/argon2 patterns
            $sql = "SELECT COUNT(*) FROM admins 
                    WHERE password REGEXP '^[0-9a-f]{32}$' 
                       OR password REGEXP '^[0-9a-f]{40}$' 
                       OR password REGEXP '^[0-9a-f]{64}$' 
                       OR (password NOT LIKE '\$2y\$%' AND password NOT LIKE '\$argon2i\$%' AND password NOT LIKE '\$argon2id\$%')";
            $weakPasswords += (int)($this->scalar($sql, [], '') ?? 0);
        }
        if ($this->hasTable('members') && $this->hasColumn('members', 'password')) {
            $sql = "SELECT COUNT(*) FROM members 
                    WHERE password REGEXP '^[0-9a-f]{32}$' 
                       OR password REGEXP '^[0-9a-f]{40}$' 
                       OR password REGEXP '^[0-9a-f]{64}$' 
                       OR (password NOT LIKE '\$2y\$%' AND password NOT LIKE '\$argon2i\$%' AND password NOT LIKE '\$argon2id\$%')";
            $weakPasswords += (int)($this->scalar($sql, [], '') ?? 0);
        }

        // Stale backups heuristic:
        // If backups table exists, flag stale when no backup within last 7 days.
        $staleBackups = 0;
        if ($this->hasTable('backups')) {
            // Prefer created_at; fallback to backup_date
            $dateCol = $this->hasColumn('backups', 'created_at') ? 'created_at' : ($this->hasColumn('backups', 'backup_date') ? 'backup_date' : null);
            if ($dateCol) {
                $lastBackup = $this->scalar("SELECT MAX($dateCol) FROM backups", [], '');
                if ($lastBackup) {
                    $lastTs = strtotime((string)$lastBackup);
                    if ($lastTs === false || (time() - $lastTs) > (7 * 24 * 60 * 60)) {
                        $staleBackups = 1; // Flag as stale
                    }
                } else {
                    $staleBackups = 1; // No backups recorded
                }
            }
        }
        $inactiveAdmins = (int)($this->scalar("SELECT COUNT(*) FROM admins WHERE status <> 'Active'", [], '') ?? 0);

        return [
            'weak_passwords' => $weakPasswords,
            'stale_backups' => $staleBackups,
            'inactive_admins' => $inactiveAdmins,
            'issues_found' => ($weakPasswords + $staleBackups + $inactiveAdmins),
        ];
    }

    public function unlockAccount(string $username, ?int $actorId = null): bool
    {
        $sql = "UPDATE admins SET status = 'Active' WHERE username = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return false; }
        $stmt->bind_param('s', $username);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    public function exportSecurityReport(string $type = 'csv'): void
    {
        $data = $this->getSecurityDashboard('week');
        switch ($type) {
            case 'csv':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="security_report_' . date('Y-m-d') . '.csv"');
                echo "Metric,Value\n";
                foreach ($data['metrics'] as $k => $v) {
                    echo $k . ',' . $v . "\n";
                }
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode($data);
        }
    }

    private function resolvePeriodRange(string $period): array
    {
        $today = date('Y-m-d');
        switch ($period) {
            case 'week':
                $start = date('Y-m-d', strtotime('monday this week'));
                $end = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'month':
                $start = date('Y-m-01');
                $end = date('Y-m-t');
                break;
            case 'quarter':
                $q = ceil(date('n') / 3);
                $start = date('Y-' . sprintf('%02d', ($q - 1) * 3 + 1) . '-01');
                $end = date('Y-m-t', strtotime($start . ' +2 months'));
                break;
            case 'year':
                $start = date('Y-01-01');
                $end = date('Y-12-31');
                break;
            default:
                $start = $today;
                $end = $today;
        }
        return ['start' => $start, 'end' => $end];
    }

    private function scalar(string $sql, array $params = [], string $types = '')
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return null; }
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_array() : null;
        $stmt->close();
        return $row ? array_values($row)[0] : null;
    }

    private function rows(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { return []; }
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
        $stmt->close();
        return $rows;
    }

    // Schema helpers for robust queries
    private function hasTable(string $table): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        if (!$stmt) { return false; }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = $res ? (int)($res->fetch_array()[0] ?? 0) : 0;
        $stmt->close();
        return $count > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        if (!$stmt) { return false; }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = $res ? (int)($res->fetch_array()[0] ?? 0) : 0;
        $stmt->close();
        return $count > 0;
    }
}
?>