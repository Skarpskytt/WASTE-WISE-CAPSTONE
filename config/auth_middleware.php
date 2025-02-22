<?php
function checkAuth($allowed_roles = []) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please log in to access this page.';
        header('Location: /auth/login.php');
        exit();
    }

    // Check if user's role is allowed
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        header('Location: /pages/unauthorized.php');
        exit();
    }

    // For branch staff, check if they're accessing their assigned branch
    if (strpos($_SESSION['role'], 'branch') !== false) {
        $current_branch = $_SESSION['branch_id'];
        $requested_branch = substr($_SESSION['role'], 6, 1); // Gets '1' or '2' from 'branch1' or 'branch2'
        
        if ($current_branch != $requested_branch) {
            $_SESSION['error'] = 'You can only access your assigned branch.';
            header('Location: /pages/unauthorized.php');
            exit();
        }
    }

    // For NGO users, check if their account is approved
    if ($_SESSION['role'] === 'ngo') {
        if ($_SESSION['status'] !== 'approved') {
            $_SESSION['error'] = 'Your NGO account is pending approval.';
            header('Location: /pages/unauthorized.php');
            exit();
        }
    }
}