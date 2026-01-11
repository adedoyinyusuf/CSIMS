<?php
ob_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/auth_controller.php';
require_once __DIR__ . '/../../controllers/SavingsController.php';
require_once __DIR__ . '/../../src/autoload.php';

// Initialize session and auth
$session = Session::getInstance();
$auth = new AuthController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    $session->setFlash('error', 'Please login to access this page');
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get current user
$current_user = $auth->getCurrentUser();

$database = Database::getInstance();
$conn = $database->getConnection();

$success = '';
$error = '';
$previewData = [];
$postResults = [];

// Handle preview request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_interest'])) {
    CSRFProtection::validateRequest();
    
    $period = $_POST['period'] ?? 'monthly';
    $month = $_POST['month'] ?? date('F');
    $year = $_POST['year'] ?? date('Y');
    
    try {
        $savingsController = new SavingsController();
        $previewData = $savingsController->calculateInterestPreview($period, $month, $year);
        $_SESSION['interest_preview'] = $previewData;
        $success = "Interest calculated for {$previewData['total_accounts']} accounts.";
    } catch (Exception $e) {
        $error = "Error calculating interest: " . $e->getMessage();
    }
}

// Handle post interest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_interest'])) {
    CSRFProtection::validateRequest();
    
    if (!isset($_SESSION['interest_preview'])) {
        $error = "Please preview interest calculations first.";
    } else {
        $preview = $_SESSION['interest_preview'];
        
        try {
            $savingsController = new SavingsController();
            $postResults = $savingsController->postInterest(
                $preview['period'],
                $preview['month'],
                $preview['year']
            );
            
            unset($_SESSION['interest_preview']);
            
            if ($postResults['success_count'] > 0) {
                $success = "Successfully posted interest for {$postResults['success_count']} accounts. Total: ₦" . number_format($postResults['total_interest'], 2);
                if ($postResults['error_count'] > 0) {
                    $success .= " {$postResults['error_count']} failed.";
                }
            } else {
                $error = "Failed to post interest. {$postResults['error_count']} errors occurred.";
            }
        } catch (Exception $e) {
            $error = "Error posting interest: " . $e->getMessage();
        }
    }
}

