<?php
// filepath: /c:/xampp/htdocs/capstone/WASTE-WISE-CAPSTONE/config/session_handler.php
namespace CustomSession;

class SessionHandler
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
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

// Move session handler setup before any session operations
if (session_status() == PHP_SESSION_NONE) {
    try {
        $pdo = new \PDO('mysql:host=localhost;dbname=wastewise', 'root', '');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        $handler = new SessionHandler($pdo);
        session_set_save_handler(
            [$handler, 'open'],
            [$handler, 'close'],
            [$handler, 'read'],
            [$handler, 'write'],
            [$handler, 'destroy'],
            [$handler, 'gc']
        );
        
        // Register shutdown function to ensure session data is saved
        register_shutdown_function('session_write_close');
        
        // Start the session after setting the handler
        session_start();
    } catch (\PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>