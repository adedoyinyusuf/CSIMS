<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['import_file'];

// Validate file type
if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed']);
    exit;
}

// Validate file size (10MB max)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Read and process CSV file
    $csvFile = fopen($file['tmp_name'], 'r');
    if (!$csvFile) {
        throw new Exception('Unable to read CSV file');
    }
    
    // Read header row
    $headers = fgetcsv($csvFile);
    if (!$headers) {
        throw new Exception('CSV file appears to be empty or invalid');
    }
    
    // Expected headers
    $expectedHeaders = ['member_id', 'contribution_type', 'amount', 'payment_method', 'contribution_date', 'receipt_number', 'notes'];
    
    // Validate headers
    $normalizedHeaders = array_map('trim', array_map('strtolower', $headers));
    $normalizedExpected = array_map('strtolower', $expectedHeaders);
    
    if ($normalizedHeaders !== $normalizedExpected) {
        throw new Exception('CSV headers do not match expected format. Expected: ' . implode(', ', $expectedHeaders));
    }
    
    // Get valid members for validation
    $stmt = $db->prepare("SELECT member_id FROM members WHERE status = 'Active'");
    $stmt->execute();
    $validMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get valid contribution types
    $stmt = $db->prepare("SELECT DISTINCT contribution_type FROM contributions");
    $stmt->execute();
    $validContributionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Add common contribution types if none exist
    if (empty($validContributionTypes)) {
        $validContributionTypes = ['Monthly Dues', 'Special Assessment', 'Donation', 'Event Fee', 'Registration Fee'];
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $rowNumber = 1; // Start from 1 (header row)
    
    // Begin transaction
    $db->beginTransaction();
    
    // Process each row
    while (($row = fgetcsv($csvFile)) !== FALSE) {
        $rowNumber++;
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Validate row has correct number of columns
        if (count($row) !== count($expectedHeaders)) {
            $errors[] = "Row {$rowNumber}: Incorrect number of columns";
            $errorCount++;
            continue;
        }
        
        // Map row data to associative array
        $contributionData = array_combine($expectedHeaders, array_map('trim', $row));
        
        // Validate required fields
        $requiredFields = ['member_id', 'contribution_type', 'amount', 'payment_method', 'contribution_date'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (empty($contributionData[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            $errors[] = "Row {$rowNumber}: Missing required fields: " . implode(', ', $missingFields);
            $errorCount++;
            continue;
        }
        
        // Validate member exists
        if (!in_array($contributionData['member_id'], $validMembers)) {
            $errors[] = "Row {$rowNumber}: Invalid member ID or member is not active";
            $errorCount++;
            continue;
        }
        
        // Validate amount is numeric and positive
        if (!is_numeric($contributionData['amount']) || $contributionData['amount'] <= 0) {
            $errors[] = "Row {$rowNumber}: Amount must be a positive number";
            $errorCount++;
            continue;
        }
        
        // Validate date format
        $contributionDate = DateTime::createFromFormat('Y-m-d', $contributionData['contribution_date']);
        if (!$contributionDate) {
            $errors[] = "Row {$rowNumber}: Invalid contribution date format (use YYYY-MM-DD)";
            $errorCount++;
            continue;
        }
        
        // Validate payment method
        $validPaymentMethods = ['Cash', 'Check', 'Bank Transfer', 'Credit Card', 'Online Payment', 'Mobile Payment'];
        if (!in_array($contributionData['payment_method'], $validPaymentMethods)) {
            $errors[] = "Row {$rowNumber}: Invalid payment method. Valid options: " . implode(', ', $validPaymentMethods);
            $errorCount++;
            continue;
        }
        
        // Check if receipt number already exists (if provided)
        if (!empty($contributionData['receipt_number'])) {
            $stmt = $db->prepare("SELECT contribution_id FROM contributions WHERE receipt_number = ?");
            $stmt->execute([$contributionData['receipt_number']]);
            if ($stmt->fetch()) {
                $errors[] = "Row {$rowNumber}: Receipt number already exists";
                $errorCount++;
                continue;
            }
        }
        
        // Insert contribution
        try {
            $stmt = $db->prepare("
                INSERT INTO contributions (
                    member_id, contribution_type, amount, payment_method, 
                    contribution_date, receipt_number, notes, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $contributionData['member_id'],
                $contributionData['contribution_type'],
                $contributionData['amount'],
                $contributionData['payment_method'],
                $contributionData['contribution_date'],
                !empty($contributionData['receipt_number']) ? $contributionData['receipt_number'] : null,
                !empty($contributionData['notes']) ? $contributionData['notes'] : null
            ]);
            
            $successCount++;
            
        } catch (PDOException $e) {
            $errors[] = "Row {$rowNumber}: Database error - " . $e->getMessage();
            $errorCount++;
        }
    }
    
    fclose($csvFile);
    
    // Commit transaction if we have any successful imports
    if ($successCount > 0) {
        $db->commit();
    } else {
        $db->rollback();
    }
    
    // Prepare response message
    $message = "Import completed. {$successCount} contributions imported successfully.";
    if ($errorCount > 0) {
        $message .= " {$errorCount} rows had errors.";
        if (count($errors) <= 10) {
            $message .= " Errors: " . implode('; ', $errors);
        } else {
            $message .= " First 10 errors: " . implode('; ', array_slice($errors, 0, 10));
        }
    }
    
    echo json_encode([
        'success' => $successCount > 0,
        'message' => $message,
        'imported' => $successCount,
        'errors' => $errorCount
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}
?>