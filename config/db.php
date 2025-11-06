<?php
/**
 * Database Connection Configuration
 * Grama Voice - Village Governance Platform
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'grama_voice');

// Error logging configuration
define('LOG_FILE', __DIR__ . '/../logs/error.log');

class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
            
            // Check connection
            if ($this->connection->connect_error) {
                $error_msg = "Database connection failed: " . $this->connection->connect_error;
                $this->logError($error_msg);
                throw new Exception($error_msg);
            }
            
            // Set charset to utf8
            $this->connection->set_charset("utf8");
            
        } catch (Exception $e) {
            $this->logError("Database connection error: " . $e->getMessage());
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        try {
            $result = $this->connection->query($sql);
            if (!$result) {
                $this->logError("Query failed: " . $this->connection->error . " | SQL: " . $sql);
            }
            return $result;
        } catch (Exception $e) {
            $this->logError("Query exception: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    public function prepare($sql) {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                $this->logError("Prepare failed: " . $this->connection->error . " | SQL: " . $sql);
            }
            return $stmt;
        } catch (Exception $e) {
            $this->logError("Prepare exception: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function getError() {
        return $this->connection->error;
    }
    
    public function beginTransaction() {
        return $this->connection->begin_transaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Log error to file
     */
    private function logError($message) {
        $log_dir = dirname(LOG_FILE);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message" . PHP_EOL;
        file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log general application errors
     */
    public static function logApplicationError($message, $file = '', $line = '') {
        $log_dir = dirname(LOG_FILE);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $context = $file ? " in $file" : '';
        $context .= $line ? " on line $line" : '';
        $log_entry = "[$timestamp] APPLICATION ERROR: $message$context" . PHP_EOL;
        file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Global function to get database instance
function getDB() {
    return Database::getInstance();
}

// Test connection
try {
    $db = getDB();
    // Connection successful
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
