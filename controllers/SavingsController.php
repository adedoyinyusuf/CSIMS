<?php

use CSIMS\Container\Container;
use CSIMS\Services\SavingsService;
use CSIMS\Repositories\SavingsAccountRepository;
use CSIMS\Repositories\SavingsTransactionRepository;
use CSIMS\Repositories\MemberRepository;
use CSIMS\Services\SecurityService;
use CSIMS\Services\NotificationService;
use CSIMS\Models\SavingsAccount;
use CSIMS\Models\Member;
use CSIMS\Core\BaseController;

require_once __DIR__ . '/../src/bootstrap.php';

class SavingsController extends BaseController
{
    private SavingsService $service;
    private MemberRepository $memberRepo;
    private SavingsAccountRepository $accountRepo;

    public function __construct()
    {
        parent::__construct(); // Initialize Core Services (DB, Session, Security)

        $container = \CSIMS\bootstrap();
        $mysqli = $this->db; // Use DB connection from BaseController

        // Set up repositories and services explicitly to ensure compatibility
        $this->accountRepo = new SavingsAccountRepository($mysqli);
        $transactionRepo = new SavingsTransactionRepository($mysqli);
        $this->memberRepo = new MemberRepository($mysqli);
        $security = $container->resolve(SecurityService::class);
        $notifier = new NotificationService($mysqli);

        $this->service = new SavingsService(
            $this->accountRepo,
            $transactionRepo,
            $this->memberRepo,
            $security,
            $notifier
        );
    }

