<?php

namespace CSIMS\Repositories;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Interfaces\RepositoryInterface;
use CSIMS\Models\SavingsAccount;
use CSIMS\Database\QueryBuilder;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * Savings Account Repository
 * 
 * Handles database operations for Savings Account entities
 */
class SavingsAccountRepository implements RepositoryInterface
{
    private mysqli $connection;
    private string $table = 'savings_accounts';
    
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Find savings account by ID
     */
    public function find(mixed $id): ?ModelInterface
    {
        $query = QueryBuilder::table($this->table)
            ->select(['*'])
            ->where('account_id', $id);
            
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return SavingsAccount::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Find all savings accounts with optional filters
     */
    public function findAll(array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $query = QueryBuilder::table($this->table)->select(['*']);
        
        // Apply filters
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
        
        // Apply ordering
        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }
        
        // Apply pagination
        if ($limit !== null) {
            $query->limit($limit);
        }
        
        if ($offset !== null) {
            $query->offset($offset);
        }
        
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = SavingsAccount::fromArray($row);
        }
        
        return $accounts;
    }
    
    /**
     * Find savings accounts by specific criteria
     */
    public function findBy(array $criteria): array
    {
        return $this->findAll($criteria);
    }
    
    /**
     * Find single savings account by criteria
     */
    public function findOneBy(array $criteria): ?ModelInterface
    {
        $accounts = $this->findAll($criteria, [], 1);
        return $accounts[0] ?? null;
    }
    
    /**
     * Create new savings account
     */
    public function create(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof SavingsAccount) {
            throw new DatabaseException('Entity must be instance of SavingsAccount');
        }
        
        $data = $entity->toArray();
        unset($data['account_id']); // Remove ID for insert
        unset($data['created_at']); // Will be set by database
        unset($data['updated_at']); // Will be set by database
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        $query = QueryBuilder::table($this->table)->insert($data);
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new DatabaseException('Failed to execute statement: ' . $stmt->error);
        }
        
        $insertId = $this->connection->insert_id;
        $entity->setId($insertId);
        
        return $entity;
    }
    
    /**
     * Update existing savings account
     */
    public function update(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof SavingsAccount) {
            throw new DatabaseException('Entity must be instance of SavingsAccount');
        }
        
        $data = $entity->toArray();
        $accountId = $data['account_id'];
        unset($data['account_id']);
        unset($data['created_at']);
        unset($data['updated_at']); // Will be updated by database
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('account_id', $accountId);
            
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new DatabaseException('Failed to execute statement: ' . $stmt->error);
        }
        
        return $entity;
    }
    
    /**
     * Delete savings account
     */
    public function delete(mixed $id): bool
    {
        $query = QueryBuilder::table($this->table)
            ->delete()
            ->where('account_id', $id);
            
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Delete savings account by entity
     */
    public function deleteEntity(SavingsAccount $entity): bool
    {
        return $this->delete($entity->getId());
    }
    
    /**
     * Count entities with optional filters
     */
    public function count(array $filters = []): int
    {
        $query = QueryBuilder::table($this->table)->select(['COUNT(*) as count']);
        
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }
        
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (int)($row['count'] ?? 0);
    }
    
    /**
     * Find savings accounts by member ID
     */
    public function findByMemberId(int $memberId): array
    {
        return $this->findBy(['member_id' => $memberId]);
    }
    
    /**
     * Find savings account by account number
     */
    public function findByAccountNumber(string $accountNumber): ?SavingsAccount
    {
        $account = $this->findOneBy(['account_number' => $accountNumber]);
        return $account instanceof SavingsAccount ? $account : null;
    }
    
    /**
     * Find active savings accounts
     */
    public function findActive(): array
    {
        return $this->findBy(['account_status' => 'Active']);
    }
    
    /**
     * Find accounts by type
     */
    public function findByType(string $accountType): array
    {
        return $this->findBy(['account_type' => $accountType]);
    }
    
    /**
     * Find accounts with low balance
     */
    public function findWithLowBalance(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE balance < minimum_balance 
                AND account_status = 'Active'
                ORDER BY balance ASC";
        
        $result = $this->connection->query($sql);
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = SavingsAccount::fromArray($row);
        }
        
        return $accounts;
    }
    
    /**
     * Find matured fixed deposits
     */
    public function findMaturedFixedDeposits(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE account_type = 'Fixed' 
                AND maturity_date <= CURDATE()
                AND account_status = 'Active'
                ORDER BY maturity_date ASC";
        
        $result = $this->connection->query($sql);
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = SavingsAccount::fromArray($row);
        }
        
        return $accounts;
    }
    
    /**
     * Find target savings that have met their target
     */
    public function findCompletedTargets(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE account_type = 'Target' 
                AND balance >= target_amount
                AND target_amount IS NOT NULL
                AND account_status = 'Active'
                ORDER BY balance DESC";
        
        $result = $this->connection->query($sql);
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = SavingsAccount::fromArray($row);
        }
        
        return $accounts;
    }
    
    /**
     * Get total balance for a member across all accounts
     */
    public function getTotalBalanceByMember(int $memberId): float
    {
        $sql = "SELECT SUM(balance) as total_balance 
                FROM {$this->table} 
                WHERE member_id = ? 
                AND account_status IN ('Active', 'Inactive')";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (float)($row['total_balance'] ?? 0.00);
    }
    
    /**
     * Get account statistics
     */
    public function getAccountStatistics(): array
    {
        $sql = "SELECT 
                    account_type,
                    account_status,
                    COUNT(*) as count,
                    SUM(balance) as total_balance,
                    AVG(balance) as avg_balance,
                    MIN(balance) as min_balance,
                    MAX(balance) as max_balance
                FROM {$this->table} 
                GROUP BY account_type, account_status
                ORDER BY account_type, account_status";
        
        $result = $this->connection->query($sql);
        
        $statistics = [];
        while ($row = $result->fetch_assoc()) {
            $statistics[] = [
                'account_type' => $row['account_type'],
                'account_status' => $row['account_status'],
                'count' => (int)$row['count'],
                'total_balance' => (float)$row['total_balance'],
                'avg_balance' => (float)$row['avg_balance'],
                'min_balance' => (float)$row['min_balance'],
                'max_balance' => (float)$row['max_balance']
            ];
        }
        
        return $statistics;
    }
    
    /**
     * Get accounts due for interest calculation
     */
    public function getAccountsDueForInterest(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE account_status = 'Active'
                AND interest_rate > 0
                AND (
                    last_interest_date IS NULL OR
                    last_interest_date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
                )
                ORDER BY opening_date ASC";
        
        $result = $this->connection->query($sql);
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = SavingsAccount::fromArray($row);
        }
        
        return $accounts;
    }
    
    /**
     * Update account balance
     */
    public function updateBalance(int $accountId, float $newBalance): bool
    {
        $sql = "UPDATE {$this->table} 
                SET balance = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE account_id = ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('di', $newBalance, $accountId);
        
        return $stmt->execute();
    }
    
    /**
     * Update last interest date
     */
    public function updateLastInterestDate(int $accountId, string $date): bool
    {
        $sql = "UPDATE {$this->table} 
                SET last_interest_date = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE account_id = ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('si', $date, $accountId);
        
        return $stmt->execute();
    }
    
    /**
     * Generate unique account number
     */
    public function generateUniqueAccountNumber(string $prefix = 'SAV'): string
    {
        do {
            $accountNumber = $prefix . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $existing = $this->findByAccountNumber($accountNumber);
        } while ($existing !== null);
        
        return $accountNumber;
    }
    
    /**
     * Search accounts with advanced filters
     */
    public function searchAccounts(array $filters): array
    {
        $sql = "SELECT sa.*, m.first_name, m.last_name, m.member_number 
                FROM {$this->table} sa
                LEFT JOIN members m ON sa.member_id = m.member_id
                WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if (!empty($filters['member_name'])) {
            $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
            $searchTerm = '%' . $filters['member_name'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($filters['account_number'])) {
            $sql .= " AND sa.account_number LIKE ?";
            $params[] = '%' . $filters['account_number'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['account_type'])) {
            $sql .= " AND sa.account_type = ?";
            $params[] = $filters['account_type'];
            $types .= 's';
        }
        
        if (!empty($filters['account_status'])) {
            $sql .= " AND sa.account_status = ?";
            $params[] = $filters['account_status'];
            $types .= 's';
        }
        
        if (!empty($filters['balance_min'])) {
            $sql .= " AND sa.balance >= ?";
            $params[] = (float)$filters['balance_min'];
            $types .= 'd';
        }
        
        if (!empty($filters['balance_max'])) {
            $sql .= " AND sa.balance <= ?";
            $params[] = (float)$filters['balance_max'];
            $types .= 'd';
        }
        
        if (!empty($filters['opening_date_from'])) {
            $sql .= " AND sa.opening_date >= ?";
            $params[] = $filters['opening_date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['opening_date_to'])) {
            $sql .= " AND sa.opening_date <= ?";
            $params[] = $filters['opening_date_to'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY sa.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            $types .= 'i';
        }
        
        $stmt = $this->connection->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accountData = $row;
            // Remove member fields from account data
            $accountData['member_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $accountData['member_number'] = $row['member_number'] ?? '';
            unset($accountData['first_name'], $accountData['last_name']);
            
            $accounts[] = SavingsAccount::fromArray($accountData);
        }
        
        return $accounts;
    }
    
    /**
     * Count total accounts
     */
    public function countTotal(array $filters = []): int
    {
        $query = QueryBuilder::table($this->table)
            ->select(['COUNT(*) as total']);
        
        // Apply filters
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
        
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (int)($row['total'] ?? 0);
    }
}