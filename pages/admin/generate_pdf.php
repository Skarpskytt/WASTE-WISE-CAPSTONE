<?php
// Ensure no whitespace or output before this opening PHP tag
// Buffer all output to prevent "headers already sent" errors
ob_start();

require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once 'fpdf186/fpdf.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Get parameters
$branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : '';
$dataType = isset($_GET['data_type']) ? trim($_GET['data_type']) : 'product'; // Default to product, can be 'ingredient'

// Define report period text based on date range
if (!empty($start_date) && !empty($end_date)) {
    $reportPeriod = date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date));
} elseif (!empty($start_date)) {
    $reportPeriod = date('M d, Y', strtotime($start_date)) . " onwards";
} elseif (!empty($end_date)) {
    $reportPeriod = "up to " . date('M d, Y', strtotime($end_date));
} else {
    $reportPeriod = "all recorded dates";
}

// Get branch name and logo
$branchStmt = $pdo->prepare("SELECT name, logo_path FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchData = $branchStmt->fetch(PDO::FETCH_ASSOC);
$branchName = $branchData['name'] ?? "Branch $branchId";

// Define multiple logo paths with different formats for fallback
$defaultLogos = [
    '../../assets/images/Logo.png',  // Try PNG format first
    '../../assets/images/Company Logo.jpg', // Then try JPG format
    '../../assets/images/LGU.png'    // Another fallback option
];

// Helper function to validate image
function isValidImage($path) {
    if (!file_exists($path)) return false;
    $imageInfo = @getimagesize($path);
    return $imageInfo && in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF]);
}

$branchLogoPath = null;

// Check if branch has a custom logo
if (!empty($branchData['logo_path'])) {
    // Check for branch logos in multiple locations
    $potentialPaths = [
        '../../assets/images/branch_logos/' . $branchData['logo_path'],
        '../../uploads/branch_logos/' . $branchData['logo_path'],
        '../../assets/images/' . $branchData['logo_path']  // Directly in images folder
    ];
    
    foreach ($potentialPaths as $path) {
        if (isValidImage($path)) {
            $branchLogoPath = $path;
            break;
        }
    }
}

// If branch logo wasn't found/valid, try the default options
if (!$branchLogoPath) {
    foreach ($defaultLogos as $logo) {
        if (isValidImage($logo)) {
            $branchLogoPath = $logo;
            break;
        }
    }
}

