<?php
// filepath: /c:/xampp/htdocs/capstone/WASTE-WISE-CAPSTONE/scripts/create_admin.php
include('../config/db_connect.php');

// Define admin user details
$fname = 'JJ';
$lname = 'Chua';
$email = 'jj@wastewise.com';
$password = 'adminpassword'; // Change this to your desired password
$role = 'admin';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert the admin user into the database
$stmt = $pdo->prepare('INSERT INTO users (fname, lname, email, password, role) VALUES (?, ?, ?, ?, ?)');
if ($stmt->execute([$fname, $lname, $email, $hashed_password, $role])) {
    echo "Admin account created successfully.";
} else {
    echo "Error creating admin account.";
}
?>