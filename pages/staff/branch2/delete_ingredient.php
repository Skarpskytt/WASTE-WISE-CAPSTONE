<?php
require_once '../../../config/auth_middleware.php';
require_once '../../../config/db_connect.php';

checkAuth(['branch2_staff']);
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM ingredients WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['successMessage'] = "Ingredient deleted successfully!";
    } else {
        $_SESSION['errorMessage'] = "Error deleting ingredient.";
    }
}
header("Location: ingredients.php");
exit;
?>