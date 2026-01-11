<?php
/**
 * Auto-Post Monthly Savings Script
 * 
 * This script automatically posts the monthly savings contribution for all active members.
 * It is designed to be run via Cron or Scheduler at the end of every month.
 * 
 * Logic:
 * 1. Identify all active members.
 * 2. Get their 'monthly_contribution' amount.
 * 3. Find their 'Mandatory Savings' account.
 * 4. Post a deposit transaction if one hasn't been posted for the current month.
 * 5. Update the cached 'savings_balance' in the members table.
 * 
 * Usage: php scripts/auto_post_monthly_savings.php [confirm]
 */

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Load configuration and classes
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/config/database.php';
require_once ROOT_PATH . '/src/autoload.php';

use CSIMS\Services\SavingsService;
use CSIMS\Services\SecurityService;
use CSIMS\Services\NotificationService;
use CSIMS\Repositories\SavingsAccountRepository;
use CSIMS\Repositories\SavingsTransactionRepository;
use CSIMS\Repositories\MemberRepository;

// CLI check
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Confirmation arg for safety (optional)
$isDryRun = !isset($argv[1]) || $argv[1] !== 'confirm';

echo "CSIMS Monthly Savings Auto-Poster\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (No changes will be saved)" : "LIVE EXECUTION") . "\n";
echo str_repeat("-", 50) . "\n";

// Initialize Services
$db = Database::getInstance();
$conn = $db->getConnection();

$memberRepo = new MemberRepository($conn);
$accountRepo = new SavingsAccountRepository($conn);
$txnRepo = new SavingsTransactionRepository($conn);
$securityService = new SecurityService($conn);
$notificationService = new NotificationService($conn);

$savingsService = new SavingsService(
    $accountRepo,
    $txnRepo,
    $memberRepo,
    $securityService,
    $notificationService
);

// Get Active Members
// Note: We use raw query for efficiency and specific fields
$sql = "SELECT member_id, id, first_name, last_name, monthly_contribution, savings_balance, email, phone 
        FROM members 
        WHERE status = 'Active' AND monthly_contribution > 0";
$result = $conn->query($sql);

if (!$result) {
    die("Error fetching members: " . $conn->error . "\n");
}

$totalMembers = $result->num_rows;
$successCount = 0;
$skipCount = 0;
$errorCount = 0;
$currentMonth = date('F Y'); // e.g., "January 2026"
$txDescription = "Monthly Auto-Deduction - " . $currentMonth;

echo "Found {$totalMembers} active members with configured monthly savings.\n\n";

while ($member = $result->fetch_assoc()) {
    $memberId = $member['member_id'] ?? $member['id']; // Handle ID variations
    $name = $member['first_name'] . ' ' . $member['last_name'];
    $amount = (float)$member['monthly_contribution'];

    echo "Processing {$name} (ID: {$memberId})... ";

    // 1. Find Mandatory Savings Account
    // We search for an account for this member with type 'Mandatory Savings' or similar
    // Using a raw query to find specific account ID
    $accSql = "SELECT account_id FROM savings_accounts 
               WHERE member_id = ? AND (account_type = 'Mandatory' OR account_type = 'Mandatory Savings' OR account_name LIKE '%Mandatory%') 
               LIMIT 1";
    $accStmt = $conn->prepare($accSql);
    $accStmt->bind_param('i', $memberId);
    $accStmt->execute();
    $accResult = $accStmt->get_result();
    $account = $accResult->fetch_assoc();

    if (!$account) {
        echo "SKIPPED (No Mandatory Account found)\n";
        $errorCount++; // Treat as error or warning
        continue;
    }

    $accountId = $account['account_id'];

    // 2. Check for Duplicate Transaction
    // Check if we already posted this description to this account this month
    // We can also check date range for stricter control
    $checkSql = "SELECT transaction_id FROM savings_transactions 
                 WHERE account_id = ? 
                   AND transaction_type = 'Deposit' 
                   AND description = ? 
                   AND transaction_status = 'Completed'
                 LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('is', $accountId, $txDescription);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        echo "SKIPPED (Already Posted)\n";
        $skipCount++;
        continue;
    }

    // 3. Post Transaction
    if (!$isDryRun) {
        try {
            // Post via Service (handles validation, logging, notification)
            $txn = $savingsService->deposit(
                $accountId,
                $amount,
                $txDescription,
                'Auto-Deduction', // Payment Method
                1, // Processed By System (Admin ID 1)
                null // Auto-generate Ref
            );

            // 4. Update Legacy 'savings_balance' column in members table
            // This ensures profile page (if reading from members table) stays in sync
            $updateMemberSql = "UPDATE members SET savings_balance = savings_balance + ? WHERE member_id = ?";
            // Handle schema: check if member_id is the PK or id is PK. Usually id is PK.
            // MemberRepository uses 'member_id' in WHERE clauses for some, but PK is 'id' usually.
            // Let's rely on the ID we fetched.
            $pkId = $member['id']; // assuming 'id' is the primary key column from fetch
            $updStmt = $conn->prepare("UPDATE members SET savings_balance = savings_balance + ? WHERE id = ?");
            $updStmt->bind_param('di', $amount, $pkId);
            $updStmt->execute();

            echo "SUCCESS (₦" . number_format($amount, 2) . ")\n";
            $successCount++;

        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    } else {
        echo "WOULD POST (₦" . number_format($amount, 2) . ")\n";
        $successCount++; // Count as potential success
    }
}

echo str_repeat("-", 50) . "\n";
echo "Summary:\n";
echo "Total Processed: {$totalMembers}\n";
echo "Successful: {$successCount}\n";
echo "Skipped (Already Done): {$skipCount}\n";
echo "Errors: {$errorCount}\n";

if ($isDryRun) {
    echo "\nTo execute purely, run:\nphp scripts/auto_post_monthly_savings.php confirm\n";
}
