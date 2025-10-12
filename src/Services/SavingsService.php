<?php

namespace CSIMS\Services;

use CSIMS\Models\SavingsAccount;
use CSIMS\Models\SavingsTransaction;
use CSIMS\Repositories\SavingsAccountRepository;
use CSIMS\Repositories\SavingsTransactionRepository;
use CSIMS\Repositories\MemberRepository;
use CSIMS\Exceptions\ValidationException;
use CSIMS\Exceptions\DatabaseException;
use CSIMS\Exceptions\BusinessException;
use DateTime;
use Exception;

/**
 * Savings Service
 * 
 * Handles business logic for savings operations
 */
class SavingsService
{
    private SavingsAccountRepository $accountRepository;
    private SavingsTransactionRepository $transactionRepository;
    private MemberRepository $memberRepository;
    private SecurityService $securityService;
    private NotificationService $notificationService;
    
    public function __construct(
        SavingsAccountRepository $accountRepository,
        SavingsTransactionRepository $transactionRepository,
        MemberRepository $memberRepository,
        SecurityService $securityService,
        NotificationService $notificationService
    ) {
        $this->accountRepository = $accountRepository;
        $this->transactionRepository = $transactionRepository;
        $this->memberRepository = $memberRepository;
        $this->securityService = $securityService;
        $this->notificationService = $notificationService;
    }
    
    /**
     * Create a new savings account
     */
    public function createAccount(array $data, int $createdBy): SavingsAccount
    {
        // Validate data
        $validatedData = $this->validateAccountData($data);
        
        // Check if member exists
        $member = $this->memberRepository->find($validatedData['member_id']);
        if (!$member) {
            throw new ValidationException('Member not found');
        }
        
        // Generate unique account number
        $accountNumber = $this->accountRepository->generateUniqueAccountNumber();
        
        // Create account
        $account = new SavingsAccount([
            'member_id' => $validatedData['member_id'],
            'account_number' => $accountNumber,
            'account_type' => $validatedData['account_type'],
            'account_name' => $validatedData['account_name'],
            'balance' => $validatedData['opening_balance'] ?? 0.00,
            'minimum_balance' => $validatedData['minimum_balance'] ?? 0.00,
            'interest_rate' => $validatedData['interest_rate'] ?? 0.00,
            'interest_calculation' => $validatedData['interest_calculation'] ?? 'monthly',
            'maturity_date' => $validatedData['maturity_date'] ?? null,
            'target_amount' => $validatedData['target_amount'] ?? null,
            'monthly_target' => $validatedData['monthly_target'] ?? null,
            'auto_deduct' => $validatedData['auto_deduct'] ?? false,
            'opening_date' => new DateTime(),
            'created_by' => $createdBy,
            'notes' => $validatedData['notes'] ?? null
        ]);
        
        // Validate account
        if (!$account->isValid()) {
            throw new ValidationException('Invalid account data: ' . implode(', ', $account->validate()));
        }
        
        // Save account
        $savedAccount = $this->accountRepository->create($account);
        
        // Create opening transaction if initial balance > 0
        if ($validatedData['opening_balance'] > 0) {
            $this->deposit(
                $savedAccount->getAccountId(),
                $validatedData['opening_balance'],
                'Opening Balance',
                'Cash',
                $createdBy
            );
        }
        
        // Log activity
        $this->logActivity('account_created', $savedAccount->getAccountId(), $createdBy);
        
        // Send notification
        $this->notificationService->sendAccountCreatedNotification($savedAccount);
        
        return $savedAccount;
    }
    
    /**
     * Make a deposit to savings account
     */
    public function deposit(
        int $accountId,
        float $amount,
        string $description = null,
        string $paymentMethod = 'Cash',
        int $processedBy = 1,
        string $referenceNumber = null
    ): SavingsTransaction {
        // Validate amount
        if ($amount <= 0) {
            throw new ValidationException('Deposit amount must be positive');
        }
        
        // Get account
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new ValidationException('Account not found');
        }
        
        // Check if account allows deposits
        if (!$account->allowsDeposits()) {
            throw new BusinessException('Account does not allow deposits');
        }
        
