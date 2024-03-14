<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X Database
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\Database;

use PDO;
use PDOException;
use X\X;

class Database
{
    public static string $query_id = '';
    public int $affected_rows = 0;
    public mixed $insert_id = 0;
    public string $error = '';
    public bool $has_error = false;

    public int $transactionLevel = 0;
    public array $missing_tables = [];

    public $databaseType;
    private $connection;
    private static array $instances = [];

    public function __construct()
    {
        $config = X::$x->config;
        $databaseConfig = [
            "drivers" => $config->get('DB_DRIVERS'),
            "host" => $config->get('DB_HOST'),
            "dbname" => $config->get('DB_DATABASE'),
            "username" => $config->get('DB_USERNAME'),
            "password" => $config->get('DB_PASSWORD')
        ];
        $this->connect($databaseConfig);
    }

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
            $this->handleDatabaseError($e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public static function getInstance(string $connectionName = 'database')
    {
        if (!isset(self::$instances[$connectionName])) {
            self::$instances[$connectionName] = new self($connectionName);
        }
        return self::$instances[$connectionName];
    }

    public function beginTransaction(): void
    {
        try {
            if ($this->transactionLevel === 0) {
                $this->connection->beginTransaction();
            }
            $this->transactionLevel++;
        } catch (PDOException $e) {
            $this->handleDatabaseError($e->getMessage());
        }
    }

    public function createSavepoint(string $savepoint): void
    {
        try {
            $this->connection->exec("SAVEPOINT $savepoint");
        } catch (PDOException $e) {
            $this->handleDatabaseError($e->getMessage());
        }
    }

    public function rollbackToSavepoint(string $savepoint): void
    {
        try {
            $this->connection->exec("ROLLBACK TO SAVEPOINT $savepoint");
        } catch (PDOException $e) {
            $this->handleDatabaseError($e->getMessage());
        }
    }

    public function commitTransaction(): void
    {
        if ($this->transactionLevel === 1) {
            try {
                $this->connection->commit();
            } catch (PDOException $e) {
                $this->handleDatabaseError($e->getMessage());
            }
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    public function rollbackTransaction(): void
    {
        if ($this->transactionLevel === 1) {
            try {
                $this->connection->rollBack();
            } catch (PDOException $e) {
                $this->handleDatabaseError($e->getMessage());
            }
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    public function setDatabaseType(string $databaseType): void
    {
        // Validate and set the database type
        if (in_array($databaseType, ['mysql', 'pgsql'])) {
            $this->databaseType = $databaseType;
        } else {
            $this->handleDatabaseError("Unsupported database type: {$databaseType}");
        }
    }

    public function getDatabaseType()
    {
        return $this->databaseType;
    }

    public function queryBuilder($table)
    {
        return new XQueryBuilder($this->connection, $table);
    }

    private function handleDatabaseError($errorMessage): void
    {
        $this->error = $errorMessage;
        $this->has_error = true;

        // Example: Log error to a file
        error_log("Database Error: $errorMessage");

        // You can also throw an exception if desired
        x_die("Database Error", $errorMessage);
    }

    public function getRow(string $query, array $data = [], string $data_type = 'object')
    {
        $result = $this->query($query, $data, $data_type);
        if (count($result) > 0) {
            return $result[0];
        }

        return false;
    }

    public function prepare($query)
    {
        return $this->connection->prepare($query);
    }

    public function query(string $query, array $params = [], string $data_type = 'object'): array
    {
        $this->error = '';
        $this->has_error = false;

        try {
            $stmt = $this->connection->prepare($query);

            // Bind named parameters if provided
            foreach ($params as $paramName => $paramValue) {
                $stmt->bindValue(":" . $paramName, $paramValue);
            }

            $result = $stmt->execute();

            $this->affected_rows = $stmt->rowCount();
            $this->insert_id = $this->connection->lastInsertId();

            if ($result) {
                if ($data_type == 'object') {
                    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
                } elseif ($data_type == 'assoc') {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $rows = $stmt->fetchAll(PDO::FETCH_CLASS);
                }
            }
        } catch (PDOException $e) {
            // Log the error
            error_log("Database Query Error: " . $e->getMessage());

            // Handle the error based on your application's needs
            // For example, you can throw a custom exception or return an error response
            $this->error = $e->getMessage();
            $this->has_error = true;
        }

        $resultData = [
            'query' => $query,
            'params' => $params,
            'result' => $rows ?? [],
            'query_id' => self::$query_id,
        ];
        self::$query_id = '';

        return $resultData;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function table_exists(string|array $tables): bool
    {
        if (!is_array($tables)) {
            $tables = [$tables];
        }

        $this->error = '';
        $this->has_error = false;

        try {
            $existingTables = [];

            // Fetch existing table names from the database
            $stmt = $this->connection->prepare('SHOW TABLES');
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($result !== false) {
                $existingTables = $result;
            }

            // Check if all specified tables exist
            foreach ($tables as $table) {
                if (!in_array($table, $existingTables)) {
                    $this->missing_tables[] = $table;
                }
            }

            return empty($this->missing_tables);
        } catch (PDOException $e) {
            $this->handleDatabaseError($e->getMessage());
            return false;
        }
    }
}