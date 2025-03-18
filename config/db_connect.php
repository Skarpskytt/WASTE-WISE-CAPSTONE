<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'gold-chinchilla-937194.hostingersite.com'; // Using IP instead of hostname
$db   = 'u697061521_wastewise';
$user = 'u697061521_rooot';
$pass = 'y5ra[x+L';
$charset = 'utf8mb4';


$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new Exception('Database connection failed: ' . $e->getMessage());
}
?>