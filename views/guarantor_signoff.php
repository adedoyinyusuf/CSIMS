<?php
// Public Guarantor Sign-off Page
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/services/guarantor_signoff_service.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$error = '';
$success = '';
$request = null;

if ($token === '') {
    $error = 'Invalid or missing sign-off token.';
} else {
    $request = get_signoff_request_by_token($conn, $token);
    if (!$request) {
        $error = 'This sign-off link is invalid or has expired.';
    } elseif (strtolower($request['status']) === 'signed') {
        $success = 'You have already signed off. Thank you.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request && !$success) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $agree = isset($_POST['agree']) ? (bool)$_POST['agree'] : false;

    if (!$agree) {
        $error = 'You must confirm and agree to act as guarantor.';
    } else {
        $updated = mark_signoff_signed($conn, $token, $name ?: null, $email ?: null);
        if ($updated) {
            $success = 'Your sign-off has been recorded and sent to admin.';
        } else {
            $error = 'Failed to record sign-off. Please try again later.';
        }
    }
}

// Basic Tailwind-like minimal styles for clarity without requiring assets
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guarantor Sign-off</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:#f7fafc; margin:0; }
        .container { max-width: 640px; margin: 40px auto; background:#fff; border-radius:12px; box-shadow: 0 10px 20px rgba(0,0,0,0.06); }
        .header { padding: 24px 28px; border-bottom:1px solid #e5e7eb; }
        .title { margin:0; font-size: 20px; color:#111827; }
        .content { padding: 24px 28px; }
        .input { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; }
        .label { font-size: 13px; color:#374151; margin-bottom:6px; display:block; }
        .row { margin-bottom:14px; }
        .btn { background:#2563eb; color:#fff; border:none; padding:12px 16px; border-radius:8px; cursor:pointer; }
        .btn:hover { background:#1d4ed8; }
        .muted { color:#6b7280; font-size: 13px; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:14px; }
        .alert-error { background:#fee2e2; color:#991b1b; }
        .alert-success { background:#dcfce7; color:#14532d; }
        .card { background:#f9fafb; border:1px solid #e5e7eb; padding:12px; border-radius:8px; }
    </style>
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <script>
        function disableOnSubmit(btn){ btn.disabled=true; btn.innerText='Submitting...'; }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Guarantor Sign-off</h1>
            <p class="muted">Confirm your guarantee for the listed loan.</p>
        </div>
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($request && !$success): ?>
                <div class="card" style="margin-bottom:16px;">
                    <div style="display:flex; gap:24px;">
                        <div style="flex:1;">
                            <div class="muted">Loan</div>
                            <div><strong>#<?php echo (int)$request['loan_id']; ?></strong></div>
                        </div>
                        <div style="flex:1;">
                            <div class="muted">Channel</div>
                            <div><?php echo htmlspecialchars(ucfirst($request['channel'])); ?></div>
                        </div>
                        <div style="flex:1;">
                            <div class="muted">Status</div>
                            <div><?php echo htmlspecialchars(ucfirst($request['status'])); ?></div>
                        </div>
                    </div>
                </div>

                <form method="post">
                    <div class="row">
                        <label class="label" for="name">Your Full Name</label>
                        <input class="input" type="text" id="name" name="name" value="<?php echo htmlspecialchars($request['guarantor_name'] ?? ''); ?>" placeholder="e.g., Jane Doe" required>
                    </div>
                    <div class="row">
                        <label class="label" for="email">Email Address</label>
                        <input class="input" type="email" id="email" name="email" value="<?php echo htmlspecialchars($request['guarantor_email'] ?? ''); ?>" placeholder="e.g., jane@example.com" required>
                    </div>
                    <div class="row" style="display:flex; align-items:flex-start; gap:10px;">
                        <input type="checkbox" id="agree" name="agree" style="margin-top:2px;" required>
                        <label for="agree" class="label" style="margin:0;">I confirm I agree to act as a guarantor for loan #<?php echo (int)$request['loan_id']; ?> and understand my obligations.</label>
                    </div>
                    <div class="row">
                        <button type="submit" class="btn" onclick="disableOnSubmit(this)">Sign Off</button>
                    </div>
                </form>
                <p class="muted">Your confirmation is securely recorded and submitted to the admin for review.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>