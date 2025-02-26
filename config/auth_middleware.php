<?php
function checkAuth($allowed_roles = []) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please log in to access this page.';
        // Fix typo in path: capstozne -> capstone
        header('Location: /capstone/WASTE-WISE-CAPSTONE/auth/login.php');
        exit();
    }

    // Check if user's role is allowed
    if (!empty($allowed_roles)) {
        // Modified to handle staff roles together
        $userHasAccess = false;
        
        foreach ($allowed_roles as $role) {
            // Special case for 'staff' to allow both branch1_staff and branch2_staff
            if ($role === 'staff' && (($_SESSION['role'] === 'branch1_staff') || ($_SESSION['role'] === 'branch2_staff'))) {
                $userHasAccess = true;
                break;
            }
            
            // Direct role match
            if ($_SESSION['role'] === $role) {
                $userHasAccess = true;
                break;
            }
        }
        
        if (!$userHasAccess) {
            $_SESSION['error'] = 'You do not have permission to access this page.';
            header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/unauthorized.php');
            exit();
        }
    }

    // For NGO users, check if their account is approved
    if ($_SESSION['role'] === 'ngo') {
        // Only check status if it's set in the session
        if (isset($_SESSION['status']) && $_SESSION['status'] !== 'approved') {
            $_SESSION['error'] = 'Your NGO account is pending approval.';
            header('Location: /capstone/WASTE-WISE-CAPSTONE/pages/unauthorized.php');
            exit();
        }
    }
}