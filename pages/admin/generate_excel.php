<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

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

// Create Excel file
$fileName = "{$branchName}_" . ($dataType == 'product' ? 'product' : 'ingredient') . "_waste_report_" . date('Y-m-d') . ".csv";

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM to fix Excel UTF-8 display issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Report: ' . $reportTitle
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

// Add column headers based on data type
if ($dataType == 'product') {
    // Removed 'Produced' and 'Sold' columns from headers
    fputcsv($output, [
        'ID', 'Product', 'Category', 'Waste Date', 'Quantity', 
        'Value (PHP)', 'Reason', 'Disposal Method', 'Staff'
    ]);
    
    // Add data rows - removed product_quantity_produced and quantity_sold fields
    foreach ($wasteData as $row) {
        fputcsv($output, [
            $row['id'],
            $row['product_name'],
            $row['category'],
            date('Y-m-d', strtotime($row['waste_date'])),
            $row['waste_quantity'],
            number_format($row['waste_value'], 2),
            ucfirst($row['waste_reason']),
            ucfirst($row['disposal_method']),
            $row['staff_name']
        ]);
    }
} else {
    fputcsv($output, [
        'ID', 'Ingredient', 'Category', 'Waste Date', 'Quantity', 'Remaining Stock', 
        'Value (PHP)', 'Reason', 'Disposal Method', 'Staff'
    ]);
    
    // Add data rows
    foreach ($wasteData as $row) {
        fputcsv($output, [
            $row['id'],
            $row['ingredient_name'],
            $row['category'],
            date('Y-m-d', strtotime($row['waste_date'])),
            $row['waste_quantity'],
            $row['remaining_stock'],
            number_format($row['waste_value'], 2),
            ucfirst($row['waste_reason']),
            ucfirst($row['disposal_method']),
            $row['staff_name']
        ]);
    }
}

// Add summary data at the end
fputcsv($output, []); // Empty line
fputcsv($output, ['SUMMARY']);

// Calculate totals
$totalWasteQty = 0;
$totalWasteValue = 0;

foreach ($wasteData as $row) {
    $totalWasteQty += $row['waste_quantity'];
    $totalWasteValue += $row['waste_value'];
}

fputcsv($output, ['Total ' . ($dataType == 'product' ? 'Products' : 'Ingredients') . ' Wasted', $totalWasteQty]);
fputcsv($output, ['Total Waste Value (PHP)', number_format($totalWasteValue, 2)]);

// Close the output stream
fclose($output);
exit;