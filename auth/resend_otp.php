<?php
session_start();
include('../config/db_connect.php');
require_once '../includes/mail/EmailService.php';

if (!isset($_SESSION['temp_user_id'])) {
    header('Location: ../index.php');
    exit();
}

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['temp_user_id']]);
    $user = $stmt->fetch();

    // Generate new OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Save new OTP
    $stmt = $pdo->prepare("
        INSERT INTO otp_codes (user_id, code, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $otp, $expires_at]);

    // Send new OTP
    $emailService = new EmailService();
    $emailService->sendOTPEmail($user, $otp);

    $_SESSION['success'] = 'New OTP has been sent to your email.';
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to resend OTP. Please try again.';
}

header('Location: verify_otp.php');
exit();