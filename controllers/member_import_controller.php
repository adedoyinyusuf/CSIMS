<?php
// Enable error reporting for debugging but don't display errors in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'csims_db');
}

require_once __DIR__ . '/../config/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Log the start of the script
error_log('Member import controller started');
error_log('POST data: ' . print_r($_POST, true));
error_log('FILES data: ' . print_r($_FILES, true));
error_log('Session data: ' . print_r($_SESSION, true));

// Check if user is logged in and is admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
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

// Get import mode (insert_only, update_existing, or mixed)
$importMode = $_POST['import_mode'] ?? 'insert_only';
$createAccounts = isset($_POST['create_accounts']) && $_POST['create_accounts'] === 'true';
$sendCredentials = isset($_POST['send_credentials']) && $_POST['send_credentials'] === 'true';

// Function to generate unique username
function generateUniqueUsername($firstName, $lastName, $db) {
    $baseUsername = strtolower(substr($firstName, 0, 1) . $lastName);
    $username = $baseUsername;
    $counter = 1;
    
    while (true) {
        $stmt = $db->prepare("SELECT member_id FROM members WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            return $username;
        }
        $username = $baseUsername . $counter;
        $counter++;
    }
}

// Function to generate temporary password
function generateTempPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// Function to generate unique IPPIS number
function generateUniqueIppis($db) {
    do {
        $ippis = 'IMP' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT member_id FROM members WHERE ippis_no = ?");
        $stmt->execute([$ippis]);
    } while ($stmt->fetch());
    
    return $ippis;
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
    // Create direct PDO connection
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    error_log('Database connection established successfully');
    
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
    
    // Expected headers (required and optional)
    $requiredHeaders = ['first_name', 'last_name', 'email'];
    $optionalHeaders = ['phone', 'gender', 'date_of_birth', 'address', 'membership_type_id', 'ippis_no', 'username', 'password'];
    $allValidHeaders = array_merge($requiredHeaders, $optionalHeaders);
    
    // Validate headers
    $normalizedHeaders = array_map('trim', array_map('strtolower', $headers));
    $normalizedRequired = array_map('strtolower', $requiredHeaders);
    $normalizedValid = array_map('strtolower', $allValidHeaders);
    
    // Check if all required headers are present
    $missingRequired = array_diff($normalizedRequired, $normalizedHeaders);
    if (!empty($missingRequired)) {
        throw new Exception('Missing required CSV headers: ' . implode(', ', $missingRequired));
    }
    
    // Check if all headers are valid
    $invalidHeaders = array_diff($normalizedHeaders, $normalizedValid);
    if (!empty($invalidHeaders)) {
        throw new Exception('Invalid CSV headers found: ' . implode(', ', $invalidHeaders) . '. Valid headers: ' . implode(', ', $allValidHeaders));
    }
    
    // Get membership types for validation
    $stmt = $db->prepare("SELECT membership_type_id FROM membership_types");
    $stmt->execute();
    $validMembershipTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $successCount = 0;
    $updateCount = 0;
    $errorCount = 0;
    $errors = [];
    $newCredentials = [];
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
        if (count($row) !== count($headers)) {
            $errors[] = "Row {$rowNumber}: Incorrect number of columns (expected " . count($headers) . ", got " . count($row) . ")";
            $errorCount++;
            continue;
        }
        
        // Map row data to associative array
        $memberData = array_combine($headers, array_map('trim', $row));
        
        // Normalize keys to lowercase for consistent access
        $memberData = array_change_key_case($memberData, CASE_LOWER);
        
        // Validate required fields
        $coreRequiredFields = ['first_name', 'last_name', 'email'];
        $missingFields = [];
        
        foreach ($coreRequiredFields as $field) {
            if (empty($memberData[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            $errors[] = "Row {$rowNumber}: Missing required fields: " . implode(', ', $missingFields);
            $errorCount++;
            continue;
        }
        
        // Validate email format
        if (!filter_var($memberData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row {$rowNumber}: Invalid email format";
            $errorCount++;
            continue;
        }
        
        // Validate gender if provided
        if (!empty($memberData['gender']) && !in_array(strtolower($memberData['gender']), ['male', 'female', 'other'])) {
            $errors[] = "Row {$rowNumber}: Invalid gender (must be Male, Female, or Other)";
            $errorCount++;
            continue;
        }
        
        // Validate membership type if provided
        if (!empty($memberData['membership_type_id']) && !in_array($memberData['membership_type_id'], $validMembershipTypes)) {
            $errors[] = "Row {$rowNumber}: Invalid membership type ID";
            $errorCount++;
            continue;
        }
        
        // Validate date of birth if provided
        if (!empty($memberData['date_of_birth'])) {
            $dob = DateTime::createFromFormat('Y-m-d', $memberData['date_of_birth']);
            if (!$dob) {
                $errors[] = "Row {$rowNumber}: Invalid date of birth format (use YYYY-MM-DD)";
                $errorCount++;
                continue;
            }
        }
        
        // Check if member already exists
        $stmt = $db->prepare("SELECT member_id, first_name, last_name, phone, gender, dob, address, membership_type_id, ippis_no, username FROM members WHERE email = ?");
        $stmt->execute([$memberData['email']]);
        $existingMember = $stmt->fetch(PDO::FETCH_ASSOC);
        
        try {
            if ($existingMember) {
                // Handle existing member
                if ($importMode === 'insert_only') {
                    $errors[] = "Row {$rowNumber}: Email already exists in database (insert_only mode)";
                    $errorCount++;
                    continue;
                } else {
                    // Update existing member with new data
                    $updateFields = [];
                    $updateValues = [];
                    
                    // Check which fields need updating
                    $fieldsToUpdate = ['first_name', 'last_name', 'phone', 'gender', 'dob', 'address', 'membership_type_id'];
                    
                    foreach ($fieldsToUpdate as $field) {
                        $csvField = ($field === 'dob') ? 'date_of_birth' : $field;
                        if (!empty($memberData[$csvField]) && $memberData[$csvField] !== $existingMember[$field]) {
                            $updateFields[] = "{$field} = ?";
                            if ($field === 'gender') {
                                $updateValues[] = ucfirst(strtolower($memberData[$csvField]));
                            } else {
                                $updateValues[] = $memberData[$csvField];
                            }
                        }
                    }
                    
                    if (!empty($updateFields)) {
                        $updateValues[] = $memberData['email']; // For WHERE clause
                        $updateSql = "UPDATE members SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE email = ?";
                        $stmt = $db->prepare($updateSql);
                        $stmt->execute($updateValues);
                        $updateCount++;
                    }
                }
            } else {
                // Insert new member
                $defaultMembershipType = !empty($memberData['membership_type_id']) ? $memberData['membership_type_id'] : $validMembershipTypes[0];
                
                // Generate required fields for new members
                $ippis = !empty($memberData['ippis_no']) ? $memberData['ippis_no'] : generateUniqueIppis($db);
                $username = null;
                $password = null;
                $hashedPassword = null;
                
                if ($createAccounts) {
                    $username = !empty($memberData['username']) ? $memberData['username'] : generateUniqueUsername($memberData['first_name'], $memberData['last_name'], $db);
                    $password = !empty($memberData['password']) ? $memberData['password'] : generateTempPassword();
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Store credentials for notification
                    $newCredentials[] = [
                        'email' => $memberData['email'],
                        'username' => $username,
                        'password' => $password,
                        'name' => $memberData['first_name'] . ' ' . $memberData['last_name']
                    ];
                }
                
                $stmt = $db->prepare("
                    INSERT INTO members (
                        ippis_no, username, password, first_name, last_name, email, phone, gender, 
                        dob, address, membership_type_id, 
                        join_date, expiry_date, status, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                        CURDATE(), 
                        DATE_ADD(CURDATE(), INTERVAL (
                            SELECT duration FROM membership_types WHERE membership_type_id = ?
                        ) MONTH),
                        'Active',
                        NOW()
                    )
                ");
                
                $stmt->execute([
                    $ippis,
                    $username,
                    $hashedPassword,
                    $memberData['first_name'],
                    $memberData['last_name'],
                    $memberData['email'],
                    $memberData['phone'] ?? null,
                    !empty($memberData['gender']) ? ucfirst(strtolower($memberData['gender'])) : null,
                    !empty($memberData['date_of_birth']) ? $memberData['date_of_birth'] : null,
                    $memberData['address'] ?? null,
                    $defaultMembershipType,
                    $defaultMembershipType
                ]);
                
                $successCount++;
            }
            
        } catch (PDOException $e) {
            $errors[] = "Row {$rowNumber}: Database error - " . $e->getMessage();
            $errorCount++;
        }
    }
    
    fclose($csvFile);
    
    // Commit transaction if we have any successful operations
    if ($successCount > 0 || $updateCount > 0) {
        $db->commit();
        
        // Send credentials to new members if requested
        if ($sendCredentials && !empty($newCredentials)) {
            require_once '../includes/email_service.php';
            $emailService = new EmailService();
            
            foreach ($newCredentials as $credential) {
                $subject = "Welcome to CSIMS - Your Account Credentials";
                $message = "Dear {$credential['name']},\n\n";
                $message .= "Your CSIMS account has been created successfully.\n\n";
                $message .= "Login Details:\n";
                $message .= "Username: {$credential['username']}\n";
                $message .= "Password: {$credential['password']}\n\n";
                $message .= "Please login and change your password immediately.\n\n";
                $message .= "Best regards,\nCSIMS Administration";
                
                try {
                    $emailService->sendEmail($credential['email'], $subject, $message);
                } catch (Exception $e) {
                    // Log email error but don't fail the import
                    error_log("Failed to send credentials to {$credential['email']}: " . $e->getMessage());
                }
            }
        }
    } else {
        $db->rollback();
    }
    
    // Prepare response message
    $message = "Import completed.";
    if ($successCount > 0) {
        $message .= " {$successCount} new members imported.";
    }
    if ($updateCount > 0) {
        $message .= " {$updateCount} existing members updated.";
    }
    if ($errorCount > 0) {
        $message .= " {$errorCount} rows had errors.";
        if (count($errors) <= 10) {
            $message .= " Errors: " . implode('; ', $errors);
        } else {
            $message .= " First 10 errors: " . implode('; ', array_slice($errors, 0, 10));
        }
    }
    if (!empty($newCredentials)) {
        $message .= " " . count($newCredentials) . " new accounts created.";
        if ($sendCredentials) {
            $message .= " Credentials sent via email.";
        }
    }
    
    $response = [
        'success' => ($successCount > 0 || $updateCount > 0),
        'message' => $message,
        'imported' => $successCount,
        'updated' => $updateCount,
        'errors' => $errorCount,
        'new_accounts' => count($newCredentials),
        'credentials' => $sendCredentials ? [] : $newCredentials // Only return credentials if not sent via email
    ];
    
    error_log('Final response: ' . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Exception caught: ' . $e->getMessage());
    error_log('Exception trace: ' . $e->getTraceAsString());
    
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    $errorResponse = [
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage()
    ];
    
    error_log('Error response: ' . json_encode($errorResponse));
    echo json_encode($errorResponse);
}
?>