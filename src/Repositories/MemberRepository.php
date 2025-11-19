<?php

namespace CSIMS\Repositories;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Interfaces\RepositoryInterface;
use CSIMS\Models\Member;
use CSIMS\Database\QueryBuilder;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * Member Repository
 * 
 * Handles database operations for Member entities
 */
class MemberRepository implements RepositoryInterface
{
    private mysqli $connection;
    private string $table = 'members';
    
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Find member by ID
     * 
     * @param mixed $id
     * @return ModelInterface|null
     * @throws DatabaseException
     */
    public function find(mixed $id): ?ModelInterface
    {
        $query = QueryBuilder::table($this->table)
            ->select(['*'])
            ->where('member_id', $id);
            
        [$sql, $params] = $query->build();
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params)); // Assume string for simplicity
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return Member::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Find all members with optional filters
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
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = Member::fromArray($row);
        }
        
        return $members;
    }
    
    /**
     * Find members by specific criteria
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
     * Find single member by criteria
     * 
     * @param array $criteria
     * @return ModelInterface|null
     * @throws DatabaseException
     */
    public function findOneBy(array $criteria): ?ModelInterface
    {
        $members = $this->findAll($criteria, [], 1);
        return $members[0] ?? null;
    }
    
    /**
     * Create new member
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function create(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof Member) {
            throw new DatabaseException('Entity must be instance of Member');
        }
        
        $data = $entity->toArray();
        unset($data['member_id']); // Remove ID for insert
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
     * Update existing member
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function update(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof Member) {
            throw new DatabaseException('Entity must be instance of Member');
        }
        
        if ($entity->getId() === null) {
            throw new DatabaseException('Cannot update entity without ID');
        }
        
        $data = $entity->toArray();
        unset($data['member_id']); // Remove ID from update data
        unset($data['created_at']); // Don't update created timestamp
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        // Set updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('member_id', $entity->getId());
            
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
     * Delete member
     * 
     * @param mixed $id
     * @return bool
     * @throws DatabaseException
     */
    public function delete(mixed $id): bool
    {
        $query = QueryBuilder::table($this->table)
            ->delete()
            ->where('member_id', $id);
            
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
     * Count members with optional filters
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
     * Find member by email
     * 
     * @param string $email
     * @return Member|null
     * @throws DatabaseException
     */
    public function findByEmail(string $email): ?Member
    {
        $member = $this->findOneBy(['email' => $email]);
        return $member instanceof Member ? $member : null;
    }
    
    /**
     * Find member by username
     * 
     * @param string $username
     * @return Member|null
     * @throws DatabaseException
     */
    public function findByUsername(string $username): ?Member
    {
        $member = $this->findOneBy(['username' => $username]);
        return $member instanceof Member ? $member : null;
    }
    
    /**
     * Find member by IPPIS number
     * 
     * @param string $ippis
     * @return Member|null
     * @throws DatabaseException
     */
    public function findByIppis(string $ippis): ?Member
    {
        $member = $this->findOneBy(['ippis_no' => $ippis]);
        return $member instanceof Member ? $member : null;
    }
    
    /**
     * Find active members
     * 
     * @return array
     * @throws DatabaseException
     */
    public function findActive(): array
    {
        return $this->findBy(['status' => 'Active']);
    }
    
    /**
     * Find expired members
     * 
     * @return array
     * @throws DatabaseException
     */
    public function findExpired(): array
    {
        $query = QueryBuilder::table($this->table)
            ->select(['*'])
            ->where('expiry_date', '<', date('Y-m-d'))
            ->where('status', '!=', 'Expired');
            
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
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = Member::fromArray($row);
        }
        
        return $members;
    }
    
    /**
     * Get members with pagination
     * 
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @return array
     * @throws DatabaseException
     */
    public function getPaginated(int $page = 1, int $limit = 10, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $members = $this->findAll($filters, ['created_at' => 'DESC'], $limit, $offset);
        $total = $this->count($filters);
        
        return [
            'data' => $members,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    /**
     * Search members by text across name, email, username, IPPIS
     *
     * @param string $term
     * @param int $page
     * @param int $limit
     * @param array $extraFilters
     * @return array
     * @throws DatabaseException
     */
    public function search(string $term, int $page = 1, int $limit = 10, array $extraFilters = []): array
    {
        $offset = ($page - 1) * $limit;
        $like = '%' . $term . '%';

        $query = QueryBuilder::table($this->table)
            ->select(['*'])
            ->whereLike('first_name', $like)
            ->orWhere('last_name', 'LIKE', $like)
            ->orWhere('email', 'LIKE', $like)
            ->orWhere('username', 'LIKE', $like)
            ->orWhere('ippis_no', 'LIKE', $like)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset);

        // Apply extra filters (e.g., status)
        foreach ($extraFilters as $field => $value) {
            // Append additional filters with AND
            $query->where($field, $value);
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

        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = Member::fromArray($row);
        }

        // Count for pagination (approximate count using similar conditions)
        $countQuery = QueryBuilder::table($this->table)
            ->select(['COUNT(*) as total'])
            ->whereLike('first_name', $like)
            ->orWhere('last_name', 'LIKE', $like)
            ->orWhere('email', 'LIKE', $like)
            ->orWhere('username', 'LIKE', $like)
            ->orWhere('ippis_no', 'LIKE', $like);
        foreach ($extraFilters as $field => $value) {
            $countQuery->where($field, $value);
        }
        [$countSql, $countParams] = $countQuery->build();

        $countStmt = $this->connection->prepare($countSql);
        if (!$countStmt) {
            throw new DatabaseException('Failed to prepare statement: ' . $this->connection->error);
        }
        if (!empty($countParams)) {
            $types = str_repeat('s', count($countParams));
            $countStmt->bind_param($types, ...$countParams);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $row = $countResult->fetch_assoc();
        $total = (int)($row['total'] ?? 0);

        return [
            'data' => $members,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 1
            ]
        ];
    }
}
