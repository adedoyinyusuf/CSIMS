<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['csvFile'];

// Validate file type
if ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
    exit;
}

// Validate file size (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 10MB allowed']);
    exit;
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Read CSV file
    $csvData = array_map('str_getcsv', file($file['tmp_name']));
    
    if (empty($csvData)) {
        echo json_encode(['success' => false, 'message' => 'CSV file is empty']);
        exit;
    }
    
    // Get header row
    $headers = array_map('trim', $csvData[0]);
    $expectedHeaders = ['member_id', 'amount', 'purpose', 'term_months', 'interest_rate', 'application_date', 'status'];
    
    // Validate headers
    $missingHeaders = array_diff($expectedHeaders, $headers);
    if (!empty($missingHeaders)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required columns: ' . implode(', ', $missingHeaders)
        ]);
        exit;
    }
    
    // Remove header row
    array_shift($csvData);
    
    if (empty($csvData)) {
        echo json_encode(['success' => false, 'message' => 'No data rows found in CSV']);
        exit;
    }
    
    $errors = [];
    $validRows = [];
    $rowNumber = 2; // Start from 2 (after header)
    
    // Validate each row
    foreach ($csvData as $row) {
        if (empty(array_filter($row))) {
            $rowNumber++;
            continue; // Skip empty rows
        }
        
        $rowData = array_combine($headers, $row);
        $rowErrors = [];
        
        // Validate required fields
        if (empty(trim($rowData['member_id']))) {
            $rowErrors[] = 'Member ID is required';
        }
        if (empty(trim($rowData['amount']))) {
            $rowErrors[] = 'Amount is required';
        }
        if (empty(trim($rowData['purpose']))) {
            $rowErrors[] = 'Purpose is required';
        }
        if (empty(trim($rowData['term_months']))) {
            $rowErrors[] = 'Term (months) is required';
        }
        if (empty(trim($rowData['interest_rate']))) {
            $rowErrors[] = 'Interest rate is required';
        }
        if (empty(trim($rowData['application_date']))) {
            $rowErrors[] = 'Application date is required';
        }
        if (empty(trim($rowData['status']))) {
            $rowErrors[] = 'Status is required';
        }
        
        // Validate member exists
        if (!empty(trim($rowData['member_id']))) {
            $stmt = $pdo->prepare("SELECT member_id FROM members WHERE member_id = ?");
            $stmt->execute([trim($rowData['member_id'])]);
            if (!$stmt->fetch()) {
                $rowErrors[] = 'Member ID does not exist';
            }
        }
        
        // Validate amount
        if (!empty(trim($rowData['amount'])) && (!is_numeric($rowData['amount']) || floatval($rowData['amount']) <= 0)) {
            $rowErrors[] = 'Amount must be a positive number';
        }
        
        // Validate term_months
        if (!empty(trim($rowData['term_months'])) && (!is_numeric($rowData['term_months']) || intval($rowData['term_months']) <= 0)) {
            $rowErrors[] = 'Term (months) must be a positive integer';
        }
        
        // Validate interest_rate
        if (!empty(trim($rowData['interest_rate'])) && (!is_numeric($rowData['interest_rate']) || floatval($rowData['interest_rate']) < 0)) {
            $rowErrors[] = 'Interest rate must be a non-negative number';
        }
        
        // Validate date format
        if (!empty(trim($rowData['application_date']))) {
            $date = DateTime::createFromFormat('Y-m-d', trim($rowData['application_date']));
            if (!$date || $date->format('Y-m-d') !== trim($rowData['application_date'])) {
                $rowErrors[] = 'Application date must be in YYYY-MM-DD format';
            }
        }
        
        // Validate status
        $validStatuses = ['Pending', 'Approved', 'Rejected', 'Disbursed', 'Active', 'Paid', 'Defaulted'];
        if (!empty(trim($rowData['status'])) && !in_array(trim($rowData['status']), $validStatuses)) {
            $rowErrors[] = 'Status must be one of: ' . implode(', ', $validStatuses);
        }
        
        if (!empty($rowErrors)) {
            $errors[] = "Row {$rowNumber}: " . implode(', ', $rowErrors);
        } else {
            $validRows[] = $rowData;
        }
        
        $rowNumber++;
    }
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Validation errors found',
            'errors' => $errors
        ]);
        exit;
    }
    
    if (empty($validRows)) {
        echo json_encode(['success' => false, 'message' => 'No valid data rows found']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    $insertedCount = 0;
    $stmt = $pdo->prepare("
        INSERT INTO loans (member_id, amount, purpose, term_months, interest_rate, application_date, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($validRows as $row) {
        $stmt->execute([
            trim($row['member_id']),
            floatval($row['amount']),
            trim($row['purpose']),
            intval($row['term_months']),
            floatval($row['interest_rate']),
            trim($row['application_date']),
            trim($row['status'])
        ]);
        $insertedCount++;
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Import completed successfully',
        'imported_count' => $insertedCount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log('Loan import error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred during import'
    ]);
}
?>