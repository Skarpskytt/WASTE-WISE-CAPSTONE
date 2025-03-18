<?php
session_start();
include('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['temp_user_id'])) {
    try {
        $user_id = $_SESSION['temp_user_id'];
        $otp = $_POST['otp'];
        $current_time = date('Y-m-d H:i:s');

        // Verify OTP
        $stmt = $pdo->prepare("
            SELECT * FROM otp_codes 
            WHERE user_id = ? 
            AND code = ? 
            AND is_used = 0 
            AND expires_at > ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $otp, $current_time]);
        $otp_record = $stmt->fetch();

        if (!$otp_record) {
            throw new Exception('Invalid or expired OTP code.');
        }

        // Mark OTP as used
        $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
        $stmt->execute([$otp_record['id']]);

        // Get user data and set session
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['branch_id'] = $user['branch_id'];

        unset($_SESSION['temp_user_id']);

        // Redirect based on role
        switch($user['role']) {
            case 'admin':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/admin/admindashboard.php');
                break;
            case 'branch1_staff':
            case 'branch2_staff':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/staff/staff_dashboard.php');
                break;
            case 'ngo':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/ngo/ngo_dashboard.php');
                break;
            default:
                throw new Exception('Invalid role configuration.');
        }
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: verify_otp.php');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}