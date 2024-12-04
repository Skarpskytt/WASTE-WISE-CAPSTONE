<?php
// hash_password.php

$password = 'Pa$$word123'; // Replace with desired admin password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Hashed Password: " . $hashed_password;
?>