    public function createAccount(int $memberId, string $accountType, float $initialDeposit = 0.0, float $interestRate = 0.0): bool
    {
        $createdBy = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 1;
        try {
            $result = $this->service->createAccount([
                'member_id' => $memberId,
                'account_type' => $accountType,
                'account_name' => $accountType . ' Account',
                'opening_balance' => $initialDeposit,
                'interest_rate' => $interestRate,
            ], $createdBy);
            return !empty($result);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function deposit(int $accountId, float $amount, int $memberId, string $description = ''): bool
    {
        $createdBy = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 1;
        try {
            $this->service->deposit($accountId, $amount, $description ?: 'Deposit', 'Cash', $createdBy);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function withdraw(int $accountId, float $amount, int $memberId, string $description = ''): bool
    {
        $createdBy = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 1;
        try {
            $this->service->withdraw($accountId, $amount, $description ?: 'Withdrawal', 'Cash', $createdBy, null, false);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function calculateInterest(?int $accountId = null): array
    {
        try {
            return $this->service->calculateInterest($accountId ?? 0);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Legacy-compatible listing used by views/admin/savings.php
     */
    public function getAllAccounts(?string $search = null, ?string $accountType = null, ?string $status = null): array
    {
        try {
            $filters = [];
            if (!empty($accountType)) {
                $filters['account_type'] = $accountType;
            }
            if (!empty($status)) {
                $filters['account_status'] = $status;
            }
            if (!empty($search)) {
                $filters['search'] = $search;
            }

            // Use repository to get raw accounts then augment member name
            $accounts = $this->accountRepo->findAll($filters);
            $result = [];

            foreach ($accounts as $acc) {
                if ($acc instanceof SavingsAccount) {
                    $row = $acc->toArray();
                } else {
                    $row = (array)$acc;
                }

                $memberName = '';
                if (!empty($row['member_id'])) {
                    $member = $this->memberRepo->find((int)$row['member_id']);
                    if ($member instanceof Member) {
                        $memberName = $member->getFullName();
                    } elseif ($member) {
                        $arr = $member->toArray();
                        $memberName = trim(($arr['first_name'] ?? '') . ' ' . ($arr['last_name'] ?? ''));
                    }
                }

                $result[] = [
                    'id' => $row['account_id'] ?? null,
                    'account_number' => $row['account_number'] ?? '',
                    'member_id' => $row['member_id'] ?? null,
                    'member_name' => $memberName,
                    'account_type' => $row['account_type'] ?? '',
                    'account_name' => $row['account_name'] ?? '',
                    'balance' => (float)($row['balance'] ?? 0.0),
                    'interest_rate' => (float)($row['interest_rate'] ?? 0.0),
                    'status' => $row['account_status'] ?? ($row['status'] ?? 'Active'),
                    'created_at' => $row['created_at'] ?? ($row['opening_date'] ?? null),
                ];
            }

            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Process IPPIS deductions - Create savings deposits from IPPIS upload
     * 
     * @param array $deductions Array of deduction data (member_id, ippis_no, amount)
     * @param string $month Deduction month
     * @param string $year Deduction year
     * @return array Processing results with success/error counts
     */
    public function processIPPISDeductions(array $deductions, string $month, string $year): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
        
        foreach ($deductions as $deduction) {
            try {
                $memberId = (int)$deduction['member_id'];
                $amount = (float)$deduction['amount'];
                $ippisNo = $deduction['ippis_no'];
                
                // Validate member exists
                $member = $this->memberRepo->find($memberId);
                if (!$member) {
                    $errors[] = "Member ID {$memberId} not found";
                    $errorCount++;
                    continue;
                }
                
                // Get member's primary savings account
                $accounts = $this->accountRepo->findByMemberId($memberId);
                
                if (empty($accounts)) {
                    // Create a default savings account if none exists
                    $accountResult = $this->service->createAccount([
                        'member_id' => $memberId,
                        'account_type' => 'Regular',
                        'account_name' => 'Primary Savings',
                        'opening_balance' => 0,
                        'interest_rate' => 5.0,
                    ], $createdBy);
                    
                    if ($accountResult) {
                        $accounts = $this->accountRepo->findByMemberId($memberId);
                    }
                }
                
                if (empty($accounts)) {
                    $errors[] = "Could not create savings account for Member ID {$memberId}";
                    $errorCount++;
                    continue;
                }
                
                // Use first account
                $account = $accounts[0];
                $accountId = $account->getAccountId();
                
                // Create deposit transaction
                $description = "IPPIS Deduction - {$month} {$year}";
                $this->service->deposit($accountId, $amount, $description, 'IPPIS', $createdBy);
                
                $successCount++;
                
            } catch (Throwable $e) {
                $errorCount++;
                $errors[] = "Error processing Member ID {$memberId}: " . $e->getMessage();
            }
        }
        
        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'total' => count($deductions)
        ];
    }
    
    /**
     * Calculate and preview interest for all active accounts
     * 
     * @param string $period Period type: 'monthly', 'quarterly', 'annual'
     * @param string $month Month name (for monthly)
     * @param string $year Year
     * @return array Preview data with calculations
     */
    public function calculateInterestPreview(string $period, string $month, string $year): array
    {
        $database = Database::getInstance();
        $conn = $database->getConnection();
        
        // Get all active accounts
        $sql = "SELECT account_id, member_id, account_name, balance, interest_rate 
                FROM savings_accounts 
                WHERE account_status = 'Active' AND balance > 0
                ORDER BY member_id";
        
        $result = $conn->query($sql);
        $calculations = [];
        $totalInterest = 0;
        $totalAccounts = 0;
        
        while ($account = $result->fetch_assoc()) {
            $balance = (float)$account['balance'];
            $rate = (float)$account['interest_rate'];
            
            // Calculate interest based on period
            $interest = $this->calculateInterestAmount($balance, $rate, $period);
            
            if ($interest > 0) {
                // Get member name
                $member = $this->memberRepo->find((int)$account['member_id']);
                $memberName = $member ? $member->getFullName() : 'Unknown';
                
                $calculations[] = [
                    'account_id' => $account['account_id'],
                    'member_id' => $account['member_id'],
                    'member_name' => $memberName,
                    'account_name' => $account['account_name'],
                    'balance' => $balance,
                    'rate' => $rate,
                    'interest' => $interest
                ];
                
                $totalInterest += $interest;
                $totalAccounts++;
            }
        }
        
        return [
            'calculations' => $calculations,
            'total_accounts' => $totalAccounts,
            'total_interest' => $totalInterest,
            'period' => $period,
            'month' => $month,
            'year' => $year
        ];
    }
    
    /**
     * Calculate interest amount based on period
     * 
     * @param float $balance Account balance
     * @param float $rate Annual interest rate (percentage)
     * @param string $period Period type
     * @return float Calculated interest
     */
    private function calculateInterestAmount(float $balance, float $rate, string $period): float
    {
        if ($balance <= 0 || $rate <= 0) {
            return 0.0;
        }
        
        switch ($period) {
            case 'monthly':
                // Monthly = (Balance × Rate / 12) / 100
                return round(($balance * $rate / 12) / 100, 2);
                
            case 'quarterly':
                // Quarterly = (Balance × Rate / 4) / 100
                return round(($balance * $rate / 4) / 100, 2);
                
            case 'annual':
                // Annual = (Balance × Rate) / 100
                return round(($balance * $rate) / 100, 2);
                
            default:
                return 0.0;
        }
    }
    
    /**
     * Post interest for all active accounts
     * 
     * @param string $period Period type: 'monthly', 'quarterly', 'annual'
     * @param string $month Month name (for monthly)
     * @param string $year Year
     * @return array Results with success/error counts
     */
    public function postInterest(string $period, string $month, string $year): array
    {
        $createdBy = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 1;
        
        // Get preview/calculations first
        $preview = $this->calculateInterestPreview($period, $month, $year);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $totalInterest = 0;
        
        foreach ($preview['calculations'] as $calc) {
            try {
                $accountId = (int)$calc['account_id'];
                $interest = (float)$calc['interest'];
                
                // Generate description based on period
                switch ($period) {
                    case 'monthly':
                        $description = "Monthly Interest - {$month} {$year}";
                        break;
                    case 'quarterly':
                        $quarter = $this->getQuarter($month);
                        $description = "Quarterly Interest - Q{$quarter} {$year}";
                        break;
                    case 'annual':
                        $description = "Annual Interest - {$year}";
                        break;
                    default:
                        $description = "Interest - {$month} {$year}";
                }
                
                // Post interest as deposit
                $this->service->deposit($accountId, $interest, $description, 'Interest', $createdBy);
                
                $successCount++;
                $totalInterest += $interest;
                
            } catch (Throwable $e) {
                $errorCount++;
                $errors[] = "Error posting interest for {$calc['member_name']}: " . $e->getMessage();
            }
        }
        
        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'total_accounts' => count($preview['calculations']),
            'total_interest' => $totalInterest,
            'period' => $period
        ];
    }
    
    /**
      * Get quarter number from month name
     */
    private function getQuarter(string $month): int
    {
        $quarters = [
            'January' => 1, 'February' => 1, 'March' => 1,
            'April' => 2, 'May' => 2, 'June' => 2,
            'July' => 3, 'August' => 3, 'September' => 3,
            'October' => 4, 'November' => 4, 'December' => 4
        ];
        
        return $quarters[$month] ?? 4;
    }
}
