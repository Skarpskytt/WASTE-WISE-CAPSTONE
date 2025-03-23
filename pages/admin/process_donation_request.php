<?php
// filepath: c:\xampp\htdocs\capstone\WASTE-WISE-CAPSTONE\pages\admin\process_donation_request.php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access
checkAuth(['admin']);

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $branchId = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate inputs
    if (!$productId || !$branchId || $quantity <= 0) {
        $_SESSION['error'] = "Invalid input data";
        header("Location: product_stocks_branch{$branchId}.php");
        exit;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check product availability
        $checkStmt = $pdo->prepare("
            SELECT stock_quantity, name FROM products 
            WHERE id = ? AND branch_id = ? AND expiry_date > CURRENT_DATE
        ");
        $checkStmt->execute([$productId, $branchId]);
        $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product || $product['stock_quantity'] < $quantity) {
            throw new Exception("Product not available in sufficient quantity");
        }
        
        // Create donation request
        $insertStmt = $pdo->prepare("
            INSERT INTO donation_requests 
            (product_id, staff_id, branch_id, requested_by, quantity, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $insertStmt->execute([
            $productId,
            $_SESSION['user_id'],
            $branchId,
            $_SESSION['user_id'],
            $quantity,
            $notes
        ]);
        
        // REMOVED: No longer deduct stock at this stage
        // Stock will be deducted when staff marks donation as prepared
        
        // Create notifications for staff
        $staffNotifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (target_role, message, notification_type, link, is_read) 
            VALUES ('staff', ?, 'donation_request', '/capstone/WASTE-WISE-CAPSTONE/pages/staff/donation_request.php', 0)
        ");
        
        $message = "New donation request for {$product['name']} ({$quantity} units)";
        $staffNotifStmt->execute([$message]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Donation request submitted successfully!";
        header("Location: product_stocks_branch{$branchId}.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: product_stocks_branch{$branchId}.php");
        exit;
    }
} else {
    // Not a POST request
    header("Location: product_stocks_branch1.php");
    exit;
}