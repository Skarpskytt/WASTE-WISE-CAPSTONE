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
            iw.disposal_method,
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
    ");
    $ingStmt->execute([$branchId]);
    $ingredientWasteData = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching ingredient waste data: " . $e->getMessage());
}

// Set headers to prompt download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $branchName . '_waste_report_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output title row
fputcsv($output, [$branchName . ' Waste Report - Generated on ' . date('F j, Y')]);
fputcsv($output, []); // Empty row as separator

// PRODUCT WASTE SECTION
fputcsv($output, ['PRODUCT WASTE DATA']);
// Output the column headings
fputcsv($output, [
    'ID',
    'Waste Date',
    'Product Name',
    'Category',
    'Waste Quantity',
    'Produced',
    'Sold',
    'Waste Value (₱)',
    'Waste Reason',
    'Staff Name',
]);

// Output the product waste data
foreach ($productWasteData as $row) {
    fputcsv($output, [
        $row['id'],
        $row['waste_date'],
        $row['product_name'],
        $row['category'],
        $row['waste_quantity'],
        $row['quantity_produced'],
        $row['quantity_sold'],
        number_format($row['waste_value'], 2),
        ucfirst($row['waste_reason']),
        $row['staff_name'],
    ]);
}

// Add separator between sections
fputcsv($output, []);
fputcsv($output, []);

// INGREDIENT WASTE SECTION
fputcsv($output, ['INGREDIENT WASTE DATA']);
// Output the column headings for ingredients
fputcsv($output, [
    'ID',
    'Waste Date',
    'Ingredient Name',
    'Category',
    'Waste Quantity',
    'Unit',
    'Waste Value (₱)',
    'Waste Reason',
    'Production Stage',
    'Disposal Method',
    'Staff Name',
]);

// Output the ingredient waste data
foreach ($ingredientWasteData as $row) {
    fputcsv($output, [
        $row['id'],
        $row['waste_date'],
        $row['ingredient_name'],
        $row['category'],
        $row['waste_quantity'],
        $row['unit'],
        number_format($row['waste_value'], 2),
        ucfirst($row['waste_reason']),
        ucfirst($row['production_stage']),
        ucfirst($row['disposal_method']),
        $row['staff_name'],
    ]);
}

fclose($output);
exit;
?>