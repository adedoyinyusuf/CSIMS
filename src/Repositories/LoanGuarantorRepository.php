<?php

namespace CSIMS\Repositories;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Interfaces\RepositoryInterface;
use CSIMS\Models\LoanGuarantor;
use CSIMS\Database\QueryBuilder;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * Loan Guarantor Repository
 * 
 * Handles database operations for LoanGuarantor entities
 */
class LoanGuarantorRepository implements RepositoryInterface
{
    private mysqli $connection;
    private string $table = 'loan_guarantors';
    
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Find loan guarantor by ID with related data
     */
    public function find(mixed $id): ?ModelInterface
    {
        $query = QueryBuilder::table($this->table)
            ->select([
                'lg.*',
                'l.amount as loan_amount',
                'm.first_name as guarantor_first_name',
                'm.last_name as guarantor_last_name',
                'm.email as guarantor_email',
                'm.phone as guarantor_phone'
            ])
            ->leftJoin('loans l', 'lg.loan_id', '=', 'l.loan_id')
            ->leftJoin('members m', 'lg.guarantor_member_id', '=', 'm.member_id')
            ->where('lg.guarantor_id', $id);
            
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
            return LoanGuarantor::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Find all loan guarantors with optional filters
     */
    public function findAll(array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $query = QueryBuilder::table($this->table)
            ->select([
                'lg.*',
                'l.amount as loan_amount',
                'm.first_name as guarantor_first_name',
                'm.last_name as guarantor_last_name',
                'm.email as guarantor_email',
                'm.phone as guarantor_phone'
            ])
            ->leftJoin('loans l', 'lg.loan_id', '=', 'l.loan_id')
            ->leftJoin('members m', 'lg.guarantor_member_id', '=', 'm.member_id');
        
        // Apply filters
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn("lg.{$field}", $value);
            } else {
                $query->where("lg.{$field}", $value);
            }
        }
        
        // Apply ordering
        foreach ($orderBy as $field => $direction) {
            $query->orderBy("lg.{$field}", $direction);
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
        
        $guarantors = [];
        while ($row = $result->fetch_assoc()) {
            $guarantors[] = LoanGuarantor::fromArray($row);
        }
        
        return $guarantors;
    }
    
    /**
     * Find guarantors by specific criteria
     */
    public function findBy(array $criteria): array
    {
        return $this->findAll($criteria);
    }
    
    /**
     * Find single guarantor by criteria
     */
    public function findOneBy(array $criteria): ?ModelInterface
    {
        $guarantors = $this->findAll($criteria, [], 1);
        return $guarantors[0] ?? null;
    }
    
    /**
     * Create new loan guarantor
     */
    public function create(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof LoanGuarantor) {
            throw new DatabaseException('Entity must be instance of LoanGuarantor');
        }
        
        $data = $entity->toArray();
        unset($data['guarantor_id']); // Remove ID for insert
        unset($data['created_at']); // Will be set by database
        unset($data['updated_at']); // Will be set by database
        
        // Remove related data (not part of loan_guarantors table)
        unset($data['loan_amount']);
        unset($data['guarantor_first_name']);
        unset($data['guarantor_last_name']);
        unset($data['guarantor_email']);
        unset($data['guarantor_phone']);
        
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
     * Update existing loan guarantor
     */
    public function update(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof LoanGuarantor) {
            throw new DatabaseException('Entity must be instance of LoanGuarantor');
        }
        
        if ($entity->getId() === null) {
            throw new DatabaseException('Cannot update entity without ID');
        }
        
        $data = $entity->toArray();
        unset($data['guarantor_id']); // Remove ID from update data
        unset($data['created_at']); // Don't update created timestamp
        
        // Remove related data
        unset($data['loan_amount']);
        unset($data['guarantor_first_name']);
        unset($data['guarantor_last_name']);
        unset($data['guarantor_email']);
        unset($data['guarantor_phone']);
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        // Set updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('guarantor_id', $entity->getId());
            
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
     * Delete loan guarantor
     */
    public function delete(mixed $id): bool
    {
        $query = QueryBuilder::table($this->table)
            ->delete()
            ->where('guarantor_id', $id);
            
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
     * Count guarantors with optional filters
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
     * Find guarantors for a specific loan
     */
    public function findByLoan(int $loanId): array
    {
        return $this->findBy(['loan_id' => $loanId]);
    }
    
    /**
     * Find loans guaranteed by a specific member
     */
    public function findByGuarantor(int $guarantorMemberId): array
    {
        return $this->findBy(['guarantor_member_id' => $guarantorMemberId]);
    }
    
    /**
     * Find active guarantors for a loan
     */
    public function findActiveByLoan(int $loanId): array
    {
        return $this->findBy(['loan_id' => $loanId, 'status' => 'Active']);
    }
    
    /**
     * Calculate total guarantee amount for a loan
     */
    public function getTotalGuaranteeAmount(int $loanId): float
    {
        $sql = "SELECT SUM(guarantee_amount) as total_amount,
                       SUM(guarantee_percentage) as total_percentage
                FROM {$this->table}
                WHERE loan_id = ? AND status = 'Active'";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $loanId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (float) ($row['total_amount'] ?: 0);
    }
    
    /**
     * Get guarantee statistics
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_guarantors,
                    COUNT(DISTINCT loan_id) as guaranteed_loans,
                    COUNT(DISTINCT guarantor_member_id) as unique_guarantors,
                    SUM(guarantee_amount) as total_guarantee_amount,
                    AVG(guarantee_amount) as average_guarantee_amount,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_guarantors
                FROM {$this->table}";
        
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new DatabaseException('Failed to execute statistics query: ' . $this->connection->error);
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Check if a member can guarantee another loan (risk assessment)
     */
    public function canMemberGuarantee(int $guarantorMemberId, float $newGuaranteeAmount): array
    {
        // Get current guarantee exposure
        $sql = "SELECT 
                    COUNT(*) as current_guarantees,
                    SUM(lg.guarantee_amount) as total_guarantee_exposure,
                    SUM(CASE WHEN l.status = 'Active' THEN lg.guarantee_amount ELSE 0 END) as active_exposure
                FROM {$this->table} lg
                LEFT JOIN loans l ON lg.loan_id = l.loan_id
                WHERE lg.guarantor_member_id = ? AND lg.status = 'Active'";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $guarantorMemberId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        $currentGuarantees = (int) $data['current_guarantees'];
        $currentExposure = (float) ($data['active_exposure'] ?: 0);
        $newTotalExposure = $currentExposure + $newGuaranteeAmount;
        
        // Define limits (these could be configurable)
        $maxGuarantees = 5; // Maximum number of loans a member can guarantee
        $maxExposureAmount = 500000; // Maximum total guarantee exposure
        
        $canGuarantee = $currentGuarantees < $maxGuarantees && $newTotalExposure <= $maxExposureAmount;
        
        return [
            'can_guarantee' => $canGuarantee,
            'current_guarantees' => $currentGuarantees,
            'max_guarantees' => $maxGuarantees,
            'current_exposure' => $currentExposure,
            'new_total_exposure' => $newTotalExposure,
            'max_exposure' => $maxExposureAmount,
            'reason' => !$canGuarantee ? 
                ($currentGuarantees >= $maxGuarantees ? 'Maximum number of guarantees exceeded' : 'Maximum guarantee exposure exceeded') : 
                null
        ];
    }
}