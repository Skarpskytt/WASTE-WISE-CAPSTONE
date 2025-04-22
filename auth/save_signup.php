<?php
session_start();
// Include necessary files
require_once '../config/app_config.php';
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

use CustomSession\SessionHandler;
use function CustomSession\initSession;

// Add this near the top of your file to enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
        $allowed_roles = ['staff', 'company', 'ngo'];
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

        // Map branch staff roles to regular 'staff' role in the database
        if (preg_match('/^branch(\d+)_staff$/', $role, $matches)) {
            // For branch staff roles, extract branch ID from the role string
            $branch_id = (int)$matches[1];
            // Store original role in a session variable if needed for display purposes
            $_SESSION['original_role'] = $role;
            // Use 'staff' as the actual role to match the database enum
            $role = 'staff';
        } else if ($role === 'ngo') {
            $branch_id = null;
        } else if ($role === 'company' || $role === 'staff') {
            // Get the selected branch ID for company and staff accounts
            $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? 
                         (int)$_POST['branch_id'] : null;
                         
            // Validation for company and staff accounts
            if (empty($branch_id)) {
                throw new Exception('Please select a branch for your ' . $role . ' account.');
            }
        } else {
            throw new Exception('Invalid role selected.');
        }

        // Insert user with is_active = 0 for new accounts that need approval
        $is_active = 0; // Default to inactive for accounts that need approval

        // Insert user record - Make sure role is one of the allowed enum values
        $allowed_db_roles = ['admin', 'staff', 'company', 'ngo'];
        if (!in_array($role, $allowed_db_roles)) {
            throw new Exception('Invalid role value for database.');
        }

        // Insert user record
        $stmt = $pdo->prepare('INSERT INTO users (fname, lname, email, password, role, branch_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $fname,
            $lname,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $branch_id,
            $is_active
        ]);

        // Get the user ID once
        $user_id = $pdo->lastInsertId();

        // Handle role-specific profile creation
        if ($role === 'ngo') {
            // Handle NGO registration
            $stmt = $pdo->prepare('INSERT INTO ngo_profiles (user_id, organization_name, phone, address, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $user_id,
                $_POST['org_name'],
                $_POST['phone'],
                $_POST['address'],
                'pending'
            ]);
            
            $_SESSION['success'] = "Your NGO account has been created successfully.";
            $_SESSION['pending_approval'] = "Your NGO account is pending approval from an administrator.";
        } 
        else if (preg_match('/^branch(\d+)_staff$/', $role)) {
            // Create staff profile with pending status
            $stmt = $pdo->prepare("INSERT INTO staff_profiles (user_id, status) VALUES (?, 'pending')");
            $stmt->execute([$user_id]);
            
            $_SESSION['success'] = "Your staff account has been created successfully.";
            $_SESSION['pending_approval'] = "Your staff account is pending approval from an administrator.";
        }
        // Create staff profile with pending status for staff role
        else if ($role === 'staff') {
            // Create staff profile with pending status
            $stmt = $pdo->prepare("INSERT INTO staff_profiles (user_id, status) VALUES (?, 'pending')");
            $stmt->execute([$user_id]);
            
            $_SESSION['success'] = "Your staff account has been created successfully.";
            $_SESSION['pending_approval'] = "Your staff account is pending approval from an administrator.";
        }

        // File upload handling - ensure directory exists and is writable
        $govIdFrontPath = null;
        $govIdBackPath = null;
        $selfiePath = null;

        // Use relative paths for both file operations and DB storage
        $uploadDir = '../assets/uploads/verification/';
        $dbUploadPath = 'assets/uploads/verification/';


        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory. Please contact support.");
            }
        }

        error_log("Upload directory: $uploadDir");
        error_log("Directory exists: " . (is_dir($uploadDir) ? "Yes" : "No"));
        error_log("Directory writable: " . (is_writable($uploadDir) ? "Yes" : "No"));

        $errors = [];

        // Process front ID upload
        try {
            if (isset($_FILES['gov_id_front']) && $_FILES['gov_id_front']['error'] === UPLOAD_ERR_OK) {
                $fileInfo = pathinfo($_FILES['gov_id_front']['name']);
                $extension = strtolower($fileInfo['extension']);
                
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'pdf', 'heic'])) {
                    $uniqueId = uniqid();
                    $fileName = 'gov_id_front_' . $user_id . '_' . $uniqueId . '.' . $extension;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['gov_id_front']['tmp_name'], $targetPath)) {
                        // Store relative path in database
                        $govIdFrontPath = $dbUploadPath . $fileName;
                        error_log("Front ID uploaded to: $targetPath");
                        error_log("Front ID DB path: $govIdFrontPath");
                    } else {
                        $errors[] = "Failed to upload front ID image. Error: " . error_get_last()['message'];
                        error_log("Failed to upload front ID: " . error_get_last()['message']);
                    }
                } else {
                    $errors[] = "Front ID: Only JPG, PNG, HEIC or PDF files are allowed.";
                }
            } else if (isset($_FILES['gov_id_front'])) {
                $errors[] = "Front ID upload error code: " . $_FILES['gov_id_front']['error'];
                error_log("Front ID upload error code: " . $_FILES['gov_id_front']['error']);
            }
        } catch (Exception $e) {
            $errors[] = "Front ID upload error: " . $e->getMessage();
            error_log("Front ID exception: " . $e->getMessage());
        }

        // Process back ID upload
        try {
            if (isset($_FILES['gov_id_back']) && $_FILES['gov_id_back']['error'] === UPLOAD_ERR_OK) {
                $fileInfo = pathinfo($_FILES['gov_id_back']['name']);
                $extension = strtolower($fileInfo['extension']);
                
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'pdf', 'heic'])) {
                    $uniqueId = uniqid();
                    $fileName = 'gov_id_back_' . $user_id . '_' . $uniqueId . '.' . $extension;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['gov_id_back']['tmp_name'], $targetPath)) {
                        // Store relative path in database
                        $govIdBackPath = $dbUploadPath . $fileName;
                        error_log("Back ID uploaded to: $targetPath");
                        error_log("Back ID DB path: $govIdBackPath");
                    } else {
                        $errors[] = "Failed to upload back ID image. Error: " . error_get_last()['message'];
                        error_log("Failed to upload back ID: " . error_get_last()['message']);
                    }
                } else {
                    $errors[] = "Back ID: Only JPG, PNG, HEIC or PDF files are allowed.";
                }
            } else if (isset($_FILES['gov_id_back'])) {
                $errors[] = "Back ID upload error code: " . $_FILES['gov_id_back']['error'];
                error_log("Back ID upload error code: " . $_FILES['gov_id_back']['error']);
            }
        } catch (Exception $e) {
            $errors[] = "Back ID upload error: " . $e->getMessage();
            error_log("Back ID exception: " . $e->getMessage());
        }

        // Process selfie data (from canvas capture)
        try {
            if (!empty($_POST['selfie_data'])) {
                $img = $_POST['selfie_data'];
                $img = str_replace('data:image/jpeg;base64,', '', $img);
                $img = str_replace(' ', '+', $img);
                $data = base64_decode($img);
                
                $fileName = 'selfie_' . $user_id . '_' . uniqid() . '.jpg';
                $targetPath = $uploadDir . $fileName;
                
                if (file_put_contents($targetPath, $data)) {
                    // Store relative path in database
                    $selfiePath = $dbUploadPath . $fileName;
                    error_log("Selfie saved to: $targetPath");
                    error_log("Selfie DB path: $selfiePath");
                } else {
                    $errors[] = "Failed to save selfie image. Error: " . error_get_last()['message'];
                    error_log("Failed to save selfie: " . error_get_last()['message']);
                }
            } else {
                $errors[] = "Selfie data is empty";
                error_log("Selfie data is empty");
            }
        } catch (Exception $e) {
            $errors[] = "Selfie upload error: " . $e->getMessage();
            error_log("Selfie exception: " . $e->getMessage());
        }

        // Debug paths
        error_log("Paths before DB update: Front=$govIdFrontPath, Back=$govIdBackPath, Selfie=$selfiePath");
        
        // Update user with document paths
        $stmt = $pdo->prepare("
            UPDATE users 
            SET gov_id_front_path = ?, gov_id_back_path = ?, selfie_path = ?
            WHERE id = ?
        ");
        $result = $stmt->execute([$govIdFrontPath, $govIdBackPath, $selfiePath, $user_id]);
        
        if (!$result) {
            error_log("Database update error: " . implode(", ", $stmt->errorInfo()));
        }

        // Output any errors but still complete registration
        if (!empty($errors)) {
            error_log("Upload errors: " . implode('; ', $errors));
        }

        // Complete transaction
        $pdo->commit();
        
        // Redirect to index.php (main login page) instead of signup.php
        header('Location: ../index.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        error_log("Signup error: " . $e->getMessage());
        header('Location: signup.php');
        exit();
    }
} else {
    header('Location: signup.php');
    exit();
}
?>