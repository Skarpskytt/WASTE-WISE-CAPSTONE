<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once 'fpdf186/fpdf.php';

// Check for admin access only
checkAuth(['admin']);

// Get parameters
$branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : '';
$dataType = isset($_GET['data_type']) ? trim($_GET['data_type']) : 'product'; // Default to product, can be 'ingredient'

// Get branch name
$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: "Branch $branchId";

// Build the query based on data type
if ($dataType == 'product') {
    // Product waste data query - FIXED: changed user_id to staff_id
    $dataQuery = "SELECT pw.id, p.name as product_name, p.category, pw.waste_date, 
                  pw.waste_quantity, pw.waste_value, pw.waste_reason, pw.disposal_method,
                  CONCAT(u.fname, ' ', u.lname) as staff_name
                  FROM product_waste pw
                  JOIN products p ON pw.product_id = p.id
                  JOIN users u ON pw.staff_id = u.id
                  WHERE pw.branch_id = ?";
    
    $reportTitle = "{$branchName} - Product Waste Report";
} else {
    // Ingredient waste data query
    $dataQuery = "SELECT iw.id, i.ingredient_name, i.category, iw.waste_date, 
                  iw.waste_quantity, i.stock_quantity as remaining_stock,
                  iw.waste_value, iw.waste_reason, iw.disposal_method,
                  CONCAT(u.fname, ' ', u.lname) as staff_name
                  FROM ingredients_waste iw
                  JOIN ingredients i ON iw.ingredient_id = i.id
                  JOIN users u ON iw.staff_id = u.id
                  WHERE iw.branch_id = ?";
    
    $reportTitle = "{$branchName} - Ingredient Waste Report";
}

$params = [$branchId];

// Add search filter
if (!empty($search)) {
    if ($dataType == 'product') {
        $dataQuery .= " AND (p.name LIKE ? OR p.category LIKE ? OR 
                       pw.waste_reason LIKE ? OR pw.disposal_method LIKE ? OR
                       CONCAT(u.fname, ' ', u.lname) LIKE ?)";
    } else {
        $dataQuery .= " AND (i.ingredient_name LIKE ? OR i.category LIKE ? OR 
                       iw.waste_reason LIKE ? OR iw.disposal_method LIKE ? OR
                       CONCAT(u.fname, ' ', u.lname) LIKE ?)";
    }
    
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add date range filter
if (!empty($start_date)) {
    if ($dataType == 'product') {
        $dataQuery .= " AND DATE(pw.waste_date) >= ?";
    } else {
        $dataQuery .= " AND DATE(iw.waste_date) >= ?";
    }
    $params[] = $start_date;
}

if (!empty($end_date)) {
    if ($dataType == 'product') {
        $dataQuery .= " AND DATE(pw.waste_date) <= ?";
    } else {
        $dataQuery .= " AND DATE(iw.waste_date) <= ?";
    }
    $params[] = $end_date;
}

// Finalize the data query
if ($dataType == 'product') {
    $dataQuery .= " ORDER BY pw.waste_date DESC";
} else {
    $dataQuery .= " ORDER BY iw.waste_date DESC";
}

// Fetch Data
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);
$wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define path to logo image
$logoPath = '../../assets/images/Company Logo.jpg';

// Create PDF with multiline cell capability
class PDF extends FPDF {
    // Path to logo (will be set from outside)
    protected $logoPath;
    
    // Set logo path
    function setLogoPath($path) {
        $this->logoPath = $path;
    }
    
    // Function for multiline cell with specified width
    function MultiCellTruncated($w, $h, $txt, $border=0, $align='J', $fill=false, $maxChars=30) {
        // Truncate or process text appropriately
        if (strlen($txt) > $maxChars) {
            $txt = substr($txt, 0, $maxChars-3) . '...';
        }
        
        // Output the cell with text
        $this->Cell($w, $h, $txt, $border, 0, $align, $fill);
    }
    
