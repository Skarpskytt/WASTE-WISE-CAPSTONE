<?php
// save_login.php
session_start();
include('../config/config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize input data
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Validate inputs
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Email and Password are required.';
        header('Location: login.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format.';
        header('Location: login.php');
        exit();
    }

    // Fetch user from the database
    $stmt = $pdo->prepare('SELECT id, fname, lname, password, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'];
        $_SESSION['role'] = $user['role'];

        // Optionally, implement "Remember Me" functionality using cookies
        if ($remember) {
            // Set cookies for 30 days
            setcookie('user_id', $user['id'], time() + (30 * 24 * 60 * 60), "/");
            setcookie('role', $user['role'], time() + (30 * 24 * 60 * 60), "/");
        }

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header('Location: ../pages/admin/admindashboard.php');
        } else {
            header('Location: ../pages/staff/userdashboard.php');
        }
        exit();
    } else {
        $_SESSION['error'] = 'Invalid email or password.';
        header('Location: login.php');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>