// Build the query based on data type
if ($dataType == 'product') {
    // Product waste data query - using product_info instead of products
    $dataQuery = "SELECT pw.id, p.name as product_name, p.category, pw.waste_date, 
                  pw.waste_quantity, pw.waste_value, pw.waste_reason, pw.disposal_method,
                  CONCAT(u.fname, ' ', u.lname) as staff_name
                  FROM product_waste pw
                  JOIN product_info p ON pw.product_id = p.id
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
    
    // Update the Header method in the PDF class

    function Header() {
        global $branchName, $reportTitle;
        
        // Add a subtle background tint for the whole page
        $this->SetFillColor(252, 252, 252);
        $this->Rect(0, 0, $this->GetPageWidth(), $this->GetPageHeight(), 'F');
        
        // Top header bar - extend slightly higher to accommodate logo
        $this->SetFillColor(0, 123, 95); // Deep green
        $this->Rect(0, 0, $this->GetPageWidth(), 18, 'F');
        
        // Logo placement - adjust positioning
        $logoWidth = 15; // Smaller logo size
        $logoX = 10;
        $logoY = 2; // Position logo higher to align with text
        
        if ($this->logoPath && file_exists($this->logoPath)) {
            try {
                // Check image type and only proceed if it's a valid image
                $imageInfo = @getimagesize($this->logoPath);
                if ($imageInfo) {
                    // Adjust position for logo - properly centered vertically
                    $this->Image($this->logoPath, $logoX, $logoY, $logoWidth);
                }
            } catch (Exception $e) {
                // Just skip logo on error
            }
        }
        
        // Company name - adjust position to align with logo
        $this->SetTextColor(255, 255, 255); // White text
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY($logoX + $logoWidth + 5, 5); // Center vertically with logo
        $this->Cell(100, 8, "WASTE-WISE", 0, 0, 'L');
        
        // Date on the right side of header bar
        $this->SetFont('Arial', '', 10);
        $this->SetXY($this->GetPageWidth() - 60, 5);
        $this->Cell(50, 8, date('F d, Y'), 0, 0, 'R');
        
        // Add spacing after header
        $this->Ln(20);
        
        // Report title with more emphasis
        $this->SetTextColor(0, 123, 95); // Green text
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(10, 25);
        $this->Cell($this->GetPageWidth() - 20, 10, $reportTitle, 0, 1, 'C');
        
        // Branch info - more professional styling
        $this->SetFont('Arial', 'I', 11);
        $this->SetTextColor(80, 80, 80); // Gray text
        $this->SetXY(10, 36);
        $this->Cell($this->GetPageWidth() - 20, 6, "Branch: " . $branchName, 0, 1, 'C');
        
        // Date range with better spacing
        global $start_date, $end_date;
        $dateStr = "";
        if (!empty($start_date) && !empty($end_date)) {
            $dateStr = "Date Range: " . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date));
        } elseif (!empty($start_date)) {
            $dateStr = "From: " . date('M d, Y', strtotime($start_date));
        } elseif (!empty($end_date)) {
            $dateStr = "Until: " . date('M d, Y', strtotime($end_date));
        }
        
        if (!empty($dateStr)) {
            $this->SetXY(10, 43);
            $this->Cell($this->GetPageWidth() - 20, 6, $dateStr, 0, 1, 'C');
            $this->Ln(8);
        } else {
            $this->Ln(14);
        }
        
        // Table header with improved styling - gradient effect
        $this->SetFillColor(0, 123, 95); // Green background
        $this->SetTextColor(255); // White text
        $this->SetDrawColor(255); // White border for contrast
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial', 'B', 10);
        
        global $dataType;   
        if ($dataType == 'product') {
            $w = array(12, 44, 28, 24, 18, 28, 34, 34, 42);
            $header = array('ID', 'Product', 'Category', 'Date', 'Qty', 'Value', 'Reason', 'Disposal', 'Staff');
        } else {
            $w = array(12, 36, 24, 22, 14, 24, 24, 30, 30, 36);
            $header = array('ID', 'Ingredient', 'Category', 'Date', 'Qty', 'Stock', 'Value', 'Reason', 'Disposal', 'Staff');
        }
        
        // Add table header cells with better styling
        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 10, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
    }
    
    // Update the Footer method

    function Footer() {
        // Position footer at bottom
        $this->SetY(-20);
        
        // Green footer bar
        $this->SetFillColor(0, 123, 95);
        $this->Rect(0, $this->GetY(), $this->GetPageWidth(), 20, 'F');
        
        // Footer text
        $this->SetY(-15);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(255); // White text
        
        // Page number
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
        
        // Generation date
        $this->SetXY($this->GetPageWidth() - 80, $this->GetY());
        $this->Cell(70, 10, 'Generated: '.date('Y-m-d H:i:s'), 0, 0, 'R');
        
        // Brand name on left
        $this->SetX(10);
        $this->Cell(60, 10, 'WASTE-WISE © '.date('Y'), 0, 0, 'L');
    }

    // Add to PDF class - rounded rectangle helper function

    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }
}

// Initialize PDF
$pdf = new PDF('L', 'mm', 'A4'); // Landscape orientation
$pdf->setLogoPath($branchLogoPath); // Set the branch-specific logo path
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
$pdf->SetFillColor(242, 247, 245); // Lighter green for alternating rows
$pdf->SetTextColor(50, 50, 50); // Darker text for better readability
$pdf->SetDrawColor(200, 220, 215); // Subtle borders

// Data rows
$fill = false;
$pdf->SetLineWidth(0.1);
$totalWasteQty = 0;
$totalWasteValue = 0;

