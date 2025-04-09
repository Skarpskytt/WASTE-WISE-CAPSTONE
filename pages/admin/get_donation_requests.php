<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access
checkAuth(['admin']);

// Get waste_id from query string
$wasteId = isset($_GET['waste_id']) ? intval($_GET['waste_id']) : 0;

// Initialize response
$response = ['requests' => []];

try {
    $pdo = getPDO();
    
    // Get all donation requests for this waste item
    $requestsQuery = $pdo->prepare("
        SELECT 
            ndr.id,
            ndr.ngo_id,
            ndr.request_date,
            ndr.pickup_date,
            ndr.pickup_time,
            ndr.status,
            ndr.quantity_requested,
            ndr.ngo_notes,
            np.organization_name
        FROM ngo_donation_requests ndr
        JOIN ngo_profiles np ON ndr.ngo_id = np.user_id
        WHERE ndr.waste_id = ?
        ORDER BY ndr.request_date DESC
    ");
    
    $requestsQuery->execute([$wasteId]);
    $requests = $requestsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    $response['requests'] = $requests;
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error (in a production environment)
    // error_log('Error in get_donation_requests.php: ' . $e->getMessage());
    
    // Return error response
    $response['error'] = 'Database error occurred';
    header('Content-Type: application/json');
    echo json_encode($response);
}