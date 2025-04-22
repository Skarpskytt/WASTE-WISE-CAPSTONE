<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff', 'company']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify that a product ID was provided
if ($productId <= 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

// Get detailed product info including stock
try {
    $stmt = $pdo->prepare("
        SELECT 
            pi.id, 
            pi.name, 
            pi.category, 
            pi.price_per_unit,
            pi.image,
            pi.unit_type,
            pi.pieces_per_box,
            pi.shelf_life_days,
            COALESCE(SUM(ps.quantity), 0) as total_stock
        FROM product_info pi
        LEFT JOIN product_stock ps ON pi.id = ps.product_info_id
        WHERE pi.id = :productId AND pi.branch_id = :branchId
        GROUP BY pi.id, pi.name, pi.category, pi.price_per_unit, pi.image, pi.unit_type, pi.pieces_per_box, pi.shelf_life_days
    ");
    
    $stmt->bindParam(':productId', $productId, PDO::PARAM_INT);
    $stmt->bindParam(':branchId', $branchId, PDO::PARAM_INT);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Format the response
    $response = [
        'id' => $product['id'],
        'name' => $product['name'],
        'category' => $product['category'],
        'price_per_unit' => $product['price_per_unit'],
        'image' => $product['image'],
        'unit_type' => $product['unit_type'],
        'pieces_per_box' => $product['pieces_per_box'],
        'shelf_life_days' => $product['shelf_life_days'],
        'total_stock' => $product['total_stock']
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Log the error but don't expose details to the client
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
    exit;
}
?>