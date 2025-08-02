<?php
require_once '../config/config.php';

// Clear temporary credentials from session
if ($session->has('temp_username')) {
    $session->remove('temp_username');
}

if ($session->has('temp_password')) {
    $session->remove('temp_password');
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>