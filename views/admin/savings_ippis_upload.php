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
$uploadResults = [];
$previewData = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_ippis'])) {
    CSRFProtection::validateRequest();
    
    if (isset($_FILES['ippis_file']) && $_FILES['ippis_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ippis_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($fileExt, ['csv', 'xlsx', 'xls'])) {
            $error = 'Invalid file type. Please upload a CSV or Excel file.';
        } else {
            // Parse the file
            try {
                $uploadMonth = $_POST['upload_month'] ?? date('F');
                $uploadYear = $_POST['upload_year'] ?? date('Y');
                
                $parsedData = parseIPPISFile($fileTmpName, $fileExt);
                
                if (empty($parsedData)) {
                    $error = 'No valid data found in the uploaded file.';
                } else {
                    $previewData = $parsedData;
                    $_SESSION['ippis_preview_data'] = $parsedData;
                    $_SESSION['upload_month'] = $uploadMonth;
                    $_SESSION['upload_year'] = $uploadYear;
                    $success = 'File parsed successfully. Review the data below and click "Process Deductions" to continue.';
                }
            } catch (Exception $e) {
                $error = 'Error parsing file: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

// Handle processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_deductions'])) {
    CSRFProtection::validateRequest();
    
    if (isset($_SESSION['ippis_preview_data'])) {
        $data = $_SESSION['ippis_preview_data'];
        $month = $_SESSION['upload_month'];
        $year = $_SESSION['upload_year'];
        
        $savingsController = new SavingsController();
        $results = $savingsController->processIPPISDeductions($data, $month, $year);
        
        $uploadResults = $results;
        
        // Clear session data
        unset($_SESSION['ippis_preview_data']);
        unset($_SESSION['upload_month']);
        unset($_SESSION['upload_year']);
        
        if ($results['success_count'] > 0) {
            $success = "Successfully processed {$results['success_count']} deductions.";
            if ($results['error_count'] > 0) {
                $success .= " {$results['error_count']} failed.";
            }
        } else {
            $error = "Failed to process deductions. {$results['error_count']} errors occurred.";
        }
    }
}

/**
 * Parse IPPIS CSV/Excel file
 */
function parseIPPISFile($filePath, $fileExt) {
    $data = [];
    
    if ($fileExt === 'csv') {
        // Parse CSV
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            $headers = fgetcsv($handle); // First row as headers
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 3) {
                    $data[] = [
                        'member_id' => trim($row[0]),
                        'ippis_no' => trim($row[1]),
                        'amount' => (float)trim($row[2]),
                        'name' => isset($row[3]) ? trim($row[3]) : ''
                    ];
                }
            }
            fclose($handle);
        }
    } else {
        // For Excel files, you'd need a library like PhpSpreadsheet
        // For now, we'll show a message
        throw new Exception('Excel file support requires PhpSpreadsheet library. Please convert to CSV format.');
    }
    
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPPIS Upload - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .upload-zone {
            border: 2px dashed #4A90E2;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .upload-zone:hover {
            background: #e9ecef;
            border-color: #2563eb;
        }
        .preview-table {
            max-height: 400px;
            overflow-y: auto;
        }
        .stats-card {
            border-left: 4px solid #4A90E2;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Include Header -->
    <?php include __DIR__ . '/../../views/includes/header.php'; ?>
    
    <div class="d-flex">
        <!-- Include Sidebar -->
        <?php include __DIR__ . '/../../views/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="flex-fill">
            <div class="container-fluid p-4" style="margin-left: 16rem; margin-top: 4rem;">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cloud-upload-alt text-primary"></i> IPPIS Deduction Upload</h1>
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
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> How It Works</h5>
                    </div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Download the monthly IPPIS deduction report from IPPIS office</li>
                            <li>Ensure the CSV file has columns: <strong>Member ID, IPPIS Number, Amount, Member Name (optional)</strong></li>
                            <li>Upload the file using the form below</li>
                            <li>Review the parsed data to ensure accuracy</li>
                            <li>Click "Process Deductions" to automatically credit members' savings accounts</li>
                        </ol>
                        
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-download"></i> <strong>CSV Template:</strong>
                            <a href="#" onclick="downloadTemplate(); return false;" class="alert-link">Download sample CSV template</a>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Form -->
                <?php if (empty($previewData)): ?>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Deduction Month</label>
                                        <select name="upload_month" class="form-select" required>
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
                                    <div class="col-md-6">
                                        <label class="form-label">Year</label>
                                        <select name="upload_year" class="form-select" required>
                                            <?php
                                            $currentYear = date('Y');
                                            for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                                                echo "<option value='$y'>$y</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="upload-zone mb-3">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                    <h5>Upload IPPIS Deduction File</h5>
                                    <p class="text-muted">Drag and drop or click to select CSV file</p>
                                    <input type="file" name="ippis_file" class="form-control mt-3" accept=".csv" required>
                                    <small class="text-muted">Supported format: CSV (Excel files should be converted to CSV)</small>
                                </div>
                                
                                <button type="submit" name="upload_ippis" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload"></i> Upload & Preview
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Preview Data -->
                <?php if (!empty($previewData)): ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-eye"></i> Preview Uploaded Data (<?php echo count($previewData); ?> records)</h5>
                        </div>
                        <div class="card-body">
                            <!-- Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card stats-card">
                                        <div class="card-body">
                                            <h6 class="text-muted">Total Records</h6>
                                            <h3><?php echo count($previewData); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stats-card">
                                        <div class="card-body">
                                            <h6 class="text-muted">Total Amount</h6>
                                            <h3>₦<?php echo number_format(array_sum(array_column($previewData, 'amount')), 2); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stats-card">
                                        <div class="card-body">
                                            <h6 class="text-muted">Month</h6>
                                            <h3><?php echo $_SESSION['upload_month'] ?? ''; ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stats-card">
                                        <div class="card-body">
                                            <h6 class="text-muted">Year</h6>
                                            <h3><?php echo $_SESSION['upload_year'] ?? ''; ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Preview Table -->
                            <div class="preview-table">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Member ID</th>
                                            <th>IPPIS Number</th>
                                            <th>Member Name</th>
                                            <th>Amount (₦)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($previewData as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['member_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['ippis_no']); ?></td>
                                                <td><?php echo htmlspecialchars($row['name'] ?? 'N/A'); ?></td>
                                                <td class="fw-bold">₦<?php echo number_format($row['amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Action Buttons -->
                            <form method="POST" class="mt-4">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <button type="submit" name="process_deductions" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-check"></i> Process Deductions (<?php echo count($previewData); ?> records)
                                </button>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Processing Results -->
                <?php if (!empty($uploadResults)): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Processing Results</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-success">
                                        <h5><i class="fas fa-check"></i> Successful: <?php echo $uploadResults['success_count']; ?></h5>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-danger">
                                        <h5><i class="fas fa-times"></i> Failed: <?php echo $uploadResults['error_count']; ?></h5>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($uploadResults['errors'])): ?>
                                <h6>Error Details:</h6>
                                <ul class="list-group">
                                    <?php foreach ($uploadResults['errors'] as $error): ?>
                                        <li class="list-group-item list-group-item-danger">
                                            <?php echo htmlspecialchars($error); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <a href="savings_reports.php" class="btn btn-primary mt-3">
                                <i class="fas fa-file-alt"></i> View Savings Reports
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div> <!-- container-fluid -->
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadTemplate() {
            const csv = 'Member ID,IPPIS Number,Amount,Member Name\n' +
                        '1001,123456,5000.00,John Doe\n' +
                        '1002,234567,3000.00,Jane Smith\n' +
                        '1003,345678,7500.00,Bob Johnson';
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ippis_template.csv';
            a.click();
        }
    </script>
</body>
</html>
