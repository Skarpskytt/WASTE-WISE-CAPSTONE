<?php
// process_waste.php

// Enable error reporting for debugging (Disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Set response header to JSON
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Include the database connection
include('../../config/db_connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $item_id = intval($_POST['item_id']);
    $waste_quantity = floatval($_POST['waste_quantity']);
    $waste_value = floatval($_POST['waste_value']);
    $waste_reason = $_POST['waste_reason'];
    $classification = $_POST['classification']; // New field
    $waste_date = $_POST['waste_date'];
    $responsible_person = htmlspecialchars(trim($_POST['responsible_person']));

    // Validate classification
    $allowed_classifications = ['product', 'inventory'];
    if (!in_array($classification, $allowed_classifications)) {
        echo json_encode(['success' => false, 'message' => 'Invalid classification selected.']);
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

    // Additional validation can be added here

    try {
        $stmt = $pdo->prepare("INSERT INTO waste (inventory_id, waste_quantity, waste_value, waste_reason, classification, waste_date, responsible_person) 
                               VALUES (:inventory_id, :waste_quantity, :waste_value, :waste_reason, :classification, :waste_date, :responsible_person)");
        $stmt->execute([
            ':inventory_id' => $item_id,
            ':waste_quantity' => $waste_quantity,
            ':waste_value' => $waste_value,
            ':waste_reason' => $waste_reason,
            ':classification' => $classification,
            ':waste_date' => $waste_date,
            ':responsible_person' => $responsible_person
        ]);

        echo json_encode(['success' => true, 'message' => 'Waste data submitted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error submitting waste data: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>