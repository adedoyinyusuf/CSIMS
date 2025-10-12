<?php
require_once '../../config/config.php';
require_once '../../controllers/auth_controller.php';
require_once '../../controllers/membership_controller.php';

$auth = new AuthController();
$current_user = $auth->getCurrentUser();

if (!$current_user) {
    header('Location: ../auth/login.php');
    exit();
}

$membershipController = new MembershipController();

// Get membership type ID from URL
$membership_type_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($membership_type_id <= 0) {
    header('Location: memberships.php');
    exit();
}

// Get membership type data
$membership_type = $membershipController->getMembershipTypeById($membership_type_id);

if (!$membership_type) {
    header('Location: memberships.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Membership Type - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Membership Type Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="edit_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                               class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($membership_type['member_count'] == 0): ?>
                                <a href="delete_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this membership type?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php endif; ?>
                        </div>
                        <a href="memberships.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Memberships
                        </a>
                    </div>
                </div>

                <!-- Membership Type Information -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle"></i> 
                                    <?php echo htmlspecialchars($membership_type['name']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Type ID:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        #<?php echo $membership_type['membership_type_id']; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Name:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <?php echo htmlspecialchars($membership_type['name']); ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Duration:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <span class="badge bg-info"><?php echo $membership_type['duration']; ?> months</span>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Fee:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <span class="badge bg-success">₦<?php echo number_format($membership_type['fee'], 2); ?></span>
                                    </div>
                                </div>

                                <?php if (isset($membership_type['monthly_contribution']) && $membership_type['monthly_contribution'] > 0): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Monthly Contribution:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <span class="badge bg-primary">₦<?php echo number_format($membership_type['monthly_contribution'], 2); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Active Members:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <span class="badge bg-primary"><?php echo $membership_type['member_count']; ?> members</span>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Created:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <?php echo date('M d, Y \a\t H:i', strtotime($membership_type['created_at'])); ?>
                                    </div>
                                </div>

                                <?php if (!empty($membership_type['description'])): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Description:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($membership_type['description'])); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($membership_type['benefits'])): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <strong>Benefits:</strong>
                                    </div>
                                    <div class="col-sm-9">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($membership_type['benefits'])); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Statistics Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Active Members</span>
                                        <strong><?php echo $membership_type['member_count']; ?></strong>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Annual Fee</span>
                                        <strong>₦<?php echo number_format($membership_type['fee'], 2); ?></strong>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Duration</span>
                                        <strong><?php echo $membership_type['duration']; ?> months</strong>
                                    </div>
                                </div>
                                <?php if ($membership_type['member_count'] > 0): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Revenue</span>
                                        <strong>₦<?php echo number_format($membership_type['fee'] * $membership_type['member_count'], 2); ?></strong>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Status Card -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Status</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($membership_type['member_count'] > 0): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i>
                                        <strong>Active</strong><br>
                                        This membership type has active members.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>No Members</strong><br>
                                        This membership type has no active members.
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid">
                                    <?php if ($membership_type['member_count'] == 0): ?>
                                        <a href="delete_membership_type.php?id=<?php echo $membership_type['membership_type_id']; ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this membership type? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete Type
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary" disabled>
                                            <i class="fas fa-trash"></i> Cannot Delete (Has Members)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>