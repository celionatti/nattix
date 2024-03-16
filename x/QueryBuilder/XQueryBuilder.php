<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X XQueryBuilder
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\QueryBuilder;


use Exception;
use PDO;
use PDOException;

class XQueryBuilder
{
    protected PDO $pdo;
    protected string $table;
    protected string $columns = '*';
    protected string $joins = '';
    protected string $where = '';
    protected array $params = [];
    protected string $groupBy = '';
    protected string $orderBy = '';
    protected string $limit = '';
    protected string $having = '';

    private int $fetchType = PDO::FETCH_ASSOC;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function table($table): static
    {
        $this->table = $table;
        return $this;
    }

    public function select($columns = '*'): static
    {
        $this->columns = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    public function where($column, $operator, $value): static
    {
        $this->where .= ($this->where ? ' AND ' : '') . "$column $operator ?";
        $this->params[] = $value;
        return $this;
    }

    public function whereIn($column, $values): static
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->where .= ($this->where ? ' AND ' : '') . "$column IN ($placeholders)";
        $this->params = array_merge($this->params, $values);
        return $this;
    }

    public function join($table, $firstColumn, $operator, $secondColumn): static
    {
        $this->joins .= " INNER JOIN $table ON $firstColumn $operator $secondColumn";
        return $this;
    }

    public function leftJoin($table, $firstColumn, $operator, $secondColumn): static
    {
        $this->joins .= " LEFT JOIN $table ON $firstColumn $operator $secondColumn";
        return $this;
    }

    /**
     * @throws Exception
     */
    public function insert($data): false|string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute($data);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            // Handle the error (log, throw custom exception, etc.)
            throw new Exception('Insert failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function update($data): int
    {
        $set = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));

        $sql = "UPDATE {$this->table} SET $set";
        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute(array_merge($data, $this->params));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            // Handle the error (log, throw custom exception, etc.)
            throw new Exception('Update failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute($this->params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            // Handle the error (log, throw custom exception, etc.)
            throw new Exception('Delete failed: ' . $e->getMessage());
        }
    }

    public function count()
    {
        $sql = "SELECT COUNT(*) AS count FROM {$this->table}";
        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchColumn();
    }

    public function groupBy($column): static
    {
        $this->groupBy = " GROUP BY $column";
        return $this;
    }

    public function orderBy($column, $direction = 'DESC'): static
    {
        $this->orderBy = " ORDER BY $column $direction";
        return $this;
    }

    public function limit($limit, $offset = 0): static
    {
        $this->limit = " LIMIT $limit OFFSET $offset";
        return $this;
    }

    public function beginTransaction(): static
    {
        $this->pdo->beginTransaction();
        return $this;
    }

    public function commit(): static
    {
        $this->pdo->commit();
        return $this;
    }

    public function rollback(): static
    {
        $this->pdo->rollBack();
        return $this;
    }

    public function get(): false|array
    {
        $sql = "SELECT {$this->columns} FROM {$this->table}";

        if ($this->joins) {
            $sql .= $this->joins;
        }

        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        if ($this->groupBy) {
            $sql .= $this->groupBy;
        }

        if ($this->orderBy) {
            $sql .= $this->orderBy;
        }

        if ($this->limit) {
            $sql .= $this->limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll($this->fetchType);
    }

    public function build(): string
    {
        $sql = "SELECT {$this->columns} FROM {$this->table}";

        if ($this->joins) {
            $sql .= $this->joins;
        }

        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        if ($this->groupBy) {
            $sql .= $this->groupBy;
        }

        if ($this->orderBy) {
            $sql .= $this->orderBy;
        }

        if ($this->limit) {
            $sql .= $this->limit;
        }

        return $sql;
    }

    public function sum($column)
    {
        $sql = "SELECT SUM($column) AS total FROM {$this->table}";
        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchColumn();
    }

    public function avg($column)
    {
        $sql = "SELECT AVG($column) AS average FROM {$this->table}";
        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchColumn();
    }

    public function max($column)
    {
        $sql = "SELECT MAX($column) AS max_value FROM {$this->table}";
        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchColumn();
    }

    public function min($column)
    {
        $sql = "SELECT MIN($column) AS min_value FROM {$this->table}";
        if ($this->where) {
            $sql .= " WHERE {$this->where}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchColumn();
    }

    public function totalPage($perPage): float|int
    {
        $total = $this->count();
        return ceil($total / $perPage);
    }

    public function paginate($perPage, $currentPage = 1): array
    {
        $totalPages = $this->totalPage($perPage);
        $offset = ($currentPage - 1) * $perPage;

        $this->limit($perPage, $offset);

        $result['data'] = $this->get();
        $result['totalPages'] = $totalPages;

        return $result;
    }

    public function having($column, $operator, $value): static
    {
        $this->having .= ($this->having ? ' AND ' : ' HAVING ') . "$column $operator ?";
        $this->params[] = $value;
        return $this;
    }

    public function distinct($column): static
    {
        $this->columns = "DISTINCT $column";
        return $this;
    }

    /**
     * @throws Exception
     */
    public function truncate(): true
    {
        $sql = "TRUNCATE TABLE {$this->table}";
        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            // Handle the error (log, throw custom exception, etc.)
            throw new Exception('Truncate failed: ' . $e->getMessage());
        }
    }

    public function alias($alias): static
    {
        $this->columns .= " AS $alias";
        return $this;
    }

}