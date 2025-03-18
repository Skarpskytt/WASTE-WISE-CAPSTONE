<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include('../config/db_connect.php');
include('../config/session_handler.php');
require_once '../includes/mail/EmailService.php';
use CustomSession\SessionHandler;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // First check if user exists
        $stmt = $pdo->prepare('
            SELECT u.*, np.status as ngo_status 
            FROM users u 
            LEFT JOIN ngo_profiles np ON u.id = np.user_id 
            WHERE u.email = ?
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Check if user exists first
        if (!$user) {
            throw new Exception('Invalid email or password.');
        }

        // Check password separately
        if (!password_verify($password, $user['password'])) {
            throw new Exception('Wrong password. Please try again.');
        }

        // Check NGO status
        if ($user['role'] === 'ngo') {
            $ngoStatusStmt = $pdo->prepare('SELECT status FROM ngo_profiles WHERE user_id = ?');
            $ngoStatusStmt->execute([$user['id']]);
            $ngoStatus = $ngoStatusStmt->fetchColumn();
            
            if (!$user['is_active'] && $ngoStatus === 'pending') {
                $_SESSION['pending_message'] = "Your NGO account is currently under review. You will be notified via email once approved.";
                header('Location: index.php');
                exit();
            } elseif (!$user['is_active'] && $ngoStatus === 'rejected') {
                $_SESSION['error'] = "Your NGO account application has been rejected.";
                header('Location: index.php');
                exit();
            }
        }

        if (!$user['is_active']) {
            throw new Exception('Your account is inactive. Please contact administrator.');
        }

        // Generate OTP
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Save OTP to database
        $stmt = $pdo->prepare("
            INSERT INTO otp_codes (user_id, code, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user['id'], $otp, $expires_at]);

        // Store temporary session data
        $_SESSION['temp_user_id'] = $user['id'];
        
        // Send OTP via email using existing EmailService
        $emailService = new \App\Mail\EmailService();
        $emailService->sendOTPEmail($user, $otp);

        header('Location: verify_otp.php');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        if (strpos($error, 'Wrong password') !== false || 
            strpos($error, 'Invalid email') !== false) {
            $_SESSION['login_error'] = $error; // Use different session variable for login errors
        } else {
            $_SESSION['error'] = $error;
        }
        header('Location: index.php');
        exit();
    }
}

header('Location: index.php');
exit();
?>