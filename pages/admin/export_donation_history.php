<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once 'fpdf186/fpdf.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Get parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'pdf';

// Build the query with filters
$dataQuery = "SELECT dp.*, 
              COALESCE(u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
              dp.received_by,
              dp.donation_request_id,
              dp.received_date,
              dp.received_quantity,
              dp.remarks,
              p.name as product_name,
              b.name as branch_name
              FROM donated_products dp
              JOIN users u ON dp.ngo_id = u.id
              JOIN donation_requests dr ON dp.donation_request_id = dr.id
              JOIN products p ON dr.product_id = p.id
              JOIN branches b ON dr.branch_id = b.id";

$params = [];

// Add search filter
if (!empty($search)) {
    $whereClause = " WHERE (p.name LIKE ? OR 
                    COALESCE(u.organization_name, CONCAT(u.fname, ' ', u.lname)) LIKE ? OR
                    dp.received_by LIKE ? OR 
                    dp.remarks LIKE ? OR
                    CAST(dp.donation_request_id AS CHAR) LIKE ?)";
    
    $dataQuery .= $whereClause;
    
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add date range filter
if (!empty($start_date)) {
    $dateClause = empty($params) ? " WHERE" : " AND";
    $dataQuery .= "$dateClause DATE(dp.received_date) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $dateClause = empty($params) ? " WHERE" : " AND";
    $dataQuery .= "$dateClause DATE(dp.received_date) <= ?";
    $params[] = $end_date;
}

// Finalize the data query
$dataQuery .= " ORDER BY dp.received_date DESC";

// Fetch data
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);
$donationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$totalQuantity = 0;
$uniqueNgos = [];

foreach ($donationData as $donation) {
    $totalQuantity += $donation['received_quantity'];
    $uniqueNgos[$donation['ngo_id']] = true;
}

$uniqueNgoCount = count($uniqueNgos);

// Handle export format
if ($format === 'excel') {
    // ------------- EXCEL EXPORT -------------
    
    $fileName = "donation_history_report_" . date('Y-m-d') . ".csv";
    
    // Set headers to force download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM to fix Excel UTF-8 display issues
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, [
        'Report: Donation History'
    ]);
    
    // Add filter info if any
    $filterInfo = [];
    if (!empty($search)) {
        $filterInfo[] = 'Search: ' . $search;
    }
    if (!empty($start_date) || !empty($end_date)) {
        $dateRange = 'Date Range: ';
        $dateRange .= !empty($start_date) ? $start_date : 'All';
        $dateRange .= ' to ';
        $dateRange .= !empty($end_date) ? $end_date : 'Present';
        $filterInfo[] = $dateRange;
    }
    
    if (!empty($filterInfo)) {
        fputcsv($output, $filterInfo);
        fputcsv($output, []); // Empty line
    }
    
    // Add column headers
    fputcsv($output, [
        'ID', 'NGO Name', 'Received By', 'Request ID', 'Product', 'Branch',
        'Received Date', 'Quantity', 'Remarks'
    ]);
    
    // Add data rows
    foreach ($donationData as $donation) {
        fputcsv($output, [
            $donation['id'],
            $donation['ngo_name'],
            $donation['received_by'],
            $donation['donation_request_id'],
            $donation['product_name'],
            $donation['branch_name'],
            date('Y-m-d', strtotime($donation['received_date'])),
            $donation['received_quantity'],
            $donation['remarks']
        ]);
    }
    
    // Add summary data
    fputcsv($output, []); // Empty line
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Donations', count($donationData)]);
    fputcsv($output, ['Total Quantity Donated', $totalQuantity]);
    fputcsv($output, ['NGOs Served', $uniqueNgoCount]);
    
    // Close the output stream
    fclose($output);
    exit;
    
} else {
    // ------------- PDF EXPORT -------------
    
    // Create PDF with custom classes for donation history
    class DonationPDF extends FPDF {
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
            $this->Cell($this->GetPageWidth() - 20 - $logoWidth, 10, "Donation History Report", 0, 1, 'C');
            
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
            
            $w = array(12, 40, 30, 20, 36, 25, 25, 16, 50);
            
            // Header
            $header = array('ID', 'NGO Name', 'Received By', 'Req ID', 'Product', 'Branch', 'Date', 'Qty', 'Remarks');
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
    
    // Define path to logo image
    $logoPath = '../../assets/images/Company Logo.jpg';
    
    // Initialize PDF
    $pdf = new DonationPDF('L', 'mm', 'A4'); // Landscape orientation
    $pdf->setLogoPath($logoPath); // Set the logo path
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 9);
    
    // Table data - adjusted widths
    $w = array(12, 40, 30, 20, 36, 25, 25, 16, 50);
    
    // Set colors for rows
    $pdf->SetFillColor(240, 248, 245); // Light green for alternating rows
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(180, 180, 180);
    
    // Data
    $fill = false;
    $pdf->SetLineWidth(0.1);
    
    foreach($donationData as $donation) {
        // Row height for all cells
        $rowHeight = 7;
        
        $pdf->Cell($w[0], $rowHeight, $donation['id'], 'LR', 0, 'C', $fill);
        $pdf->MultiCellTruncated($w[1], $rowHeight, $donation['ngo_name'], 'LR', 'L', $fill, 38);
        $pdf->MultiCellTruncated($w[2], $rowHeight, $donation['received_by'], 'LR', 'L', $fill, 28);
        $pdf->Cell($w[3], $rowHeight, '#' . $donation['donation_request_id'], 'LR', 0, 'C', $fill);
        $pdf->MultiCellTruncated($w[4], $rowHeight, $donation['product_name'], 'LR', 'L', $fill, 34);
        $pdf->MultiCellTruncated($w[5], $rowHeight, $donation['branch_name'], 'LR', 'L', $fill, 23);
        $pdf->Cell($w[6], $rowHeight, date('M d, Y', strtotime($donation['received_date'])), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[7], $rowHeight, $donation['received_quantity'], 'LR', 0, 'C', $fill);
        
        // Handle remarks with potential overflow
        $pdf->MultiCellTruncated($w[8], $rowHeight, $donation['remarks'], 'LR', 'L', $fill, 48);
        
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
    $pdf->Cell(40, 6, 'Total Donations:', 1, 0, 'L', true);
    $pdf->Cell(60, 6, count($donationData), 1, 1, 'R', true);
    
    $pdf->Cell(40, 6, 'Total Quantity Donated:', 1, 0, 'L', true);
    $pdf->Cell(60, 6, $totalQuantity . ' items', 1, 1, 'R', true);
    
    $pdf->Cell(40, 6, 'NGOs Served:', 1, 0, 'L', true);
    $pdf->Cell(60, 6, $uniqueNgoCount, 1, 1, 'R', true);
    
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
    $fileName = "donation_history_report_" . date('Y-m-d') . ".pdf";
    $pdf->Output('D', $fileName);
    exit;
}