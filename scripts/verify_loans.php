<?php
/**
 * Verification Script for Loans Data
 * 
 * Checks if seeded members have loans and verifies the data structure
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

echo "=== LOAN DATA VERIFICATION ===\n\n";

// Check if loans table exists
$result = $conn->query("SHOW TABLES LIKE 'loans'");
if ($result->num_rows === 0) {
    echo "ERROR: 'loans' table does not exist in the database.\n";
    exit(1);
}
echo "✓ 'loans' table exists\n\n";

// Get total count of loans
$result = $conn->query("SELECT COUNT(*) as total FROM loans");
$row = $result->fetch_assoc();
$total_loans = $row['total'];
echo "Total Loans in Database: {$total_loans}\n\n";

if ($total_loans === 0) {
    echo "⚠️  WARNING: No loans found in the database.\n";
    echo "   You may need to run the seed script: php scripts/simple_seed.php\n\n";
    exit(0);
}

// Check loans structure - what columns exist
echo "=== LOAN TABLE STRUCTURE ===\n";
$result = $conn->query("SHOW COLUMNS FROM loans");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo "  - {$row['Field']} ({$row['Type']})\n";
}
echo "\n";

// Get sample loans with member information
echo "=== SAMPLE LOANS DATA ===\n";
$query = "
    SELECT 
        l.*,
        m.member_id,
        m.first_name,
        m.last_name,
        m.email
    FROM loans l
    LEFT JOIN members m ON l.member_id = m.member_id
    ORDER BY l.loan_id DESC
    LIMIT 10
";

$result = $conn->query($query);
if (!$result) {
    echo "ERROR: Failed to query loans: " . $conn->error . "\n";
    exit(1);
}

$loans = [];
$sample_count = 0;
while ($row = $result->fetch_assoc()) {
    $sample_count++;
    $loans[] = $row;
    
    echo "\nLoan #{$sample_count}:\n";
    echo "  Loan ID: " . ($row['loan_id'] ?? $row['id'] ?? 'N/A') . "\n";
    echo "  Member: " . ($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '') . " (ID: " . ($row['member_id'] ?? 'N/A') . ")\n";
    echo "  Email: " . ($row['email'] ?? 'N/A') . "\n";
    echo "  Amount: ₦" . number_format($row['amount'] ?? $row['principal_amount'] ?? $row['total_amount'] ?? 0, 2) . "\n";
    echo "  Status: " . ($row['status'] ?? 'N/A') . "\n";
    echo "  Term: " . ($row['term'] ?? $row['term_months'] ?? 'N/A') . " months\n";
    echo "  Interest Rate: " . ($row['interest_rate'] ?? $row['annual_rate'] ?? 'N/A') . "%\n";
    echo "  Application Date: " . ($row['application_date'] ?? $row['created_at'] ?? 'N/A') . "\n";
    echo "  Purpose: " . ($row['purpose'] ?? 'N/A') . "\n";
}

echo "\n=== DATA STRUCTURE COMPATIBILITY ===\n";

// Check what the view expects
$expected_fields = [
    'loan_id' => ['loan_id', 'id'],
    'member_id' => ['member_id'],
    'first_name' => ['first_name', 'member_first_name'],
    'last_name' => ['last_name', 'member_last_name'],
    'amount' => ['amount', 'principal_amount', 'total_amount'],
    'status' => ['status'],
    'term' => ['term', 'term_months'],
    'interest_rate' => ['interest_rate', 'annual_rate'],
    'application_date' => ['application_date', 'created_at'],
    'purpose' => ['purpose'],
    'loan_type_name' => ['loan_type_name', 'loan_type', 'type_name']
];

$compatibility_issues = [];

if (!empty($loans)) {
    $sample_loan = $loans[0];
    
    foreach ($expected_fields as $field_name => $possible_keys) {
        $found = false;
        foreach ($possible_keys as $key) {
            if (isset($sample_loan[$key])) {
                $found = true;
                echo "✓ {$field_name}: Found as '{$key}'\n";
                break;
            }
        }
        if (!$found) {
            $compatibility_issues[] = $field_name;
            echo "⚠ {$field_name}: NOT FOUND (tried: " . implode(', ', $possible_keys) . ")\n";
        }
    }
} else {
    echo "No loans found to check compatibility\n";
}

// Check loan types table
echo "\n=== LOAN TYPES ===\n";
$result = $conn->query("SHOW TABLES LIKE 'loan_types'");
if ($result->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as total FROM loan_types");
    $row = $result->fetch_assoc();
    echo "✓ 'loan_types' table exists with {$row['total']} types\n";
    
    // Check if loans have loan_type_id
    if (in_array('loan_type_id', $columns)) {
        $result = $conn->query("
            SELECT COUNT(*) as total 
            FROM loans l
            LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
            WHERE lt.id IS NULL AND l.loan_type_id IS NOT NULL
        ");
        $row = $result->fetch_assoc();
        if ($row['total'] > 0) {
            echo "⚠ {$row['total']} loans have invalid loan_type_id references\n";
        } else {
            echo "✓ All loan_type_id references are valid\n";
        }
    }
} else {
    echo "⚠ 'loan_types' table does not exist\n";
}

// Check for members with loans
echo "\n=== MEMBERS WITH LOANS ===\n";
$result = $conn->query("
    SELECT 
        m.member_id,
        m.first_name,
        m.last_name,
        m.email,
        COUNT(l.loan_id) as loan_count
    FROM members m
    INNER JOIN loans l ON m.member_id = l.member_id
    GROUP BY m.member_id, m.first_name, m.last_name, m.email
    ORDER BY loan_count DESC
");

if ($result && $result->num_rows > 0) {
    echo "Members with loans:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['first_name']} {$row['last_name']} ({$row['email']}): {$row['loan_count']} loan(s)\n";
    }
} else {
    echo "⚠ No members found with loans (this might indicate a data issue)\n";
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Total Loans: {$total_loans}\n";
echo "Sample Loans Displayed: {$sample_count}\n";
if (count($compatibility_issues) > 0) {
    echo "⚠ Compatibility Issues: " . implode(', ', $compatibility_issues) . "\n";
    echo "   These fields are expected by the view but not found in the data structure.\n";
} else {
    echo "✓ No compatibility issues found\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";


