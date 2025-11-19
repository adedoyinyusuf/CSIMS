<?php
/**
 * Simple Seed Script for CSIMS Demo Data
 * Bypasses web framework to avoid session/header issues
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'csims_db');

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully.\n";

// Clear existing demo data (avoid subquery deletes that trigger cross-table constraints)
echo "Clearing existing demo data...\n";
$emailsList = "'alice@example.com','bola@example.com','chika@example.com'";

// Fetch member IDs
$memberIds = [];
$res = $conn->query("SELECT member_id FROM members WHERE email IN ($emailsList)");
if ($res) {
    while ($row = $res->fetch_assoc()) { $memberIds[] = (int)$row['member_id']; }
    $res->close();
}

if (!empty($memberIds)) {
    $inMembers = implode(',', $memberIds);

    // Fetch loan IDs for these members
    $loanIds = [];
    $resLoans = $conn->query("SELECT loan_id FROM loans WHERE member_id IN ($inMembers)");
    if ($resLoans) {
        while ($row = $resLoans->fetch_assoc()) { $loanIds[] = (int)$row['loan_id']; }
        $resLoans->close();
    }

    if (!empty($loanIds)) {
        $inLoans = implode(',', $loanIds);
        $conn->query("DELETE FROM loan_repayments WHERE loan_id IN ($inLoans)");
        $conn->query("DELETE FROM loan_payment_schedule WHERE loan_id IN ($inLoans)");
        $conn->query("DELETE FROM loans WHERE loan_id IN ($inLoans)");
    }

    // Fetch savings account IDs for these members
    $accountIds = [];
    $resAcc = $conn->query("SELECT account_id FROM savings_accounts WHERE member_id IN ($inMembers)");
    if ($resAcc) {
        while ($row = $resAcc->fetch_assoc()) { $accountIds[] = (int)$row['account_id']; }
        $resAcc->close();
    }

    if (!empty($accountIds)) {
        $inAccounts = implode(',', $accountIds);
        $conn->query("DELETE FROM savings_transactions WHERE account_id IN ($inAccounts)");
        $conn->query("DELETE FROM savings_accounts WHERE account_id IN ($inAccounts)");
    }

    // Finally, delete members
    $conn->query("DELETE FROM members WHERE member_id IN ($inMembers)");
}

// Create demo members
echo "Creating demo members...\n";

$members = [
    [
        'ippis_no' => 'IPPIS001',
        'username' => 'alice.ade',
        'first_name' => 'Alice',
        'last_name' => 'Ade',
        'email' => 'alice@example.com',
        'phone' => '08012345678',
        'address' => '123 Victoria Island, Lagos',
        'occupation' => 'Accountant',
        'dob' => '1985-03-15',
        'gender' => 'Female',
        'marital_status' => 'Married',
        'next_of_kin_name' => 'John Ade',
        'next_of_kin_phone' => '08087654321',
        'password' => password_hash('alice123', PASSWORD_DEFAULT)
    ],
    [
        'ippis_no' => 'IPPIS002',
        'username' => 'bola.bamidele',
        'first_name' => 'Bola',
        'last_name' => 'Bamidele',
        'email' => 'bola@example.com',
        'phone' => '08023456789',
        'address' => '456 Ikeja GRA, Lagos',
        'occupation' => 'Software Engineer',
        'dob' => '1988-07-22',
        'gender' => 'Male',
        'marital_status' => 'Single',
        'next_of_kin_name' => 'Funmi Bamidele',
        'next_of_kin_phone' => '08098765432',
        'password' => password_hash('bola123', PASSWORD_DEFAULT)
    ],
    [
        'ippis_no' => 'IPPIS003',
        'username' => 'chika.okafor',
        'first_name' => 'Chika',
        'last_name' => 'Okafor',
        'email' => 'chika@example.com',
        'phone' => '08034567890',
        'address' => '789 Enugu Road, Abuja',
        'occupation' => 'Teacher',
        'dob' => '1990-11-08',
        'gender' => 'Female',
        'marital_status' => 'Single',
        'next_of_kin_name' => 'Emeka Okafor',
        'next_of_kin_phone' => '08076543210',
        'password' => password_hash('chika123', PASSWORD_DEFAULT)
    ]
];

$member_ids = [];
foreach ($members as $member) {
    $stmt = $conn->prepare("INSERT INTO members (ippis_no, username, first_name, last_name, email, phone, address, occupation, dob, gender, marital_status, next_of_kin_name, next_of_kin_phone, password, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())");
    
    $stmt->bind_param("ssssssssssssss", 
        $member['ippis_no'], $member['username'], $member['first_name'], $member['last_name'], $member['email'], $member['phone'],
        $member['address'], $member['occupation'], $member['dob'], $member['gender'], 
        $member['marital_status'], $member['next_of_kin_name'], $member['next_of_kin_phone'], 
        $member['password']
    );
    
    if ($stmt->execute()) {
        $member_ids[] = $conn->insert_id;
        echo "Created member: {$member['first_name']} {$member['last_name']} (ID: {$conn->insert_id})\n";
    } else {
        echo "Error creating member {$member['first_name']}: " . $stmt->error . "\n";
    }
    $stmt->close();
}

// Create savings accounts
echo "Creating savings accounts...\n";
foreach ($member_ids as $i => $member_id) {
    $account_number = 'SAV' . str_pad($member_id, 6, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("INSERT INTO savings_accounts (member_id, account_number, balance, account_status, opening_date, created_by, created_at) VALUES (?, ?, 50000.00, 'active', NOW(), 1, NOW())");
    $stmt->bind_param("is", $member_id, $account_number);
    
    if ($stmt->execute()) {
        $account_id = $conn->insert_id;
        echo "Created savings account: {$account_number} for member ID {$member_id}\n";
        
        // Create initial deposit transaction
        $stmt2 = $conn->prepare("INSERT INTO savings_transactions (account_id, member_id, transaction_type, amount, balance_before, balance_after, transaction_date, description, transaction_status, processed_by, created_at) VALUES (?, ?, 'deposit', 50000.00, 0.00, 50000.00, NOW(), 'Initial deposit', 'completed', 1, NOW())");
        $stmt2->bind_param("ii", $account_id, $member_id);
        $stmt2->execute();
        $stmt2->close();
        
        echo "Added initial deposit of NGN 50,000\n";
    } else {
        echo "Error creating savings account: " . $stmt->error . "\n";
    }
    $stmt->close();
}

// Create loans for Alice and Bola
echo "Creating loans...\n";
$loan_data = [
    [
        'member_id' => $member_ids[0], // Alice
        'amount' => 200000,
        'purpose' => 'Business expansion'
    ],
    [
        'member_id' => $member_ids[1], // Bola
        'amount' => 200000,
        'purpose' => 'Home improvement'
    ]
];

$loan_ids = [];
foreach ($loan_data as $loan) {
    $monthly_payment = 18526.70; // Pre-calculated for NGN 200,000 at 10% for 12 months
    
    $stmt = $conn->prepare("INSERT INTO loans (member_id, amount, interest_rate, term, purpose, status, application_date, approval_date, disbursement_date, monthly_payment, created_by, created_at) VALUES (?, ?, 10.0, 12, ?, 'Pending', NOW(), NULL, NULL, ?, 1, NOW())");
    
    $stmt->bind_param("idsd", 
        $loan['member_id'], $loan['amount'], $loan['purpose'], $monthly_payment
    );
    
    if ($stmt->execute()) {
        $loan_ids[] = $conn->insert_id;
        echo "Created loan ID {$conn->insert_id} for member ID {$loan['member_id']} - NGN " . number_format($loan['amount']) . "\n";
    } else {
        echo "Error creating loan: " . $stmt->error . "\n";
    }
    $stmt->close();
}

// Note: Skipping sample repayment to keep loans Pending for approval testing

echo "\n=== SEEDING COMPLETED SUCCESSFULLY ===\n";
echo "Demo members created:\n";
echo "1. Alice Ade (alice@example.com / alice123) - Accountant\n";
echo "2. Bola Bamidele (bola@example.com / bola123) - Software Engineer\n";
echo "3. Chika Okafor (chika@example.com / chika123) - Teacher\n";
echo "\nAll members have NGN 50,000 in savings accounts.\n";
echo "Alice and Bola have Pending loans of NGN 200,000 each (10% interest, 12 months).\n";
echo "No repayments added to keep loans awaiting approval/disbursement.\n";
echo "\nYou can now test the system with these demo accounts!\n";

$conn->close();
?>