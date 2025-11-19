<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../controllers/member_controller.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit();
}

// Check if we have the necessary session data
if (!isset($_SESSION['reset_password_data'])) {
    error_log('No reset_password_data found in session. Available session keys: ' . implode(', ', array_keys($_SESSION)));
    $_SESSION['error'] = 'Password reset data not found. Please try again. Debug: Session keys available: ' . implode(', ', array_keys($_SESSION));
    header('Location: ' . BASE_URL . '/views/admin/members.php');
    exit();
}

// Debug: Log what we received
error_log('Password reset success page - received data: ' . print_r($_SESSION['reset_password_data'], true));

$resetData = $_SESSION['reset_password_data'];
$member_id = $resetData['member_id'];
$new_password = $resetData['new_password'];
$member_name = $resetData['member_name'];
$email_sent = $resetData['email_sent'] ?? false;

// Clear the session data for security
unset($_SESSION['reset_password_data']);

// Get member details
$memberController = new MemberController();
$member = $memberController->getMemberById($member_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Include Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main">
            <!-- Include Header -->
            <?php include '../includes/header.php'; ?>
            
            <main class="content">
                <div class="container-fluid p-0">
                    <div class="row justify-content-center">
                        <div class="col-md-8 col-lg-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-success text-white">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Password Reset Successful
                                    </h4>
                                </div>
                                <div class="card-body p-4">
                                    <div class="alert alert-success">
                                        <i class="fas fa-user-check me-2"></i>
                                        Password has been successfully reset for <strong><?php echo htmlspecialchars($member_name); ?></strong>
                                    </div>
                                    
                                    <div class="alert alert-warning border-warning">
                                        <h5 class="alert-heading">
                                            <i class="fas fa-key me-2"></i>
                                            New Password Generated
                                        </h5>
                                        <hr>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="password-display">
                                                <span class="badge bg-dark fs-6 p-3 font-monospace" id="passwordText"><?php echo htmlspecialchars($new_password); ?></span>
                                            </div>
                                            <button class="btn btn-outline-primary btn-sm" onclick="copyPassword()" title="Copy to clipboard">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                        <hr>
                                        <p class="mb-0">
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            <strong>Important:</strong> Please copy this password and share it with the member through a secure channel. This password will not be displayed again.
                                        </p>
                                    </div>
                                    
                                    <?php if ($email_sent): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-envelope me-2"></i>
                                        The new password has been sent to the member's email address: <strong><?php echo htmlspecialchars($member['email']); ?></strong>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-envelope-open-text me-2"></i>
                                        No email was sent. Please share the password with the member manually.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Member Login Information
                                            </h6>
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <strong>Username:</strong><br>
                                                    <span class="font-monospace"><?php echo htmlspecialchars($member['username']); ?></span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <strong>Member ID:</strong><br>
                                                    <span class="font-monospace"><?php echo htmlspecialchars($member['member_id']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                        <a href="<?php echo BASE_URL; ?>/views/admin/view_member.php?id=<?php echo $member_id; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-user me-2"></i>
                                            Back to Member Profile
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/views/admin/members.php" class="btn btn-primary">
                                            <i class="fas fa-users me-2"></i>
                                            All Members
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function copyPassword() {
            const passwordText = document.getElementById('passwordText').textContent;
            
            // Use the modern Clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(passwordText).then(function() {
                    showCopySuccess();
                }).catch(function(err) {
                    fallbackCopyTextToClipboard(passwordText);
                });
            } else {
                fallbackCopyTextToClipboard(passwordText);
            }
        }
        
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess();
                } else {
                    showCopyError();
                }
            } catch (err) {
                showCopyError();
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopySuccess() {
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-success');
            
            setTimeout(function() {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-primary');
            }, 2000);
        }
        
        function showCopyError() {
            alert('Failed to copy password. Please select and copy manually.');
        }
        
        // Auto-select password text when clicked
        document.getElementById('passwordText').addEventListener('click', function() {
            const range = document.createRange();
            range.selectNode(this);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
        });
    </script>
</body>
</html>