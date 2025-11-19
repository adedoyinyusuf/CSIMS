<?php
session_start();
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/member_type_controller.php';

$auth = new AuthController();
$current_user = $auth->getCurrentUser();
if (!$current_user) {
    header('Location: ../auth/login.php');
    exit();
}

$controller = new MemberTypeController();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: member_types.php');
    exit();
}

// Check if type has members before allowing deletion
$type = $controller->getMemberTypeById($id);
if (!$type) {
    header('Location: member_types.php');
    exit();
}

if ((int)$type['member_count'] > 0) {
    $_SESSION['success_message'] = 'Cannot delete member type: it has associated members.';
    header('Location: member_types.php');
    exit();
}

$deleted = $controller->deleteMemberType($id);
if ($deleted) {
    $_SESSION['success_message'] = 'Member type deleted successfully.';
} else {
    $_SESSION['success_message'] = 'Failed to delete member type.';
}

header('Location: member_types.php');
exit();