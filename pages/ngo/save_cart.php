<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check if user is NGO
checkAuth(['ngo']);

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['cart'])) {
    // Update the cart in session
    $_SESSION['donation_cart'] = $data['cart'];
    
    // Send success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Cart updated']);
} else {
    // Send error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}