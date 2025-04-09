<?php
require_once '../config/db_connect.php';

$pdo = getPDO();
$pdo->beginTransaction();

try {
    // Get all waste records marked for donation
    $wasteStmt = $pdo->prepare("
        SELECT 
            id, product_id, branch_id, waste_quantity, auto_approval, donation_priority, 
            (SELECT expiry_date FROM product_stock WHERE id = stock_id) as expiry_date
        FROM product_waste
        WHERE disposal_method = 'donation' AND archived = 0
    ");
    $wasteStmt->execute();
    $wasteRecords = $wasteStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare insert statement
    $insertStmt = $pdo->prepare("
        INSERT INTO donation_products
        (product_id, waste_id, branch_id, quantity_available, expiry_date, 
         auto_approval, donation_priority, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
    ");
    
    // Insert records
    $count = 0;
    foreach ($wasteRecords as $record) {
        // Skip if no quantity
        if ($record['waste_quantity'] <= 0) continue;
        
        // Use actual expiry date or default to 7 days from now
        $expiryDate = $record['expiry_date'] ?: date('Y-m-d', strtotime('+7 days'));
        
        $insertStmt->execute([
            $record['product_id'],
            $record['id'],
            $record['branch_id'],
            $record['waste_quantity'],
            $expiryDate,
            $record['auto_approval'] ?: 0,
            $record['donation_priority'] ?: 'normal'
        ]);
        $count++;
    }
    
    $pdo->commit();
    echo "Migration completed. Migrated $count donation records.";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage();
}