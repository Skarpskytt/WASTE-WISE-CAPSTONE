<?php
// process_waste.php

session_start();
include('../../config/db_connect.php');

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve and sanitize input data
    $user_id = $_SESSION['user_id'];
    $item_id = intval($_POST['item_id']);
    $waste_quantity = floatval($_POST['waste_quantity']);
    $waste_reason = trim($_POST['waste_reason']);
    $waste_date = $_POST['waste_date'];
    $responsible_person = htmlspecialchars(trim($_POST['responsible_person']));
    $item_type = $_POST['item_type']; // Get item type (product or ingredient)

    // Validate inputs
    if (empty($item_id) || empty($waste_quantity) || empty($waste_reason) || empty($waste_date) || empty($responsible_person) || empty($item_type)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    // Validate waste reason
    $allowed_reasons = ['overproduction', 'expired', 'compost', 'donation', 'dumpster'];
    if (!in_array($waste_reason, $allowed_reasons)) {
        echo json_encode(['success' => false, 'message' => 'Invalid waste reason selected.']);
        exit();
    }

    // Validate waste date
    if (!DateTime::createFromFormat('Y-m-d', $waste_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid waste date format.']);
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Fetch item details based on type
        if ($item_type === 'ingredient') {
            $stmt = $pdo->prepare("SELECT price, quantity FROM ingredients WHERE id = :id FOR UPDATE");
        } else {
            $stmt = $pdo->prepare("SELECT price_per_unit as price, quantity FROM inventory WHERE id = :id FOR UPDATE");
        }
        
        $stmt->execute([':id' => $item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            $pdo->rollBack();
            exit();
        }

        $price_per_unit = floatval($item['price']);
        $current_quantity = floatval($item['quantity']);

        // Check if sufficient quantity is available
        if ($waste_quantity > $current_quantity) {
            echo json_encode(['success' => false, 'message' => 'Waste quantity exceeds available inventory.']);
            $pdo->rollBack();
            exit();
        }

        // Calculate waste value
        $waste_value = $waste_quantity * $price_per_unit;

        // Insert waste record
        $stmt = $pdo->prepare("
            INSERT INTO waste (
                user_id, 
                item_id,
                waste_quantity, 
                waste_value, 
                waste_reason, 
                waste_date, 
                responsible_person, 
                item_type
            ) VALUES (
                :user_id, 
                :item_id,
                :waste_quantity, 
                :waste_value, 
                :waste_reason, 
                :waste_date, 
                :responsible_person, 
                :item_type
            )
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':item_id' => $item_id,  
            ':waste_quantity' => $waste_quantity,
            ':waste_value' => $waste_value,
            ':waste_reason' => $waste_reason,
            ':waste_date' => $waste_date,
            ':responsible_person' => $responsible_person,
            ':item_type' => $item_type
        ]);

        // Update quantity based on type
        $new_quantity = $current_quantity - $waste_quantity;
        if ($item_type === 'ingredient') {
            $updateStmt = $pdo->prepare("UPDATE ingredients SET quantity = :new_quantity, waste_processed = TRUE WHERE id = :id");
        } else {
            $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = :new_quantity, waste_processed = TRUE WHERE id = :id");
        }
        
        $updateStmt->execute([
            ':new_quantity' => $new_quantity,
            ':id' => $item_id
        ]);

        // Commit transaction
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Waste recorded and inventory updated successfully.']);
        exit();
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to process waste: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
?>