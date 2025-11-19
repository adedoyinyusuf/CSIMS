<?php

/**
 * Database Connection Class for CSIMS
 * 
 * Provides PDO database connection using configuration from config/database.php
 */

require_once __DIR__ . '/../../config/database.php';

class PdoDatabase 
{
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;

    public function __construct() 
    {
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->username = defined('DB_USER') ? DB_USER : 'root';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
        $this->database = defined('DB_NAME') ? DB_NAME : 'csims_db';
    }

    public function getConnection(): PDO 
    {
        if ($this->connection === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
                $this->connection = new PDO($dsn, $this->username, $this->password);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        return $this->connection;
    }

    public function closeConnection(): void 
    {
        $this->connection = null;
    }
}