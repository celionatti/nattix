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
use InvalidArgumentException;
use PDO;
use PDOException;

class XQueryBuilder
{
    private $connection;
    private string $table;
    private $query;
    private array $bindValues = [];
    private array $joinClauses = [];
    private string $currentStep = 'initial';

    public function __construct($connection, string $table)
    {
        if (empty($table)) {
            throw new InvalidArgumentException('Table name must not be empty.');
        }

        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Select columns for the query.
     *
     * @param array|string $columns The columns to select.
     * @return $this
     * @throws Exception If called in an invalid method order.
     * @throws InvalidArgumentException If $columns is invalid.
     */
    public function select(array|string $columns = '*'): static
    {
        $this->currentStep = "initial";

        if (!in_array($this->currentStep, ['initial', 'raw'])) {
            throw new Exception('Invalid method order. SELECT should come first.');
        }

        if (!is_array($columns) && !is_string($columns)) {
            throw new InvalidArgumentException('Invalid argument for SELECT method. Columns must be an array or a comma-separated string.');
        }

        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        $this->query = "SELECT $columns FROM $this->table";
        $this->currentStep = 'select';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function insert(array $data): static
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Invalid argument for INSERT method. Data array must not be empty.');
        }

        if (!in_array($this->currentStep, ['initial'])) {
            throw new Exception('Invalid method order. INSERT should come before other query building methods.');
        }

        $columns = implode(', ', array_keys($data));
        $values = ':' . implode(', :', array_keys($data));

        $this->query = "INSERT INTO $this->table ($columns) VALUES ($values)";
        $this->bindValues = $data;
        $this->currentStep = 'insert';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function update(array $data): static
    {
        $this->currentStep = 'initial';

        if (empty($data)) {
            throw new InvalidArgumentException('Invalid argument for UPDATE method. Data array must not be empty.');
        }

        if (!in_array($this->currentStep, ['initial', 'where', 'select', 'raw'])) {
            throw new Exception('Invalid method order. UPDATE should come before other query building methods.');
        }

        $set = [];
        foreach ($data as $column => $value) {
            if (!is_string($column) || empty($column)) {
                throw new InvalidArgumentException('Invalid argument for UPDATE method. Column names must be non-empty strings.');
            }

            $set[] = "$column = :$column";
            $this->bindValues[":$column"] = $value;
        }

        $this->query = "UPDATE $this->table SET " . implode(', ', $set);
        $this->currentStep = 'update';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function delete(): static
    {
        if (!in_array($this->currentStep, ['select', 'initial', 'limit', 'raw', 'where'])) {
            throw new Exception('Invalid method order. DELETE should come before other query building methods.');
        }

        $this->query = "DELETE FROM $this->table";
        $this->currentStep = 'delete';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function where(array $conditions, string $operator = 'AND'): static
    {
        if (!in_array($this->currentStep, ['select', 'update', 'delete', 'raw', 'where', 'join', 'count'])) {
            throw new Exception('Invalid method order. WHERE should come after SELECT, UPDATE, DELETE, or a previous WHERE.');
        }

        if (empty($conditions)) {
            throw new InvalidArgumentException('Invalid argument for WHERE method. Conditions array must not be empty.');
        }

        $where = [];
        foreach ($conditions as $column => $value) {
            if (!is_string($column) || empty($column)) {
                throw new InvalidArgumentException('Invalid argument for WHERE method. Column names must be non-empty strings.');
            }

            // Check if the value contains the '%' wildcard for LIKE condition
            if (is_string($value) && str_contains($value, '%')) {
                $where[] = "$column LIKE :$column";
            } else {
                $where[] = "$column = :$column";
            }

            $this->bindValues[":$column"] = $value;
        }

        $this->query .= " WHERE " . implode(" $operator ", $where);
        $this->currentStep = 'where';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function join(string $table, string $onClause, string $type = 'INNER'): static
    {
        $this->validateJoinMethod();

        $this->validateJoinArguments($table, $onClause, $type);

        $this->joinClauses[] = "$type JOIN $table ON $onClause";

        $this->currentStep = 'join';

        return $this;
    }

    /**
     * @throws Exception
     */
    private function validateJoinMethod(): void
    {
        $allowedPreviousSteps = ['initial', 'select', 'count', 'raw'];

        if (!in_array($this->currentStep, $allowedPreviousSteps)) {
            throw new Exception('Invalid method order. JOIN should come after SELECT, COUNT, or a previous JOIN.');
        }
    }

    private function validateJoinArguments(string $table, string $onClause, string $type): void
    {
        if (empty($table) || empty($onClause) || !in_array($type, ['INNER', 'LEFT', 'RIGHT', 'OUTER'])) {
            throw new InvalidArgumentException('Invalid arguments for JOIN method.');
        }
    }

    /**
     * @throws Exception
     */
    public function leftJoin($table, $onClause): static
    {
        return $this->join($table, $onClause, 'LEFT');
    }

    /**
     * @throws Exception
     */
    public function rightJoin($table, $onClause): static
    {
        return $this->join($table, $onClause, 'RIGHT');
    }

    /**
     * @param $table
     * @param $onClause
     * @return XQueryBuilder
     * @throws Exception
     */
    public function outerJoin($table, $onClause): static
    {
        return $this->join($table, $onClause, 'OUTER');
    }

    /**
     * @throws Exception
     */
    public function count(): static
    {
        $allowedPreviousSteps = ['initial', 'select', 'count', 'raw', 'where'];

        if (!in_array($this->currentStep, $allowedPreviousSteps)) {
            throw new Exception('Invalid method order. COUNT should come before other query building methods.');
        }

        $this->query = "SELECT COUNT(*) AS count FROM $this->table";
        $this->currentStep = 'count';

        return $this;
    }


    /**
     * @throws Exception
     */
    public function orderBy($column, $direction = 'ASC'): static
    {
        if ($this->currentStep !== 'select' && $this->currentStep !== 'where' && $this->currentStep !== 'raw') {
            throw new Exception('Invalid method order. ORDER BY should come after SELECT, WHERE, or a previous ORDER BY.');
        }

        if (!is_string($column) || empty($column)) {
            throw new InvalidArgumentException('Invalid argument for ORDER BY method. Column name must be a non-empty string.');
        }

        $this->query .= " ORDER BY $column $direction";
        $this->currentStep = 'order';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function groupBy($column): static
    {
        if ($this->currentStep !== 'select' && $this->currentStep !== 'where' && $this->currentStep !== 'order' && $this->currentStep !== 'raw') {
            throw new Exception('Invalid method order. GROUP BY should come after SELECT, WHERE, ORDER BY, or a previous GROUP BY.');
        }

        if (!is_string($column) || empty($column)) {
            throw new InvalidArgumentException('Invalid argument for GROUP BY method. Column name must be a non-empty string.');
        }

        $this->query .= "GROUP BY $column";
        $this->currentStep = 'group';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function limit($limit): static
    {
        if ($this->currentStep !== 'select' && $this->currentStep !== 'where' && $this->currentStep !== 'order' && $this->currentStep !== 'group' && $this->currentStep !== 'raw') {
            throw new Exception('Invalid method order. LIMIT should come after SELECT, WHERE, ORDER BY, GROUP BY, or a previous LIMIT.');
        }

        if (!is_numeric($limit) || $limit < 1) {
            throw new InvalidArgumentException('Invalid argument for LIMIT method. Limit must be a positive numeric value.');
        }

        $this->query .= " LIMIT $limit";
        $this->currentStep = 'limit';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function offset($offset): static
    {
        if ($this->currentStep !== 'select' && $this->currentStep !== 'where' && $this->currentStep !== 'order' && $this->currentStep !== 'group' && $this->currentStep !== 'limit' && $this->currentStep !== 'raw') {
            throw new Exception('Invalid method order. OFFSET should come after SELECT, WHERE, ORDER BY, GROUP BY, LIMIT, or a previous OFFSET.');
        }

        if (!is_numeric($offset) || $offset < 0) {
            throw new InvalidArgumentException('Invalid argument for OFFSET method. Offset must be a non-negative numeric value.');
        }

        $this->query .= " OFFSET $offset";
        $this->currentStep = 'offset';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function execute()
    {
        try {
            $stm = $this->executeQuery();

            return $stm->rowCount();
        } catch (PDOException $e) {
            // Handle database error, e.g., log or throw an exception
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function get($data_type = 'object')
    {
        try {
            $stm = $this->executeQuery();

            if ($data_type === 'object') {
                return $stm->fetchAll(PDO::FETCH_OBJ);
            } elseif ($data_type === 'assoc') {
                return $stm->fetchAll(PDO::FETCH_ASSOC);
            } else {
                return $stm->fetchAll(PDO::FETCH_CLASS);
            }
        } catch (PDOException $e) {
            // Handle database error, e.g., log or throw an exception
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function executeQuery()
    {
        try {
            // Combine the main query and join clauses
            $fullQuery = $this->query;

            if (!empty($this->joinClauses)) {
                $fullQuery .= ' ' . implode(' ', $this->joinClauses);
            }

            // Prepare the combined query
            $stm = $this->connection->prepare($fullQuery);

            // Bind values
            foreach ($this->bindValues as $param => $value) {
                $stm->bindValue($param, $value);
            }

            // Execute the query
            $stm->execute();

            // Clear existing bind values
            $this->bindValues = [];

            return $stm;
        } catch (PDOException $e) {
            // Handle database error, e.g., log or throw an exception
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function distinct($columns = '*'): static
    {
        if ($this->currentStep !== 'initial') {
            throw new Exception('Invalid method order. DISTINCT should come before other query building methods.');
        }

        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $columns = implode(', ', $columns);
        $this->query = "SELECT DISTINCT $columns FROM $this->table";
        $this->currentStep = 'distinct';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function truncate(): static
    {
        if ($this->currentStep !== 'initial') {
            throw new Exception('Invalid method order. TRUNCATE should come before other query building methods.');
        }

        $this->query = "TRUNCATE TABLE $this->table";
        $this->currentStep = 'truncate';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function union(BoltQueryBuilder ...$queries)
    {
        if ($this->currentStep !== 'initial') {
            throw new Exception('Invalid method order. UNION should come before other query building methods.');
        }

        // Store the current query and reset it
        $currentQuery = $this->query;
        $this->query = '';

        $queryStrings = [$currentQuery];
        foreach ($queries as $query) {
            $queryStrings[] = $query->query; // Assuming your query property is called "query"
        }

        $this->query = implode(' UNION ', $queryStrings);
        $this->currentStep = 'union';

        return $this;
    }

    public function rawQuery(string $sql, array $bindValues = [], bool $clearExisting = false): static
    {
        if ($clearExisting) {
            $this->bindValues = [];
        }

        $this->query = $sql;
        $this->bindValues = array_merge($this->bindValues, $bindValues);
        $this->currentStep = 'raw';

        return $this;
    }


    /**
     * @throws Exception
     */
    public function alias(string $alias): static
    {
        if ($this->currentStep === 'initial') {
            throw new Exception('Invalid method order. Alias should come after other query building methods.');
        }

        $this->query .= " AS $alias";

        return $this;
    }

    /**
     * @throws Exception
     */
    public function subquery(XQueryBuilder $subquery, string $alias)
    {
        if ($this->currentStep === 'initial') {
            throw new Exception('Invalid method order. Subquery should come after other query building methods.');
        }

        $this->query .= " ($subquery) AS $alias";

        return $this;
    }

    /**
     * @throws Exception
     */
    public function between(string $column, $value1, $value2): static
    {
        if ($this->currentStep !== 'select' && $this->currentStep !== 'where') {
            throw new Exception('Invalid method order. BETWEEN should come after SELECT, WHERE, or a previous BETWEEN.');
        }

        $this->query .= " AND $column BETWEEN :value1 AND :value2";
        $this->bindValues[':value1'] = $value1;
        $this->bindValues[':value2'] = $value2;

        $this->currentStep = 'between';

        return $this;
    }

    /**
     * @throws Exception
     */
    public function having(array $conditions): static
    {
        if ($this->currentStep !== 'group') {
            throw new Exception('Invalid method order. HAVING should come after GROUP BY.');
        }

        $having = [];
        foreach ($conditions as $column => $value) {
            $having[] = "$column = :$column";
            $this->bindValues[":$column"] = $value;
        }

        $this->query .= " HAVING " . implode(' AND ', $having);

        return $this;
    }
}