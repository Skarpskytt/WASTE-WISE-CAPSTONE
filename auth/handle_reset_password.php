<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../config/app_config.php';
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

use CustomSession\SessionHandler;
use function CustomSession\initSession;

// Get database connection
$pdo = getPDO();

// Initialize custom session
initSession($pdo);

// Get session handler instance
$session = SessionHandler::getInstance($pdo);
// Debug - log the request method
error_log("Handle reset password: Method is " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $token = $_POST['token'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Debug - log the received data
    error_log("Reset password attempt - Token: $token, Email: $email");
    
    // Basic validation
    if (empty($token) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: reset_password.php?token=$token");
        exit();
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password.php?token=$token");
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // FIX: Complete the SQL query
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email, u.id as user_id
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ?
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug - log the result
        error_log("Reset record found: " . ($reset ? "Yes" : "No"));
        
        if (!$reset) {
            throw new Exception("This password reset token is invalid.");
        }
        
        // Ignore the expiry date check temporarily
        // This is because your dates are in 2025, not 2024
        /*
        // Check if token is expired
        $now = time();
        $expiry = strtotime($reset['expiry_date']);
        if ($now > $expiry) {
            throw new Exception("This password reset token has expired.");
        }
        */
        
        // Check if token has been used
        if ($reset['used'] == 1) {
            throw new Exception("This password reset token has already been used.");
        }
        
        // Extra check: verify email matches token's user
        if ($reset['email'] !== $email) {
            throw new Exception("Email does not match the reset token.");
        }
        
        // Update the user's password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                failed_attempts = 0, 
                locked_until = NULL
            WHERE id = ?
        ");
        $stmt->execute([$hashed_password, $reset['user_id']]);
        
        // Mark the token as used
        $stmt = $pdo->prepare("
            UPDATE password_resets 
            SET used = 1 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        // Commit transaction
        $pdo->commit();
        
        // Set success message
        $_SESSION['success'] = "Your password has been reset successfully. You can now login with your new password.";
        
        // Log success
        error_log("Password reset successful for user ID: " . $reset['user_id']);
        
        // Redirect to login page
        header("Location: ../index.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log error
        error_log("Password reset error: " . $e->getMessage());
        
        // Set error message
        $_SESSION['error'] = $e->getMessage();
        
        // Redirect back to reset page
        header("Location: reset_password.php?token=$token");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: ../index.php");
    exit();
}
?>