<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        error_log("Processing reset password for token: " . $token);
       
        if ($password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match.";
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
        
        if (strlen($password) < 8) {
            $_SESSION['error'] = "Password must be at least 8 characters long.";
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
        
        $now = date('Y-m-d H:i:s');
        error_log("Checking token validity at: " . $now);
        
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM password_resets 
            WHERE token = ? AND expiry_date > ? AND used = 0
        ");
        $stmt->execute([$token, $now]);
        $reset = $stmt->fetch();
        
        error_log("Token check result: " . ($reset ? "Token valid" : "Token invalid"));
        
        if (!$reset) {
            $_SESSION['error'] = "This password reset link has expired or is invalid.";
            header('Location: ../index.php');
            exit();
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $reset['user_id']]);
        
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        error_log("Password reset successful for user ID: " . $reset['user_id']);
        
        $_SESSION['success'] = "Your password has been reset successfully. You can now login with your new password.";
        header('Location: ../index.php');
        exit();
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while resetting your password. Please try again.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }
}

header('Location: ../index.php');
exit();