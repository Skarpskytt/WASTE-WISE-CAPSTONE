<?php
include('../config/db_connect.php');

$fname = 'Joshua';
$lname = 'Chua';
$email = 'joshuabchua11@gmail.com';
$password = 'Pogiako123*'; 
$role = 'admin';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('INSERT INTO users (fname, lname, email, password, role) VALUES (?, ?, ?, ?, ?)');
if ($stmt->execute([$fname, $lname, $email, $hashed_password, $role])) {
    echo "Admin account created successfully.";
} else {
    echo "Error creating admin account.";
}
?>