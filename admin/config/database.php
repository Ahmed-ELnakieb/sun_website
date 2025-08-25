<?php

/**
 * Sun Trading Company - Admin Panel Database Configuration
 * Database configuration and connection management
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sun_trading_admin');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            // Log successful connection for debugging
            error_log("Database connected successfully to: " . DB_NAME);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                error_log("QUERY FAILED: " . $sql);
                error_log("QUERY PARAMS: " . print_r($params, true));
                error_log("PDO ERROR: " . print_r($stmt->errorInfo(), true));
            }

            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("PARAMS: " . print_r($params, true));
            return false;
        }
    }

    public function insert($table, $data)
    {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute($data);

            if ($result) {
                return $this->connection->lastInsertId();
            } else {
                error_log("Insert failed. SQL: $sql, Data: " . print_r($data, true));
                error_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage());
            return false;
        }
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $set = '';
        foreach (array_keys($data) as $key) {
            $set .= "{$key} = :{$key}, ";
        }
        $set = rtrim($set, ', ');

        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $allParams = array_merge($data, $whereParams);

        // Detailed logging for debugging
        error_log("UPDATE_DEBUG: SQL = " . $sql);
        error_log("UPDATE_DEBUG: AllParams = " . print_r($allParams, true));

        $stmt = $this->query($sql, $allParams);
        $result = $stmt ? $stmt->rowCount() : false;

        error_log("UPDATE_DEBUG: Result = " . ($result !== false ? "SUCCESS ($result rows)" : "FAILED"));

        return $result;
    }

    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }

    public function execute($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
}
