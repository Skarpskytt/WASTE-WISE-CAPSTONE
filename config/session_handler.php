<?php
// filepath: /c:/xampp/htdocs/capstone/WASTE-WISE-CAPSTONE/config/session_handler.php
namespace CustomSession;

class SessionHandler
{
    private $pdo;
    private static $instance;

    private function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function getInstance($pdo)
    {
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $stmt = $this->pdo->prepare('SELECT session_data FROM sessions WHERE session_id = ?');
        $stmt->execute([$id]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $session ? $session['session_data'] : '';
    }

    public function write($id, $data)
    {
        $stmt = $this->pdo->prepare('REPLACE INTO sessions (session_id, session_data, updated_at) VALUES (?, ?, NOW())');
        return $stmt->execute([$id, $data]);
    }

    public function destroy($id)
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE session_id = ?');
        return $stmt->execute([$id]);
    }

    public function gc($maxlifetime)
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE updated_at < NOW() - INTERVAL ? SECOND');
        return $stmt->execute([$maxlifetime]);
    }
}

// Initialize session only if explicitly requested
function initSession($pdo) {
    if (session_status() == PHP_SESSION_NONE) {
        try {
            $handler = SessionHandler::getInstance($pdo);
            session_set_save_handler(
                [$handler, 'open'],
                [$handler, 'close'],
                [$handler, 'read'],
                [$handler, 'write'],
                [$handler, 'destroy'],
                [$handler, 'gc']
            );
            
            register_shutdown_function('session_write_close');
            session_start();
            return true;
        } catch (\PDOException $e) {
            error_log("Session initialization error: " . $e->getMessage());
            return false;
        }
    }
    return true;
}
?>