<?php
// Use standard PHP session
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/app_config.php';
require_once '../config/db_connect.php';

// Get database connection
$pdo = getPDO();

// Debug session data
error_log("verify_otp_process.php - Session ID: " . session_id() . ", temp_user_id: " . ($_SESSION['temp_user_id'] ?? 'not set'));

// Check if temp_user_id exists
if (!isset($_SESSION['temp_user_id'])) {
    $_SESSION['error'] = "Your session has expired. Please log in again.";
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
        
        // Log OTP verification result
        error_log("OTP verification result: " . ($otp_record ? 'Valid OTP found' : 'No valid OTP found'));
        
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
        $_SESSION['authenticated'] = true; // Add this line
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'];
        $_SESSION['email'] = $user['email']; // Add email
        $_SESSION['role'] = $user['role'];
        $_SESSION['branch_id'] = $user['branch_id'] ?? null;
        $_SESSION['organization_name'] = $user['organization_name'] ?? null; // Add organization name
        
        // Clear temp data
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_email']);
        
        // Log session after user data is set
        error_log("SESSION after user login: " . print_r($_SESSION, true));
        error_log("Redirecting user with role: " . $user['role']);
        
        // Add session checking
        if (session_status() !== PHP_SESSION_ACTIVE) {
            error_log("ERROR: Session became inactive during OTP verification!");
            session_start(); // Try to restart the session
        }
        
        // Ensure session is written before redirect
        session_write_close();
        
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
                header('Location: ../index.php');
                break;
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