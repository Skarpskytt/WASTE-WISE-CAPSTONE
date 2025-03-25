<?php
require_once '../config/db_connect.php';

// Set up logging
$logFile = __DIR__ . '/expire_log.txt';
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("Starting expired donations check");

try {
    $pdo = getPDO();
    $currentDate = date('Y-m-d');
    
    // 1. Auto-cancel expired donation items that haven't been approved
    $expiredItemsStmt = $pdo->prepare("
        UPDATE product_waste pw
        JOIN products p ON pw.product_id = p.id
        SET pw.donation_status = 'cancelled', 
            pw.admin_notes = CONCAT(IFNULL(pw.admin_notes, ''), '\n[System] Automatically cancelled due to expiration on ', ?)
        WHERE pw.disposal_method = 'donation'
        AND pw.donation_status IN ('pending', 'in-progress')
        AND p.expiry_date < ?
        AND NOT EXISTS (
            SELECT 1 FROM ngo_donation_requests ndr 
            WHERE ndr.donation_request_id = pw.id AND ndr.status = 'approved'
        )
    ");
    
    $expiredItemsStmt->execute([$currentDate, $currentDate]);
    $changedRows = $expiredItemsStmt->rowCount();
    
    logMessage("Successfully cancelled $changedRows expired donation items");
    
    // 2. Auto-reject pending NGO requests for expired products
    $rejectRequestsStmt = $pdo->prepare("
        UPDATE ngo_donation_requests ndr
        JOIN products p ON ndr.product_id = p.id
        SET ndr.status = 'rejected',
            ndr.admin_notes = CONCAT(IFNULL(ndr.admin_notes, ''), '\n[System] Automatically rejected due to product expiration on ', ?)
        WHERE ndr.status = 'pending'
        AND p.expiry_date < ?
    ");
    
    $rejectRequestsStmt->execute([$currentDate, $currentDate]);
    $rejectedRequests = $rejectRequestsStmt->rowCount();
    
    logMessage("Successfully rejected $rejectedRequests NGO requests for expired products");
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
}

logMessage("Completed expired donations check");