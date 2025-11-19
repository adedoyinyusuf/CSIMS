<?php
/**
 * CSIMS Demo Data Seeder
 *
 * Usage:
 *   php scripts/seed_demo_data.php [--dry-run]
 *
 * This script seeds demo members, savings accounts & transactions,
 * loans, payment schedules, and one repayment.
 */

// Autoload and bootstrap container
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

use CSIMS\Repositories\MemberRepository;
use CSIMS\Repositories\SavingsAccountRepository;
use CSIMS\Repositories\SavingsTransactionRepository;
use CSIMS\Repositories\LoanRepository;
use CSIMS\Services\LoanService;
use CSIMS\Models\Member;
use CSIMS\Models\SavingsAccount;
use CSIMS\Models\SavingsTransaction;
use CSIMS\Models\Loan;

// Initialize container
$container = CSIMS\bootstrap();

/**
 * Helpers
 */
function arg_has(string $flag): bool {
    global $argv;
    foreach ($argv ?? [] as $arg) {
        if ($arg === $flag) return true;
    }
    return false;
}

function println(string $msg = ''): void {
    echo $msg . PHP_EOL;
}

// Dry run mode
$dryRun = arg_has('--dry-run');
println('[Seeder] Dry-run mode: ' . ($dryRun ? 'ON' : 'OFF'));

// Resolve dependencies
/** @var mysqli $db */
$db = $container->resolve(mysqli::class);
/** @var MemberRepository $memberRepo */
$memberRepo = $container->resolve(MemberRepository::class);
/** @var SavingsAccountRepository $accountRepo */
$accountRepo = $container->resolve(SavingsAccountRepository::class);
/** @var SavingsTransactionRepository $txRepo */
$txRepo = $container->resolve(SavingsTransactionRepository::class);
/** @var LoanRepository $loanRepo */
$loanRepo = $container->resolve(LoanRepository::class);
/** @var LoanService $loanService */
$loanService = $container->resolve(LoanService::class);

// Seed data definitions
$adminUserId = 1; // processed_by/created_by
$today = date('Y-m-d');

$members = [
    [
        'first_name' => 'Alice',
        'last_name' => 'Ade',
        'email' => 'alice.ade@example.com',
        'phone' => '08030000001',
        'username' => 'alice',
        'password' => password_hash('alice123', PASSWORD_DEFAULT),
        'status' => 'Active',
        'gender' => 'Female',
        'dob' => '1990-04-12',
        'address' => '12 Unity Crescent, Abuja',
        'occupation' => 'Accountant',
        'join_date' => $today
    ],
    [
        'first_name' => 'Bola',
        'last_name' => 'Bamidele',
        'email' => 'bola.b@example.com',
        'phone' => '08030000002',
        'username' => 'bola',
        'password' => password_hash('bola123', PASSWORD_DEFAULT),
        'status' => 'Active',
        'gender' => 'Male',
        'dob' => '1987-11-03',
        'address' => '8 Market Road, Lagos',
        'occupation' => 'Engineer',
        'join_date' => $today
    ],
    [
        'first_name' => 'Chika',
        'last_name' => 'Okafor',
        'email' => 'chika.ok@example.com',
        'phone' => '08030000003',
        'username' => 'chika',
        'password' => password_hash('chika123', PASSWORD_DEFAULT),
        'status' => 'Active',
        'gender' => 'Female',
        'dob' => '1992-07-19',
        'address' => '5 Central Ave, Enugu',
        'occupation' => 'Teacher',
        'join_date' => $today
    ],
];

$createdMembers = [];
/** @var Member[] $createdMembers */

println('[Seeder] Seeding members...');
foreach ($members as $m) {
    $member = Member::fromArray($m);
    /** @var Member $member */
    if ($dryRun) {
        println('  [DRY] Member: ' . json_encode($member->toArray()));
        // Simulate IDs for dry-run
        $member->fromArray(['member_id' => 1000 + count($createdMembers) + 1]);
        $createdMembers[] = $member;
        continue;
    }
    /** @var Member $saved */
    $saved = $memberRepo->create($member);
    println("  Created member #{$saved->getId()} - {$m['first_name']} {$m['last_name']}");
    $createdMembers[] = $saved;
}

