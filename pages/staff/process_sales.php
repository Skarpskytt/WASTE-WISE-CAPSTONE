<?php
// process_sales.php

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
include('../../config/db_connect.php'); // Adjust the path as needed

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $product_id = intval($_POST['product_id']);
    $date = $_POST['date'];
    $quantity_sold = intval($_POST['quantity_sold']);
    $revenue = floatval($_POST['revenue']);
    $inventory_level = intval($_POST['inventory_level']);
    $staff_member = htmlspecialchars(trim($_POST['staff_member']));
    $comments = htmlspecialchars(trim($_POST['comments']));

    // Validate date
    if (!DateTime::createFromFormat('Y-m-d', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
        exit();
    }

    // Additional validation can be added here

    try {
        // Insert sales data into the 'sales' table
        $stmt = $pdo->prepare("INSERT INTO sales (product_id, date, quantity_sold, revenue, inventory_level, staff_member, comments) 
                               VALUES (:product_id, :date, :quantity_sold, :revenue, :inventory_level, :staff_member, :comments)");
        $stmt->execute([
            ':product_id' => $product_id,
            ':date' => $date,
            ':quantity_sold' => $quantity_sold,
            ':revenue' => $revenue,
            ':inventory_level' => $inventory_level,
            ':staff_member' => $staff_member,
            ':comments' => $comments
        ]);

        echo json_encode(['success' => true, 'message' => 'Sales data submitted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error submitting sales data: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>