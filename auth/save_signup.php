<?php
session_start();
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

        // Add this password validation:
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }

        // Check for complexity
        if (!preg_match('/[A-Z]/', $password) || 
            !preg_match('/[a-z]/', $password) || 
            !preg_match('/[0-9]/', $password) || 
            !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new Exception('Password must include uppercase, lowercase, number, and special character.');
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

        // Update the section that processes role and branch_id:

        // Validate role - allow all branch staff roles and ngo
        $role = $_POST['role'];
        if ($role === 'ngo') {
            $branch_id = null;
        } else if (preg_match('/^branch(\d+)_staff$/', $role, $matches)) {
            $branch_id = (int)$matches[1];
            $role = 'staff'; // Standardize the role to just 'staff'
        } else {
            throw new Exception('Invalid role selected.');
        }

        // Use $branch_id and $role in your database insertion

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

        if ($role === 'staff') {
            // Create staff profile with pending status
            $stmt = $pdo->prepare("INSERT INTO staff_profiles (user_id, status) VALUES (?, 'pending')");
            $stmt->execute([$user_id]);
            
            // Set both session variables for index.php
            $_SESSION['success'] = "Your staff account has been created successfully.";
            $_SESSION['pending_approval'] = "Your staff account is pending approval from an administrator.";
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
            
            // Set both session variables for index.php
            $_SESSION['success'] = "Your NGO account has been created successfully.";
            $_SESSION['pending_approval'] = "Your NGO account is pending approval from an administrator.";
        } else {
            $_SESSION['success'] = "Account created successfully. Please login.";
        }

        // File upload handling
        $uploads_dir = '../uploads/verification';

        // Create directory if it doesn't exist
        if (!file_exists($uploads_dir)) {
            mkdir($uploads_dir, 0777, true);
        }

        // Handle Government ID upload
        if (isset($_FILES['gov_id']) && $_FILES['gov_id']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['gov_id']['tmp_name'];
            $gov_id_name = uniqid('gov_id_'.$user_id.'_') . '_' . basename($_FILES['gov_id']['name']);
            $gov_id_path = $uploads_dir . '/' . $gov_id_name;
            
            if (move_uploaded_file($tmp_name, $gov_id_path)) {
                // Save file path to database
                $stmt = $pdo->prepare("UPDATE users SET gov_id_path = ? WHERE id = ?");
                $stmt->execute([$gov_id_path, $user_id]);
            } else {
                // Handle upload error
                throw new Exception("Failed to upload government ID.");
            }
        }

        // Handle Selfie upload
        if (isset($_POST['selfie_data']) && !empty($_POST['selfie_data'])) {
            // Get the base64 part of the image data
            $image_parts = explode(";base64,", $_POST['selfie_data']);
            $image_base64 = isset($image_parts[1]) ? $image_parts[1] : $_POST['selfie_data'];
            
            // Generate filename and path
            $selfie_name = uniqid('selfie_'.$user_id.'_') . '.jpg';
            $selfie_path = $uploads_dir . '/' . $selfie_name;
            
            // Save the file
            $result = file_put_contents($selfie_path, base64_decode($image_base64));
            if ($result) {
                // Save file path to database
                $stmt = $pdo->prepare("UPDATE users SET selfie_path = ? WHERE id = ?");
                $stmt->execute([$selfie_path, $user_id]);
            } else {
                // Handle save error
                throw new Exception("Failed to save selfie image.");
            }
        }

        // Update success message
        $pdo->commit();
        
        // Redirect to index.php (main login page) instead of signup.php
        header('Location: ../index.php');
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