// Seed savings accounts and transactions
println('[Seeder] Seeding savings accounts and initial deposits...');
$createdAccounts = [];
/** @var SavingsAccount[] $createdAccounts */
foreach ($createdMembers as $member) {
    /** @var Member $member */
    $accountNumber = 'SA' . date('ymdHis') . rand(100, 999);
    $initialDeposit = 50000.00; // NGN
    $accountData = [
        'member_id' => $member->getId(),
        'account_number' => $accountNumber,
        'account_type' => 'Regular',
        'account_name' => $member->getFullName() . ' - Regular',
        'balance' => $initialDeposit, // initialize with deposit
        'interest_rate' => 0.00,
        'interest_calculation' => 'monthly',
        'opening_date' => $today,
        'account_status' => 'Active',
        'created_by' => $adminUserId,
        'notes' => 'Seeded demo account'
    ];
    $account = SavingsAccount::fromArray($accountData);

    if ($dryRun) {
        println('  [DRY] Account: ' . json_encode($account->toArray()));
        $account->fromArray(['account_id' => 2000 + count($createdAccounts) + 1]);
        $createdAccounts[] = $account;
    } else {
        /** @var SavingsAccount $savedAcc */
        $savedAcc = $accountRepo->create($account);
        println("  Created savings account #{$savedAcc->getId()} for member #{$member->getId()}");
        $createdAccounts[] = $savedAcc;
    }

    // Initial deposit transaction
    $txData = [
        'account_id' => $account->getId() ?? ($createdAccounts[array_key_last($createdAccounts)])->getId(),
        'member_id' => $member->getId(),
        'transaction_type' => 'Deposit',
        'amount' => $initialDeposit,
        'balance_before' => 0.00,
        'balance_after' => $initialDeposit,
        'transaction_date' => $today,
        'transaction_time' => date('H:i:s'),
        'payment_method' => 'Bank_Transfer',
        'reference_number' => 'DEP-' . substr($accountNumber, -6),
        'description' => 'Initial deposit (seed)',
        'processed_by' => $adminUserId,
        'approved_by' => null,
        'transaction_status' => 'Completed',
        'fees_charged' => 0.00,
        'receipt_number' => 'RCPT-' . substr($accountNumber, -6),
        'notes' => 'Seeded transaction'
    ];
    $tx = SavingsTransaction::fromArray($txData);
    if ($dryRun) {
        println('  [DRY] Transaction: ' . json_encode($tx->toArray()));
    } else {
        /** @var SavingsTransaction $savedTx */
        $savedTx = $txRepo->create($tx);
        println("  Recorded deposit tx #{$savedTx->getId()} for account #{$savedTx->getAccountId()}");
    }
}

// Seed loans for selected members
println('[Seeder] Seeding loans...');
$createdLoans = [];
foreach ($createdMembers as $ix => $member) {
    // Seed a loan for first two members
    if ($ix > 1) break;
    $loanData = [
        'member_id' => $member->getId(),
        'amount' => 200000.00,
        'purpose' => 'Personal development seed loan',
        'term_months' => 12,
        'interest_rate' => 10.0,
        'status' => 'Pending',
        'application_date' => $today,
        'notes' => 'Seeded demo loan'
    ];
    $loan = Loan::fromArray($loanData);

    if ($dryRun) {
        // Simulate monthly payment calc and ID
        $loan->fromArray(['loan_id' => 3000 + count($createdLoans) + 1, 'monthly_payment' => $loan->calculateMonthlyPayment()]);
        println('  [DRY] Loan: ' . json_encode($loan->toArray()));
        $createdLoans[] = $loan;
    } else {
        $savedLoan = $loanRepo->create($loan);
        println("  Created loan #{$savedLoan->getId()} for member #{$member->getId()} @ {$loanData['interest_rate']}%");
        $createdLoans[] = $savedLoan;
    }
}

// Skip generating payment schedules for pending loans to focus on approval testing
println('[Seeder] Skipping payment schedules for pending loans...');

// Skipping sample loan repayment to keep loans in Pending state for approval testing
println('[Seeder] Skipping loan repayment insertion...');

println('[Seeder] Done.');