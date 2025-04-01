<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

if (isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    $pdo = getPDO();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM products WHERE id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Add debug info for image path
            $product['debug_image_info'] = [
                'raw_path' => $product['image'],
                'file_exists_uploads' => file_exists("../../uploads/products/{$product['image']}"),
                'file_exists_assets' => file_exists("../../assets/uploads/products/{$product['image']}")
            ];
            
            // Check exact image locations and add to response
            $product['image_paths'] = [
                'raw_filename' => $product['image'],
                'exists_in_assets' => !empty($product['image']) && 
                    file_exists("../../assets/uploads/products/{$product['image']}"),
                'exists_in_uploads' => !empty($product['image']) && 
                    file_exists("../../uploads/products/{$product['image']}"),
                'recommended_path' => !empty($product['image']) ? 
                    (file_exists("../../assets/uploads/products/{$product['image']}") ? 
                        "assets/uploads/products/{$product['image']}" : 
                        (file_exists("../../uploads/products/{$product['image']}") ? 
                            "uploads/products/{$product['image']}" : "")) : ""
            ];
            
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