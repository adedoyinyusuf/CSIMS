<?php

namespace CSIMS\Repositories;

use CSIMS\Interfaces\ModelInterface;
use CSIMS\Interfaces\RepositoryInterface;
use CSIMS\Models\User;
use CSIMS\Database\QueryBuilder;
use CSIMS\Exceptions\DatabaseException;
use mysqli;

/**
 * User Repository
 * 
 * Handles database operations for User entities
 */
class UserRepository implements RepositoryInterface
{
    private mysqli $connection;
    private string $table = 'users';
    
    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Find user by ID
     * 
     * @param mixed $id
     * @return User|null
     * @throws DatabaseException
     */
    public function find(mixed $id): ?User
    {
        $query = QueryBuilder::table($this->table)
            ->select(['*'])
            ->where('user_id', $id);
            
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
            return User::fromArray($row);
        }
        
        return null;
    }
    
    /**
     * Find all users with optional filters
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
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = User::fromArray($row);
        }
        
        return $users;
    }
    
    /**
     * Find users by specific criteria
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
     * Find single user by criteria
     * 
     * @param array $criteria
     * @return ModelInterface|null
     * @throws DatabaseException
     */
    public function findOneBy(array $criteria): ?ModelInterface
    {
        $users = $this->findAll($criteria, [], 1);
        return $users[0] ?? null;
    }
    
    /**
     * Create new user
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function create(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof User) {
            throw new DatabaseException('Entity must be instance of User');
        }
        
        $data = $entity->toArray(true); // Include password hash
        unset($data['user_id']); // Remove ID for insert
        unset($data['created_at']); // Will be set by database
        unset($data['updated_at']); // Will be set by database
        
        // Remove computed fields
        unset($data['full_name']);
        unset($data['is_active']);
        unset($data['is_locked']);
        unset($data['permissions']);
        
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
     * Update existing user
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     * @throws DatabaseException
     */
    public function update(ModelInterface $entity): ModelInterface
    {
        if (!$entity instanceof User) {
            throw new DatabaseException('Entity must be instance of User');
        }
        
        if ($entity->getId() === null) {
            throw new DatabaseException('Cannot update entity without ID');
        }
        
        $data = $entity->toArray(true); // Include password hash
        unset($data['user_id']); // Remove ID from update data
        unset($data['created_at']); // Don't update created timestamp
        
        // Remove computed fields
        unset($data['full_name']);
        unset($data['is_active']);
        unset($data['is_locked']);
        unset($data['permissions']);
        
        // Remove null values
        $data = array_filter($data, fn($value) => $value !== null);
        
        // Set updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $query = QueryBuilder::table($this->table)
            ->update($data)
            ->where('user_id', $entity->getId());
            
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
     * Delete user
     * 
     * @param mixed $id
     * @return bool
     * @throws DatabaseException
     */
    public function delete(mixed $id): bool
    {
        $query = QueryBuilder::table($this->table)
            ->delete()
            ->where('user_id', $id);
            
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
     * Count users with optional filters
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
     * Find user by username
     * 
     * @param string $username
     * @return User|null
     * @throws DatabaseException
     */
    public function findByUsername(string $username): ?User
    {
        $user = $this->findOneBy(['username' => $username]);
        return $user instanceof User ? $user : null;
    }
    
    /**
     * Find user by email
     * 
     * @param string $email
     * @return User|null
     * @throws DatabaseException
     */
    public function findByEmail(string $email): ?User
    {
        $user = $this->findOneBy(['email' => $email]);
        return $user instanceof User ? $user : null;
    }
    
    /**
     * Find user by password reset token
     * 
     * @param string $token
     * @return User|null
     * @throws DatabaseException
     */
    public function findByPasswordResetToken(string $token): ?User
    {
        $user = $this->findOneBy(['password_reset_token' => $token]);
        return $user instanceof User ? $user : null;
    }
    
    /**
     * Find active users
     * 
     * @return array
     * @throws DatabaseException
     */
    public function findActive(): array
    {
        return $this->findBy(['status' => 'Active']);
    }
    
    /**
     * Find users by role
     * 
     * @param string $role
     * @return array
     * @throws DatabaseException
     */
    public function findByRole(string $role): array
    {
        return $this->findBy(['role' => $role]);
    }
    
    /**
     * Find locked users
     * 
     * @return array
     * @throws DatabaseException
     */
    public function findLocked(): array
    {
        $query = QueryBuilder::table($this->table)
            ->select(['*'])
            ->where('locked_until', '>', date('Y-m-d H:i:s'));
            
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
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = User::fromArray($row);
        }
        
        return $users;
    }
    
    /**
     * Get users with pagination
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
        $users = $this->findAll($filters, $orderBy, $limit, $offset);
        $total = $this->count($filters);
        
        return [
            'data' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Search users by name, username, or email
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
            ->select(['*'])
            ->where('first_name', 'LIKE', $searchPattern)
            ->orWhere('last_name', 'LIKE', $searchPattern)
            ->orWhere('username', 'LIKE', $searchPattern)
            ->orWhere('email', 'LIKE', $searchPattern);
        
        // Apply additional filters
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
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
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = User::fromArray($row);
        }
        
        // Get total count for pagination
        $countQuery = QueryBuilder::table($this->table)
            ->select(['COUNT(*) as total'])
            ->where('first_name', 'LIKE', $searchPattern)
            ->orWhere('last_name', 'LIKE', $searchPattern)
            ->orWhere('username', 'LIKE', $searchPattern)
            ->orWhere('email', 'LIKE', $searchPattern);
            
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $countQuery->whereIn($field, $value);
            } else {
                $countQuery->where($field, $value);
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
            'data' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Check if username exists
     * 
     * @param string $username
     * @param int|null $excludeId
     * @return bool
     * @throws DatabaseException
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $query = QueryBuilder::table($this->table)
            ->select(['COUNT(*) as count'])
            ->where('username', $username);
            
        if ($excludeId !== null) {
            $query->where('user_id', '!=', $excludeId);
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
        
        return (int) $row['count'] > 0;
    }
    
    /**
     * Check if email exists
     * 
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     * @throws DatabaseException
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = QueryBuilder::table($this->table)
            ->select(['COUNT(*) as count'])
            ->where('email', $email);
            
        if ($excludeId !== null) {
            $query->where('user_id', '!=', $excludeId);
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
        
        return (int) $row['count'] > 0;
    }
    
    /**
     * Update user login attempt
     * 
     * @param int $userId
     * @param bool $success
     * @param int $maxAttempts
     * @param int $lockoutMinutes
     * @return User|null
     * @throws DatabaseException
     */
    public function updateLoginAttempt(int $userId, bool $success, int $maxAttempts = 5, int $lockoutMinutes = 30): ?User
    {
        $user = $this->find($userId);
        if (!$user) {
            return null;
        }
        
        if ($success) {
            $user->resetFailedLogins();
            $user->updateLastLogin();
        } else {
            $user->incrementFailedLogins();
            
            if ($user->getFailedLoginAttempts() >= $maxAttempts) {
                $user->lockAccount($lockoutMinutes);
            }
        }
        
        $this->update($user);
        
        return $user;
    }
    
    /**
     * Clean expired password reset tokens
     * 
     * @return int Number of users updated
     * @throws DatabaseException
     */
    public function cleanExpiredPasswordResetTokens(): int
    {
        $query = QueryBuilder::table($this->table)
            ->update([
                'password_reset_token' => null,
                'password_reset_expires' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ])
            ->where('password_reset_expires', '<', date('Y-m-d H:i:s'));
            
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
        
        return $stmt->affected_rows;
    }
    
    /**
     * Unlock expired locked accounts
     * 
     * @return int Number of users updated
     * @throws DatabaseException
     */
    public function unlockExpiredAccounts(): int
    {
        $query = QueryBuilder::table($this->table)
            ->update([
                'locked_until' => null,
                'failed_login_attempts' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ])
            ->where('locked_until', '<', date('Y-m-d H:i:s'));
            
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
        
        return $stmt->affected_rows;
    }
    
    /**
     * Get user statistics
     * 
     * @return array
     * @throws DatabaseException
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive_users,
                    SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) as suspended_users,
                    SUM(CASE WHEN locked_until > NOW() THEN 1 ELSE 0 END) as locked_users,
                    COUNT(CASE WHEN role = 'Admin' THEN 1 END) as admin_users,
                    COUNT(CASE WHEN role = 'Manager' THEN 1 END) as manager_users,
                    COUNT(CASE WHEN role = 'Officer' THEN 1 END) as officer_users,
                    COUNT(CASE WHEN role = 'Viewer' THEN 1 END) as viewer_users,
                    COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_last_week,
                    COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_last_month
                FROM {$this->table}";
        
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new DatabaseException('Failed to execute statistics query: ' . $this->connection->error);
        }
        
        return $result->fetch_assoc();
    }
}
