<?php
session_start();
// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: productdata.php");
    exit();
}

$id = intval($_GET['id']);

// Delete the product from the database using PDO
try {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $_SESSION['success'] = "Product deleted successfully.";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
}

header("Location: productdata.php");
exit();
?>