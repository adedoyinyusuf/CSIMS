<?php

namespace CSIMS\Repositories;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Interfaces\RepositoryInterface;
use CSIMS\Models\SavingsTransaction;
use CSIMS\Database\QueryBuilder;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * Savings Transaction Repository
 * 
 * Handles database operations for Savings Transaction entities
 */
class SavingsTransactionRepository implements RepositoryInterface
{
    private mysqli $connection;
    private string $table = 'savings_transactions';
    
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Find savings transaction by ID
     */
    public function find(mixed $id): ?ModelInterface
    {
        $query = QueryBuilder::table($this->table)
            ->select(['*'])
            ->where('transaction_id', $id);
            
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
            return SavingsTransaction::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Find all savings transactions with optional filters
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
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = SavingsTransaction::fromArray($row);
        }
        
        return $transactions;
    }
    
    /**
     * Find savings transactions by specific criteria
     */
    public function findBy(array $criteria): array
    {
        return $this->findAll($criteria);
    }
    
    /**
     * Find single savings transaction by criteria
     */
    public function findOneBy(array $criteria): ?ModelInterface
    {
        $transactions = $this->findAll($criteria, [], 1);
        return $transactions[0] ?? null;
    }
    
    /**
     * Create new savings transaction
     */
    public function create(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof SavingsTransaction) {
            throw new DatabaseException('Entity must be instance of SavingsTransaction');
        }
        
        $data = $entity->toArray();
        unset($data['transaction_id']); // Remove ID for insert
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
     * Update existing savings transaction
     */
    public function update(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof SavingsTransaction) {
            throw new DatabaseException('Entity must be instance of SavingsTransaction');
        }
        
        $data = $entity->toArray();
        $transactionId = $data['transaction_id'];
        unset($data['transaction_id']);
        unset($data['created_at']);
        unset($data['updated_at']); // Will be updated by database
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('transaction_id', $transactionId);
            
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
     * Delete savings transaction
     */
    public function delete(mixed $id): bool
    {
        $query = QueryBuilder::table($this->table)
            ->delete()
            ->where('transaction_id', $id);
            
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
     * Delete savings transaction by entity
     */
    public function deleteEntity(SavingsTransaction $entity): bool
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
     * Find transactions by account ID
     */
    public function findByAccountId(int $accountId): array
    {
        return $this->findBy(['account_id' => $accountId]);
    }
    
    /**
     * Find transactions by member ID
     */
    public function findByMemberId(int $memberId): array
    {
        return $this->findBy(['member_id' => $memberId]);
    }
    
    /**
     * Find transactions by reference number
     */
    public function findByReference(string $referenceNumber): array
    {
        return $this->findBy(['reference_number' => $referenceNumber]);
    }
    
    /**
     * Find pending transactions
     */
    public function findPending(): array
    {
        return $this->findBy(['transaction_status' => 'Pending']);
    }
    
    /**
     * Find transactions requiring approval
     */
    public function findRequiringApproval(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE transaction_status = 'Pending' 
                AND (
                    transaction_type IN ('Withdrawal', 'Adjustment', 'Transfer_Out') 
                    OR amount > 50000
                )
                ORDER BY created_at ASC";
        
        $result = $this->connection->query($sql);
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = SavingsTransaction::fromArray($row);
        }
        
        return $transactions;
    }
    
    /**
     * Get account transaction history with pagination
     */
    public function getAccountHistory(int $accountId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT st.*, sa.account_number, m.first_name, m.last_name, m.member_number
                FROM {$this->table} st
                LEFT JOIN savings_accounts sa ON st.account_id = sa.account_id
                LEFT JOIN members m ON st.member_id = m.member_id
                WHERE st.account_id = ?
                ORDER BY st.transaction_date DESC, st.transaction_time DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('iii', $accountId, $limit, $offset);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactionData = $row;
            $transactionData['member_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            unset($transactionData['first_name'], $transactionData['last_name']);
            
            $transactions[] = SavingsTransaction::fromArray($transactionData);
        }
        
        return $transactions;
    }
    
    /**
     * Get member transaction summary
     */
    public function getMemberTransactionSummary(int $memberId, string $dateFrom = null, string $dateTo = null): array
    {
        $sql = "SELECT 
                    transaction_type,
                    COUNT(*) as count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                FROM {$this->table} 
                WHERE member_id = ?";
        
        $params = [$memberId];
        $types = 'i';
        
        if ($dateFrom) {
            $sql .= " AND transaction_date >= ?";
            $params[] = $dateFrom;
            $types .= 's';
        }
        
        if ($dateTo) {
            $sql .= " AND transaction_date <= ?";
            $params[] = $dateTo;
            $types .= 's';
        }
        
        $sql .= " GROUP BY transaction_type
                  ORDER BY total_amount DESC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        $summary = [];
        while ($row = $result->fetch_assoc()) {
            $summary[] = [
                'transaction_type' => $row['transaction_type'],
                'count' => (int)$row['count'],
                'total_amount' => (float)$row['total_amount'],
                'avg_amount' => (float)$row['avg_amount']
            ];
        }
        
        return $summary;
    }
    
    /**
     * Get daily transaction totals for a date range
     */
    public function getDailyTotals(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT 
                    transaction_date,
                    transaction_type,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                FROM {$this->table}
                WHERE transaction_date BETWEEN ? AND ?
                AND transaction_status = 'Completed'
                GROUP BY transaction_date, transaction_type
                ORDER BY transaction_date DESC, transaction_type";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('ss', $dateFrom, $dateTo);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        $dailyTotals = [];
        while ($row = $result->fetch_assoc()) {
            $dailyTotals[] = [
                'date' => $row['transaction_date'],
                'transaction_type' => $row['transaction_type'],
                'count' => (int)$row['transaction_count'],
                'total_amount' => (float)$row['total_amount']
            ];
        }
        
        return $dailyTotals;
    }
    
    /**
     * Get transaction statistics
     */
    public function getTransactionStatistics(array $filters = []): array
    {
        $sql = "SELECT 
                    transaction_type,
                    transaction_status,
                    COUNT(*) as count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount
                FROM {$this->table} WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND transaction_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND transaction_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        if (!empty($filters['transaction_type'])) {
            $sql .= " AND transaction_type = ?";
            $params[] = $filters['transaction_type'];
            $types .= 's';
        }
        
        if (!empty($filters['account_id'])) {
            $sql .= " AND account_id = ?";
            $params[] = $filters['account_id'];
            $types .= 'i';
        }
        
        $sql .= " GROUP BY transaction_type, transaction_status
                  ORDER BY transaction_type, transaction_status";
        
        $stmt = $this->connection->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        $statistics = [];
        while ($row = $result->fetch_assoc()) {
            $statistics[] = [
                'transaction_type' => $row['transaction_type'],
                'transaction_status' => $row['transaction_status'],
                'count' => (int)$row['count'],
                'total_amount' => (float)$row['total_amount'],
                'avg_amount' => (float)$row['avg_amount'],
                'min_amount' => (float)$row['min_amount'],
                'max_amount' => (float)$row['max_amount']
            ];
        }
        
        return $statistics;
    }
    
    /**
     * Find failed transactions for retry
     */
    public function findFailedTransactions(int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE transaction_status = 'Failed' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = SavingsTransaction::fromArray($row);
        }
        
        return $transactions;
    }
    
    /**
     * Find transactions for reversal
     */
    public function findForReversal(string $referenceNumber): ?SavingsTransaction
    {
        $transaction = $this->findOneBy(['reference_number' => $referenceNumber]);
        return $transaction instanceof SavingsTransaction ? $transaction : null;
    }
    
    /**
     * Search transactions with advanced filters
     */
    public function searchTransactions(array $filters): array
    {
        $sql = "SELECT st.*, sa.account_number, m.first_name, m.last_name, m.member_number,
                       au.username as processed_by_name
                FROM {$this->table} st
                LEFT JOIN savings_accounts sa ON st.account_id = sa.account_id
                LEFT JOIN members m ON st.member_id = m.member_id
                LEFT JOIN admin_users au ON st.processed_by = au.admin_id
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
        
        if (!empty($filters['reference_number'])) {
            $sql .= " AND st.reference_number LIKE ?";
            $params[] = '%' . $filters['reference_number'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['transaction_type'])) {
            $sql .= " AND st.transaction_type = ?";
            $params[] = $filters['transaction_type'];
            $types .= 's';
        }
        
        if (!empty($filters['transaction_status'])) {
            $sql .= " AND st.transaction_status = ?";
            $params[] = $filters['transaction_status'];
            $types .= 's';
        }
        
        if (!empty($filters['payment_method'])) {
            $sql .= " AND st.payment_method = ?";
            $params[] = $filters['payment_method'];
            $types .= 's';
        }
        
        if (!empty($filters['amount_min'])) {
            $sql .= " AND st.amount >= ?";
            $params[] = (float)$filters['amount_min'];
            $types .= 'd';
        }
        
        if (!empty($filters['amount_max'])) {
            $sql .= " AND st.amount <= ?";
            $params[] = (float)$filters['amount_max'];
            $types .= 'd';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND st.transaction_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND st.transaction_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY st.transaction_date DESC, st.transaction_time DESC";
        
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
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactionData = $row;
            $transactionData['member_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $transactionData['processed_by_name'] = $row['processed_by_name'] ?? '';
            unset($transactionData['first_name'], $transactionData['last_name'], $transactionData['username']);
            
            $transactions[] = SavingsTransaction::fromArray($transactionData);
        }
        
        return $transactions;
    }
    
    /**
     * Generate unique receipt number
     */
    public function generateUniqueReceiptNumber(string $prefix = 'REC'): string
    {
        do {
            $receiptNumber = $prefix . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            $existing = $this->findOneBy(['receipt_number' => $receiptNumber]);
        } while ($existing !== null);
        
        return $receiptNumber;
    }
    
    /**
     * Get last transaction for account
     */
    public function getLastTransactionForAccount(int $accountId): ?SavingsTransaction
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE account_id = ? 
                ORDER BY transaction_date DESC, transaction_time DESC, transaction_id DESC 
                LIMIT 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return SavingsTransaction::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Count total transactions
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