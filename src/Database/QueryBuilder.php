<?php

namespace CSIMS\Database;

use CSIMS\Exceptions\DatabaseException;

/**
 * Query Builder
 * 
 * Fluent interface for building SQL queries
 */
class QueryBuilder
{
    private string $table = '';
    private array $select = ['*'];
    private array $joins = [];
    private array $where = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private array $having = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $params = [];
    private string $queryType = '';
    private array $insertData = [];
    private array $updateData = [];
    
    /**
     * Create new query builder instance
     * 
     * @param string $table
     * @return static
     */
    public static function table(string $table): static
    {
        $instance = new static();
        $instance->table = $table;
        return $instance;
    }
    
    /**
     * Set SELECT columns
     * 
     * @param array|string $columns
     * @return $this
     */
    public function select(array|string $columns = ['*']): self
    {
        $this->queryType = 'select';
        $this->select = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    
    /**
     * Add JOIN clause
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return $this
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }
    
    /**
     * Add LEFT JOIN clause
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return $this
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }
    
    /**
     * Add RIGHT JOIN clause
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return $this
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }
    
    /**
     * Add WHERE clause
     * 
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        // Handle where($column, $value) syntax
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->where[] = [
            'boolean' => count($this->where) === 0 ? '' : $boolean,
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        
        $this->params[] = $value;
        return $this;
    }
    
    /**
     * Add OR WHERE clause
     * 
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }
    
    /**
     * Add WHERE IN clause
     * 
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return $this
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        
        $this->where[] = [
            'boolean' => count($this->where) === 0 ? '' : $boolean,
            'column' => $column,
            'operator' => 'IN',
            'value' => "({$placeholders})"
        ];
        
        $this->params = array_merge($this->params, $values);
        return $this;
    }
    
    /**
     * Add WHERE NOT IN clause
     * 
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return $this
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        
        $this->where[] = [
            'boolean' => count($this->where) === 0 ? '' : $boolean,
            'column' => $column,
            'operator' => 'NOT IN',
            'value' => "({$placeholders})"
        ];
        
        $this->params = array_merge($this->params, $values);
        return $this;
    }
    
    /**
     * Add WHERE LIKE clause
     * 
     * @param string $column
     * @param string $value
     * @param string $boolean
     * @return $this
     */
    public function whereLike(string $column, string $value, string $boolean = 'AND'): self
    {
        return $this->where($column, 'LIKE', $value, $boolean);
    }
    
    /**
     * Add WHERE BETWEEN clause
     * 
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     * @param string $boolean
     * @return $this
     */
    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND'): self
    {
        $this->where[] = [
            'boolean' => count($this->where) === 0 ? '' : $boolean,
            'column' => $column,
            'operator' => 'BETWEEN',
            'value' => '? AND ?'
        ];
        
        $this->params[] = $min;
        $this->params[] = $max;
        return $this;
    }
    
    /**
     * Add ORDER BY clause
     * 
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }
    
    /**
     * Add GROUP BY clause
     * 
     * @param string|array $columns
     * @return $this
     */
    public function groupBy(string|array $columns): self
    {
        if (is_array($columns)) {
            $this->groupBy = array_merge($this->groupBy, $columns);
        } else {
            $this->groupBy[] = $columns;
        }
        return $this;
    }
    
    /**
     * Add HAVING clause
     * 
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->having[] = "{$column} {$operator} ?";
        $this->params[] = $value;
        return $this;
    }
    
    /**
     * Set LIMIT clause
     * 
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Set OFFSET clause
     * 
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Prepare INSERT statement
     * 
     * @param array $data
     * @return $this
     */
    public function insert(array $data): self
    {
        $this->queryType = 'insert';
        $this->insertData = $data;
        $this->params = array_values($data);
        return $this;
    }
    
    /**
     * Prepare UPDATE statement
     * 
     * @param array $data
     * @return $this
     */
    public function update(array $data): self
    {
        $this->queryType = 'update';
        $this->updateData = $data;
        // Add update values to beginning of params array
        $this->params = array_merge(array_values($data), $this->params);
        return $this;
    }
    
    /**
     * Prepare DELETE statement
     * 
     * @return $this
     */
    public function delete(): self
    {
        $this->queryType = 'delete';
        return $this;
    }
    
    /**
     * Build the SQL query
     * 
     * @return array [sql, params]
     * @throws DatabaseException
     */
    public function build(): array
    {
        $sql = match ($this->queryType) {
            'select' => $this->buildSelect(),
            'insert' => $this->buildInsert(),
            'update' => $this->buildUpdate(),
            'delete' => $this->buildDelete(),
            default => throw new DatabaseException('Query type not set')
        };
        
        return [$sql, $this->params];
    }
    
    /**
     * Build SELECT query
     * 
     * @return string
     */
    private function buildSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select);
        $sql .= ' FROM ' . $this->table;
        
        // Add JOINs
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        // Add WHERE clauses
        if (!empty($this->where)) {
            $sql .= ' WHERE ';
            $whereParts = [];
            foreach ($this->where as $where) {
                $part = $where['boolean'] ? " {$where['boolean']} " : '';
                $part .= "{$where['column']} {$where['operator']} ";
                $part .= is_string($where['value']) && str_contains($where['value'], '?') ? $where['value'] : '?';
                $whereParts[] = $part;
            }
            $sql .= implode('', $whereParts);
        }
        
        // Add GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // Add HAVING
        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }
        
        // Add ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        
        // Add LIMIT
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        // Add OFFSET
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        return $sql;
    }
    
    /**
     * Build INSERT query
     * 
     * @return string
     */
    private function buildInsert(): string
    {
        $columns = array_keys($this->insertData);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        return "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
    }
    
    /**
     * Build UPDATE query
     * 
     * @return string
     */
    private function buildUpdate(): string
    {
        $setParts = [];
        foreach (array_keys($this->updateData) as $column) {
            $setParts[] = "{$column} = ?";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts);
        
        // Add WHERE clauses
        if (!empty($this->where)) {
            $sql .= ' WHERE ';
            $whereParts = [];
            foreach ($this->where as $where) {
                $part = $where['boolean'] ? " {$where['boolean']} " : '';
                $part .= "{$where['column']} {$where['operator']} ";
                $part .= is_string($where['value']) && str_contains($where['value'], '?') ? $where['value'] : '?';
                $whereParts[] = $part;
            }
            $sql .= implode('', $whereParts);
        }
        
        return $sql;
    }
    
    /**
     * Build DELETE query
     * 
     * @return string
     */
    private function buildDelete(): string
    {
        $sql = "DELETE FROM {$this->table}";
        
        // Add WHERE clauses
        if (!empty($this->where)) {
            $sql .= ' WHERE ';
            $whereParts = [];
            foreach ($this->where as $where) {
                $part = $where['boolean'] ? " {$where['boolean']} " : '';
                $part .= "{$where['column']} {$where['operator']} ";
                $part .= is_string($where['value']) && str_contains($where['value'], '?') ? $where['value'] : '?';
                $whereParts[] = $part;
            }
            $sql .= implode('', $whereParts);
        }
        
        return $sql;
    }
    
    /**
     * Get parameters for binding
     * 
     * @return array
     */
    public function getParameters(): array
    {
        return $this->params;
    }
    
    /**
     * Reset builder state
     * 
     * @return $this
     */
    public function reset(): self
    {
        $this->select = ['*'];
        $this->joins = [];
        $this->where = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        $this->params = [];
        $this->queryType = '';
        $this->insertData = [];
        $this->updateData = [];
        
        return $this;
    }
}
