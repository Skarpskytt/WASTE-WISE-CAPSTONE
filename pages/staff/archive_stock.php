<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

checkAuth(['staff', 'company']);

$pdo = getPDO();

// Get the stock ID from the URL
$stockId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($stockId <= 0) {
    // Invalid ID, redirect back to stocks page
    header('Location: product_stocks.php');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get current stock information before archiving
    $stockInfoSql = "SELECT product_info_id, quantity FROM product_stock WHERE id = ?";
    $stockInfoStmt = $pdo->prepare($stockInfoSql);
    $stockInfoStmt->execute([$stockId]);
    $stockInfo = $stockInfoStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stockInfo) {
        throw new Exception("Stock item not found");
    }
    
    // Update both is_archived and archived_at columns
    // Set quantity to 0 when archiving to reflect that the stock is no longer available
    $archiveSql = "UPDATE product_stock SET is_archived = 1, archived_at = NOW(), quantity = 0 WHERE id = ?";
    $archiveStmt = $pdo->prepare($archiveSql);
    $result = $archiveStmt->execute([$stockId]);
    
    if (!$result) {
        throw new Exception("Failed to archive stock item");
    }
    
    // Record this archive operation in a log table if you have one
    // This is optional but recommended for audit purposes
    if (isset($stockInfo['quantity']) && $stockInfo['quantity'] > 0) {
        $logSql = "INSERT INTO inventory_log (product_info_id, stock_id, action_type, quantity, performed_by, notes, created_at) 
                  VALUES (?, ?, 'archive', ?, ?, ?, NOW())";
        $logStmt = $pdo->prepare($logSql);
        
        // Check if the inventory_log table exists and has the expected structure before executing
        try {
            $logStmt->execute([
                $stockInfo['product_info_id'],
                $stockId,
                $stockInfo['quantity'],
                $_SESSION['user_id'],
                'Stock archived and removed from inventory'
            ]);
        } catch (Exception $e) {
            // If the log table doesn't exist or has a different structure, just continue
            // This is not critical for the archive operation
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Stock item has been archived successfully and removed from inventory.";
    $_SESSION['flash_message_type'] = "success";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Set error message
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "error";
}

// Redirect back to products page
header('Location: product_stocks.php');
exit;
?>