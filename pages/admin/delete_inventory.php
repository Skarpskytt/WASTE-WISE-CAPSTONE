<?php
// delete_inventory.php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php');

if (isset($_GET['id'])) {
    $inventory_id = intval($_GET['id']);

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Fetch the inventory item to get the image path
        $stmt = $pdo->prepare("SELECT image FROM inventory WHERE id = ?");
        $stmt->execute([$inventory_id]);
        $item = $stmt->fetch();

        if ($item) {
            // Delete the inventory item
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = :id");
            $stmt->execute([':id' => $inventory_id]);

            // Commit transaction
            $pdo->commit();

            // Optionally delete the image file
            if ($item['image'] && file_exists($item['image'])) {
                unlink($item['image']);
            }

            $_SESSION['success'] = "Inventory item deleted successfully.";
        } else {
            $_SESSION['success'] = "Inventory item not found.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['errors'] = "Error deleting inventory item: " . $e->getMessage();
    }

    header("Location: inventory_data.php");
    exit();
} else {
    $_SESSION['success'] = "No inventory item specified.";
    header("Location: inventory_data.php");
    exit();
}
?>