// Restore preview if exists
if (isset($_SESSION['interest_preview']) && empty($previewData)) {
    $previewData = $_SESSION['interest_preview'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Interest - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .preview-table {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../../views/includes/header.php'; ?>
    
    <div class="d-flex">
        <?php include __DIR__ . '/../../views/includes/sidebar.php'; ?>
        
        <main class="flex-fill">
            <div class="container-fluid p-4" style="margin-left: 16rem; margin-top: 4rem;">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-percent text-success"></i> Post Interest</h1>
                    <div>
                        <a href="savings_accounts.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Accounts
                        </a>
                    </div>
                </div>
                
                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Instructions -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> How Interest Posting Works</h5>
                    </div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Select the interest period (Monthly, Quarterly, or Annual)</li>
                            <li>Choose the month and year for the interest posting</li>
                            <li>Click "Preview Interest" to calculate interest for all active accounts</li>
                            <li>Review the calculations to ensure accuracy</li>
                            <li>Click "Post Interest" to credit interest to all accounts</li>
                        </ol>
                        
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> 
                            Interest is calculated only on active accounts with positive balances.
                        </div>
                    </div>
                </div>
                
                <!-- Interest Configuration Form -->
                <?php if (empty($previewData)): ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-cog"></i> Interest Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Interest Period</label>
                                        <div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="period" id="monthly" value="monthly" checked>
                                                <label class="form-check-label" for="monthly">
                                                    <strong>Monthly</strong> (Rate / 12)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="period" id="quarterly" value="quarterly">
                                                <label class="form-check-label" for="quarterly">
                                                    <strong>Quarterly</strong> (Rate / 4)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="period" id="annual" value="annual">
                                                <label class="form-check-label" for="annual">
                                                    <strong>Annual</strong> (Full Rate)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Month</label>
                                        <select name="month" class="form-select" required>
                                            <?php
                                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                                      'July', 'August', 'September', 'October', 'November', 'December'];
                                            $currentMonth = date('F');
                                            foreach ($months as $month) {
                                                $selected = $month === $currentMonth ? 'selected' : '';
                                                echo "<option value='$month' $selected>$month</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select" required>
                                            <?php
                                            $currentYear = date('Y');
                                            for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                                                echo "<option value='$y'>$y</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" name="preview_interest" class="btn btn-primary btn-lg">
                                    <i class="fas fa-calculator"></i> Preview Interest Calculations
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Preview Results -->
                <?php if (!empty($previewData)): ?>
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-eye"></i> Interest Preview - <?php echo ucfirst($previewData['period']); ?> (<?php echo $previewData['month']; ?> <?php echo $previewData['year']; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <!-- Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card stats-card" style="border-left-color: #3b82f6;">
                                        <div class="card-body">
                                            <h6 class="text-muted">Active Accounts</h6>
                                            <h3><?php echo number_format($previewData['total_accounts']); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stats-card" style="border-left-color: #10b981;">
                                        <div class="card-body">
                                            <h6 class="text-muted">Total Interest</h6>
                                            <h3 class="text-success">₦<?php echo number_format($previewData['total_interest'], 2); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stats-card" style="border-left-color: #f59e0b;">
                                        <div class="card-body">
                                            <h6 class="text-muted">Period Type</h6>
                                            <h3><?php echo ucfirst($previewData['period']); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Preview Table -->
                            <div class="preview-table">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Member</th>
                                            <th>Account</th>
                                            <th class="text-end">Balance</th>
                                            <th class="text-center">Rate (%)</th>
                                            <th class="text-end">Interest</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($previewData['calculations'] as $calc): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($calc['member_name']); ?></td>
                                                <td><?php echo htmlspecialchars($calc['account_name']); ?></td>
                                                <td class="text-end">₦<?php echo number_format($calc['balance'], 2); ?></td>
                                                <td class="text-center"><?php echo number_format($calc['rate'], 2); ?>%</td>
                                                <td class="text-end fw-bold text-success">₦<?php echo number_format($calc['interest'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-dark">
                                        <tr>
                                            <th colspan="4" class="text-end">Total Interest:</th>
                                            <th class="text-end">₦<?php echo number_format($previewData['total_interest'], 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <!-- Action Buttons -->
                            <form method="POST" class="mt-4">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    You are about to post <strong>₦<?php echo number_format($previewData['total_interest'], 2); ?></strong> 
                                    in interest to <strong><?php echo $previewData['total_accounts']; ?></strong> accounts. This action cannot be undone.
                                </div>
                                <button type="submit" name="post_interest" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-check"></i> Post Interest (<?php echo $previewData['total_accounts']; ?> accounts)
                                </button>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Post Results -->
                <?php if (!empty($postResults)): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Interest Posting Results</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="alert alert-success">
                                        <h5><i class="fas fa-check"></i> Successful: <?php echo $postResults['success_count']; ?></h5>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="alert alert-info">
                                        <h5><i class="fas fa-coins"></i> Total: ₦<?php echo number_format($postResults['total_interest'], 2); ?></h5>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="alert alert-<?php echo $postResults['error_count'] > 0 ? 'danger' : 'success'; ?>">
                                        <h5><i class="fas fa-times"></i> Failed: <?php echo $postResults['error_count']; ?></h5>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($postResults['errors'])): ?>
                                <h6>Error Details:</h6>
                                <ul class="list-group">
                                    <?php foreach ($postResults['errors'] as $err): ?>
                                        <li class="list-group-item list-group-item-danger">
                                            <?php echo htmlspecialchars($err); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <a href="savings_accounts.php" class="btn btn-primary mt-3">
                                <i class="fas fa-piggy-bank"></i> View Savings Accounts
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
