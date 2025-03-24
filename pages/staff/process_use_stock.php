<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

$pdo = getPDO();

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging code to log the received ingredient_id
    error_log("Received ingredient_id: " . filter_input(INPUT_POST, 'ingredient_id', FILTER_VALIDATE_INT));

    try {
        // Get form data
        $ingredientId = filter_input(INPUT_POST, 'ingredient_id', FILTER_VALIDATE_INT);
        $quantityUsed = filter_input(INPUT_POST, 'quantity_used', FILTER_VALIDATE_FLOAT);
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
        $branchId = $_SESSION['branch_id'];
        $userId = $_SESSION['user_id'];
        
        if (!$ingredientId || !$quantityUsed) {
            throw new Exception("Invalid input data");
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check current stock
        $checkStmt = $pdo->prepare("SELECT stock_quantity, ingredient_name, unit FROM ingredients WHERE id = ? AND branch_id = ?");
        $checkStmt->execute([$ingredientId, $branchId]);
        $ingredient = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ingredient) {
            throw new Exception("Ingredient not found");
        }
        
        if ($ingredient['stock_quantity'] < $quantityUsed) {
            throw new Exception("Not enough stock available");
        }
        
        // Update ingredient stock
        $newStock = $ingredient['stock_quantity'] - $quantityUsed;
        $updateStmt = $pdo->prepare("UPDATE ingredients SET stock_quantity = ? WHERE id = ? AND branch_id = ?");
        $updateStmt->execute([$newStock, $ingredientId, $branchId]);
        
        // Record usage in the ingredient_usage table
        $logStmt = $pdo->prepare("
            INSERT INTO ingredient_usage 
            (ingredient_id, quantity_used, user_id, branch_id, notes, usage_date) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$ingredientId, $quantityUsed, $userId, $branchId, $notes]);
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect with success message
        header("Location: ingredients_stocks.php?success=1&message=" . urlencode("Successfully used {$quantityUsed} {$ingredient['unit']} of {$ingredient['ingredient_name']}"));
        exit;
        
    } catch (Exception $e) {
        // Roll back transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Redirect with error message
        header("Location: ingredients_stocks.php?error=1&message=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Not a POST request
    header("Location: ingredients_stocks.php");
    exit;
}