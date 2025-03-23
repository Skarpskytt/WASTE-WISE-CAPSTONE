<?php
// Start with standard PHP session
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../config/app_config.php';
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../includes/mail/EmailService.php';

use CustomSession\SessionHandler;
use function CustomSession\initSession;
use App\Mail\EmailService;

// Add explicit debugging
error_log("Login attempt started");

// Create database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=wastewise', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed");
}

// Initialize session with our custom handler
initSession($pdo);

// Get session handler instance
$session = SessionHandler::getInstance($pdo);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Get user
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   np.status as ngo_status,
                   sp.status as staff_status,
                   u.failed_attempts,
                   u.locked_until
            FROM users u
            LEFT JOIN ngo_profiles np ON u.id = np.user_id
            LEFT JOIN staff_profiles sp ON u.id = sp.user_id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // If user not found
        if (!$user) {
            error_log("User not found: $email");
            $_SESSION['error'] = 'No account found with that email.';
            header('Location: ../index.php');
            exit();
        }

        // Check if account is locked
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $unlock_time = date('F j, Y, g:i a', strtotime($user['locked_until']));
            $timeRemaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            $lockMessage = "Your account is locked due to multiple failed login attempts. Try again in $timeRemaining minutes.";
            
            error_log("Account locked: $email until " . $user['locked_until']);
            
            // Set the specific error session variable
            $_SESSION['error'] = $lockMessage;
            
            // Force redirect without checking password
            header('Location: ../index.php');
            exit();
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Increment failed attempts
            $newAttempts = ($user['failed_attempts'] ?? 0) + 1;
            
            // Check if we should lock the account (after 5 attempts)
            if ($newAttempts >= 5) {
                // Lock for 30 minutes
                $lockedUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                $stmt->execute([$newAttempts, $lockedUntil, $user['id']]);
                
                throw new Exception('Your account has been locked due to multiple failed attempts. Try again after 30 minutes.');
            } else {
                // Just update the attempts counter
                $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                $stmt->execute([$newAttempts, $user['id']]);
                
                $attemptsLeft = 5 - $newAttempts;
                $_SESSION['attempts_left'] = $attemptsLeft;
                // CHANGE THIS LINE - make sure it explicitly mentions attempts
                $_SESSION['login_error'] = "Wrong password.";

                // Add debugging
                error_log("Setting login error: " . $_SESSION['login_error']);
                error_log("Attempts left: " . $_SESSION['attempts_left']);

                // Redirect back to login page
                header('Location: ../index.php');
                exit();
            }
        }

        // If login successful, reset failed attempts
        $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Check NGO status
        if ($user['role'] === 'ngo') {
            $ngoStatusStmt = $pdo->prepare('SELECT status FROM ngo_profiles WHERE user_id = ?');
            $ngoStatusStmt->execute([$user['id']]);
            $ngoStatus = $ngoStatusStmt->fetchColumn();
            
            if (!$user['is_active'] && $ngoStatus === 'pending') {
                $_SESSION['pending_message'] = "Your NGO account is currently under review. You will be notified via email once approved.";
                header('Location: ../index.php');
                exit();
            } elseif (!$user['is_active'] && $ngoStatus === 'rejected') {
                $_SESSION['error'] = "Your NGO account application has been rejected.";
                header('Location: ../index.php');
                exit();
            }
        }

        // Check staff status
        if ($user['role'] === 'branch1_staff' || $user['role'] === 'branch2_staff') {
            $staffStatusStmt = $pdo->prepare('SELECT status FROM staff_profiles WHERE user_id = ?');
            $staffStatusStmt->execute([$user['id']]);
            $staffStatus = $staffStatusStmt->fetchColumn();
            
            if (!$user['is_active'] && $staffStatus === 'pending') {
                $_SESSION['pending_message'] = "Your staff account is currently under review. You will be notified via email once approved.";
                header('Location: ../index.php');
                exit();
            } elseif (!$user['is_active'] && $staffStatus === 'rejected') {
                $_SESSION['error'] = "Your staff account application has been rejected. Contact administration for details.";
                header('Location: ../index.php');
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
        $emailService->sendOTPEmail([
            'email' => $user['email'],
            'fname' => $user['fname'],
            'otp' => $otp
        ]);

        // Log the session for debugging
        error_log("SESSION after OTP generation: " . print_r($_SESSION, true));

        header('Location: verify_otp.php');
        exit();

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = $e->getMessage();
        
        // Improve this condition to better detect password-related errors
        if (strpos($error, 'Wrong password') !== false || 
            strpos($error, 'Invalid email') !== false || 
            strpos($error, 'attempts remaining') !== false) {
            $_SESSION['login_error'] = $error; // Use login_error for password-related issues
            error_log("Setting login_error: " . $error);
        } else {
            $_SESSION['error'] = $error; // Use regular error for other issues
            error_log("Setting error: " . $error);
        }
        
        // When redirecting back to index
        header('Location: ../index.php');
        exit();
    }
}

// When redirecting back to index
header('Location: ../index.php');
exit();
?>