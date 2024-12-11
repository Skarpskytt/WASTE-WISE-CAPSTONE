<?php
include '../../config/db_connect.php';
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM ngos WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: ngo.php');
exit();
?>