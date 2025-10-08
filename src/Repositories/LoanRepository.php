<?php

namespace CSIMS\Repositories;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Interfaces\RepositoryInterface;
use CSIMS\Models\Loan;
use CSIMS\Database\QueryBuilder;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * Loan Repository
 * 
 * Handles database operations for Loan entities
 */
class LoanRepository implements RepositoryInterface
{
    private mysqli $connection;
    private string $table = 'loans';
    
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Find loan by ID with member information
     * 
     * @param mixed $id
     * @return ModelInterface|null
     * @throws DatabaseException
     */
    public function find(mixed $id): ?ModelInterface
    {
        $query = QueryBuilder::table($this->table)
            ->select([
                'l.*',
                'm.first_name as member_first_name',
                'm.last_name as member_last_name',
                'm.email as member_email'
            ])
            ->leftJoin('members m', 'l.member_id', '=', 'm.member_id')
            ->where('l.loan_id', $id);
            
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
            return Loan::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Find all loans with optional filters
     * 
     * @param array $filters
     * @param array $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws DatabaseException
     */
    public function findAll(array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $query = QueryBuilder::table($this->table)
            ->select([
                'l.*',
                'm.first_name as member_first_name',
                'm.last_name as member_last_name',
                'm.email as member_email'
            ])
            ->leftJoin('members m', 'l.member_id', '=', 'm.member_id');
        
        // Apply filters
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn("l.{$field}", $value);
            } else {
                $query->where("l.{$field}", $value);
            }
        }
        
        // Apply ordering
        foreach ($orderBy as $field => $direction) {
            $query->orderBy("l.{$field}", $direction);
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
        
        $loans = [];
        while ($row = $result->fetch_assoc()) {
            $loans[] = Loan::fromArray($row);
        }
        
        return $loans;
    }
    
    /**
     * Find loans by specific criteria
     * 
     * @param array $criteria
     * @return array
     * @throws DatabaseException
     */
    public function findBy(array $criteria): array
    {
        return $this->findAll($criteria);
    }
    
    /**
     * Find single loan by criteria
     * 
     * @param array $criteria
     * @return ModelInterface|null
     * @throws DatabaseException
     */
    public function findOneBy(array $criteria): ?ModelInterface
    {
        $loans = $this->findAll($criteria, [], 1);
        return $loans[0] ?? null;
    }
    
    /**
     * Create new loan
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function create(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof Loan) {
            throw new DatabaseException('Entity must be instance of Loan');
        }
        
        $data = $entity->toArray();
        unset($data['loan_id']); // Remove ID for insert
        unset($data['created_at']); // Will be set by database
        unset($data['updated_at']); // Will be set by database
        
        // Remove member data (not part of loans table)
        unset($data['member_first_name']);
        unset($data['member_last_name']);
        unset($data['member_email']);
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        // Set defaults
        if (!isset($data['application_date'])) {
            $data['application_date'] = date('Y-m-d');
        }
        
        // Auto-calculate monthly payment if not set
        if (!isset($data['monthly_payment']) || $data['monthly_payment'] <= 0) {
            $entity->autoCalculatePayment();
            $data['monthly_payment'] = $entity->getMonthlyPayment();
        }
        
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
     * Update existing loan
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function update(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof Loan) {
            throw new DatabaseException('Entity must be instance of Loan');
        }
        
        if ($entity->getId() === null) {
            throw new DatabaseException('Cannot update entity without ID');
        }
        
        $data = $entity->toArray();
        unset($data['loan_id']); // Remove ID from update data
        unset($data['created_at']); // Don't update created timestamp
        
        // Remove member data (not part of loans table)
        unset($data['member_first_name']);
        unset($data['member_last_name']);
        unset($data['member_email']);
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        // Set updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('loan_id', $entity->getId());
            
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
     * Delete loan
     * 
     * @param mixed $id
     * @return bool
     * @throws DatabaseException
     */
    public function delete(mixed $id): bool
    {
        $query = QueryBuilder::table($this->table)
            ->delete()
            ->where('loan_id', $id);
            
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
        
        return $stmt->affected_rows > 0;
    }
    
    /**
     * Count loans with optional filters
     * 
     * @param array $filters
     * @return int
     * @throws DatabaseException
     */
    public function count(array $filters = []): int
    {
        $query = QueryBuilder::table($this->table)->select(['COUNT(*) as total']);
        
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
        
        return (int) $row['total'];
    }
    
    /**
     * Find loans by member ID
     * 
     * @param int $memberId
     * @return array
     * @throws DatabaseException
     */
    public function findByMember(int $memberId): array
    {
        return $this->findBy(['member_id' => $memberId]);
    }
    
    /**
     * Find active loans
     * 
     * @return array
     * @throws DatabaseException
     */
    public function findActive(): array
    {
        return $this->findBy(['status' => ['Active', 'Disbursed', 'Approved']]);
    }
    
    /**
     * Find loans by status
     * 
     * @param string $status
     * @return array
     * @throws DatabaseException
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }
    
    /**
     * Find overdue loans
     * 
     * @return array
     * @throws DatabaseException
     */
    public function findOverdue(): array
    {
        $query = QueryBuilder::table($this->table)
            ->select([
                'l.*',
                'm.first_name as member_first_name',
                'm.last_name as member_last_name',
                'm.email as member_email'
            ])
            ->leftJoin('members m', 'l.member_id', '=', 'm.member_id')
            ->where('l.status', 'Active')
            ->where('l.next_payment_date', '<', date('Y-m-d'))
            ->orderBy('l.next_payment_date', 'ASC');
            
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
        
        $loans = [];
        while ($row = $result->fetch_assoc()) {
            $loans[] = Loan::fromArray($row);
        }
        
        return $loans;
    }
    
    /**
     * Get loan statistics
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_loans,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_loans,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_loans,
                    SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_loans,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN status = 'Active' THEN amount ELSE 0 END) as active_amount,
                    AVG(amount) as average_amount,
                    AVG(interest_rate) as average_interest_rate
                FROM {$this->table}";
        
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new DatabaseException('Failed to execute statistics query: ' . $this->connection->error);
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get loans with pagination
     * 
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @param array $orderBy
     * @return array
     * @throws DatabaseException
     */
    public function getPaginated(int $page = 1, int $limit = 10, array $filters = [], array $orderBy = ['created_at' => 'DESC']): array
    {
        $offset = ($page - 1) * $limit;
        $loans = $this->findAll($filters, $orderBy, $limit, $offset);
        $total = $this->count($filters);
        
        return [
            'data' => $loans,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Update loan status
     * 
     * @param int $loanId
     * @param string $status
     * @param array $additionalData
     * @return bool
     * @throws DatabaseException
     */
    public function updateStatus(int $loanId, string $status, array $additionalData = []): bool
    {
        $data = array_merge(['status' => $status], $additionalData);
        
        // Set appropriate dates based on status
        switch (strtolower($status)) {
            case 'approved':
                $data['approval_date'] = date('Y-m-d');
                break;
            case 'disbursed':
                $data['disbursement_date'] = date('Y-m-d');
                if (!isset($data['next_payment_date'])) {
                    // Set next payment date to next month
                    $data['next_payment_date'] = date('Y-m-d', strtotime('+1 month'));
                }
                break;
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('loan_id', $loanId);
            
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
        
        return $stmt->affected_rows > 0;
    }
}
