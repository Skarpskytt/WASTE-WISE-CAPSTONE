<?php
// filepath: /c:/xampp/htdocs/capstone/WASTE-WISE-CAPSTONE/config/session_handler.php
namespace CustomSession;

require_once __DIR__ . '/db_config.php';

class SessionHandler implements \SessionHandlerInterface
{
    private $pdo;
    private static $instance = null;

    private function __construct()
    {
        try {
            $this->pdo = new \PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Create sessions table if it doesn't exist
            $this->createSessionsTable();
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function createSessionsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS sessions (
            session_id VARCHAR(128) NOT NULL PRIMARY KEY,
            session_data TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            session_set_save_handler(self::$instance, true);
        }
        return self::$instance;
    }

    public static function init()
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::getInstance();
            session_start();
        }
    }

    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public static function remove($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string|false
    {
        try {
            $stmt = $this->pdo->prepare('SELECT session_data FROM sessions WHERE session_id = ?');
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? $result['session_data'] : '';
        } catch (\PDOException $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }

    public function write($id, $data): bool
    {
        try {
            $stmt = $this->pdo->prepare('REPLACE INTO sessions (session_id, session_data) VALUES (?, ?)');
            return $stmt->execute([$id, $data]);
        } catch (\PDOException $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE session_id = ?');
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function gc($maxlifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)');
            $stmt->execute([$maxlifetime]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("Session gc error: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize the session handler
SessionHandler::init();
?>