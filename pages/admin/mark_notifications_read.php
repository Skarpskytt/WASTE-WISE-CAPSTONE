<?php
session_start();
include('../../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        $stmt->execute();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>