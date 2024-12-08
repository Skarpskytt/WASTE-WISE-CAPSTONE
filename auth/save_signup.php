<?php
// save_signup.php
session_start();
include('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize input data
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $conpassword = $_POST['conpassword'];
    $terms = isset($_POST['terms']) ? $_POST['terms'] : '';

    // Validate inputs
    if (empty($fname) || empty($lname) || empty($email) || empty($password) || empty($conpassword)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: signup.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format.';
        header('Location: signup.php');
        exit();
    }

    if ($password !== $conpassword) {
        $_SESSION['error'] = 'Passwords do not match.';
        header('Location: signup.php');
        exit();
    }

    if (!$terms) {
        $_SESSION['error'] = 'You must accept the Terms and Conditions.';
        header('Location: signup.php');
        exit();
    }

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email is already registered.';
        header('Location: signup.php');
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into the database with role 'staff'
    $stmt = $pdo->prepare('INSERT INTO users (fname, lname, email, password, role) VALUES (?, ?, ?, ?, ?)');
    if ($stmt->execute([$fname, $lname, $email, $hashed_password, 'staff'])) {
        $_SESSION['success'] = 'Registration successful. Please log in.';
        header('Location: login.php');
        exit();
    } else {
        $_SESSION['error'] = 'Registration failed. Please try again.';
        header('Location: signup.php');
        exit();
    }
} else {
    header('Location: signup.php');
    exit();
}
?>