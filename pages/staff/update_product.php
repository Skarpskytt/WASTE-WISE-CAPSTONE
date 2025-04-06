<?php
// Start output buffering to catch any unwanted output
ob_start();

require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

checkAuth(['staff']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get product ID and check if it belongs to user's branch
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        // Validate product exists and belongs to branch
        $checkStmt = $pdo->prepare("SELECT id FROM product_info WHERE id = ? AND branch_id = ?");
        $checkStmt->execute([$productId, $branchId]);
        
        if (!$checkStmt->fetch()) {
            throw new Exception("Product not found or you don't have permission to edit it");
        }
        
        // Gather form data
        $productName = trim($_POST['product_name'] ?? '');
        $category = $_POST['product_category'] ?? ''; // Fix: changed from 'category' to 'product_category'
        
        // If 'new' category was selected, use the new_category value
        if ($category === 'new' && !empty($_POST['new_category'])) {
            $category = trim($_POST['new_category']);
        }
        
        $pricePerUnit = floatval($_POST['price_per_unit'] ?? 0);
        $unitType = $_POST['unit_type'] ?? 'piece';
        $shelfLifeDays = intval($_POST['shelf_life_days'] ?? 30);
        $piecesPerBox = ($unitType === 'box') ? intval($_POST['pieces_per_box'] ?? 0) : null;
        
        // Handle image upload
        $imagePath = $_POST['current_image'] ?? ''; // Default to current image
        
        if (!empty($_FILES['product_image']['name'])) {
            // A new image was uploaded
            $targetDir = "../../assets/uploads/products/";
            
            // Create directory if it doesn't exist
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // Generate unique filename with timestamp
            $timestamp = time();
            $fileExtension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $newFileName = $timestamp . '_' . basename($_FILES['product_image']['name']);
            $targetFile = $targetDir . $newFileName;
            
            // Check if file is an actual image
            $check = getimagesize($_FILES['product_image']['tmp_name']);
            if ($check === false) {
                throw new Exception("File is not an image");
            }
            
            // Check file size (limit to 2MB)
            if ($_FILES['product_image']['size'] > 2000000) {
                throw new Exception("File is too large. Maximum size is 2MB");
            }
            
            // Allow certain file formats
            if ($fileExtension != "jpg" && $fileExtension != "jpeg" && $fileExtension != "png") {
                throw new Exception("Only JPG, JPEG, and PNG files are allowed");
            }
            
            // Try to upload file
            if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $targetFile)) {
                throw new Exception("Failed to upload image");
            }
            
            // Store the full path for proper display
            $imagePath = "../../assets/uploads/products/" . $newFileName;
            
            // Log the image path for debugging
            error_log("New image path: " . $imagePath);
        }
        
        // Update product data
        $updateSql = "
            UPDATE product_info SET
                name = :name,
                category = :category,
                price_per_unit = :price,
                image = :image,
                unit_type = :unit_type,
                pieces_per_box = :pieces_per_box,
                shelf_life_days = :shelf_life,
                updated_at = NOW()  -- Ensure this timestamp is updated
            WHERE id = :id AND branch_id = :branch_id
        ";
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':name' => $productName,
            ':category' => $category,
            ':price' => $pricePerUnit,
            ':image' => $imagePath,
            ':unit_type' => $unitType,
            ':pieces_per_box' => $piecesPerBox,
            ':shelf_life' => $shelfLifeDays,
            ':id' => $productId,
            ':branch_id' => $branchId
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Set session message
        $_SESSION['success_message'] = "Product successfully updated!";
        
        // Set success response
        $response['success'] = true;
        $response['message'] = "Product successfully updated!";
        $response['redirect'] = "add_stock.php";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        $response['success'] = false;
        $response['message'] = "Error: " . $e->getMessage();
        
        // Store errors in session for non-AJAX requests
        $_SESSION['form_errors'] = [$e->getMessage()];
    }
}

// Clear any buffered output
ob_end_clean();

// Send proper JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>