<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check if user is admin or staff
checkAuth(['admin', 'staff']);

// Get donation ID
$donation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$donation_id) {
    echo json_encode(['error' => 'Invalid donation ID']);
    exit;
}

// Get donation details
$sql = "
    SELECT 
        dr.id,
        dr.product_waste_id,
        dr.quantity_requested,
        dr.pickup_date,
        dr.pickup_time,
        dr.notes as ngo_notes,
        dr.status,
        dr.is_received,
        dr.staff_notes,
        dr.created_at,
        dr.updated_at,
        dr.received_at,
        pw.waste_quantity as available_quantity,
        p.name as product_name,
        p.category,
        pw.donation_expiry_date,
        CONCAT(u.fname, ' ', u.lname) as ngo_name,
        u.organization_name,
        u.email as ngo_email,
        b.name as branch_name,
        b.address as branch_address
    FROM 
        donation_requests dr
    JOIN 
        product_waste pw ON dr.product_waste_id = pw.id
    JOIN 
        products p ON pw.product_id = p.id
    JOIN 
        users u ON dr.ngo_id = u.id
    JOIN 
        branches b ON pw.branch_id = b.id
    WHERE 
        dr.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$donation_id]);
$donation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donation) {
    echo json_encode(['error' => 'Donation not found']);
    exit;
}

// Return donation details as JSON
echo json_encode($donation);