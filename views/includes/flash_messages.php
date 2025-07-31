<?php
// Flash Messages Display
// This file handles the display of flash messages (success, error, warning, info)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to set flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

// Function to get and clear flash messages
function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// Display flash messages
$flash_messages = getFlashMessages();
if (!empty($flash_messages)): ?>
    <div class="flash-messages mb-3">
        <?php foreach ($flash_messages as $flash): ?>
            <?php
            $alert_class = 'alert-info'; // default
            switch ($flash['type']) {
                case 'success':
                    $alert_class = 'alert-success';
                    break;
                case 'error':
                case 'danger':
                    $alert_class = 'alert-danger';
                    break;
                case 'warning':
                    $alert_class = 'alert-warning';
                    break;
                case 'info':
                    $alert_class = 'alert-info';
                    break;
            }
            ?>
            <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>