<?php
// export_waste_report.php

// Include the database connection
include('../../config/db_connect.php'); // Adjust the path as needed

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
            waste.responsible_person,
            waste.comments
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
    'Waste Value',
    'Waste Reason',
    'Responsible Person',
    'Comments'
]);

// Output the data
foreach ($wasteData as $row) {
    fputcsv($output, [
        $row['id'],
        $row['waste_date'],
        $row['item_name'],
        $row['waste_quantity'],
        $row['waste_value'],
        $row['waste_reason'],
        $row['responsible_person'],
        $row['comments']
    ]);
}

fclose($output);
?>