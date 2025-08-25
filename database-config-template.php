<?php

/**
 * Database Configuration Template for Production Server
 * 
 * IMPORTANT: Copy this file to /admin/config/database.php and update the settings below
 * with your actual server database credentials.
 * 
 * Developed by Ahmed Elnakieb
 * Email: ahmedelnakieb95@gmail.com
 */

class Database
{
    // ========================================
    // PRODUCTION DATABASE SETTINGS
    // ========================================
    // UPDATE THESE VALUES FOR YOUR SERVER

    private static $host = 'localhost';              // Your MySQL server host
    private static $dbname = 'sun_trading_db';       // Your database name  
    private static $username = 'your_db_username';   // Your database username
    private static $password = 'your_db_password';   // Your database password
    private static $charset = 'utf8mb4';

    // ========================================
    // DO NOT MODIFY BELOW THIS LINE
    // ========================================

    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=" . self::$charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::$charset
            ];

            $this->pdo = new PDO($dsn, self::$username, self::$password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    // CRUD Operations
    public function fetchAll($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database fetchAll error: " . $e->getMessage());
            return [];
        }
    }

    public function fetchOne($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database fetchOne error: " . $e->getMessage());
            return false;
        }
    }

    public function insert($table, $data)
    {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));

            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);

            if ($stmt->execute($data)) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage());
            return false;
        }
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        try {
            $setClause = [];
            foreach (array_keys($data) as $key) {
                $setClause[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $setClause);

            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);

            $params = array_merge($data, $whereParams);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database update error: " . $e->getMessage());
            return false;
        }
    }

    public function delete($table, $where, $whereParams = [])
    {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($whereParams);
        } catch (PDOException $e) {
            error_log("Database delete error: " . $e->getMessage());
            return false;
        }
    }

    public function execute($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage());
            return false;
        }
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
}
