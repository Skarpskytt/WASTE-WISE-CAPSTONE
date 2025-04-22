<?php
require_once __DIR__ . '/app_config.php';

function checkAuth($allowedRoles = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['authenticated']) || !isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please log in to access this page.';
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }

    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }

    return true;
}

// Fix: Make checkBranchAccess use 'role' instead of 'user_role'
function checkBranchAccess($requiredBranchId = null) {
    // Skip check for admin
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // For staff and company users, verify branch access
    if (in_array($_SESSION['role'], ['staff', 'company'])) {
        // If no specific branch is required, just ensure they have a branch_id
        if ($requiredBranchId === null) {
            return isset($_SESSION['branch_id']) && !empty($_SESSION['branch_id']);
        }
        
        // Check if user's branch_id matches required branch
        return $_SESSION['branch_id'] == $requiredBranchId;
    }
    
    // For other roles, no branch check needed
    return true;
}
?>