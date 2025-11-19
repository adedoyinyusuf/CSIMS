<?php
// Guarantor Sign-off Service (procedural helpers for legacy views/controllers)
// Provides: table creation, request creation, email dispatch, and sign-off handling

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../email_service.php';

/**
 * Ensure the guarantor sign-off requests table exists
 */
function ensure_guarantor_signoff_tables(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS guarantor_signoff_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        loan_guarantor_id INT NULL,
        guarantor_member_id INT NULL,
        guarantor_email VARCHAR(255) NULL,
        guarantor_name VARCHAR(255) NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        channel VARCHAR(32) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        signed_at DATETIME NULL,
        INDEX idx_loan_id (loan_id),
        INDEX idx_token (token),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql);
}

/**
 * Create a sign-off request record and return token
 */
function create_signoff_request(mysqli $conn, int $loan_id, string $channel, ?int $loan_guarantor_id = null, ?int $guarantor_member_id = null, ?string $guarantor_email = null, ?string $guarantor_name = null): array {
    ensure_guarantor_signoff_tables($conn);
    $token = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("INSERT INTO guarantor_signoff_requests (loan_id, loan_guarantor_id, guarantor_member_id, guarantor_email, guarantor_name, token, channel, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('iiissss', $loan_id, $loan_guarantor_id, $guarantor_member_id, $guarantor_email, $guarantor_name, $token, $channel);
    $stmt->execute();
    $id = $conn->insert_id;
    return ['id' => $id, 'token' => $token];
}

/**
 * Lookup a sign-off request by token
 */
function get_signoff_request_by_token(mysqli $conn, string $token): ?array {
    ensure_guarantor_signoff_tables($conn);
    $stmt = $conn->prepare("SELECT * FROM guarantor_signoff_requests WHERE token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc() ?: null;
}

/**
 * Mark sign-off as signed and, if linked, update loan_guarantors status
 */
function mark_signoff_signed(mysqli $conn, string $token, ?string $name = null, ?string $email = null): bool {
    ensure_guarantor_signoff_tables($conn);
    $req = get_signoff_request_by_token($conn, $token);
    if (!$req) return false;

    // Update request status
    $stmt = $conn->prepare("UPDATE guarantor_signoff_requests SET status = 'signed', signed_at = NOW(), guarantor_name = COALESCE(?, guarantor_name), guarantor_email = COALESCE(?, guarantor_email) WHERE id = ?");
    $stmt->bind_param('ssi', $name, $email, $req['id']);
    $ok = $stmt->execute();

    // If linked to a loan_guarantor row, set status to 'Signed'
    if ($ok && !empty($req['loan_guarantor_id'])) {
        $gid = (int)$req['loan_guarantor_id'];
        $conn->query("UPDATE loan_guarantors SET status = 'Signed', updated_at = NOW() WHERE guarantor_id = " . $gid);
    }

    // Notify admin of completed sign-off
    if ($ok) {
        notify_admin_guarantor_signed($req['loan_id'], $name ?: ($req['guarantor_name'] ?? 'Guarantor'), $email ?: ($req['guarantor_email'] ?? ''), $req['channel']);
    }
    return $ok;
}

/**
 * Send sign-off email with unique link
 */
function send_signoff_email(string $recipientEmail, string $recipientName, string $token, int $loan_id): bool {
    $emailService = new EmailService();
    $signUrl = get_signoff_url($token);
    $subject = "Loan Guarantor Sign-off Request (Loan #$loan_id)";
    $html = "<p>Dear " . htmlspecialchars($recipientName) . ",</p>"
          . "<p>You have been listed as a guarantor for loan #$loan_id. Please review and sign off using the link below:</p>"
          . "<p><a href='" . htmlspecialchars($signUrl) . "' style='color:#2563EB;'>Sign Off as Guarantor</a></p>"
          . "<p>If you did not expect this request, contact the cooperative admin.</p>";
    return $emailService->send($recipientEmail, $subject, $html, $recipientName);
}

/**
 * Compose absolute sign-off URL
 */
function get_signoff_url(string $token): string {
    $base = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost:8001';
    return rtrim($base, '/') . '/views/guarantor_signoff.php?token=' . urlencode($token);
}

/**
 * Resolve admin email address
 */
function get_admin_email(): string {
    $candidates = [
        $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: null,
        $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: null,
        $_ENV['EMAIL_FROM'] ?? getenv('EMAIL_FROM') ?: null,
    ];
    foreach ($candidates as $c) {
        if ($c && filter_var($c, FILTER_VALIDATE_EMAIL)) {
            return $c;
        }
    }
    return 'admin@localhost';
}

/**
 * Send admin notification when a guarantor signs
 */
function notify_admin_guarantor_signed(int $loan_id, string $guarantor_name, string $guarantor_email, string $channel): bool {
    $emailService = new EmailService();
    $adminEmail = get_admin_email();
    $subject = "Guarantor signed for Loan #$loan_id";
    $html = "<p>Guarantor sign-off completed.</p>"
          . "<ul>"
          . "<li><strong>Loan:</strong> #$loan_id</li>"
          . "<li><strong>Name:</strong> " . htmlspecialchars($guarantor_name) . "</li>"
          . "<li><strong>Email:</strong> " . htmlspecialchars($guarantor_email ?: 'N/A') . "</li>"
          . "<li><strong>Channel:</strong> " . htmlspecialchars(ucfirst($channel)) . "</li>"
          . "</ul>";
    return $emailService->send($adminEmail, $subject, $html, 'Admin');
}

/**
 * Convenience: create and send sign-off for a non-member guarantor by email
 */
function create_and_send_non_member_signoff(mysqli $conn, int $loan_id, string $email, string $name): array {
    $req = create_signoff_request($conn, $loan_id, 'email', null, null, $email, $name);
    send_signoff_email($email, $name, $req['token'], $loan_id);
    return $req;
}

?>