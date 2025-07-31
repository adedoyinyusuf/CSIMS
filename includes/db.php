<?php
class Database {
    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $conn;
    private static $instance = null;
    
    private function __construct() {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/CSIMS/config/database.php';
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->dbname = DB_NAME;
        $this->connect();
    }
    
    // Singleton pattern to ensure only one database connection
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    // Connect to the database
    private function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        
        // Set character set
        $this->conn->set_charset("utf8");
    }
    
    // Get database connection
    public function getConnection() {
        return $this->conn;
    }
    
    // Execute query
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    // Prepare statement
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    // Get last inserted ID
    public function getLastId() {
        return $this->conn->insert_id;
    }
    
    // Escape string
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    // Count affected rows
    public function affectedRows() {
        return $this->conn->affected_rows;
    }
    
    // Begin transaction
    public function beginTransaction() {
        $this->conn->autocommit(FALSE);
    }
    
    // Commit transaction
    public function commitTransaction() {
        $this->conn->commit();
        $this->conn->autocommit(TRUE);
    }
    
    // Rollback transaction
    public function rollbackTransaction() {
        $this->conn->rollback();
        $this->conn->autocommit(TRUE);
    }
    
    // Close connection
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>