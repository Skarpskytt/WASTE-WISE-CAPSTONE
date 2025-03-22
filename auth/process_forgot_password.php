<?php
ob_start(); // Start output buffering

require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../includes/mail/EmailService.php';

use App\Mail\EmailService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, fname, lname, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token in database
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expiry_date) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $token, $expiry]);
            
            // Generate reset link with correct path
            $resetLink = "https://beabakes.site/auth/reset_password.php?token=" . $token;
            
            // Send email
            $emailService = new EmailService();
            $emailService->sendPasswordResetEmail($user, $resetLink);
            
            $_SESSION['success'] = "Password reset instructions have been sent to your email.";
        } else {
            // Don't reveal if email exists or not for security
            $_SESSION['success'] = "If your email exists in our system, you will receive password reset instructions.";
        }
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again later.";
    }
    
    header('Location: forgot_password.php');
    ob_end_flush(); // End output buffering and send output
    exit();
}