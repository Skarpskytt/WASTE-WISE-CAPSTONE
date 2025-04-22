<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

checkAuth(['staff', 'company']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];
$errors = [];

// Process archive request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if ($productId <= 0) {
        $errors[] = "Invalid product ID.";
    } else {
        try {
            // First, verify product belongs to this branch
            $verifyStmt = $pdo->prepare("
                SELECT name FROM product_info WHERE id = ? AND branch_id = ?
            ");
            $verifyStmt->execute([$productId, $branchId]);
            $product = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                $errors[] = "Product not found or you don't have permission to archive it.";
            } else {
                // Archive by updating status field
                // Note: In a real system, you might have a 'status' or 'is_archived' column
                // For this example, let's assume there is a status column in product_info
                // If your DB doesn't have this column, you'd need to add it first
                
                $archiveStmt = $pdo->prepare("
                    UPDATE product_info 
                    SET status = 'archived', updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                
                $archiveStmt->execute([$productId, $branchId]);
                
                // Success message
                $_SESSION['success_message'] = "Product '{$product['name']}' has been archived successfully.";
                
                // Redirect back to product listing
                header("Location: add_stock.php");
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Product archive error: " . $e->getMessage());
        }
    }
    
    // If we got here, there were errors
    $_SESSION['form_errors'] = $errors;
    header("Location: add_stock.php");
    exit;
}

// If not a POST request, redirect to product page
header("Location: add_stock.php");
exit;
?>