    function Header() {
        global $branchName, $reportTitle;
        
        // Logo placement
        $logoWidth = 20;
        if (file_exists($this->logoPath)) {
            $this->Image($this->logoPath, 10, 10, $logoWidth);
        }
        
        // Company name and report title
        $this->SetTextColor(0, 123, 95); // Green color for header
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(10 + $logoWidth, 12);
        $this->Cell($this->GetPageWidth() - 20 - $logoWidth, 10, "WASTE-WISE", 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(10 + $logoWidth, 22);
        $this->Cell($this->GetPageWidth() - 20 - $logoWidth, 10, $reportTitle, 0, 1, 'C');
        
        // Reset text color for subtitle
        $this->SetTextColor(0);
        $this->SetXY(10, 42);
        
        // Date range
        global $start_date, $end_date;
        $this->SetFont('Arial', 'I', 10);
        $dateStr = "";
        if (!empty($start_date) && !empty($end_date)) {
            $dateStr = "Date Range: {$start_date} to {$end_date}";
        } elseif (!empty($start_date)) {
            $dateStr = "From {$start_date}";
        } elseif (!empty($end_date)) {
            $dateStr = "Until {$end_date}";
        }
        
        if (!empty($dateStr)) {
            $this->Cell(0, 6, $dateStr, 0, 1, 'R');
        }
        
        // Move down for the table header
        $this->Ln(5);
        
        // Table header
        $this->SetFillColor(0, 123, 95); // Green background for header
        $this->SetTextColor(255); // White text
        $this->SetDrawColor(0, 100, 80); // Darker border
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial', 'B', 9);
        
        global $dataType;   
        if ($dataType == 'product') {
            // Adjusted widths - removed 'Produced' and 'Sold' columns
            $w = array(12, 44, 28, 24, 18, 28, 34, 34, 42);
            // Header - removed 'Produced' and 'Sold'
            $header = array('ID', 'Product', 'Category', 'Date', 'Qty', 'Value', 'Reason', 'Disposal', 'Staff');
        } else {
            $w = array(12, 36, 24, 22, 14, 24, 24, 30, 30, 36);
            // Header
            $header = array('ID', 'Ingredient', 'Category', 'Date', 'Qty', 'Stock', 'Value', 'Reason', 'Disposal', 'Staff');
        }
        
        for($i=0; $i<count($header); $i++) {
            $this->Cell(isset($w[$i]) ? $w[$i] : 20, 8, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
    }
    
    function Footer() {
        // Footer with line
        $this->SetY(-20);
        $this->SetDrawColor(0, 123, 95);
        $this->Line(10, $this->GetY(), $this->GetPageWidth()-10, $this->GetY());
        
        // Footer text
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(80, 80, 80);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
        // Generation date
        $this->Cell(0, 10, 'Generated: '.date('Y-m-d H:i:s'), 0, 0, 'R');
    }
}

// Initialize PDF
$pdf = new PDF('L', 'mm', 'A4'); // Landscape orientation
$pdf->setLogoPath($logoPath); // Set the logo path
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// Table data - adjusted widths
if ($dataType == 'product') {
    // Adjusted widths - removed 'Produced' and 'Sold' columns
    $w = array(12, 44, 28, 24, 18, 28, 34, 34, 42);
} else {
    $w = array(12, 36, 24, 22, 14, 24, 24, 30, 30, 36);
}

// Set colors for rows
$pdf->SetFillColor(240, 248, 245); // Light green for alternating rows
$pdf->SetTextColor(0);
$pdf->SetDrawColor(180, 180, 180);

// Data
$fill = false;
$pdf->SetLineWidth(0.1);

// Calculate totals
$totalWasteQty = 0;
$totalWasteValue = 0;

foreach($wasteData as $row) {
    // Track totals
    $totalWasteQty += $row['waste_quantity'];
    $totalWasteValue += $row['waste_value'];
    
    // Row height for all cells
    $rowHeight = 7;
    
    $pdf->Cell($w[0], $rowHeight, $row['id'], 'LR', 0, 'C', $fill);
    
    // Handle product/ingredient name with potential overflow
    if ($dataType == 'product') {
        $pdf->MultiCellTruncated($w[1], $rowHeight, $row['product_name'], 'LR', 'L', $fill, 42);
        $pdf->MultiCellTruncated($w[2], $rowHeight, $row['category'], 'LR', 'L', $fill, 26);
    } else {
        $pdf->MultiCellTruncated($w[1], $rowHeight, $row['ingredient_name'], 'LR', 'L', $fill, 35);
        $pdf->MultiCellTruncated($w[2], $rowHeight, $row['category'], 'LR', 'L', $fill, 22);
    }
    
    $pdf->Cell($w[3], $rowHeight, date('M d, Y', strtotime($row['waste_date'])), 'LR', 0, 'C', $fill);
    $pdf->Cell($w[4], $rowHeight, $row['waste_quantity'], 'LR', 0, 'C', $fill);
    
    if ($dataType == 'product') {
        // Removed cells for 'product_quantity_produced' and 'quantity_sold'
        $pdf->Cell($w[5], $rowHeight, 'PHP ' . number_format($row['waste_value'], 2), 'LR', 0, 'R', $fill);
        
        // Handle reason with potential overflow
        $pdf->MultiCellTruncated($w[6], $rowHeight, ucfirst($row['waste_reason']), 'LR', 'L', $fill, 32);
        $pdf->MultiCellTruncated($w[7], $rowHeight, ucfirst($row['disposal_method']), 'LR', 'L', $fill, 32);
        $pdf->MultiCellTruncated($w[8], $rowHeight, $row['staff_name'], 'LR', 'L', $fill, 40);
    } else {
        $pdf->Cell($w[5], $rowHeight, $row['remaining_stock'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[6], $rowHeight, 'PHP ' . number_format($row['waste_value'], 2), 'LR', 0, 'R', $fill);
        
        // Handle reason with potential overflow
        $pdf->MultiCellTruncated($w[7], $rowHeight, ucfirst($row['waste_reason']), 'LR', 'L', $fill, 28);
        $pdf->MultiCellTruncated($w[8], $rowHeight, ucfirst($row['disposal_method']), 'LR', 'L', $fill, 28);
        $pdf->MultiCellTruncated($w[9], $rowHeight, $row['staff_name'], 'LR', 'L', $fill, 34);
    }
    
    $pdf->Ln();
    
    // Add bottom border to the row
    $pdf->Cell(array_sum($w), 0, '', 'T');
    $pdf->Ln();
    
    $fill = !$fill; // Alternate row colors
}

// Add summary section
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(0, 123, 95); // Change summary header to match main header
$pdf->SetTextColor(255); // White text

$pdf->Cell(100, 8, 'Summary', 1, 1, 'L', true);
$pdf->SetTextColor(0); // Reset to black text

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 6, 'Total ' . ($dataType == 'product' ? 'Products' : 'Ingredients') . ' Wasted:', 1, 0, 'L', true);
$pdf->Cell(60, 6, $totalWasteQty . ' items', 1, 1, 'R', true);

$pdf->Cell(40, 6, 'Total Waste Value:', 1, 0, 'L', true);
// Use "PHP" instead of "₱" to avoid encoding issues
$pdf->Cell(60, 6, 'PHP ' . number_format($totalWasteValue, 2), 1, 1, 'R', true);

$pdf->Cell(40, 6, 'Report Period:', 1, 0, 'L', true);

// Date range for summary
$reportPeriod = "";
if (!empty($start_date) && !empty($end_date)) {
    $reportPeriod = "{$start_date} to {$end_date}";
} elseif (!empty($start_date)) {
    $reportPeriod = "From {$start_date}";
} elseif (!empty($end_date)) {
    $reportPeriod = "Until {$end_date}";
} else {
    $reportPeriod = "All time";
}

$pdf->Cell(60, 6, $reportPeriod, 1, 1, 'R', true);

// Output the PDF
$fileName = "{$branchName}_" . ($dataType == 'product' ? 'product' : 'ingredient') . "_waste_report_" . date('Y-m-d') . ".pdf";
$pdf->Output('D', $fileName);