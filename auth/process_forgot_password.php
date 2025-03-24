<?php
session_start();
// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../config/app_config.php';
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../includes/mail/EmailService.php';  // Add this line to include the EmailService class

use CustomSession\SessionHandler;
use function CustomSession\initSession;
use App\Mail\EmailService;  // Add this line to use the correct namespace

// Get database connection
$pdo = getPDO();

// Initialize session with our custom handler
initSession($pdo);

// Get session handler instance
$session = SessionHandler::getInstance($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $_SESSION['error'] = "Please enter your email address.";
        header('Location: forgot_password.php');
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header('Location: forgot_password.php');
        exit();
    }
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, fname FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal that the user doesn't exist
            $_SESSION['success'] = "If your email is registered, you will receive a password reset link.";
            header('Location: forgot_password.php');
            exit();
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (user_id, token, expiry_date, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $token, $expiry]);
        
        // Generate reset link with BASE_URL
        $resetLink = BASE_URL . "/auth/reset_password.php?token=" . $token;
        
        // Send email
        $emailService = new EmailService();
        $emailData = [
            'name' => $user['fname'],
            'email' => $email,
            'resetLink' => $resetLink
        ];
        
        if ($emailService->sendPasswordResetEmail($emailData)) {
            $_SESSION['success'] = "A password reset link has been sent to your email address.";
            header('Location: forgot_password.php');
            exit();
        } else {
            throw new Exception("Failed to send email.");
        }
        
    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again later.";
        header('Location: forgot_password.php');
        exit();
    }
    
} else {
    header('Location: forgot_password.php');
    exit();
}
?>