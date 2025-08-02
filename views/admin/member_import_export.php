<?php
require_once '../../config/auth_check.php';
require_once '../../controllers/member_controller.php';
require_once '../../controllers/membership_controller.php';

$memberController = new MemberController();
$membershipController = new MembershipController();

// Handle file upload and import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_members'])) {
    $errors = [];
    $success = false;
    $importResults = [];
    
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['import_file'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
            $errors[] = "Invalid file format. Please upload CSV or Excel files only.";
        } else {
            try {
                $importData = parseImportFile($uploadedFile['tmp_name'], $fileExtension);
                $importResults = processImportData($importData, $memberController);
                $success = true;
            } catch (Exception $e) {
                $errors[] = "Error processing file: " . $e->getMessage();
            }
        }
    } else {
        $errors[] = "Please select a file to upload.";
    }
}

// Handle export request
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $format = $_GET['format'] ?? 'csv';
    $filters = [
        'status' => $_GET['status'] ?? '',
        'membership_type' => $_GET['membership_type'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? ''
    ];
    
    exportMembers($exportType, $format, $filters, $memberController);
    exit;
}

// Get membership types for filters
$membershipTypes = $membershipController->getAllMembershipTypes();

// Get import/export statistics
$totalMembers = $memberController->getMemberStatistics()['total'];
$recentImports = getRecentImports(5);
$recentExports = getRecentExports(5);

// Helper functions
function parseImportFile($filePath, $extension) {
    $data = [];
    
    if ($extension === 'csv') {
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                $data[] = array_combine($headers, $row);
            }
            fclose($handle);
        }
    } else {
        // For Excel files, you would use a library like PhpSpreadsheet
        // This is a placeholder implementation
        throw new Exception("Excel import not implemented yet. Please use CSV format.");
    }
    
    return $data;
}

function processImportData($data, $memberController) {
    $results = [
        'total' => count($data),
        'success' => 0,
        'errors' => 0,
        'duplicates' => 0,
        'details' => []
    ];
    
    foreach ($data as $index => $row) {
        $rowNumber = $index + 2; // +2 because index starts at 0 and we skip header
        
        try {
            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'email', 'phone'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $results['errors']++;
                $results['details'][] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'message' => 'Missing required fields: ' . implode(', ', $missingFields)
                ];
                continue;
            }
            
            // Check for duplicates
            if ($memberController->getMemberByEmail($row['email'])) {
                $results['duplicates']++;
                $results['details'][] = [
                    'row' => $rowNumber,
                    'status' => 'duplicate',
                    'message' => 'Email already exists: ' . $row['email']
                ];
                continue;
            }
            
            // Prepare member data
            $memberData = [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'address' => $row['address'] ?? '',
                'city' => $row['city'] ?? '',
                'state' => $row['state'] ?? '',
                'postal_code' => $row['postal_code'] ?? '',
                'date_of_birth' => $row['date_of_birth'] ?? null,
                'gender' => $row['gender'] ?? 'other',
                'membership_type_id' => $row['membership_type_id'] ?? 1,
                'join_date' => $row['join_date'] ?? date('Y-m-d'),
                'status' => $row['status'] ?? 'active'
            ];
            
            // Create member
            $memberId = $memberController->createMember($memberData);
            
            if ($memberId) {
                $results['success']++;
                $results['details'][] = [
                    'row' => $rowNumber,
                    'status' => 'success',
                    'message' => 'Member created successfully: ' . $row['first_name'] . ' ' . $row['last_name']
                ];
            } else {
                $results['errors']++;
                $results['details'][] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'message' => 'Failed to create member'
                ];
            }
            
        } catch (Exception $e) {
            $results['errors']++;
            $results['details'][] = [
                'row' => $rowNumber,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Log import activity
    logImportActivity($results);
    
    return $results;
}

function exportMembers($type, $format, $filters, $memberController) {
    // Get members based on filters
    $members = [];
    
    switch ($type) {
        case 'all':
            $members = $memberController->getAllMembers();
            break;
        case 'active':
            $members = $memberController->getMembersByStatus('active');
            break;
        case 'expired':
            $members = $memberController->getMembersByStatus('expired');
            break;
        case 'filtered':
            $members = $memberController->searchMembersAdvanced([
                'status' => $filters['status'],
                'membership_type' => $filters['membership_type'],
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to']
            ])['members'];
            break;
    }
    
    // Apply additional filters if needed
    if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
        $members = array_filter($members, function($member) use ($filters) {
            $joinDate = strtotime($member['join_date']);
            $fromDate = !empty($filters['date_from']) ? strtotime($filters['date_from']) : 0;
            $toDate = !empty($filters['date_to']) ? strtotime($filters['date_to']) : time();
            
            return $joinDate >= $fromDate && $joinDate <= $toDate;
        });
    }
    
    // Generate filename
    $filename = 'members_export_' . date('Y-m-d_H-i-s') . '.' . $format;
    
    if ($format === 'csv') {
        exportToCSV($members, $filename);
    } else {
        exportToExcel($members, $filename);
    }
    
    // Log export activity
    logExportActivity($type, $format, count($members));
}

