<?php
require_once '../config/db_connect.php';

// This script checks for products nearing expiry and sends notifications

function checkExpiringProducts() {
    $pdo = getPDO();
    
    // Define expiry thresholds
    $urgentThreshold = 2; // 2 days
    $warningThreshold = 7; // 7 days
    
    // Current date
    $currentDate = date('Y-m-d');
    
    // Get products expiring within threshold days, grouped by branch
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            name, 
            category, 
            expiry_date, 
            branch_id,
            stock_quantity,
            DATEDIFF(expiry_date, ?) AS days_until_expiry
        FROM 
            products
        WHERE 
            expiry_date > ? 
            AND expiry_date <= DATE_ADD(?, INTERVAL ? DAY)
            AND stock_quantity > 0
        ORDER BY
            branch_id, days_until_expiry
    ");
    
    $stmt->execute([$currentDate, $currentDate, $currentDate, $warningThreshold]);
    $expiringProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group products by branch
    $productsByBranch = [];
    foreach ($expiringProducts as $product) {
        $branchId = $product['branch_id'];
        if (!isset($productsByBranch[$branchId])) {
            $productsByBranch[$branchId] = [
                'urgent' => [],
                'warning' => []
            ];
        }
        
        // Categorize as urgent or warning
        if ($product['days_until_expiry'] <= $urgentThreshold) {
            $productsByBranch[$branchId]['urgent'][] = $product;
        } else {
            $productsByBranch[$branchId]['warning'][] = $product;
        }
    }
    
    // Create notifications for each branch
    foreach ($productsByBranch as $branchId => $categories) {
        // Handle urgent expiring products (within 2 days)
        if (!empty($categories['urgent'])) {
            $urgentCount = count($categories['urgent']);
            $firstProduct = $categories['urgent'][0];
            
            // Create notification
            $message = "URGENT: {$urgentCount} product(s) expiring within 48 hours. ";
            
            if ($urgentCount == 1) {
                $message .= "\"{$firstProduct['name']}\" expires in {$firstProduct['days_until_expiry']} day(s)!";
            } else {
                $message .= "Including \"{$firstProduct['name']}\" and others.";
            }
            
            // Urgent notifications use a path with leading slash
            createNotification($pdo, $message, "/pages/staff/product_stocks.php?show_expiring=1", $branchId, "product_expiry_urgent");
        }
        
        // Handle warning expiring products (within 7 days)
        if (!empty($categories['warning'])) {
            $warningCount = count($categories['warning']);
            
            // Create notification
            $message = "{$warningCount} product(s) expiring soon within 7 days. Please check inventory.";
            createNotification($pdo, $message, "/pages/staff/product_stocks.php?show_expiring=1", $branchId, "product_expiry_warning");
        }
    }
    
    return count($expiringProducts);
}

// Change this function to use notification_type instead of priority
// Change this function parameter name to match its purpose
function createNotification($pdo, $message, $link, $branchId, $notificationType = "normal") {
    // Check if a similar notification was already sent today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE message = ? 
        AND target_branch_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$message, $branchId]);
    
    if ($stmt->fetchColumn() > 0) {
        // Skip sending duplicate notifications on the same day
        return false;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (message, link, target_role, target_branch_id, notification_type, created_at) 
        VALUES (?, ?, 'staff', ?, ?, NOW())
    ");
    
    return $stmt->execute([$message, $link, $branchId, $notificationType]);
}

// Run the check
$count = checkExpiringProducts();
echo "Checked for expiring products. Found {$count} products nearing expiry.";