<?php
require_once 'app_config.php';

function checkAuth($allowedRoles = []) {
    // Use standard sessions
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Debug auth check
    error_log("Auth check - SESSION: " . print_r($_SESSION, true));
    error_log("Checking for roles: " . implode(", ", $allowedRoles));
    
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please log in to access this page.';
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
    
    // If specific roles are required
    if (!empty($allowedRoles)) {
        $userHasAccess = false;
        
        foreach ($allowedRoles as $role) {
            if ($_SESSION['role'] === $role || 
                ($role === 'staff' && ($_SESSION['role'] === 'branch1_staff' || $_SESSION['role'] === 'branch2_staff'))) {
                $userHasAccess = true;
                break;
            }
        }
        
        if (!$userHasAccess) {
            $_SESSION['error'] = 'You do not have permission to access this page.';
            header('Location: ' . BASE_URL . '/pages/unauthorized.php');
            exit();
        }
    }

    // For NGO users, check if their account is approved
    if ($_SESSION['role'] === 'ngo') {
        // Only check status if it's set in the session
        if (isset($_SESSION['status']) && $_SESSION['status'] !== 'approved') {
            $_SESSION['error'] = 'Your NGO account is pending approval.';
            header('Location: ' . BASE_URL . '/pages/unauthorized.php');
            exit();
        }
    }
    
    return true;
}
?>