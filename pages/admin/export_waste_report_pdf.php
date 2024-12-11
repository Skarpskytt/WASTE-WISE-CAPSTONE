<?php
// export_waste_report_pdf.php

require('fpdf186/fpdf.php'); // Adjust the path as needed

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

// Create instance of FPDF
$pdf = new FPDF();
$pdf->AddPage();

// Set font
$pdf->SetFont('Arial', 'B', 16);

// Title
$pdf->Cell(0, 10, 'Waste Data Report', 0, 1, 'C');
$pdf->Ln(10);

// Table Header
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(22, 70, 34); // Matches 'primarycol' #47663B
$pdf->SetTextColor(255, 255, 255);

// Define the table headers
$headers = [
    ['width' => 10, 'title' => '#'],
    ['width' => 30, 'title' => 'Waste Date'],
    ['width' => 40, 'title' => 'Item Name'],
    ['width' => 30, 'title' => 'Quantity'],
    ['width' => 30, 'title' => 'Value (₱)'],
    ['width' => 30, 'title' => 'Reason'],
    ['width' => 30, 'title' => 'Responsible'],
];

// Output the table headers
foreach ($headers as $header) {
    $pdf->Cell($header['width'], 10, $header['title'], 1, 0, 'C', true);
}
$pdf->Ln();

// Table Body
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);

$count = 1;
foreach ($wasteData as $waste) {
    $pdf->Cell(10, 10, $count++, 1, 0, 'C');
    $pdf->Cell(30, 10, $waste['waste_date'], 1, 0, 'C');
    $pdf->Cell(40, 10, $waste['item_name'], 1, 0, 'C');
    $pdf->Cell(30, 10, number_format($waste['waste_quantity'], 2), 1, 0, 'C');
    $pdf->Cell(30, 10, number_format($waste['waste_value'], 2), 1, 0, 'C');
    $pdf->Cell(30, 10, ucfirst($waste['waste_reason']), 1, 0, 'C');
    $pdf->Cell(30, 10, $waste['responsible_person'], 1, 1, 'C');
}

// Output the PDF
$pdf->Output('D', 'waste_report_' . date('Y-m-d') . '.pdf');
?>