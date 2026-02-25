<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $last_query;
    private $query_count = 0;
    
    private function __construct() {
        $this->connect();
    }
    
    // Singleton pattern - only one database connection
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    private function connect() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        
        $this->connection->set_charset("utf8mb4");
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Execute raw query
    public function query($sql) {
        $this->last_query = $sql;
        $this->query_count++;
        return $this->connection->query($sql);
    }
    
    // Prepare statement
    public function prepare($sql) {
        $this->last_query = $sql;
        return $this->connection->prepare($sql);
    }
    
    // Escape string
    public function escapeString($string) {
        return $this->connection->real_escape_string($string);
    }
    
    // Get last insert ID
    public function insertId() {
        return $this->connection->insert_id;
    }
    
    // Get affected rows
    public function affectedRows() {
        return $this->connection->affected_rows;
    }
    
    // Begin transaction
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    // Commit transaction
    public function commit() {
        $this->connection->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        $this->connection->rollback();
    }
    
    // Get last query
    public function lastQuery() {
        return $this->last_query;
    }
    
    // Get query count
    public function queryCount() {
        return $this->query_count;
    }
    
    // Select one row
    public function selectOne($sql, $params = [], $types = '') {
        $stmt = $this->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    // Select multiple rows
    public function selectMany($sql, $params = [], $types = '') {
        $stmt = $this->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
    
    // Insert data
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $types = '';
        $values = [];
        
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $values[] = $value;
        }
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            return $this->insertId();
        }
        return false;
    }
    
    // Update data
    public function update($table, $data, $where, $whereParams = [], $whereTypes = '') {
        $setClause = [];
        $types = '';
        $values = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = "{$column} = ?";
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $values[] = $value;
        }
        
        // Add where parameters
        foreach ($whereParams as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $values[] = $value;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE {$where}";
        $stmt = $this->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        return $stmt->execute();
    }
    
    // Delete data
    public function delete($table, $where, $params = [], $types = '') {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        return $stmt->execute();
    }
    
    // Count rows
    public function count($table, $where = '', $params = [], $types = '') {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        $stmt = $this->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    // Check if record exists
    public function exists($table, $where, $params = [], $types = '') {
        $count = $this->count($table, $where, $params, $types);
        return $count > 0;
    }
    
    // Get error
    public function error() {
        return $this->connection->error;
    }
    
    // Close connection
    public function close() {
        $this->connection->close();
    }
    
    // Prevent cloning
    private function __clone() {}
}

// Create global database instance
$db = Database::getInstance();
?>