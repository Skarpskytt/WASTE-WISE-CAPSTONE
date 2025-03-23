<?php
// Include necessary files
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

        // Insert user - set is_active to 0 for all newly created staff accounts
        // This is key - all staff accounts start as inactive
        $stmt = $pdo->prepare('INSERT INTO users (fname, lname, email, password, role, branch_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 0)');
        $stmt->execute([
            $fname,
            $lname,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $branch_id
        ]);

        // After inserting the user
        $user_id = $pdo->lastInsertId();

        if ($role === 'branch1_staff' || $role === 'branch2_staff') {
            // Create staff profile with pending status
            $stmt = $pdo->prepare("INSERT INTO staff_profiles (user_id, status) VALUES (?, 'pending')");
            $stmt->execute([$user_id]);
            
            // Set success message about pending approval
            $_SESSION['success'] = "Your staff account has been created successfully. Please wait for approval from an administrator.";
        } elseif ($role === 'ngo') {
            // Handle NGO registration
            $stmt = $pdo->prepare('INSERT INTO ngo_profiles (user_id, organization_name, phone, address, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $user_id,
                $_POST['org_name'],
                $_POST['phone'],
                $_POST['address'],
                'pending'
            ]);
        } else {
            $_SESSION['success'] = "Account created successfully. Please login.";
        }

        // Update success message
        $pdo->commit();
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