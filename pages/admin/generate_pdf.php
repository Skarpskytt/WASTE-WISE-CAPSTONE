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

// Get branch name
$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: "Branch $branchId";

// Build the query with filters
$dataQuery = "SELECT pw.*, p.name as product_name, p.category, p.quantity_produced as product_quantity_produced,
              CONCAT(u.fname, ' ', u.lname) as staff_name
              FROM product_waste pw
              JOIN products p ON pw.product_id = p.id
              JOIN users u ON pw.user_id = u.id
              WHERE pw.branch_id = ?";

$params = [$branchId];

// Add search filter
if (!empty($search)) {
    $dataQuery .= " AND (p.name LIKE ? OR p.category LIKE ? OR 
                   pw.waste_reason LIKE ? OR pw.disposal_method LIKE ? OR
                   CONCAT(u.fname, ' ', u.lname) LIKE ?)";
    
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add date range filter
if (!empty($start_date)) {
    $dataQuery .= " AND DATE(pw.waste_date) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $dataQuery .= " AND DATE(pw.waste_date) <= ?";
    $params[] = $end_date;
}

// Finalize the data query
$dataQuery .= " ORDER BY pw.waste_date DESC";

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
        $this->Cell($w, $h, $txt, $border, 0, $align, $fill);
    }
    
    function Header() {
        global $branchName;
        
        // Set colors for header
        $this->SetFillColor(0, 123, 95); // Green theme
        $this->SetTextColor(255, 255, 255);
        
        // Header box
        $this->Rect(10, 10, $this->GetPageWidth()-20, 30, 'F');
        
        // Add logo if file exists
        if (file_exists($this->logoPath)) {
            // Add company logo (max height 25mm, positioned at the left side of header)
            $this->Image($this->logoPath, 15, 12, 0, 25); // Width is auto-calculated to maintain proportion
            $logoWidth = 40; // Estimate logo width + margin
        } else {
            $logoWidth = 0;
        }
        
        // Set font and title - adjust position to account for logo
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(10 + $logoWidth, 12);
        $this->Cell($this->GetPageWidth() - 20 - $logoWidth, 10, "WASTE-WISE", 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(10 + $logoWidth, 22);
        $this->Cell($this->GetPageWidth() - 20 - $logoWidth, 10, "{$branchName} - Product Waste Report", 0, 1, 'C');
        
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
            $dateStr = "From: {$start_date}";
        } elseif (!empty($end_date)) {
            $dateStr = "Until: {$end_date}";
        } else {
            $dateStr = "Report date: " . date('Y-m-d');
        }
        $this->Cell(0, 6, $dateStr, 0, 1, 'C');
        
        // Search term
        global $search;
        if (!empty($search)) {
            $this->Cell(0, 6, "Search: \"{$search}\"", 0, 1, 'C');
        }
        
        $this->Ln(4);
        
        // Table header
        $this->SetFillColor(0, 123, 95); // Primary color
        $this->SetTextColor(255);
        $this->SetDrawColor(180, 180, 180);
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial', 'B', 9);
        
        // Column widths (slightly adjusted)
        $w = array(12, 36, 24, 22, 14, 18, 14, 24, 30, 30, 36);
        
        // Header
        $header = array('ID', 'Product', 'Category', 'Date', 'Qty', 'Produced', 'Sold', 'Value', 'Reason', 'Disposal', 'Staff');
        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
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
$w = array(12, 36, 24, 22, 14, 18, 14, 24, 30, 30, 36);

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
    
    // Handle product name with potential overflow
    $pdf->MultiCellTruncated($w[1], $rowHeight, $row['product_name'], 'LR', 'L', $fill, 35);
    
    $pdf->MultiCellTruncated($w[2], $rowHeight, $row['category'], 'LR', 'L', $fill, 25);
    $pdf->Cell($w[3], $rowHeight, date('M d, Y', strtotime($row['waste_date'])), 'LR', 0, 'C', $fill);
    $pdf->Cell($w[4], $rowHeight, $row['waste_quantity'], 'LR', 0, 'C', $fill);
    $pdf->Cell($w[5], $rowHeight, $row['product_quantity_produced'], 'LR', 0, 'C', $fill);
    $pdf->Cell($w[6], $rowHeight, $row['quantity_sold'], 'LR', 0, 'C', $fill);
    
    // Use "PHP" instead of "₱" to avoid encoding issues
    $pdf->Cell($w[7], $rowHeight, 'PHP ' . number_format($row['waste_value'], 2), 'LR', 0, 'R', $fill);
    
    // Handle reason with potential overflow
    $pdf->MultiCellTruncated($w[8], $rowHeight, ucfirst($row['waste_reason']), 'LR', 'L', $fill, 30);
    $pdf->MultiCellTruncated($w[9], $rowHeight, ucfirst($row['disposal_method']), 'LR', 'L', $fill, 30);
    $pdf->MultiCellTruncated($w[10], $rowHeight, $row['staff_name'], 'LR', 'L', $fill, 35);
    
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
$pdf->Cell(40, 6, 'Total Products Wasted:', 1, 0, 'L', true);
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
$fileName = "{$branchName}_product_waste_report_" . date('Y-m-d') . ".pdf";
$pdf->Output('D', $fileName);