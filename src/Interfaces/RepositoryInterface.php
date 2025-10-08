<?php

namespace CSIMS\Interfaces;

/**
 * Base Repository Interface
 * 
 * All repositories must implement this interface for consistent data access
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID
     * 
     * @param mixed $id
     * @return ModelInterface|null
     */
    public function find(mixed $id): ?ModelInterface;
    
    /**
     * Find all entities with optional filters
     * 
     * @param array $filters
     * @param array $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function findAll(array $filters = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array;
    
    /**
     * Find entities by specific criteria
     * 
     * @param array $criteria
     * @return array
     */
    public function findBy(array $criteria): array;
    
    /**
     * Find single entity by criteria
     * 
     * @param array $criteria
     * @return ModelInterface|null
     */
    public function findOneBy(array $criteria): ?ModelInterface;
    
    /**
     * Create new entity
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     */
    public function create(ModelInterface $entity): ModelInterface;
    
    /**
     * Update existing entity
     * 
     * @param ModelInterface $entity
     * @return ModelInterface
     */
    public function update(ModelInterface $entity): ModelInterface;
    
    /**
     * Delete entity
     * 
     * @param mixed $id
     * @return bool
     */
    public function delete(mixed $id): bool;
    
    /**
     * Count entities with optional filters
     * 
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int;
}
