<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../vendor/autoload.php';
require_once '../config/app_config.php';

use App\Mail\EmailService;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['temp_user_id'])) {
    header('Location: ../index.php');
    exit();
}

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['temp_user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Generate new OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Save new OTP
    $stmt = $pdo->prepare("
        INSERT INTO otp_codes (user_id, code, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $otp, $expires_at]);

    // Debug logging
    error_log("Generated OTP: " . $otp . " for user: " . $user['email']);

    // Send email with OTP
    $emailService = new EmailService();
    $emailService->sendOTPEmail([
        'email' => $user['email'],
        'fname' => $user['fname'],
        'otp' => $otp
    ]);

    $_SESSION['success'] = 'New OTP has been sent to your email.';
} catch (Exception $e) {
    error_log("OTP Resend Error: " . $e->getMessage());
    $_SESSION['error'] = 'Failed to resend OTP. Please try again.';
}

header('Location: verify_otp.php');
exit();