foreach($wasteData as $row) {
    // Track totals
    $totalWasteQty += $row['waste_quantity'];
    $totalWasteValue += $row['waste_value'];
    
    // Slightly increase row height for better readability
    $rowHeight = 8;
    
    // ID column - centered
    $pdf->Cell($w[0], $rowHeight, $row['id'], 'LR', 0, 'C', $fill);
    
    // Product/Ingredient name with better truncation
    if ($dataType == 'product') {
        $pdf->MultiCellTruncated($w[1], $rowHeight, $row['product_name'], 'LR', 'L', $fill, 42);
        $pdf->MultiCellTruncated($w[2], $rowHeight, $row['category'], 'LR', 'L', $fill, 26);
        $pdf->Cell($w[3], $rowHeight, date('M d, Y', strtotime($row['waste_date'])), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[4], $rowHeight, $row['waste_quantity'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[5], $rowHeight, 'PHP ' . number_format($row['waste_value'], 2), 'LR', 0, 'R', $fill);
        $pdf->MultiCellTruncated($w[6], $rowHeight, $row['waste_reason'], 'LR', 'L', $fill, 30);
        $pdf->MultiCellTruncated($w[7], $rowHeight, $row['disposal_method'], 'LR', 'L', $fill, 30);
        $pdf->MultiCellTruncated($w[8], $rowHeight, $row['staff_name'], 'LR', 'L', $fill, 40);
    } else {
        $pdf->MultiCellTruncated($w[1], $rowHeight, $row['ingredient_name'], 'LR', 'L', $fill, 35);
        $pdf->MultiCellTruncated($w[2], $rowHeight, $row['category'], 'LR', 'L', $fill, 22);
        $pdf->Cell($w[3], $rowHeight, date('M d, Y', strtotime($row['waste_date'])), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[4], $rowHeight, $row['waste_quantity'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[5], $rowHeight, $row['remaining_stock'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[6], $rowHeight, 'PHP ' . number_format($row['waste_value'], 2), 'LR', 0, 'R', $fill);
        $pdf->MultiCellTruncated($w[7], $rowHeight, $row['waste_reason'], 'LR', 'L', $fill, 28);
        $pdf->MultiCellTruncated($w[8], $rowHeight, $row['disposal_method'], 'LR', 'L', $fill, 28);
        $pdf->MultiCellTruncated($w[9], $rowHeight, $row['staff_name'], 'LR', 'L', $fill, 34);
    }
    
    // Add a subtle bottom border to each row
    $pdf->Ln();
    $pdf->SetDrawColor(200, 220, 215);
    $pdf->Cell(array_sum($w), 0, '', 'T');
    $pdf->Ln();
    
    $fill = !$fill; // Alternate row colors
}

// Enhanced summary section

// Add summary section with better styling
$pdf->Ln(15);

// Create a visually appealing summary box
$pdf->SetFillColor(240, 248, 245); // Light background
$pdf->SetDrawColor(0, 123, 95); // Green border
$pdf->SetLineWidth(0.5);
$summaryX = 20;
$summaryY = $pdf->GetY();
$summaryWidth = 150;
$summaryHeight = 40;

// Draw the summary box with rounded corners effect
$pdf->RoundedRect($summaryX, $summaryY, $summaryWidth, $summaryHeight, 3.5, 'DF');

// Title with better styling
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 123, 95); // Green text
$pdf->SetXY($summaryX + 10, $summaryY + 6);
$pdf->Cell($summaryWidth - 20, 8, 'SUMMARY', 0, 1, 'L');

// Summary content with improved layout
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(50, 50, 50); // Dark gray text

// Total items wasted
$pdf->SetXY($summaryX + 10, $summaryY + 18);
$pdf->Cell(90, 6, 'Total ' . ($dataType == 'product' ? 'Products' : 'Ingredients') . ' Wasted:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 6, number_format($totalWasteQty) . ' items', 0, 1, 'R');

// Total waste value with emphasis
$pdf->SetXY($summaryX + 10, $summaryY + 26);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(90, 6, 'Total Waste Value:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 100, 80); // Darker green for emphasis
$pdf->Cell(40, 6, 'PHP ' . number_format($totalWasteValue, 2), 0, 1, 'R');

// At the final output, clear any buffered output and send the PDF
ob_end_clean(); // This is crucial - clears any accidental output before PDF generation

// Output the PDF
$fileName = "{$branchName}_" . ($dataType == 'product' ? 'product' : 'ingredient') . "_waste_report_" . date('Y-m-d') . ".pdf";
$pdf->Output('D', $fileName);
exit; // Ensure no further code execution after PDF output