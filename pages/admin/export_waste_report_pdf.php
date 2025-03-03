<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

// Get branch ID from URL parameter
$branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;

// Validate branch ID
if (!$branchId) {
    die("Invalid branch ID");
}

// Get branch name
$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: "Branch $branchId";

// Fetch product waste data for this branch
try {
    $prodStmt = $pdo->prepare("
        SELECT 
            pw.id,
            pw.waste_date,
            p.name as product_name,
            p.category,
            pw.waste_quantity,
            pw.quantity_produced,
            pw.quantity_sold,
            pw.waste_value,
            pw.waste_reason,
            CONCAT(u.fname, ' ', u.lname) as staff_name
        FROM 
            product_waste pw
        JOIN 
            products p ON pw.product_id = p.id
        JOIN 
            users u ON pw.user_id = u.id
        WHERE 
            pw.branch_id = ?
        ORDER BY 
            pw.waste_date DESC
        LIMIT 100
    ");
    $prodStmt->execute([$branchId]);
    $productWasteData = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching product waste data: " . $e->getMessage());
}

// Fetch ingredient waste data for this branch
try {
    $ingStmt = $pdo->prepare("
        SELECT 
            iw.id,
            iw.waste_date,
            i.ingredient_name,
            i.category,
            i.unit,
            iw.waste_quantity,
            iw.waste_value,
            iw.waste_reason,
            iw.production_stage,
            CONCAT(u.fname, ' ', u.lname) as staff_name
        FROM 
            ingredients_waste iw
        JOIN 
            ingredients i ON iw.ingredient_id = i.id
        JOIN 
            users u ON iw.user_id = u.id
        WHERE 
            iw.branch_id = ?
        ORDER BY 
            iw.waste_date DESC
        LIMIT 100
    ");
    $ingStmt->execute([$branchId]);
    $ingredientWasteData = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching ingredient waste data: " . $e->getMessage());
}

require('fpdf186/fpdf.php'); // Adjust path as needed

// Create custom PDF class with header and footer
class WasteReportPDF extends FPDF {
    protected $branchName;
    
    function __construct($branchName) {
        parent::__construct();
        $this->branchName = $branchName;
    }
    
    // Page header
    function Header() {
        // Logo - if you have one
        // $this->Image('logo.png', 10, 6, 30);
        
        // Header
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(71, 102, 59); // primarycol color
        $this->Cell(0, 10, $this->branchName . ' - Waste Report', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 6, 'Generated on: ' . date('F j, Y'), 0, 1, 'C');
        $this->Ln(10);
    }
    
    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Create instance of custom PDF
$pdf = new WasteReportPDF($branchName);
$pdf->AliasNbPages(); // For page numbering
$pdf->AddPage();

// PRODUCT WASTE SECTION
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Product Waste Data', 0, 1, 'L');

// Table Header - Product Waste
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(71, 102, 59); // primarycol color
$pdf->SetTextColor(255, 255, 255);

// Define product waste table headers
$prodHeaders = [
    ['width' => 12, 'title' => 'ID'],
    ['width' => 25, 'title' => 'Date'],
    ['width' => 35, 'title' => 'Product'],
    ['width' => 20, 'title' => 'Category'],
    ['width' => 15, 'title' => 'Qty'],
    ['width' => 15, 'title' => 'Value (₱)'],
    ['width' => 30, 'title' => 'Reason'],
    ['width' => 40, 'title' => 'Staff'],
];

// Output product waste table headers
foreach ($prodHeaders as $header) {
    $pdf->Cell($header['width'], 10, $header['title'], 1, 0, 'C', true);
}
$pdf->Ln();

// Table Body - Product Waste
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(240, 240, 240);
$fill = false;

if (count($productWasteData) > 0) {
    foreach ($productWasteData as $waste) {
        $pdf->Cell(12, 6, $waste['id'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, date('M d, Y', strtotime($waste['waste_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(35, 6, $waste['product_name'], 1, 0, 'L', $fill);
        $pdf->Cell(20, 6, $waste['category'], 1, 0, 'C', $fill);
        $pdf->Cell(15, 6, $waste['waste_quantity'], 1, 0, 'C', $fill);
        $pdf->Cell(15, 6, number_format($waste['waste_value'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(30, 6, ucfirst($waste['waste_reason']), 1, 0, 'L', $fill);
        $pdf->Cell(40, 6, $waste['staff_name'], 1, 1, 'L', $fill);
        $fill = !$fill; // Alternate row colors
    }
} else {
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(192, 10, 'No product waste records found.', 1, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

// INGREDIENT WASTE SECTION - Add a new page if needed
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
} else {
    $pdf->Ln(10);
}

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Ingredient Waste Data', 0, 1, 'L');

// Table Header - Ingredient Waste
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(71, 102, 59);
$pdf->SetTextColor(255, 255, 255);

// Define ingredient waste table headers
$ingHeaders = [
    ['width' => 12, 'title' => 'ID'],
    ['width' => 25, 'title' => 'Date'],
    ['width' => 35, 'title' => 'Ingredient'],
    ['width' => 15, 'title' => 'Qty'],
    ['width' => 12, 'title' => 'Unit'],
    ['width' => 15, 'title' => 'Value (₱)'],
    ['width' => 25, 'title' => 'Reason'],
    ['width' => 20, 'title' => 'Stage'],
    ['width' => 33, 'title' => 'Staff'],
];

// Output ingredient waste table headers
foreach ($ingHeaders as $header) {
    $pdf->Cell($header['width'], 10, $header['title'], 1, 0, 'C', true);
}
$pdf->Ln();

// Table Body - Ingredient Waste
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$fill = false;

if (count($ingredientWasteData) > 0) {
    foreach ($ingredientWasteData as $waste) {
        $pdf->Cell(12, 6, $waste['id'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, date('M d, Y', strtotime($waste['waste_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(35, 6, $waste['ingredient_name'], 1, 0, 'L', $fill);
        $pdf->Cell(15, 6, $waste['waste_quantity'], 1, 0, 'C', $fill);
        $pdf->Cell(12, 6, $waste['unit'], 1, 0, 'C', $fill);
        $pdf->Cell(15, 6, number_format($waste['waste_value'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(25, 6, ucfirst($waste['waste_reason']), 1, 0, 'L', $fill);
        $pdf->Cell(20, 6, ucfirst($waste['production_stage']), 1, 0, 'L', $fill);
        $pdf->Cell(33, 6, $waste['staff_name'], 1, 1, 'L', $fill);
        $fill = !$fill; // Alternate row colors
    }
} else {
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(192, 10, 'No ingredient waste records found.', 1, 1, 'C');
}

// Output the PDF
$pdf->Output('D', $branchName . '_waste_report_' . date('Y-m-d') . '.pdf');
?>