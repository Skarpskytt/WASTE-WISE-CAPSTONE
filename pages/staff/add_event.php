<?php
include('../../config/db_connect.php');

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