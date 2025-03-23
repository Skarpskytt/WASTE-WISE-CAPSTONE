<?php
// Database connection
function getPDO() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=wastewise', 'root', '');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}
?>