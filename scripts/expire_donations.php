<?php
require_once '../config/db_connect.php';

$pdo = getPDO();

try {
    // Mark expired donations
    $expireStmt = $pdo->prepare("
        UPDATE donation_products
        SET status = 'expired'
        WHERE expiry_date < CURRENT_DATE()
        AND status = 'available'
    ");
    
    $expireStmt->execute();
    $expiredCount = $expireStmt->rowCount();
    
    echo "Marked $expiredCount donations as expired.";
} catch (Exception $e) {
    echo "Failed to expire donations: " . $e->getMessage();
}