function exportToCSV($members, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    $headers = [
        'ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'State', 
        'Postal Code', 'Date of Birth', 'Gender', 'Membership Type', 'Join Date', 
        'Membership Expiry', 'Status', 'Created At'
    ];
    fputcsv($output, $headers);
    
    // Write data
    foreach ($members as $member) {
        $row = [
            $member['id'],
            $member['first_name'],
            $member['last_name'],
            $member['email'],
            $member['phone'],
            $member['address'],
            $member['city'],
            $member['state'],
            $member['postal_code'],
            $member['date_of_birth'],
            $member['gender'],
            $member['membership_type'],
            $member['join_date'],
            $member['membership_expiry'],
            $member['status'],
            $member['created_at']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportToExcel($members, $filename) {
    // Placeholder for Excel export
    // In a real implementation, you would use PhpSpreadsheet
    throw new Exception("Excel export not implemented yet. Please use CSV format.");
}

function logImportActivity($results) {
    global $memberController;
    
    $sql = "INSERT INTO import_export_logs (type, action, total_records, success_count, error_count, details, created_at) 
            VALUES ('import', 'member_import', ?, ?, ?, ?, NOW())";
    
    $stmt = $memberController->conn->prepare($sql);
    $details = json_encode($results['details']);
    $stmt->bind_param('iiis', $results['total'], $results['success'], $results['errors'], $details);
    $stmt->execute();
}

function logExportActivity($type, $format, $count) {
    global $memberController;
    
    $sql = "INSERT INTO import_export_logs (type, action, total_records, success_count, error_count, details, created_at) 
            VALUES ('export', ?, ?, ?, 0, ?, NOW())";
    
    $stmt = $memberController->conn->prepare($sql);
    $action = "member_export_{$type}_{$format}";
    $details = json_encode(['export_type' => $type, 'format' => $format]);
    $stmt->bind_param('siis', $action, $count, $count, $details);
    $stmt->execute();
}

function getRecentImports($limit) {
    global $memberController;
    
    $sql = "SELECT * FROM import_export_logs WHERE type = 'import' ORDER BY created_at DESC LIMIT ?";
    $stmt = $memberController->conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getRecentExports($limit) {
    global $memberController;
    
    $sql = "SELECT * FROM import_export_logs WHERE type = 'export' ORDER BY created_at DESC LIMIT ?";
    $stmt = $memberController->conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Import/Export - CSIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .import-export-card {
            transition: transform 0.2s;
        }
        .import-export-card:hover {
            transform: translateY(-2px);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s;
        }
        .upload-area:hover {
            border-color: #0d6efd;
        }
        .upload-area.dragover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .result-item {
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 4px;
        }
        .result-success {
            background-color: #d1edff;
            border-left: 4px solid #0d6efd;
        }
        .result-error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .result-duplicate {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <?php include '../../views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="members.php">Members</a></li>
                        <li class="breadcrumb-item active">Import/Export</li>
                    </ol>
                </nav>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-exchange-alt me-2"></i>Member Import/Export</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
                                <i class="fas fa-download me-1"></i>Download Template
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historyModal">
                                <i class="fas fa-history me-1"></i>History
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <h6>Import completed successfully!</h6>
                        <ul class="mb-0">
                            <li>Total records: <?= $importResults['total'] ?></li>
                            <li>Successfully imported: <?= $importResults['success'] ?></li>
                            <li>Errors: <?= $importResults['errors'] ?></li>
                            <li>Duplicates skipped: <?= $importResults['duplicates'] ?></li>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= $totalMembers ?></h3>
                                <p class="mb-0">Total Members</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= count($recentImports) ?></h3>
                                <p class="mb-0">Recent Imports</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= count($recentExports) ?></h3>
                                <p class="mb-0">Recent Exports</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h3 class="mb-0"><?= count($membershipTypes) ?></h3>
                                <p class="mb-0">Membership Types</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Import Section -->
                    <div class="col-lg-6">
                        <div class="card import-export-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Import Members</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="upload-area" id="uploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <h5>Drag & Drop your file here</h5>
                                        <p class="text-muted">or click to browse</p>
                                        <input type="file" class="form-control d-none" id="import_file" name="import_file" 
                                               accept=".csv,.xlsx,.xls" required>
                                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('import_file').click()">
                                            <i class="fas fa-folder-open me-1"></i>Choose File
                                        </button>
                                    </div>
                                    
                                    <div class="mt-3" id="fileInfo" style="display: none;">
                                        <div class="alert alert-info">
                                            <i class="fas fa-file me-2"></i>
                                            <span id="fileName"></span>
                                            <button type="button" class="btn-close float-end" onclick="clearFile()"></button>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <h6>Import Options</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="skip_duplicates" name="skip_duplicates" checked>
                                            <label class="form-check-label" for="skip_duplicates">
                                                Skip duplicate emails
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="validate_data" name="validate_data" checked>
                                            <label class="form-check-label" for="validate_data">
                                                Validate data before import
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="send_welcome" name="send_welcome">
                                            <label class="form-check-label" for="send_welcome">
                                                Send welcome emails to new members
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" name="import_members" class="btn btn-primary">
                                            <i class="fas fa-upload me-1"></i>Import Members
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#previewModal">
                                            <i class="fas fa-eye me-1"></i>Preview
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>Supported formats:</strong> CSV, Excel (.xlsx, .xls)<br>
                                        <strong>Required fields:</strong> first_name, last_name, email, phone<br>
                                        <strong>Optional fields:</strong> address, city, state, postal_code, date_of_birth, gender, membership_type_id, join_date, status
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Export Section -->
                    <div class="col-lg-6">
                        <div class="card import-export-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-download me-2"></i>Export Members</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET">
                                    <input type="hidden" name="export" value="filtered">
                                    
                                    <div class="mb-3">
                                        <label for="export_type" class="form-label">Export Type</label>
                                        <select class="form-select" id="export_type" name="export" onchange="toggleFilters()">
                                            <option value="all">All Members</option>
                                            <option value="active">Active Members Only</option>
                                            <option value="expired">Expired Members Only</option>
                                            <option value="filtered">Custom Filter</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="format" class="form-label">Format</label>
                                        <select class="form-select" name="format">
                                            <option value="csv">CSV</option>
                                            <option value="xlsx" disabled>Excel (Coming Soon)</option>
                                        </select>
                                    </div>
                                    
                                    <div id="filterOptions" class="mb-3">
                                        <h6>Filter Options</h6>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="">All Statuses</option>
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                    <option value="expired">Expired</option>
                                                    <option value="suspended">Suspended</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="membership_type" class="form-label">Membership Type</label>
                                                <select class="form-select" name="membership_type">
                                                    <option value="">All Types</option>
                                                    <?php foreach ($membershipTypes as $type): ?>
                                                        <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-md-6">
                                                <label for="date_from" class="form-label">Join Date From</label>
                                                <input type="date" class="form-control" name="date_from">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="date_to" class="form-label">Join Date To</label>
                                                <input type="date" class="form-control" name="date_to">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-download me-1"></i>Export Members
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" onclick="exportQuick('all', 'csv')">
                                            <i class="fas fa-bolt me-1"></i>Quick Export (All Members)
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Import Results -->
                <?php if (isset($importResults) && !empty($importResults['details'])): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Import Results</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-primary"><?= $importResults['total'] ?></h4>
                                                <small>Total Records</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-success"><?= $importResults['success'] ?></h4>
                                                <small>Successful</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-danger"><?= $importResults['errors'] ?></h4>
                                                <small>Errors</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-warning"><?= $importResults['duplicates'] ?></h4>
                                                <small>Duplicates</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3" style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach ($importResults['details'] as $detail): ?>
                                            <div class="result-item result-<?= $detail['status'] ?>">
                                                <strong>Row <?= $detail['row'] ?>:</strong> <?= htmlspecialchars($detail['message']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Template Download Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Download Import Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Download a template file to ensure your import data is in the correct format.</p>
                    <div class="d-grid gap-2">
                        <a href="?download_template=csv" class="btn btn-outline-primary">
                            <i class="fas fa-file-csv me-1"></i>Download CSV Template
                        </a>
                        <a href="?download_template=xlsx" class="btn btn-outline-success" disabled>
                            <i class="fas fa-file-excel me-1"></i>Download Excel Template (Coming Soon)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload handling
        document.getElementById('import_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileInfo').style.display = 'block';
            }
        });
        
        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('import_file').files = files;
                document.getElementById('fileName').textContent = files[0].name;
                document.getElementById('fileInfo').style.display = 'block';
            }
        });
        
        function clearFile() {
            document.getElementById('import_file').value = '';
            document.getElementById('fileInfo').style.display = 'none';
        }
        
        function toggleFilters() {
            const exportType = document.getElementById('export_type').value;
            const filterOptions = document.getElementById('filterOptions');
            
            if (exportType === 'filtered') {
                filterOptions.style.display = 'block';
            } else {
                filterOptions.style.display = 'none';
            }
        }
        
        function exportQuick(type, format) {
            window.location.href = `?export=${type}&format=${format}`;
        }
        
        // Initialize
        toggleFilters();
    </script>
</body>
</html>
