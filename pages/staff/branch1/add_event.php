<?php
require_once '../../../config/auth_middleware.php';
require_once '../../../config/db_connect.php';

// Check for Branch 1 staff access only
checkAuth(['branch1_staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $title = $_POST['title'];

    $addEventQuery = "INSERT INTO events (title, start, end) VALUES (:title, :start, :end)";
    $stmt = $pdo->prepare($addEventQuery);
    $stmt->execute([
        ':title' => $title,
        ':start' => $date,
        ':end' => $date
    ]);

    echo json_encode(['status' => 'success']);
}
?>