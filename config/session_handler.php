<?php
// filepath: /c:/xampp/htdocs/capstone/WASTE-WISE-CAPSTONE/config/session_handler.php
namespace CustomSession;

class SessionHandler
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $this->pdo = new \PDO('mysql:host=localhost;dbname=wastewise', 'root', '');
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
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

if (session_status() == PHP_SESSION_NONE) {
    $handler = SessionHandler::getInstance();
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
}
?>