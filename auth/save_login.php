<?php
session_start();
include('../config/db_connect.php');
include('../config/session_handler.php');
use CustomSession\SessionHandler;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Fetch user with role and branch information
        $stmt = $pdo->prepare('
            SELECT u.*, b.name as branch_name, np.status as ngo_status 
            FROM users u 
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN ngo_profiles np ON u.id = np.user_id
            WHERE u.email = ?
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid email or password.');
        }

        if (!$user['is_active']) {
            if ($user['role'] === 'ngo' && $user['ngo_status'] === 'pending') {
                throw new Exception('Your NGO account is pending approval.');
            }
            throw new Exception('Your account is inactive.');
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
            case 'branch1_staff':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/staff/branch1/branch1_dashboard.php');
                break;
            case 'branch2_staff':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/staff/branch2/branch2_dashboard.php');
                break;
            case 'ngo':
                header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/ngo/dashboard.php');
                break;
            default:
                throw new Exception('Invalid role configuration.');
        }
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: login.php');
        exit();
    }
}

header('Location: login.php');
exit();
?>