<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

if (isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    $pdo = getPDO();
    
    try {
        // Modify your query to get shelf_life_days
        $stmt = $pdo->prepare("
            SELECT id, name, category, price_per_unit, image, unit_type, pieces_per_box, 
                   shelf_life_days, unit, production_date
            FROM products 
            WHERE id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If shelf_life_days is null, set a default value
        if ($product) {
            // Set a default shelf life if none is specified
            if ($product['shelf_life_days'] === null) {
                $product['shelf_life_days'] = 30; // Default to 30 days
            }
            
            // Replace the image path detection code with this simpler version
            if ($product && isset($product['image'])) {
                // Extract just the filename without directory path
                $filename = basename($product['image']);
                
                // Send back just the filename - client will handle path construction
                $product['original_image_path'] = $product['image'];
                $product['image'] = $filename;
                
                // Add debug information
                $product['debug_image_info'] = [
                    'original_path' => $product['original_image_path'],
                    'filename' => $filename,
                    'webroot_path' => realpath($_SERVER['DOCUMENT_ROOT']),
                    'script_path' => __DIR__
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($product);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing product ID']);
}
?>