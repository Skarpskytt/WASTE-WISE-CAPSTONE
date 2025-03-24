<?php
// Database connection
function getPDO() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Add charset and other important PDO options
            $dsn = 'mysql:host=localhost;dbname=wastewise;charset=utf8mb4';
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new \PDO($dsn, 'root', '', $options);
            
            // Test connection
            $pdo->query('SELECT 1');
            error_log("Database connection established successfully");
            
        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            // More detailed error for development
            if (defined('DEV_MODE') && DEV_MODE === true) {
                throw new \PDOException("Database connection failed: " . $e->getMessage());
            }
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

// Test connection immediately when file is included
try {
    $testConnection = getPDO();
    error_log("Initial database connection test successful");
} catch (\Exception $e) {
    error_log("Initial database connection test failed: " . $e->getMessage());
}
?>