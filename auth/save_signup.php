<?php
session_start();
require_once('../config/db_connect.php');
include('../config/session_handler.php');
use CustomSession\SessionHandler;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // Validate role
        $allowed_roles = ['branch1_staff', 'branch2_staff', 'ngo'];
        if (!in_array($_POST['role'], $allowed_roles)) {
            throw new Exception('Invalid role selected.');
        }

        // Retrieve and sanitize input data
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $conpassword = $_POST['conpassword'];
        $role = $_POST['role'];
        $terms = isset($_POST['terms']) ? $_POST['terms'] : '';

        // Basic validation
        if (empty($fname) || empty($lname) || empty($email) || empty($password) || empty($role)) {
            throw new Exception('All fields are required.');
        }

        // Password validation
        if ($password !== $conpassword) {
            throw new Exception('Passwords do not match.');
        }

        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already registered.');
        }

        // Determine branch_id based on role
        $branch_id = null;
        if ($role === 'branch1_staff') {
            $branch_id = 1;
        } elseif ($role === 'branch2_staff') {
            $branch_id = 2;
        }

        // Insert user
        $stmt = $pdo->prepare('INSERT INTO users (fname, lname, email, password, role, branch_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $fname,
            $lname,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $branch_id,
            $role === 'ngo' ? 0 : 1 // NGOs need approval
        ]);

        $user_id = $pdo->lastInsertId();

        // Handle NGO registration
        if ($role === 'ngo') {
            $stmt = $pdo->prepare('INSERT INTO ngo_profiles (user_id, organization_name, phone, address, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $user_id,
                $_POST['org_name'],
                $_POST['phone'],
                $_POST['address'],
                'pending'
            ]);
        }

        $pdo->commit();
        $_SESSION['success'] = $role === 'ngo' ? 
            'Registration successful. Please wait for admin approval.' : 
            'Registration successful. Please log in.';
        header('Location: signup.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header('Location: signup.php');
        exit();
    }
} else {
    header('Location: signup.php');
    exit();
}
?>