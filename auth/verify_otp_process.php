<?php
require_once '../config/app_config.php';
require_once '../config/db_connect.php';

// Start session after config
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp'])) {
    try {
        $pdo = getPDO();
        $user_id = $_SESSION['temp_user_id'] ?? null;
        $otp = $_POST['otp'];

        if (!$user_id) {
            throw new Exception('Session expired. Please login again.');
        }

        // Verify OTP and user
        $stmt = $pdo->prepare("
            SELECT o.*, u.*, b.id as branch_id, b.name as branch_name 
            FROM otp_codes o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE o.user_id = ? AND o.code = ? AND o.is_used = 0 
            AND o.expires_at > NOW()
            ORDER BY o.created_at DESC LIMIT 1
        ");
        $stmt->execute([$user_id, $otp]);
        $result = $stmt->fetch();

        if (!$result) {
            throw new Exception('Invalid or expired OTP.');
        }

        // Mark OTP as used
        $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
        $stmt->execute([$result['id']]);

        // Set session variables
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['email'] = $result['email'];
        $_SESSION['fname'] = $result['fname'];
        $_SESSION['lname'] = $result['lname'];
        $_SESSION['role'] = $result['role'];
        $_SESSION['branch_id'] = $result['branch_id'];
        $_SESSION['branch_name'] = $result['branch_name'];

        // Debug log
        error_log("Session variables set successfully: " . print_r($_SESSION, true));
        error_log("User role: " . $result['role']);
        error_log("Branch ID: " . $result['branch_id']);

        // Redirect based on role
        switch($result['role']) {
            case 'admin':
                header('Location: ../pages/admin/admindashboard.php');
                break;
            case 'staff':
            case 'company':
                header('Location: ../pages/staff/staff_dashboard.php');
                break;
            case 'ngo':
                header('Location: ../pages/ngo/ngo_dashboard.php');
                break;
            default:
                header('Location: ../index.php');
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
?>