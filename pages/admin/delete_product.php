<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

// Validate product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID provided.";
    header("Location: product_data.php");
    exit();
}

$product_id = intval($_GET['id']);

try {
    $pdo->beginTransaction();

    // First check if product exists and get image info
    $stmt = $pdo->prepare("SELECT id, image FROM inventory WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Check for and handle any dependent records (if needed)
        // For example, check waste records, etc.

        // Delete the product
        $deleteStmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $deleteStmt->execute([$product_id]);

        // Handle image deletion if exists
        if (!empty($product['image'])) {
            $imagePath = __DIR__ . '/../../assets/uploads/' . $product['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Product deleted successfully.";
    } else {
        $pdo->rollBack();
        $_SESSION['error'] = "Product not found.";
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
    error_log("Delete product error: " . $e->getMessage());
}

// Make sure the redirect path is correct
header("Location: product_data.php");
exit();
?>