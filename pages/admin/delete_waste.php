<?php
// delete_waste.php

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php'); // Adjust the path as necessary

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Prepare the delete statement
        $stmt = $pdo->prepare("DELETE FROM waste WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Redirect back with success message
            header('Location: table.php?message=Waste+record+deleted+successfully');
            exit();
        } else {
            header('Location: table.php?error=Failed+to+delete+waste+record');
            exit();
        }
    } catch (PDOException $e) {
        die("Error deleting waste record: " . $e->getMessage());
    }
} else {
    header('Location: table.php?error=Invalid+waste+record+ID');
    exit();
}
?>