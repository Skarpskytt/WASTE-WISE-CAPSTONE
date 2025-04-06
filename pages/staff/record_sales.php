<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

$pdo = getPDO();

/**
 * Safely format a date to prevent 1970 issues
 * @param string $dateString The date string to format
 * @param string $format The desired output format
 * @return string Formatted date or error message
 */
function safeFormatDate($dateString, $format = 'F j, Y') {
    if (empty($dateString) || $dateString === '0000-00-00') {
        return 'Not set';
    }
    
    try {
        $date = new DateTime($dateString);
        return $date->format($format);
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

// Initialize variables
$message = '';
$messageType = '';
$date = date('Y-m-d'); // Default to current date

// Pagination setup for recent sales
$itemsPerPage = 5; // Number of sales records per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Fetch active products for the branch
$stmt = $pdo->prepare("
    SELECT 
        pi.id, 
        pi.name, 
        pi.category, 
        pi.price_per_unit, 
        pi.image,
        GROUP_CONCAT(ps.id) AS stock_ids,
        SUM(ps.quantity) AS stock_quantity,
        MIN(ps.expiry_date) AS earliest_expiry_date,
        GROUP_CONCAT(ps.batch_number) AS batch_numbers
    FROM product_info pi
    JOIN product_stock ps ON pi.id = ps.product_info_id
    WHERE pi.branch_id = ? 
    AND ps.expiry_date > CURRENT_DATE
    AND ps.quantity > 0
    AND (ps.is_archived = 0 OR ps.is_archived IS NULL)
    GROUP BY pi.id, pi.name, pi.category, pi.price_per_unit, pi.image
    ORDER BY pi.name ASC
");
$stmt->execute([$_SESSION['branch_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_sales']) && !isset($_POST['archive_sales'])) {
    // Debug incoming data
    error_log("Sales data: " . json_encode($_POST));
    
    try {
        $salesDate = $_POST['sales_date'];
        $productSales = $_POST['sales'] ?? [];
        
        // Start transaction
        $pdo->beginTransaction();
        
        $salesRecorded = false;
        
        // Before processing, check if there's at least one valid entry
        $validSalesExist = false;
        foreach ($productSales as $productId => $data) {
            $quantity = $data['quantity'] ?? 0;
            if (!empty($quantity) && $quantity > 0) {
                $validSalesExist = true;
                break;
            }
        }

        if (!$validSalesExist) {
            throw new Exception("No valid sales quantities entered. Please enter at least one quantity greater than zero.");
        }
        
        // Insert each product sale
        foreach ($productSales as $productId => $data) {
            $quantity = $data['quantity'] ?? 0;
            $stockIds = isset($data['stock_ids']) ? explode(',', $data['stock_ids']) : [];
            
            // Only process if quantity is greater than 0
            if (empty($quantity) || $quantity <= 0 || empty($stockIds)) {
                continue;
            }
            
            // Get stock information for each batch of this product
            $stockQuery = $pdo->prepare("
                SELECT ps.id, ps.quantity
                FROM product_stock ps
                WHERE ps.id IN (" . implode(',', array_fill(0, count($stockIds), '?')) . ")
                AND ps.quantity > 0
                ORDER BY ps.expiry_date ASC, ps.production_date ASC
            ");
            $stockQuery->execute($stockIds);
            $stocks = $stockQuery->fetchAll(PDO::FETCH_ASSOC);
            
            // Check total available quantity
            $totalAvailable = 0;
            foreach ($stocks as $stock) {
                $totalAvailable += $stock['quantity'];
            }
            
            if ($totalAvailable < $quantity) {
                throw new Exception("Not enough stock for product ID $productId. Available: $totalAvailable, Requested: $quantity");
            }
            
            // Distribute the quantity across batches (FIFO approach)
            $remainingQuantity = $quantity;
            $salesRecorded = false;
            
            foreach ($stocks as $stock) {
                if ($remainingQuantity <= 0) break;
                
                $stockId = $stock['id'];
                $availableQty = $stock['quantity'];
                $qtyToDeduct = min($availableQty, $remainingQuantity);
                
                // Check if there's already a sales record for this product batch on this date
                $existingSalesStmt = $pdo->prepare("
                    SELECT id, quantity_sold 
                    FROM sales 
                    WHERE product_id = ? 
                    AND stock_id = ?
                    AND branch_id = ? 
                    AND sales_date = ? 
                    AND (archived = 0 OR archived IS NULL)
                    LIMIT 1
                ");
                $existingSalesStmt->execute([
                    $productId,
                    $stockId,
                    $_SESSION['branch_id'],
                    $salesDate
                ]);
                $existingSales = $existingSalesStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingSales) {
                    // Update existing sales record
                    $updateStmt = $pdo->prepare("
                        UPDATE sales 
                        SET quantity_sold = quantity_sold + ?, 
                        updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        $qtyToDeduct,
                        $existingSales['id']
                    ]);
                } else {
                    // Insert new sales record
                    $insertStmt = $pdo->prepare("
                        INSERT INTO sales (product_id, stock_id, branch_id, staff_id, quantity_sold, sales_date, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $insertStmt->execute([
                        $productId,
                        $stockId,
                        $_SESSION['branch_id'],
                        $_SESSION['user_id'],
                        $qtyToDeduct,
                        $salesDate
                    ]);
                }
                
                // Update product quantity in stock
                $updateStmt = $pdo->prepare("
                    UPDATE product_stock 
                    SET quantity = quantity - ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$qtyToDeduct, $stockId]);
                
                $remainingQuantity -= $qtyToDeduct;
                $salesRecorded = true;
            }
            
            if ($salesRecorded) {
                $salesRecorded = true;
            }
        }
        
        if (!$salesRecorded) {
            throw new Exception("No valid sales quantities entered. Please enter at least one quantity greater than zero.");
        }
        
        // Commit transaction
        $pdo->commit();
        
        $message = 'Sales records have been successfully added! Product stocks updated.';
        $messageType = 'success';
        
        // Reset form
        $date = date('Y-m-d');
        
        // Reload products to reflect updated quantities
        $stmt->execute([$_SESSION['branch_id']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle edit/update sales record action
if (isset($_POST['update_sales'])) {
    try {
        $salesId = $_POST['sales_id'];
        $newQuantity = $_POST['quantity_sold'];
        $salesDate = $_POST['sales_date'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get current sales record to calculate quantity difference
        $currentSalesStmt = $pdo->prepare("
            SELECT s.quantity_sold, s.product_id, s.stock_id
            FROM sales s 
            WHERE s.id = ? AND s.branch_id = ?
        ");
        $currentSalesStmt->execute([$salesId, $_SESSION['branch_id']]);
        $currentSales = $currentSalesStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentSales) {
            throw new Exception("Sales record not found.");
        }
        
        // Calculate quantity difference
        $quantityDifference = $newQuantity - $currentSales['quantity_sold'];
        
        // Check if there's enough stock if we're increasing the quantity
        if ($quantityDifference > 0) {
            $stockCheckStmt = $pdo->prepare("
                SELECT quantity 
                FROM product_stock
                WHERE id = ?
            ");
            $stockCheckStmt->execute([$currentSales['stock_id']]);
            $stockCheck = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stockCheck['quantity'] < $quantityDifference) {
                throw new Exception("Not enough stock available to update this sales record.");
            }
        }
        
        // Update sales record
        $updateSalesStmt = $pdo->prepare("
            UPDATE sales 
            SET quantity_sold = ?, sales_date = ?
            WHERE id = ? AND branch_id = ?
        ");
        $updateSalesStmt->execute([
            $newQuantity,
            $salesDate,
            $salesId,
            $_SESSION['branch_id']
        ]);
        
        // Update product stock
        $updateStockStmt = $pdo->prepare("
            UPDATE product_stock 
            SET quantity = quantity - ?
            WHERE id = ?
        ");
        $updateStockStmt->execute([
            $quantityDifference,
            $currentSales['stock_id']
        ]);
        
        $pdo->commit();
        
        $message = "Sales record updated successfully!";
        $messageType = "success";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle archive sales record action
if (isset($_POST['archive_sales'])) {
    try {
        $salesId = $_POST['sales_id'];
        
        // Update archive status
        $archiveStmt = $pdo->prepare("
            UPDATE sales 
            SET archived = 1
            WHERE id = ? AND branch_id = ?
        ");
        $archiveStmt->execute([
            $salesId,
            $_SESSION['branch_id']
        ]);
        
        $message = "Sales record archived successfully!";
        $messageType = "success";
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Add archived filter to query
$showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] == 1;

// Update the count query with archived filter
$countSql = "
    SELECT COUNT(*) 
    FROM sales 
    WHERE branch_id = ?
";

if (!$showArchived) {
    $countSql .= " AND (archived = 0 OR archived IS NULL)";
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute([$_SESSION['branch_id']]);
$totalSales = $countStmt->fetchColumn();
$totalPages = ceil($totalSales / $itemsPerPage);

// Update recent sales query with archived filter
$recentSalesSql = "
    SELECT s.id, s.product_id, s.stock_id, pi.name as product_name, s.quantity_sold, 
           s.sales_date, pi.price_per_unit, s.archived
    FROM sales s
    JOIN product_info pi ON s.product_id = pi.id
    WHERE s.branch_id = ?
";

if (!$showArchived) {
    $recentSalesSql .= " AND (s.archived = 0 OR s.archived IS NULL)";
}

$recentSalesSql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";

$recentSalesStmt = $pdo->prepare($recentSalesSql);
$recentSalesStmt->bindValue(1, $_SESSION['branch_id'], PDO::PARAM_INT);
$recentSalesStmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
$recentSalesStmt->bindValue(3, $offset, PDO::PARAM_INT);
$recentSalesStmt->execute();
$recentSales = $recentSalesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Record Sales - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primarycol: '#47663B',
                        sec: '#E8ECD7',
                        third: '#EED3B1',
                        fourth: '#1F4529',
                    }
                }
            }
        }

        $(document).ready(function() {
            $('#toggleSidebar').on('click', function() {
                $('#sidebar').toggleClass('-translate-x-full');
            });

            $('#closeSidebar').on('click', function() {
                $('#sidebar').addClass('-translate-x-full');
            });
            
            // Calculate totals when quantities change
            $('.quantity-input').on('change', function() {
                calculateRowTotal($(this));
                calculateGrandTotal();
            });
            
            function calculateRowTotal(input) {
                const quantity = parseInt(input.val()) || 0;
                const price = parseFloat(input.data('price'));
                const total = quantity * price;
                input.closest('tr').find('.row-total').text('₱' + total.toFixed(2));
            }
            
            function calculateGrandTotal() {
                let grandTotal = 0;
                $('.quantity-input').each(function() {
                    const quantity = parseInt($(this).val()) || 0;
                    const price = parseFloat($(this).data('price'));
                    grandTotal += quantity * price;
                });
                $('#grand-total').text('₱' + grandTotal.toFixed(2));
            }
        });
    </script>
    <!-- Add this print CSS -->
    <style>
        @media print {
            /* Hide elements not needed when printing */
            nav, button, .btn, form, .modal, .no-print {
                display: none !important;
            }
            
            /* Hide the left sidebar */
            #sidebar, #toggleSidebar, #closeSidebar {
                display: none !important;
            }
            
            /* Reset layout for printing */
            body {
                display: block !important;
                width: 100% !important;
                min-height: auto !important;
                overflow: visible !important;
                background: white !important;
            }
            
            /* Expand main content */
            .p-7 {
                padding: 0 !important;
                width: 100% !important;
            }
            
            /* Make sure tables expand properly */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            
            th, td {
                border: 1px solid #ddd !important;
                padding: 8px !important;
                text-align: left !important;
            }
            
            /* Add print title */
            .print-title {
                display: block !important;
                text-align: center;
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 20px;
            }
            
            /* Format grid for printing */
            .grid {
                display: block !important;
            }
            
            .grid > div:first-child {
                display: none !important; /* Hide the sales entry form */
            }
            
            .grid > div:last-child {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                background: none !important;
            }
            
            /* Hide action buttons in table */
            table th:last-child, 
            table td:last-child {
                display: none !important;
            }
            
            /* Hide pagination */
            .join {
                display: none !important;
            }
            
            /* Add page break inside avoid */
            tr {
                page-break-inside: avoid !important;
            }
        }
        .summary-row {
            display: none;
            background-color: #f0f0f0;
        }

        @media print {
            .summary-row {
                display: table-row !important;
                font-weight: bold !important;
                background-color: #f0f0f0 !important;
            }
        }
    </style>
</head>

<body class="flex h-screen">
    <?php include ('../layout/staff_nav.php'); ?>

    <div class="p-7 w-full overflow-y-auto">
        <div>
        <nav class="mb-4">
                <ol class="flex items-center gap-2 text-gray-600">
                    <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="record_sales.php" class="hover:text-primarycol">Record Sales</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="product_stocks.php" class="hover:text-primarycol">Product Stocks</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Excess</a></li>
                    <li class="text-gray-400">/</li>
                    
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Excess Records</a></li>
                </ol>
            </nav>
            <h1 class="text-3xl font-bold mb-6 text-primarycol">Record Daily Sales</h1>
            <p class="text-gray-500 mt-2">Track sales for products in your inventory</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?> mt-4">
                <div>
                    <?php if ($messageType === 'success'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <!-- Sales Entry Form -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4 text-primarycol">Record New Sales</h2>
                
                <?php if (empty($products)): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-700 p-4 rounded-md mb-4">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p>No active products available. Please add products first.</p>
                        </div>
                        <a href="product_data.php" class="mt-3 inline-block px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700 text-sm">
                            Add New Product
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label for="sales_date" class="block text-sm font-medium text-gray-700 mb-1">Sales Date</label>
                            <input type="date" id="sales_date" name="sales_date" value="<?= htmlspecialchars($date) ?>" 
                                  max="<?= date('Y-m-d') ?>"
                                  class="input input-bordered w-full" required>
                        </div>
                        
                        <div class="mt-4">
                            <h3 class="text-lg font-medium mb-2">Product Sales</h3>
                            <div class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <tr class="bg-sec">
                                            <th>Product Name</th>
                                            <th>Available Stock</th>
                                            <th>Price/Unit</th>
                                            <th>Quantity Sold</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <?php 
                                                        $imgPath = '../../assets/images/default-product.jpg';
                                                        if (!empty($product['image'])) {
                                                            if (strpos($product['image'], '/') !== false) {
                                                                // Path already has structure
                                                                if (strpos($product['image'], '../../') === 0) {
                                                                    $imgPath = $product['image'];
                                                                } else if (strpos($product['image'], 'assets/') === 0) {
                                                                    $imgPath = '../../' . $product['image'];
                                                                } else {
                                                                    $imgPath = $product['image'];
                                                                }
                                                            } else {
                                                                // Just a filename
                                                                $imgPath = "../../assets/uploads/products/" . $product['image'];
                                                            }
                                                        }
                                                        ?>
                                                        <img src="<?= htmlspecialchars($imgPath) ?>" alt="Product Image" class="h-8 w-8 object-cover rounded" 
                                                            onerror="this.src='../../assets/images/default-product.jpg'"/>
                                                        <span><?= htmlspecialchars($product['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($product['stock_quantity']) ?></td>
                                                <td>₱<?= number_format($product['price_per_unit'], 2) ?></td>
                                                <td>
                                                    <input type="number" name="sales[<?= $product['id'] ?>][quantity]" 
                                                           class="input input-bordered w-24 quantity-input" 
                                                           data-price="<?= $product['price_per_unit'] ?>"
                                                           min="0" max="<?= $product['stock_quantity'] ?>" 
                                                           placeholder="0">
                                                    <input type="hidden" name="sales[<?= $product['id'] ?>][stock_ids]" 
                                                           value="<?= $product['stock_ids'] ?>">
                                                </td>
                                                <td><span class="row-total">₱0.00</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-bold">
                                            <td colspan="4" class="text-right">Grand Total:</td>
                                            <td id="grand-total">₱0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" class="btn bg-primarycol hover:bg-green-700 text-white">
                                Record Sales
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Recent Sales Records -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-primarycol">Recent Sales Records</h2>
                    
                    <!-- Controls -->
                    <div class="flex items-center space-x-2">
                        <!-- Print button -->
                        <button id="print-sales-btn" class="btn btn-sm bg-primarycol text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print
                        </button>
                        
                        <!-- Archive toggle -->
                        <a href="?<?= $showArchived ? '' : 'show_archived=1' ?>" class="btn btn-sm <?= $showArchived ? 'btn-outline' : 'bg-primarycol text-white' ?>">
                            <?= $showArchived ? 'Hide Archived' : 'Show Archived' ?>
                        </a>
                    </div>
                </div>
                
                <?php if (empty($recentSales)): ?>
                    <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-center">No sales records found. Start recording sales to see them here.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr class="bg-sec">
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSales as $sale): 
                                    $total = $sale['quantity_sold'] * $sale['price_per_unit'];
                                    $formattedDate = date('F j, Y', strtotime($sale['sales_date']));
                                ?>
                                    <tr<?= $sale['archived'] ? ' class="bg-gray-100 text-gray-500"' : '' ?>>
                                        <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                        <td><?= htmlspecialchars($sale['quantity_sold']) ?></td>
                                        <td>₱<?= number_format($sale['price_per_unit'], 2) ?></td>
                                        <td>₱<?= number_format($total, 2) ?></td>
                                        <td><?= htmlspecialchars($formattedDate) ?></td>
                                        <td>
                                            <?php if (!$sale['archived']): ?>
                                            <div class="flex justify-center space-x-2">
                                                <button 
                                                    class="edit-sales-btn btn btn-sm btn-outline btn-success"
                                                    data-id="<?= $sale['id'] ?>"
                                                    data-product-id="<?= $sale['product_id'] ?>"
                                                    data-product-name="<?= htmlspecialchars($sale['product_name']) ?>"
                                                    data-quantity="<?= $sale['quantity_sold'] ?>"
                                                    data-date="<?= $sale['sales_date'] ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13" />
                                                    </svg>
                                                    Edit
                                                </button>
                                                
                                                <button 
                                                    class="archive-sales-btn btn btn-sm btn-outline btn-warning"
                                                    data-id="<?= $sale['id'] ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                                    </svg>
                                                    Archive
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <span class="badge badge-ghost">Archived</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php 
                                $totalQuantity = 0;
                                $totalValue = 0;
                                ?>
                                <?php foreach ($recentSales as $sale): 
                                    $total = $sale['quantity_sold'] * $sale['price_per_unit'];
                                    $totalQuantity += $sale['quantity_sold'];
                                    $totalValue += $total;
                                endforeach; ?>

                                <tr class="font-bold summary-row">
                                    <td colspan="1">TOTAL</td>
                                    <td><?= $totalQuantity ?></td>
                                    <td>-</td>
                                    <td>₱<?= number_format($totalValue, 2) ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="flex justify-center mt-6">
                            <div class="join">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>" class="join-item btn">«</a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $startPage + 4);
                                
                                if ($startPage > 1): ?>
                                    <a href="?page=1" class="join-item btn">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="join-item btn btn-disabled">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?page=<?= $i ?>" class="join-item btn <?= $i === $page ? 'btn-active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span class="join-item btn btn-disabled">...</span>
                                    <?php endif; ?>
                                    <a href="?page=<?= $totalPages ?>" class="join-item btn"><?= $totalPages ?></a>
                                <?php endif; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>" class="join-item btn">»</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Sales Modal -->
    <dialog id="edit_sales_modal" class="modal">
        <div class="modal-box w-11/12 max-w-3xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-lg text-primarycol">Edit Sales Record</h3>
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                </form>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_sales_id" name="sales_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                    <input type="text" id="edit_product_name" class="input input-bordered w-full" readonly>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Sold</label>
                        <input type="number" id="edit_quantity" name="quantity_sold" min="1" step="1" required class="input input-bordered w-full">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sales Date</label>
                        <input type="date" id="edit_sales_date" name="sales_date" required class="input input-bordered w-full">
                    </div>
                </div>
                
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('edit_sales_modal').close();" class="btn">Cancel</button>
                    <button type="submit" name="update_sales" class="btn bg-primarycol text-white hover:bg-fourth">Update Record</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Archive Sales Modal -->
    <dialog id="archive_sales_modal" class="modal">
        <div class="modal-box">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-lg text-primarycol">Archive Sales Record</h3>
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                </form>
            </div>
            <form method="POST">
                <input type="hidden" id="archive_sales_id" name="sales_id">
                
                <p class="py-4">Are you sure you want to archive this sales record? This action can't be undone.</p>
                
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('archive_sales_modal').close();" class="btn">Cancel</button>
                    <button type="submit" name="archive_sales" class="btn bg-orange-500 text-white hover:bg-orange-600">Archive Record</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        $(document).ready(function() {
            // Edit sales button
            $('.edit-sales-btn').on('click', function() {
                const salesId = $(this).data('id');
                const productName = $(this).data('product-name');
                const quantity = $(this).data('quantity');
                const date = $(this).data('date');
                
                // Set the values in the edit form
                $('#edit_sales_id').val(salesId);
                $('#edit_product_name').val(productName);
                $('#edit_quantity').val(quantity);
                $('#edit_sales_date').val(date);
                
                // Open the modal
                document.getElementById('edit_sales_modal').showModal();
            });
            
            // Archive sales button
            $('.archive-sales-btn').on('click', function() {
                const salesId = $(this).data('id');
                
                // Set the sales ID in the archive form
                $('#archive_sales_id').val(salesId);
                
                // Open the modal
                document.getElementById('archive_sales_modal').showModal();
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            // Existing code...
            
            // Print button functionality
            $('#print-sales-btn').on('click', function() {
                // Add a temporary title that will only show when printing
                const $title = $('<div class="print-title" style="display:none;">Sales Records Report</div>');
                const $subtitle = $('<div class="print-title" style="display:none; font-size:14pt;">Generated on: ' + new Date().toLocaleDateString() + '</div>');
                
                $('body').prepend($title);
                $('body').prepend($subtitle);
                
                // Print the page
                window.print();
                
                // Remove the temporary title after printing
                setTimeout(function() {
                    $title.remove();
                    $subtitle.remove();
                }, 100);
            });
        });
    </script>
</body>
</html>