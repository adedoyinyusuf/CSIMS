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

$type = $controller->getMemberTypeById($id);
if (!$type) {
    header('Location: member_types.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Member Type - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include '../../views/includes/header.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include '../../views/includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Member Type Details</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="member_types.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <a href="edit_member_type.php?id=<?php echo (int)$type['id']; ?>" class="btn btn-primary ms-2">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Member Type Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">ID</dt>
                        <dd class="col-sm-9"><?php echo (int)$type['id']; ?></dd>

                        <dt class="col-sm-3">Name</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($type['name']); ?></dd>

                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($type['description'] ?? ''); ?></dd>

                        <dt class="col-sm-3">Created At</dt>
                        <dd class="col-sm-9"><?php echo isset($type['created_at']) ? date('M d, Y H:i', strtotime($type['created_at'])) : '-'; ?></dd>
                    </dl>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>