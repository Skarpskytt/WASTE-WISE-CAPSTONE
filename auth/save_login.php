<?php
session_start();
include('../config/db_connect.php');
include('../config/session_handler.php');
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
                header('Location: login.php');
                exit();
            } elseif (!$user['is_active'] && $ngoStatus === 'rejected') {
                $_SESSION['error'] = "Your NGO account application has been rejected.";
                header('Location: login.php');
                exit();
            }
        }

        if (!$user['is_active']) {
            throw new Exception('Your account is inactive. Please contact administrator.');
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['branch_id'] = $user['branch_id'];
        $_SESSION['branch_name'] = $user['branch_name'];

        // Redirect based on role
        switch($user['role']) {
            case 'admin':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/admin/admindashboard.php');
                break;
            case 'branch1_staff': // Individual case for branch1_staff
            case 'branch2_staff': 
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/staff/staff_dashboard.php');
                break;
            case 'ngo':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/ngo/dashboard.php');
                break;
            default:
                throw new Exception('Invalid role configuration.');
        }
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        if (strpos($error, 'Wrong password') !== false || 
            strpos($error, 'Invalid email') !== false) {
            $_SESSION['login_error'] = $error; // Use different session variable for login errors
        } else {
            $_SESSION['error'] = $error;
        }
        header('Location: login.php');
        exit();
    }
}

header('Location: login.php');
exit();
?>