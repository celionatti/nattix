<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X Database
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\Database;

use Exception;
use PDO;
use PDOException;
use X\Exception\XException;
use X\QueryBuilder\XQueryBuilder;
use X\X;

class Database
{
    public array $missing_tables = [];
    protected array $fillable = [];

    private $connection;
    private $error;
    private \X\Config $config;
    private static Database $instances;
    private int $fetchType = PDO::FETCH_OBJ;

    /**
     * @throws XException
     */
    public function __construct()
    {
        $this->config = X::$x->config;
        $databaseConfig = [
            "drivers" => $this->config->get('DB_DRIVERS'),
            "host" => $this->config->get('DB_HOST'),
            "dbname" => $this->config->get('DB_DATABASE'),
            "username" => $this->config->get('DB_USERNAME'),
            "password" => $this->config->get('DB_PASSWORD')
        ];

        $this->connect($databaseConfig);
    }

    /**
     * @throws XException
     */
    private function connect($config): void
    {
        $dsn = "{$config['drivers']}:host={$config['host']};dbname={$config['dbname']}";
        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->connection->setAttribute(PDO::ATTR_PERSISTENT, true);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        } catch (PDOException $e) {
//            $this->handleDatabaseError($e->getMessage(), $e->getCode());
            throw new XException($e->getMessage(), $e->getCode());
        }
    }

    public function handleDatabaseError($errorMessage, $errorCode, $sql = '', $params = []): void
    {
        // Log directory for different types of errors
        $errorLogDir = X::$x->pathResolver->resolve() . 'logs' . DIRECTORY_SEPARATOR .  'errors/';
        $duplicateLogDir = $errorLogDir . 'duplicate/';
        $authenticationLogDir = $errorLogDir . 'authentication/';
        $genericLogDir = $errorLogDir . 'generic/';

        // Create log directories if they don't exist
        if (!file_exists($errorLogDir)) {
            mkdir($errorLogDir, 0777, true);
        }

        // Log the error based on error code
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Error $errorCode: $errorMessage";
        $logFilePath = '';

        if ($errorCode === 1062) {
            if (!file_exists($duplicateLogDir)) {
                mkdir($duplicateLogDir, 0777, true);
            }
            // Log duplicate entry errors in a separate folder
            $logFilePath = $duplicateLogDir . 'duplicate_error.log';
        } elseif ($errorCode === 1045) {
            if (!file_exists($authenticationLogDir)) {
                mkdir($authenticationLogDir, 0777, true);
            }
            // Log authentication errors in a separate folder
            $logFilePath = $authenticationLogDir . 'authentication_error.log';
        } else {
            if (!file_exists($genericLogDir)) {
                mkdir($genericLogDir, 0777, true);
            }
            // Log other errors in a generic folder
            $logFilePath = $genericLogDir . 'generic_error.log';
        }

        // Log the error message
        error_log($logMessage . PHP_EOL, 3, $logFilePath);

        // Additional actions based on error code or specific conditions
        if ($errorCode === 1062) {
            // Handle duplicate entry error
            // Example: Notify admin or display a user-friendly message
            x_die("Error: Duplicate entry detected!");
        } elseif ($errorCode === 1045) {
            // Handle authentication error
            // Example: Redirect user to login page or display an error message
            x_die("Error: Authentication failed!");
        } else {
            // Handle other errors or generic fallback
            // Example: Display a generic error message to the user
            x_die("Error: Something went wrong. Please try again later.");
        }

        // Optionally, you can log the SQL query and parameters causing the error
        if (!empty($sql)) {
            $logMessage .= " | SQL: $sql";
        }
        if (!empty($params)) {
            $logMessage .= " | Params: " . json_encode($params);
        }
        error_log($logMessage . PHP_EOL, 3, $logFilePath);
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public static function getInstance(): Database
    {
        if (!isset(self::$instances)) {
            self::$instances = new self();
        }
        return self::$instances;
    }
    
    public function setFetchType($type): void
    {
        $this->fetchType = $type;
    }

    /**
     * @throws XException
     */
    public function createDatabaseIfNotExists(): void
    {
        try {
            $stmt = $this->connection->prepare("SHOW DATABASES LIKE :dbname");
            $database = $this->config->get("DB_DATABASE");
            $stmt->bindParam(':dbname', $database);
            $stmt->execute();
            $result = $stmt->fetch($this->fetchType);

            if (!$result) {
                $this->connection->exec("CREATE DATABASE " . $database);
            }
        } catch (PDOException $e) {
            throw new XException($e->getMessage(), $e->getCode());
        }
    }

    public function prepare($query): false|\PDOStatement
    {
        return $this->connection->prepare($query);
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $this->error = $stmt->errorInfo();
        return $stmt;
    }

    public function select($table, $columns = '*', $conditions = [], $orderBy = '', $limit = ''): false|array
    {
        $sql = "SELECT $columns FROM $table";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        return $this->query($sql)->fetchAll($this->fetchType);
    }

    public function selectOne($table, $columns = '*', $conditions = [], $orderBy = '') {
        $sql = "SELECT $columns FROM $table";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        $sql .= " LIMIT 1";
        return $this->query($sql)->fetch($this->fetchType);
    }


    /**
     *
     * $db->transaction(function ($db) use ($data) {
     * $db->insert('table1', $data);
     * $db->update('table2', $dataToUpdate, $conditions);
     * });
     *
 */
    public function transaction($callback): void
    {
        try {
            $this->beginTransaction();
            $callback($this);
            $this->commit();
        } catch (PDOException $e) {
            $this->rollback();
            echo 'Transaction failed: ' . $e->getMessage();
        }
    }

    public function getColumnNames($table): false|array
    {
        $sql = "SHOW COLUMNS FROM $table";
        $stmt = $this->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function selectColumn($table, $column, $conditions = []): false|array
    {
        $sql = "SELECT $column FROM $table";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $stmt = $this->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function recordExists($table, $conditions = []): bool
    {
        $sql = "SELECT COUNT(*) as count FROM $table";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $result = $this->query($sql)->fetch($this->fetchType);
        return $result['count'] > 0;
    }

    public function insert($table, $data): false|int
    {
        if (!$this->validateData($data, $this->fillable)) {
            return false;
        }

        $columns = implode(', ', array_keys($data));
        $values = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO $table ($columns) VALUES ($values)";
        return $this->query($sql, $data)->rowCount();
    }

    public function update($table, $data, $conditions = []): false|int
    {
        if (!$this->validateData($data, $this->fillable)) {
            return false;
        }

        $set = '';
        foreach ($data as $key => $value) {
            $set .= "$key = :$key, ";
        }
        $set = rtrim($set, ', ');

        $where = '';
        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "UPDATE $table SET $set $where";
        return $this->query($sql, $data)->rowCount();
    }

    public function delete($table, $conditions = []): int
    {
        $where = '';
        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "DELETE FROM $table $where";
        return $this->query($sql)->rowCount();
    }

    public function countRows($table, $conditions = []) {
        $where = '';
        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT COUNT(*) as count FROM $table $where";
        return $this->query($sql)->fetch(PDO::FETCH_ASSOC)['count'];
    }

    public function executeRaw($sql, $params = []): false|\PDOStatement
    {
        return $this->query($sql, $params);
    }

    private function validateData($data, array $fillableColumns = []): bool
    {
        if (empty($fillableColumns)) {
            return false; // Fillable columns not defined
        }

        $dataKeys = array_keys($data);
        foreach ($dataKeys as $key) {
            if (!in_array($key, $fillableColumns)) {
                return false; // Data contains non-fillable columns
            }
        }

        // Perform additional validation logic here if needed
        return true; // Validation passed
    }

    public function getLastInsertId(): false|string
    {
        return $this->connection->lastInsertId();
    }

    public function queryAndFetch($sql, $params = []): false|array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll($this->fetchType);
    }

    public function selectWithPagination($table, $columns = '*', $conditions = [], $orderBy = '', $limit = 10, $offset = 0): false|array
    {
        $sql = "SELECT $columns FROM $table";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        $sql .= " LIMIT $limit OFFSET $offset";
        return $this->query($sql)->fetchAll($this->fetchType);
    }

    public function executeBatchQueries($queries): bool
    {
        try {
            $this->beginTransaction();
            foreach ($queries as $query) {
                $this->query($query);
            }
            $this->commit();
            return true;
        } catch (PDOException $e) {
            $this->rollback();
            echo 'Batch execution failed: ' . $e->getMessage();
            return false;
        }
    }

    public function selectDistinct($table, $column, $conditions = [], $orderBy = ''): false|array
    {
        $sql = "SELECT DISTINCT $column FROM $table";
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        return $this->query($sql)->fetchAll($this->fetchType);
    }

    public function getDatabaseInfo(): array
    {
        $tables = $this->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $info = [];
        foreach ($tables as $table) {
            $columns = $this->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
            $info[$table] = $columns;
        }
        return $info;
    }

    /**
     * @throws Exception
     */
    public function queryBuilder(XQueryBuilder $queryBuilder): false|array
    {
        $sql = $queryBuilder->build();
        $stmt = $this->connection->prepare($sql);

        try {
            $stmt->execute($queryBuilder->getParams());
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Handle the error (log, throw custom exception, etc.)
            throw new Exception('Query execution failed: ' . $e->getMessage());
        }
    }
}