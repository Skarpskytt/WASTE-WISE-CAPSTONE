<?php
// config.php

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "waste";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the charset to utf8mb4 for better Unicode support
$conn->set_charset("utf8mb4");

// Error reporting for debugging (disable in production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>