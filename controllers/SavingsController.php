<?php
/**
 * Savings Controller
 * 
 * Handles HTTP requests for savings account operations
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/autoload.php';

use CSIMS\Services\SavingsService;
use CSIMS\Services\SecurityService;
use CSIMS\Services\NotificationService;
use CSIMS\Repositories\SavingsAccountRepository;
use CSIMS\Repositories\SavingsTransactionRepository;
use CSIMS\Repositories\MemberRepository;

class SavingsController
{
    private SavingsService $savingsService;
    private SecurityService $securityService;
    private mysqli $connection;
    
    public function __construct()
    {
        // Initialize database connection
        $database = Database::getInstance();
        $this->connection = $database->getConnection();
        
        // Initialize repositories
        $accountRepository = new SavingsAccountRepository($this->connection);
        $transactionRepository = new SavingsTransactionRepository($this->connection);
        $memberRepository = new MemberRepository($this->connection);
        
        // Initialize services
        $this->securityService = resolve(SecurityService::class);
        $notificationService = resolve(NotificationService::class);
        
        $this->savingsService = new SavingsService(
            $accountRepository,
            $transactionRepository,
            $memberRepository,
            $this->securityService,
            $notificationService
        );
    }
    
    /**
     * Handle savings operations
     */
    public function handleRequest()
    {
        // Ensure user is authenticated
        if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Get action from request
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($action) {
                case 'list_accounts':
                    $this->listAccounts();
                    break;
                    
                case 'create_account':
                    if ($method === 'POST') {
                        $this->createAccount();
                    } else {
                        $this->showCreateAccountForm();
                    }
                    break;
                    
                case 'view_account':
                    $this->viewAccount();
                    break;
                    
                case 'deposit':
                    if ($method === 'POST') {
                        $this->processDeposit();
                    } else {
                        $this->showDepositForm();
                    }
                    break;
                    
                case 'withdraw':
                    if ($method === 'POST') {
                        $this->processWithdrawal();
                    } else {
                        $this->showWithdrawalForm();
                    }
                    break;
                    
                case 'transfer':
                    if ($method === 'POST') {
                        $this->processTransfer();
                    } else {
                        $this->showTransferForm();
                    }
                    break;
                    
                case 'account_history':
                    $this->getAccountHistory();
                    break;
                    
                case 'close_account':
                    $this->closeAccount();
                    break;
                    
                case 'calculate_interest':
                    $this->calculateInterest();
                    break;
                    
                case 'search_accounts':
                    $this->searchAccounts();
                    break;
                    
                case 'get_statistics':
                    $this->getStatistics();
                    break;
                    
                case 'export_transactions':
                    $this->exportTransactions();
                    break;
                    
                default:
                    $this->listAccounts(); // Default action
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * List savings accounts
     */
    private function listAccounts()
    {
        $accountRepository = new SavingsAccountRepository($this->connection);
        
        // Get filters from request
        $filters = [];
        if (isset($_GET['member_id'])) {
            $filters['member_id'] = (int)$_GET['member_id'];
        }
        if (isset($_GET['account_type'])) {
            $filters['account_type'] = $_GET['account_type'];
        }
        if (isset($_GET['status'])) {
            $filters['account_status'] = $_GET['status'];
        }
        
        // Pagination
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $accounts = $accountRepository->findAll($filters, ['created_at' => 'DESC'], $limit, $offset);
        $totalAccounts = $accountRepository->countTotal($filters);
        
        if ($this->isAjaxRequest()) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'accounts' => array_map(fn($account) => $account->toArray(), $accounts),
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($totalAccounts / $limit),
                        'total_records' => $totalAccounts,
                        'per_page' => $limit
                    ]
                ]
            ]);
        } else {
            include __DIR__ . '/../views/admin/savings_accounts.php';
        }
    }
    
    /**
     * Create new savings account
     */
    private function createAccount()
    {
        $this->requirePermission('create_savings_account');
        
        $data = [
            'member_id' => (int)$_POST['member_id'],
            'account_type' => $_POST['account_type'],
            'account_name' => $_POST['account_name'],
            'opening_balance' => (float)($_POST['opening_balance'] ?? 0),
            'minimum_balance' => (float)($_POST['minimum_balance'] ?? 0),
            'interest_rate' => (float)($_POST['interest_rate'] ?? 0),
            'interest_calculation' => $_POST['interest_calculation'] ?? 'monthly',
            'maturity_date' => $_POST['maturity_date'] ?? null,
            'target_amount' => !empty($_POST['target_amount']) ? (float)$_POST['target_amount'] : null,
            'monthly_target' => !empty($_POST['monthly_target']) ? (float)$_POST['monthly_target'] : null,
            'auto_deduct' => isset($_POST['auto_deduct']),
            'notes' => $_POST['notes'] ?? null
        ];
        
        $createdBy = $_SESSION['admin_id'] ?? $_SESSION['user_id'];
        
        $account = $this->savingsService->createAccount($data, $createdBy);
        
        if ($this->isAjaxRequest()) {
            echo json_encode([
                'success' => true,
                'message' => 'Savings account created successfully',
                'data' => $account->toArray()
            ]);
        } else {
            $_SESSION['success_message'] = 'Savings account created successfully';
            header("Location: ?action=view_account&id=" . $account->getAccountId());
            exit;
        }
    }
    
    /**
     * Show create account form
     */
    private function showCreateAccountForm()
    {
        $this->requirePermission('create_savings_account');
        
        // Get members for dropdown
        $memberRepository = new MemberRepository($this->connection);
        $members = $memberRepository->findAll(['status' => 'Active'], ['first_name' => 'ASC']);
        
        include __DIR__ . '/../views/admin/create_savings_account.php';
    }
    
    /**
     * View savings account details
     */
    private function viewAccount()
    {
        $accountId = (int)($_GET['id'] ?? 0);
        
        if ($accountId <= 0) {
            throw new InvalidArgumentException('Invalid account ID');
        }
        
        $summary = $this->savingsService->getAccountSummary($accountId);
        
        if ($this->isAjaxRequest()) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'account' => $summary['account']->toArray(),
                    'recent_transactions' => array_map(fn($t) => $t->toArray(), $summary['recent_transactions']),
                    'monthly_summary' => $summary['monthly_summary'],
                    'balance' => $summary['balance'],
                    'formatted_balance' => $summary['formatted_balance'],
                    'target_progress' => $summary['target_progress'],
                    'days_until_maturity' => $summary['days_until_maturity'],
                    'has_matured' => $summary['has_matured'],
                    'has_met_target' => $summary['has_met_target']
                ]
            ]);
        } else {
            include __DIR__ . '/../views/admin/view_savings_account.php';
        }
    }
    
    /**
     * Process deposit
     */
    private function processDeposit()
    {
        $this->requirePermission('process_savings_deposit');
        
        $accountId = (int)$_POST['account_id'];
        $amount = (float)$_POST['amount'];
        $description = $_POST['description'] ?? null;
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $referenceNumber = $_POST['reference_number'] ?? null;
        
        $processedBy = $_SESSION['admin_id'] ?? $_SESSION['user_id'];
        
        $transaction = $this->savingsService->deposit(
            $accountId,
            $amount,
            $description,
            $paymentMethod,
            $processedBy,
            $referenceNumber
        );
        
        if ($this->isAjaxRequest()) {
            echo json_encode([
                'success' => true,
                'message' => 'Deposit processed successfully',
                'data' => $transaction->toArray()
            ]);
        } else {
            $_SESSION['success_message'] = 'Deposit processed successfully';
            header("Location: ?action=view_account&id=" . $accountId);
            exit;
        }
    }
    
    /**
     * Show deposit form
     */
    private function showDepositForm()
    {
        $accountId = (int)($_GET['account_id'] ?? 0);
        
        if ($accountId <= 0) {
            throw new InvalidArgumentException('Invalid account ID');
        }
        
        $accountRepository = new SavingsAccountRepository($this->connection);
        $account = $accountRepository->find($accountId);
        
        if (!$account) {
            throw new InvalidArgumentException('Account not found');
        }
        
        include __DIR__ . '/../views/admin/deposit_form.php';
    }
    
    /**
     * Process withdrawal
     */
    private function processWithdrawal()
    {
        $this->requirePermission('process_savings_withdrawal');
        
        $accountId = (int)$_POST['account_id'];
        $amount = (float)$_POST['amount'];
        $description = $_POST['description'] ?? null;
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $referenceNumber = $_POST['reference_number'] ?? null;
        
        $processedBy = $_SESSION['admin_id'] ?? $_SESSION['user_id'];
        
        $transaction = $this->savingsService->withdraw(
            $accountId,
            $amount,
            $description,
            $paymentMethod,
            $processedBy,
            $referenceNumber
        );
        
        if ($this->isAjaxRequest()) {
            echo json_encode([
                'success' => true,
                'message' => 'Withdrawal processed successfully',
                'data' => $transaction->toArray()
            ]);
        } else {
            $_SESSION['success_message'] = 'Withdrawal processed successfully';
            header("Location: ?action=view_account&id=" . $accountId);
            exit;
        }
    }
    
    /**
     * Show withdrawal form
     */
    private function showWithdrawalForm()
    {
        $accountId = (int)($_GET['account_id'] ?? 0);
        
        if ($accountId <= 0) {
            throw new InvalidArgumentException('Invalid account ID');
        }
        
        $accountRepository = new SavingsAccountRepository($this->connection);
        $account = $accountRepository->find($accountId);
        
        if (!$account) {
            throw new InvalidArgumentException('Account not found');
        }
        
        include __DIR__ . '/../views/admin/withdrawal_form.php';
    }
    
    /**
     * Process transfer
     */
    private function processTransfer()
    {
        $this->requirePermission('process_savings_transfer');
        
        $fromAccountId = (int)$_POST['from_account_id'];
        $toAccountId = (int)$_POST['to_account_id'];
        $amount = (float)$_POST['amount'];
        $description = $_POST['description'] ?? null;
        
        $processedBy = $_SESSION['admin_id'] ?? $_SESSION['user_id'];
        
        $transactions = $this->savingsService->transfer(
            $fromAccountId,
            $toAccountId,
            $amount,
            $description,
            $processedBy
        );
        
        if ($this->isAjaxRequest()) {
            echo json_encode([
                'success' => true,
                'message' => 'Transfer processed successfully',
                'data' => [
                    'out_transaction' => $transactions[0]->toArray(),
                    'in_transaction' => $transactions[1]->toArray()
                ]
            ]);
        } else {
            $_SESSION['success_message'] = 'Transfer processed successfully';
            header("Location: ?action=view_account&id=" . $fromAccountId);
            exit;
        }
    }
    
    /**
     * Show transfer form
     */
    private function showTransferForm()
    {
        $fromAccountId = (int)($_GET['from_account_id'] ?? 0);
        
        if ($fromAccountId <= 0) {
            throw new InvalidArgumentException('Invalid account ID');
        }
        
        $accountRepository = new SavingsAccountRepository($this->connection);
        $fromAccount = $accountRepository->find($fromAccountId);
        
        if (!$fromAccount) {
            throw new InvalidArgumentException('Account not found');
        }
        
        // Get other accounts for transfer destination
        $allAccounts = $accountRepository->findAll(['account_status' => 'Active']);
        $toAccounts = array_filter($allAccounts, fn($account) => $account->getAccountId() !== $fromAccountId);
        
        include __DIR__ . '/../views/admin/transfer_form.php';
    }
    
    /**
     * Get account transaction history
     */
    private function getAccountHistory()
    {
        $accountId = (int)($_GET['account_id'] ?? 0);
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $transactionRepository = new SavingsTransactionRepository($this->connection);
        $transactions = $transactionRepository->getAccountHistory($accountId, $limit, $offset);
        $totalTransactions = $transactionRepository->countTotal(['account_id' => $accountId]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'transactions' => array_map(fn($t) => $t->toArray(), $transactions),
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalTransactions / $limit),
                    'total_records' => $totalTransactions,
                    'per_page' => $limit
                ]
            ]
        ]);
    }
    
    /**
     * Close savings account
     */
    private function closeAccount()
    {
        $this->requirePermission('close_savings_account');
        
        $accountId = (int)$_POST['account_id'];
        $reason = $_POST['reason'] ?? 'Account closure requested';
        
        $processedBy = $_SESSION['admin_id'] ?? $_SESSION['user_id'];
        
        $result = $this->savingsService->closeAccount($accountId, $reason, $processedBy);
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Account closed successfully' : 'Failed to close account'
        ]);
    }
    
    /**
     * Calculate interest for accounts
     */
    private function calculateInterest()
    {
        $this->requirePermission('calculate_interest');
        
        $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
        
        $results = $this->savingsService->calculateInterest($accountId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Interest calculation completed',
            'data' => $results
        ]);
    }
    
    /**
     * Search savings accounts
     */
    private function searchAccounts()
    {
        $filters = [
            'member_name' => $_GET['member_name'] ?? null,
            'account_number' => $_GET['account_number'] ?? null,
            'account_type' => $_GET['account_type'] ?? null,
            'account_status' => $_GET['account_status'] ?? null,
            'balance_min' => $_GET['balance_min'] ?? null,
            'balance_max' => $_GET['balance_max'] ?? null,
            'opening_date_from' => $_GET['opening_date_from'] ?? null,
            'opening_date_to' => $_GET['opening_date_to'] ?? null,
            'limit' => (int)($_GET['limit'] ?? 50)
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        $accountRepository = new SavingsAccountRepository($this->connection);
        $accounts = $accountRepository->searchAccounts($filters);
        
        echo json_encode([
            'success' => true,
            'data' => array_map(fn($account) => $account->toArray(), $accounts)
        ]);
    }
    
    /**
     * Get savings statistics
     */
    private function getStatistics()
    {
        $accountRepository = new SavingsAccountRepository($this->connection);
        $transactionRepository = new SavingsTransactionRepository($this->connection);
        
        $accountStats = $accountRepository->getAccountStatistics();
        $transactionStats = $transactionRepository->getTransactionStatistics([
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-t')
        ]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'account_statistics' => $accountStats,
                'transaction_statistics' => $transactionStats
            ]
        ]);
    }
    
    /**
     * Export transactions
     */
    private function exportTransactions()
    {
        $this->requirePermission('export_savings_data');
        
        $filters = [
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'account_id' => $_GET['account_id'] ?? null,
            'transaction_type' => $_GET['transaction_type'] ?? null,
            'transaction_status' => $_GET['transaction_status'] ?? null
        ];
        
        $transactionRepository = new SavingsTransactionRepository($this->connection);
        $transactions = $transactionRepository->searchTransactions($filters);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="savings_transactions_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write header row
        fputcsv($output, [
            'Transaction ID', 'Account Number', 'Member Name', 'Transaction Type',
            'Amount', 'Balance Before', 'Balance After', 'Transaction Date',
            'Payment Method', 'Reference Number', 'Description', 'Status'
        ]);
        
        // Write data rows
        foreach ($transactions as $transaction) {
            fputcsv($output, [
                $transaction->getTransactionId(),
                $transaction->getAccountNumber() ?? '',
                $transaction->getMemberName() ?? '',
                $transaction->getTransactionType(),
                $transaction->getAmount(),
                $transaction->getBalanceBefore(),
                $transaction->getBalanceAfter(),
                $transaction->getTransactionDate()->format('Y-m-d'),
                $transaction->getPaymentMethod(),
                $transaction->getReferenceNumber(),
                $transaction->getDescription(),
                $transaction->getTransactionStatus()
            ]);
        }
        
        fclose($output);
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Require specific permission
     */
    private function requirePermission(string $permission)
    {
        // Check if user has required permission
        if (!$this->hasPermission($permission)) {
            throw new Exception('Access denied. Required permission: ' . $permission);
        }
    }
    
    /**
     * Check if user has permission
     */
    private function hasPermission(string $permission): bool
    {
        // Basic permission check - can be enhanced with role-based permissions
        return isset($_SESSION['admin_id']) || isset($_SESSION['user_id']);
    }
    
    /**
     * Handle errors
     */
    private function handleError(Exception $e)
    {
        error_log("Savings Controller Error: " . $e->getMessage());
        
        if ($this->isAjaxRequest()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } else {
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: ?action=list_accounts");
            exit;
        }
    }
}

// Initialize and handle request
if (!defined('SAVINGS_CONTROLLER_INCLUDED')) {
    define('SAVINGS_CONTROLLER_INCLUDED', true);
    $controller = new SavingsController();
    $controller->handleRequest();
}
?>