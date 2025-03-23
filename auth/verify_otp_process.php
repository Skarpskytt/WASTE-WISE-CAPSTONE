<?php
// Use standard PHP session 
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/app_config.php';
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

use CustomSession\SessionHandler;
use function CustomSession\initSession;

// Get database connection
$pdo = getPDO();

// Initialize session with our custom handler
initSession($pdo);

// Get session handler instance
$session = SessionHandler::getInstance($pdo);

// Debug session data
error_log("SESSION in verify_otp_process.php: " . print_r($_SESSION, true));

// Check if temp_user_id exists
if (!isset($_SESSION['temp_user_id'])) {
    $_SESSION['error'] = "Session expired. Please login again.";
    header('Location: ../index.php');
    exit();
}

// Process the OTP
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get data
        $user_id = $_SESSION['temp_user_id'];
        $otp = $_POST['otp'];
        $current_time = date('Y-m-d H:i:s');
        
        // Log the verification attempt
        error_log("Verifying OTP: $otp for user ID: $user_id");
        
        // Verify OTP in database
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
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otp_record) {
            throw new Exception('Invalid or expired OTP code.');
        }
        
        // Mark OTP as used
        $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
        $stmt->execute([$otp_record['id']]);
        
        // Get user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found.');
        }
        
        // Set session variables for user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['branch_id'] = $user['branch_id'];
        
        // Clear temp data
        unset($_SESSION['temp_user_id']);
        
        // Log session after user data is set
        error_log("SESSION after user login: " . print_r($_SESSION, true));
        error_log("Redirecting user with role: " . $user['role']);
        
        // Redirect based on role
        switch($user['role']) {
            case 'admin':
                header('Location: ../pages/admin/admindashboard.php');
                break;
            case 'branch1_staff':
            case 'branch2_staff':
                header('Location: ../pages/staff/staff_dashboard.php');
                break;
            case 'ngo':
                header('Location: ../pages/ngo/ngo_dashboard.php');
                break;
            default:
                throw new Exception('Invalid role configuration.');
        }
        exit();
        
    } catch (Exception $e) {
        error_log("OTP verification error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: verify_otp.php');
        exit();
    }
} else {
    // If not POST request
    $_SESSION['error'] = "Invalid request method.";
    header('Location: verify_otp.php');
    exit();
}
?>