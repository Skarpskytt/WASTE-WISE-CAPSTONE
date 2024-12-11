<?php
// process_waste.php

// Enable error reporting for debugging (Disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Set response header to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Include the database connection
include('../../config/db_connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $item_id = intval($_POST['item_id']);
    $waste_quantity = floatval($_POST['waste_quantity']);
    $waste_reason = $_POST['waste_reason'];
    $waste_date = $_POST['waste_date'];
    $responsible_person = htmlspecialchars(trim($_POST['responsible_person']));

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

    // Fetch item's price_per_unit and current quantity
    try {
        $stmt = $pdo->prepare("SELECT price_per_unit, quantity FROM inventory WHERE id = :id");
        $stmt->execute([':id' => $item_id]);
        $item = $stmt->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit();
        }

        $price_per_unit = floatval($item['price_per_unit']);
        $current_quantity = floatval($item['quantity']);

        // Check if sufficient quantity is available
        if ($waste_quantity > $current_quantity) {
            echo json_encode(['success' => false, 'message' => 'Waste quantity exceeds available inventory.']);
            exit();
        }

        // Calculate waste value
        $waste_value = $waste_quantity * $price_per_unit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching item data: ' . $e->getMessage()]);
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Insert waste record
        $stmt = $pdo->prepare("INSERT INTO waste (inventory_id, waste_quantity, waste_value, waste_reason, waste_date, responsible_person) 
                               VALUES (:inventory_id, :waste_quantity, :waste_value, :waste_reason, :waste_date, :responsible_person)");
        $stmt->execute([
            ':inventory_id' => $item_id,
            ':waste_quantity' => $waste_quantity,
            ':waste_value' => $waste_value,
            ':waste_reason' => $waste_reason,
            ':waste_date' => $waste_date,
            ':responsible_person' => $responsible_person
        ]);

        // Update inventory quantity
        $new_quantity = $current_quantity - $waste_quantity;
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = :new_quantity WHERE id = :id");
        $stmt->execute([
            ':new_quantity' => $new_quantity,
            ':id' => $item_id
        ]);

        // Commit transaction
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Waste data submitted successfully.']);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error submitting waste data: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>