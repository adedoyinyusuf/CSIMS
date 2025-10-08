<?php

namespace CSIMS\Repositories;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Interfaces\RepositoryInterface;
use CSIMS\Models\Contribution;
use CSIMS\Database\QueryBuilder;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * Contribution Repository
 * 
 * Handles database operations for Contribution entities
 */
class ContributionRepository implements RepositoryInterface
{
    private mysqli $connection;
    private string $table = 'contributions';
    
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Find contribution by ID with member information
     * 
     * @param mixed $id
     * @return ModelInterface|null
     * @throws DatabaseException
     */
    public function find(mixed $id): ?ModelInterface
    {
        $query = QueryBuilder::table($this->table)
            ->select([
                'c.*',
                'm.first_name as member_first_name',
                'm.last_name as member_last_name',
                'm.email as member_email'
            ])
            ->leftJoin('members m', 'c.member_id', '=', 'm.member_id')
            ->where('c.contribution_id', $id);
            
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
            return Contribution::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Find all contributions with optional filters
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
                'c.*',
                'm.first_name as member_first_name',
                'm.last_name as member_last_name',
                'm.email as member_email'
            ])
            ->leftJoin('members m', 'c.member_id', '=', 'm.member_id');
        
        // Apply filters
        foreach ($filters as $field => $value) {
            if ($field === 'date_from') {
                $query->where('c.contribution_date', '>=', $value);
            } elseif ($field === 'date_to') {
                $query->where('c.contribution_date', '<=', $value);
            } elseif (is_array($value)) {
                $query->whereIn("c.{$field}", $value);
            } else {
                $query->where("c.{$field}", $value);
            }
        }
        
        // Apply ordering
        foreach ($orderBy as $field => $direction) {
            $query->orderBy("c.{$field}", $direction);
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
        
        $contributions = [];
        while ($row = $result->fetch_assoc()) {
            $contributions[] = Contribution::fromArray($row);
        }
        
        return $contributions;
    }
    
    /**
     * Find contributions by specific criteria
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
     * Find single contribution by criteria
     * 
     * @param array $criteria
     * @return ModelInterface|null
     * @throws DatabaseException
     */
    public function findOneBy(array $criteria): ?ModelInterface
    {
        $contributions = $this->findAll($criteria, [], 1);
        return $contributions[0] ?? null;
    }
    
    /**
     * Create new contribution
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function create(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof Contribution) {
            throw new DatabaseException('Entity must be instance of Contribution');
        }
        
        $data = $entity->toArray();
        unset($data['contribution_id']); // Remove ID for insert
        unset($data['created_at']); // Will be set by database
        unset($data['updated_at']); // Will be set by database
        
        // Remove member data (not part of contributions table)
        unset($data['member_first_name']);
        unset($data['member_last_name']);
        unset($data['member_email']);
        unset($data['member_full_name']);
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        // Set defaults
        if (!isset($data['contribution_date'])) {
            $data['contribution_date'] = date('Y-m-d');
        }
        
        if (!isset($data['status'])) {
            $data['status'] = 'Confirmed';
        }
        
        // Generate receipt number if not provided
        if (empty($data['receipt_number'])) {
            $data['receipt_number'] = $this->generateReceiptNumber();
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
     * Update existing contribution
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function update(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof Contribution) {
            throw new DatabaseException('Entity must be instance of Contribution');
        }
        
        if ($entity->getId() === null) {
            throw new DatabaseException('Cannot update entity without ID');
        }
        
        $data = $entity->toArray();
        unset($data['contribution_id']); // Remove ID from update data
        unset($data['created_at']); // Don't update created timestamp
        
        // Remove member data (not part of contributions table)
        unset($data['member_first_name']);
        unset($data['member_last_name']);
        unset($data['member_email']);
        unset($data['member_full_name']);
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        // Set updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('contribution_id', $entity->getId());
            
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
     * Delete contribution
     * 
     * @param mixed $id
     * @return bool
     * @throws DatabaseException
     */
    public function delete(mixed $id): bool
    {
        $query = QueryBuilder::table($this->table)
            ->delete()
            ->where('contribution_id', $id);
            
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
     * Count contributions with optional filters
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
            if ($field === 'date_from') {
                $query->where('contribution_date', '>=', $value);
            } elseif ($field === 'date_to') {
                $query->where('contribution_date', '<=', $value);
            } elseif (is_array($value)) {
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
     * Find contributions by member ID
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
     * Find contributions by type
     * 
     * @param string $type
     * @return array
     * @throws DatabaseException
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['contribution_type' => $type]);
    }
    
    /**
     * Find contributions by status
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
     * Find contributions by date range
     * 
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     * @throws DatabaseException
     */
    public function findByDateRange(string $dateFrom, string $dateTo): array
    {
        return $this->findBy(['date_from' => $dateFrom, 'date_to' => $dateTo]);
    }
    
    /**
     * Find monthly contributions for a specific month and year
     * 
     * @param int $month
     * @param int $year
     * @return array
     * @throws DatabaseException
     */
    public function findMonthlyContributions(int $month, int $year): array
    {
        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
        
        return $this->findBy([
            'contribution_type' => 'Monthly',
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
    }
    
    /**
     * Get contributions with pagination
     * 
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @param array $orderBy
     * @return array
     * @throws DatabaseException
     */
    public function getPaginated(int $page = 1, int $limit = 10, array $filters = [], array $orderBy = ['contribution_date' => 'DESC']): array
    {
        $offset = ($page - 1) * $limit;
        $contributions = $this->findAll($filters, $orderBy, $limit, $offset);
        $total = $this->count($filters);
        
        return [
            'data' => $contributions,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Search contributions by member name or receipt number
     * 
     * @param string $searchTerm
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     * @throws DatabaseException
     */
    public function search(string $searchTerm, array $filters = [], int $page = 1, int $limit = 10): array
    {
        $searchPattern = '%' . $searchTerm . '%';
        
        $query = QueryBuilder::table($this->table)
            ->select([
                'c.*',
                'm.first_name as member_first_name',
                'm.last_name as member_last_name',
                'm.email as member_email'
            ])
            ->leftJoin('members m', 'c.member_id', '=', 'm.member_id')
            ->where('m.first_name', 'LIKE', $searchPattern)
            ->orWhere('m.last_name', 'LIKE', $searchPattern)
            ->orWhere('c.receipt_number', 'LIKE', $searchPattern)
            ->orWhere('c.notes', 'LIKE', $searchPattern);
        
        // Apply additional filters
        foreach ($filters as $field => $value) {
            if ($field === 'date_from') {
                $query->where('c.contribution_date', '>=', $value);
            } elseif ($field === 'date_to') {
                $query->where('c.contribution_date', '<=', $value);
            } elseif (is_array($value)) {
                $query->whereIn("c.{$field}", $value);
            } else {
                $query->where("c.{$field}", $value);
            }
        }
        
        // Apply pagination
        $offset = ($page - 1) * $limit;
        $query->limit($limit)->offset($offset);
        
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
        
        $contributions = [];
        while ($row = $result->fetch_assoc()) {
            $contributions[] = Contribution::fromArray($row);
        }
        
        // Get total count for pagination
        $countQuery = QueryBuilder::table($this->table)
            ->select(['COUNT(*) as total'])
            ->leftJoin('members m', 'c.member_id', '=', 'm.member_id')
            ->where('m.first_name', 'LIKE', $searchPattern)
            ->orWhere('m.last_name', 'LIKE', $searchPattern)
            ->orWhere('c.receipt_number', 'LIKE', $searchPattern)
            ->orWhere('c.notes', 'LIKE', $searchPattern);
            
        foreach ($filters as $field => $value) {
            if ($field === 'date_from') {
                $countQuery->where('c.contribution_date', '>=', $value);
            } elseif ($field === 'date_to') {
                $countQuery->where('c.contribution_date', '<=', $value);
            } elseif (is_array($value)) {
                $countQuery->whereIn("c.{$field}", $value);
            } else {
                $countQuery->where("c.{$field}", $value);
            }
        }
        
        [$countSql, $countParams] = $countQuery->build();
        
        $countStmt = $this->connection->prepare($countSql);
        if (!$countStmt) {
            throw new DatabaseException('Failed to prepare count statement: ' . $this->connection->error);
        }
        
        if (!empty($countParams)) {
            $types = str_repeat('s', count($countParams));
            $countStmt->bind_param($types, ...$countParams);
        }
        
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countRow = $countResult->fetch_assoc();
        $total = (int) $countRow['total'];
        
        return [
            'data' => $contributions,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get contribution statistics
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_contributions,
                    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_contributions,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_contributions,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN status = 'Confirmed' THEN amount ELSE 0 END) as confirmed_amount,
                    AVG(amount) as average_amount,
                    COUNT(DISTINCT member_id) as contributing_members,
                    COUNT(CASE WHEN contribution_type = 'Monthly' THEN 1 END) as monthly_contributions,
                    COUNT(CASE WHEN contribution_type = 'Special' THEN 1 END) as special_contributions,
                    COUNT(CASE WHEN DATE(contribution_date) >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) THEN 1 END) as contributions_last_30_days
                FROM {$this->table}";
        
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new DatabaseException('Failed to execute statistics query: ' . $this->connection->error);
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get member contribution summary
     * 
     * @param int $memberId
     * @return array
     * @throws DatabaseException
     */
    public function getMemberSummary(int $memberId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_contributions,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount,
                    MIN(contribution_date) as first_contribution,
                    MAX(contribution_date) as last_contribution,
                    SUM(CASE WHEN contribution_type = 'Monthly' THEN amount ELSE 0 END) as monthly_total,
                    SUM(CASE WHEN contribution_type = 'Special' THEN amount ELSE 0 END) as special_total,
                    COUNT(CASE WHEN contribution_type = 'Monthly' THEN 1 END) as monthly_count,
                    COUNT(CASE WHEN contribution_type = 'Special' THEN 1 END) as special_count
                FROM {$this->table} 
                WHERE member_id = ? AND status = 'Confirmed'";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Generate unique receipt number
     * 
     * @param string $prefix
     * @return string
     * @throws DatabaseException
     */
    private function generateReceiptNumber(string $prefix = 'CONT'): string
    {
        $date = date('Ymd');
        $attempt = 0;
        
        do {
            $attempt++;
            $receiptNumber = $prefix . $date . sprintf('%04d', $attempt);
            
            // Check if receipt number exists
            $query = QueryBuilder::table($this->table)
                ->select(['COUNT(*) as count'])
                ->where('receipt_number', $receiptNumber);
                
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
            
            $exists = (int) $row['count'] > 0;
        } while ($exists && $attempt < 9999);
        
        if ($attempt >= 9999) {
            throw new DatabaseException('Unable to generate unique receipt number');
        }
        
        return $receiptNumber;
    }
}