        // Calculate new balance
        $newBalance = $account->getBalance() + $amount;
        
        // Create transaction
        $transaction = new SavingsTransaction([
            'account_id' => $accountId,
            'member_id' => $account->getMemberId(),
            'transaction_type' => 'Deposit',
            'amount' => $amount,
            'balance_before' => $account->getBalance(),
            'balance_after' => $newBalance,
            'transaction_date' => new DateTime(),
            'transaction_time' => date('H:i:s'),
            'payment_method' => $paymentMethod,
            'reference_number' => $referenceNumber ?: $this->generateReferenceNumber(),
            'description' => $description,
            'processed_by' => $processedBy,
            'transaction_status' => 'Completed',
            'receipt_number' => $this->transactionRepository->generateUniqueReceiptNumber()
        ]);
        
        // Validate transaction
        if (!$transaction->isValid()) {
            throw new ValidationException('Invalid transaction data: ' . implode(', ', $transaction->validate()));
        }
        
        try {
            // Save transaction
            $savedTransaction = $this->transactionRepository->create($transaction);
            
            // Update account balance
            $this->accountRepository->updateBalance($accountId, $newBalance);
            
            // Log activity
            $this->logActivity('deposit', $accountId, $processedBy, $amount);
            
            // Send notification
            $this->notificationService->sendTransactionNotification($savedTransaction, 'deposit');
            
            return $savedTransaction;
        } catch (Exception $e) {
            throw new DatabaseException('Failed to process deposit: ' . $e->getMessage());
        }
    }
    
    /**
     * Make a withdrawal from savings account
     */
    public function withdraw(
        int $accountId,
        float $amount,
        string $description = null,
        string $paymentMethod = 'Cash',
        int $processedBy = 1,
        string $referenceNumber = null,
        bool $requireApproval = true
    ): SavingsTransaction {
        // Validate amount
        if ($amount <= 0) {
            throw new ValidationException('Withdrawal amount must be positive');
        }
        
        // Get account
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new ValidationException('Account not found');
        }
        
        // Check if account allows withdrawals
        if (!$account->allowsWithdrawals()) {
            throw new BusinessException('Account does not allow withdrawals');
        }
        
        // Check sufficient balance
        $newBalance = $account->getBalance() - $amount;
        if ($newBalance < 0) {
            throw new BusinessException('Insufficient balance');
        }
        
        // Check minimum balance
        if ($newBalance < $account->getMinimumBalance()) {
            throw new BusinessException('Withdrawal would violate minimum balance requirement');
        }
        
        // Calculate fees if any
        $fees = $this->calculateWithdrawalFees($account, $amount);
        $totalDeduction = $amount + $fees;
        $finalBalance = $account->getBalance() - $totalDeduction;
        
        // Check final balance after fees
        if ($finalBalance < $account->getMinimumBalance()) {
            throw new BusinessException('Withdrawal including fees would violate minimum balance requirement');
        }
        
        // Create transaction
        $transaction = new SavingsTransaction([
            'account_id' => $accountId,
            'member_id' => $account->getMemberId(),
            'transaction_type' => 'Withdrawal',
            'amount' => $amount,
            'balance_before' => $account->getBalance(),
            'balance_after' => $finalBalance,
            'transaction_date' => new DateTime(),
            'transaction_time' => date('H:i:s'),
            'payment_method' => $paymentMethod,
            'reference_number' => $referenceNumber ?: $this->generateReferenceNumber(),
            'description' => $description,
            'processed_by' => $processedBy,
            'fees_charged' => $fees,
            'transaction_status' => $requireApproval && $transaction->requiresApproval() ? 'Pending' : 'Completed',
            'receipt_number' => $this->transactionRepository->generateUniqueReceiptNumber()
        ]);
        
        // Validate transaction
        if (!$transaction->isValid()) {
            throw new ValidationException('Invalid transaction data: ' . implode(', ', $transaction->validate()));
        }
        
        try {
            // Save transaction
            $savedTransaction = $this->transactionRepository->create($transaction);
            
            // If approved, update account balance immediately
            if ($transaction->getTransactionStatus() === 'Completed') {
                $this->accountRepository->updateBalance($accountId, $finalBalance);
                
                // Create fee transaction if applicable
                if ($fees > 0) {
                    $this->createFeeTransaction($accountId, $fees, 'Withdrawal fee', $processedBy);
                }
            }
            
            // Log activity
            $this->logActivity('withdrawal', $accountId, $processedBy, $amount);
            
            // Send notification
            $this->notificationService->sendTransactionNotification($savedTransaction, 'withdrawal');
            
            return $savedTransaction;
        } catch (Exception $e) {
            throw new DatabaseException('Failed to process withdrawal: ' . $e->getMessage());
        }
    }
    
    /**
     * Transfer funds between savings accounts
     */
    public function transfer(
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        string $description = null,
        int $processedBy = 1,
        string $referenceNumber = null
    ): array {
        // Validate amount
        if ($amount <= 0) {
            throw new ValidationException('Transfer amount must be positive');
        }
        
        // Get accounts
        $fromAccount = $this->accountRepository->find($fromAccountId);
        $toAccount = $this->accountRepository->find($toAccountId);
        
        if (!$fromAccount || !$toAccount) {
            throw new ValidationException('One or both accounts not found');
        }
        
        // Validate accounts
        if (!$fromAccount->allowsWithdrawals()) {
            throw new BusinessException('Source account does not allow withdrawals');
        }
        
        if (!$toAccount->allowsDeposits()) {
            throw new BusinessException('Destination account does not allow deposits');
        }
        
        // Check sufficient balance
        if ($fromAccount->getBalance() < $amount) {
            throw new BusinessException('Insufficient balance in source account');
        }
        
        // Check minimum balance
        if (($fromAccount->getBalance() - $amount) < $fromAccount->getMinimumBalance()) {
            throw new BusinessException('Transfer would violate minimum balance requirement');
        }
        
        $reference = $referenceNumber ?: $this->generateReferenceNumber();
        
        // Create outgoing transaction
        $outTransaction = new SavingsTransaction([
            'account_id' => $fromAccountId,
            'member_id' => $fromAccount->getMemberId(),
            'transaction_type' => 'Transfer_Out',
            'amount' => $amount,
            'balance_before' => $fromAccount->getBalance(),
            'balance_after' => $fromAccount->getBalance() - $amount,
            'transaction_date' => new DateTime(),
            'transaction_time' => date('H:i:s'),
            'payment_method' => 'Transfer',
            'reference_number' => $reference,
            'description' => $description ?: "Transfer to account {$toAccount->getAccountNumber()}",
            'processed_by' => $processedBy,
            'transaction_status' => 'Completed',
            'receipt_number' => $this->transactionRepository->generateUniqueReceiptNumber()
        ]);
        
        // Create incoming transaction
        $inTransaction = new SavingsTransaction([
            'account_id' => $toAccountId,
            'member_id' => $toAccount->getMemberId(),
            'transaction_type' => 'Transfer_In',
            'amount' => $amount,
            'balance_before' => $toAccount->getBalance(),
            'balance_after' => $toAccount->getBalance() + $amount,
            'transaction_date' => new DateTime(),
            'transaction_time' => date('H:i:s'),
            'payment_method' => 'Transfer',
            'reference_number' => $reference,
            'description' => $description ?: "Transfer from account {$fromAccount->getAccountNumber()}",
            'processed_by' => $processedBy,
            'transaction_status' => 'Completed',
            'receipt_number' => $this->transactionRepository->generateUniqueReceiptNumber()
        ]);
        
        try {
            // Save transactions
            $savedOutTransaction = $this->transactionRepository->create($outTransaction);
            $savedInTransaction = $this->transactionRepository->create($inTransaction);
            
            // Update account balances
            $this->accountRepository->updateBalance($fromAccountId, $fromAccount->getBalance() - $amount);
            $this->accountRepository->updateBalance($toAccountId, $toAccount->getBalance() + $amount);
            
            // Log activities
            $this->logActivity('transfer_out', $fromAccountId, $processedBy, $amount);
            $this->logActivity('transfer_in', $toAccountId, $processedBy, $amount);
            
            // Send notifications
            $this->notificationService->sendTransactionNotification($savedOutTransaction, 'transfer_out');
            $this->notificationService->sendTransactionNotification($savedInTransaction, 'transfer_in');
            
            return [$savedOutTransaction, $savedInTransaction];
        } catch (Exception $e) {
            throw new DatabaseException('Failed to process transfer: ' . $e->getMessage());
        }
    }
    
    /**
     * Calculate and add interest to eligible accounts
     */
    public function calculateInterest(int $accountId = null): array
    {
        $results = [];
        
        if ($accountId) {
            // Calculate for specific account
            $account = $this->accountRepository->find($accountId);
            if ($account) {
                $results[] = $this->processAccountInterest($account);
            }
        } else {
            // Calculate for all eligible accounts
            $accounts = $this->accountRepository->getAccountsDueForInterest();
            
            foreach ($accounts as $account) {
                try {
                    $results[] = $this->processAccountInterest($account);
                } catch (Exception $e) {
                    $results[] = [
                        'account_id' => $account->getAccountId(),
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Process interest for a single account
     */
    private function processAccountInterest(SavingsAccount $account): array
    {
        if ($account->getInterestRate() <= 0) {
            return [
                'account_id' => $account->getAccountId(),
                'success' => false,
                'error' => 'No interest rate set'
            ];
        }
        
        // Calculate interest amount
        $principal = $account->getBalance();
        $rate = $account->getInterestRate() / 100;
        $interestAmount = $this->calculateInterestAmount($principal, $rate, $account->getInterestCalculation());
        
        if ($interestAmount <= 0) {
            return [
                'account_id' => $account->getAccountId(),
                'success' => false,
                'error' => 'No interest to add'
            ];
        }
        
        // Create interest transaction
        $transaction = new SavingsTransaction([
            'account_id' => $account->getAccountId(),
            'member_id' => $account->getMemberId(),
            'transaction_type' => 'Interest',
            'amount' => $interestAmount,
            'balance_before' => $principal,
            'balance_after' => $principal + $interestAmount,
            'transaction_date' => new DateTime(),
            'transaction_time' => date('H:i:s'),
            'payment_method' => 'Online',
            'reference_number' => $this->generateReferenceNumber(),
            'description' => 'Monthly interest credit',
            'processed_by' => 1, // System generated
            'transaction_status' => 'Completed',
            'receipt_number' => $this->transactionRepository->generateUniqueReceiptNumber()
        ]);
        
        try {
            // Save transaction
            $savedTransaction = $this->transactionRepository->create($transaction);
            
            // Update account balance and last interest date
            $this->accountRepository->updateBalance($account->getAccountId(), $principal + $interestAmount);
            $this->accountRepository->updateLastInterestDate($account->getAccountId(), date('Y-m-d'));
            
            // Log activity
            $this->logActivity('interest_added', $account->getAccountId(), 1, $interestAmount);
            
            return [
                'account_id' => $account->getAccountId(),
                'success' => true,
                'interest_amount' => $interestAmount,
                'new_balance' => $principal + $interestAmount,
                'transaction_id' => $savedTransaction->getTransactionId()
            ];
        } catch (Exception $e) {
            return [
                'account_id' => $account->getAccountId(),
                'success' => false,
                'error' => 'Failed to add interest: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate interest amount based on method
     */
    private function calculateInterestAmount(float $principal, float $rate, string $method): float
    {
        switch ($method) {
            case 'simple':
                return $principal * $rate / 12; // Monthly simple interest
            case 'compound':
                return $principal * (pow(1 + $rate / 12, 1) - 1); // Monthly compound
            case 'daily':
                return $principal * $rate / 365; // Daily interest
            case 'quarterly':
                return $principal * $rate / 4; // Quarterly interest
            case 'annually':
                return $principal * $rate; // Annual interest
            case 'monthly':
            default:
                return $principal * $rate / 12; // Monthly interest (default)
        }
    }
    
    /**
     * Close savings account
     */
    public function closeAccount(int $accountId, string $reason, int $processedBy): bool
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new ValidationException('Account not found');
        }
        
        if (!$account->canBeClosed()) {
            throw new BusinessException('Account cannot be closed in current status');
        }
        
        // If there's remaining balance, create withdrawal transaction
        if ($account->getBalance() > 0) {
            $this->withdraw(
                $accountId,
                $account->getBalance(),
                'Account closure - final withdrawal',
                'Cash',
                $processedBy,
                null,
                false // No approval needed for closure
            );
        }
        
        // Update account status
        $account->setAccountStatus('Closed');
        $account->setClosingDate(new DateTime());
        $account->setUpdatedBy($processedBy);
        $account->setNotes($account->getNotes() . "\nClosed on " . date('Y-m-d') . ". Reason: " . $reason);
        
        $this->accountRepository->update($account);
        
        // Log activity
        $this->logActivity('account_closed', $accountId, $processedBy);
        
        // Send notification
        $this->notificationService->sendAccountClosedNotification($account);
        
        return true;
    }
    
    /**
     * Get account summary
     */
    public function getAccountSummary(int $accountId): array
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new ValidationException('Account not found');
        }
        
        // Get recent transactions
        $recentTransactions = $this->transactionRepository->getAccountHistory($accountId, 10);
        
        // Get transaction summary for current month
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $monthlyTransactions = $this->transactionRepository->getMemberTransactionSummary(
            $account->getMemberId(),
            $monthStart,
            $monthEnd
        );
        
        return [
            'account' => $account,
            'recent_transactions' => $recentTransactions,
            'monthly_summary' => $monthlyTransactions,
            'balance' => $account->getBalance(),
            'formatted_balance' => $account->getFormattedBalance(),
            'target_progress' => $account->getTargetProgress(),
            'days_until_maturity' => $account->getDaysUntilMaturity(),
            'has_matured' => $account->hasMatured(),
            'has_met_target' => $account->hasMetTarget()
        ];
    }
    
    /**
     * Validate account data
     */
    private function validateAccountData(array $data): array
    {
        $rules = [
            'member_id' => 'required|integer',
            'account_type' => 'required|string|in:Regular,Fixed,Target,Emergency,Retirement',
            'account_name' => 'required|string|min:3|max:100',
            'opening_balance' => 'numeric|min:0',
            'minimum_balance' => 'numeric|min:0',
            'interest_rate' => 'numeric|min:0|max:100',
            'interest_calculation' => 'string|in:simple,compound,daily,monthly,quarterly,annually',
            'target_amount' => 'numeric|min:0',
            'monthly_target' => 'numeric|min:0',
            'auto_deduct' => 'boolean'
        ];
        
        return $this->securityService->validateInput($data, $rules)->getData();
    }
    
    /**
     * Calculate withdrawal fees
     */
    private function calculateWithdrawalFees(SavingsAccount $account, float $amount): float
    {
        // Basic fee calculation - can be enhanced based on account type and rules
        $feeRate = 0.001; // 0.1% fee
        $maxFee = 50.00;
        $minFee = 5.00;
        
        $fee = $amount * $feeRate;
        
        return min($maxFee, max($minFee, $fee));
    }
    
    /**
     * Create fee transaction
     */
    private function createFeeTransaction(int $accountId, float $amount, string $description, int $processedBy): SavingsTransaction
    {
        $account = $this->accountRepository->find($accountId);
        
        $transaction = new SavingsTransaction([
            'account_id' => $accountId,
            'member_id' => $account->getMemberId(),
            'transaction_type' => 'Fee',
            'amount' => $amount,
            'balance_before' => $account->getBalance(),
            'balance_after' => $account->getBalance() - $amount,
            'transaction_date' => new DateTime(),
            'transaction_time' => date('H:i:s'),
            'payment_method' => 'Online',
            'reference_number' => $this->generateReferenceNumber(),
            'description' => $description,
            'processed_by' => $processedBy,
            'transaction_status' => 'Completed'
        ]);
        
        return $this->transactionRepository->create($transaction);
    }
    
    /**
     * Generate unique reference number
     */
    private function generateReferenceNumber(): string
    {
        return 'SAV' . date('YmdHis') . mt_rand(100, 999);
    }
    
    /**
     * Log activity
     */
    private function logActivity(string $action, int $accountId, int $userId, float $amount = null): void
    {
        // Implementation for activity logging
        // This would typically log to a separate activity log table
    }
}