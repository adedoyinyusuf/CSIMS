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

require_once __DIR__ . '/../src/bootstrap.php';

class SavingsController
{
    private SavingsService $service;
    private MemberRepository $memberRepo;
    private SavingsAccountRepository $accountRepo;

    public function __construct()
    {
        $container = \CSIMS\bootstrap();
        $mysqli = $container->resolve(mysqli::class);

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
}