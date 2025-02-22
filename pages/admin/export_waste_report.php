<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

// Fetch waste data
try {
    $stmt = $pdo->prepare("
        SELECT 
            waste.id,
            waste.waste_date,
            inventory.name AS item_name,
            waste.waste_quantity,
            waste.waste_value,
            waste.waste_reason,
            waste.responsible_person
        FROM 
            waste
        LEFT JOIN 
            inventory ON waste.inventory_id = inventory.id
        ORDER BY 
            waste.waste_date DESC
    ");
    $stmt->execute();
    $wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching waste data: " . $e->getMessage());
}

// Set headers to prompt download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=waste_report_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, [
    'ID',
    'Waste Date',
    'Item Name',
    'Waste Quantity',
    'Waste Value (₱)',
    'Waste Reason',
    'Responsible Person',
]);

// Output the data
foreach ($wasteData as $row) {
    fputcsv($output, [
        $row['id'],
        $row['waste_date'],
        $row['item_name'],
        number_format($row['waste_quantity'], 2),
        number_format($row['waste_value'], 2),
        ucfirst($row['waste_reason']),
        $row['responsible_person'],
    ]);
}

fclose($output);
?>