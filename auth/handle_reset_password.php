<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Validate passwords match
        if ($password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match.";
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            $_SESSION['error'] = "Password must be at least 8 characters long.";
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
        
        // Check if token is valid and not expired
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM password_resets 
            WHERE token = ? AND expiry_date > ? AND used = 0
        ");
        $stmt->execute([$token, $now]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            $_SESSION['error'] = "This password reset link has expired or is invalid.";
            header('Location: ../index.php');
            exit();
        }
        
        // Update password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $reset['user_id']]);
        
        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
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

// If we get here, redirect to login
header('Location: ../index.php');
exit();