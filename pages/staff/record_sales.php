<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

$pdo = getPDO();

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
    SELECT * 
    FROM products 
    WHERE branch_id = ? 
    AND expiry_date > CURRENT_DATE
    AND stock_quantity > 0
    ORDER BY name ASC
");
$stmt->execute([$_SESSION['branch_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $salesDate = $_POST['sales_date'];
        $productSales = $_POST['sales'] ?? [];
        
        // Start transaction
        $pdo->beginTransaction();
        
        $salesRecorded = false;
        
        // Insert each product sale
        foreach ($productSales as $productId => $quantity) {
            // Only process if quantity is greater than 0
            if (empty($quantity) || $quantity <= 0) {
                continue;
            }
            
            // Check if product exists, belongs to the branch, and has enough quantity
            $productStmt = $pdo->prepare("
                SELECT id, stock_quantity 
                FROM products 
                WHERE id = ? AND branch_id = ?
            ");
            $productStmt->execute([$productId, $_SESSION['branch_id']]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Invalid product selected.");
            }
            
            if ($product['stock_quantity'] < $quantity) {
                throw new Exception("Not enough stock for one or more products. Please check quantities.");
            }
            
            // Insert sales record
            $insertStmt = $pdo->prepare("
                INSERT INTO sales (product_id, branch_id, staff_id, quantity_sold, sales_date, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([
                $productId,
                $_SESSION['branch_id'],
                $_SESSION['user_id'],
                $quantity,
                $salesDate
            ]);
            
            // Update product quantity in stock
            $updateStmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ? AND branch_id = ?
            ");
            $updateStmt->execute([$quantity, $productId, $_SESSION['branch_id']]);
            
            $salesRecorded = true;
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

// Count total sales records for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM sales 
    WHERE branch_id = ?
");
$countStmt->execute([$_SESSION['branch_id']]);
$totalSales = $countStmt->fetchColumn();
$totalPages = ceil($totalSales / $itemsPerPage);

// Fetch recent sales for this branch with pagination - removed staff_id since we don't need it
$recentSalesStmt = $pdo->prepare("
    SELECT s.id, s.product_id, p.name as product_name, s.quantity_sold, 
           s.sales_date, p.price_per_unit
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE s.branch_id = ?
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
");
$recentSalesStmt->bindValue(1, $_SESSION['branch_id'], PDO::PARAM_INT);
$recentSalesStmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
$recentSalesStmt->bindValue(3, $offset, PDO::PARAM_INT);
$recentSalesStmt->execute();
$recentSales = $recentSalesStmt->fetchAll(PDO::FETCH_ASSOC);

// No need for this code since we're not displaying staff names anymore
// $userNames = [];
// if (!empty($recentSales)) {
//     $staffIds = array_unique(array_column($recentSales, 'staff_id'));
//     ...
// }
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
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Waste</a></li>
                    <li class="text-gray-400">/</li>
                    
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Waste Records</a></li>
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
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
                                                        $imgPath = !empty($product['image']) ? $product['image'] : '../../assets/images/default-product.jpg';
                                                        ?>
                                                        <img src="<?= htmlspecialchars($imgPath) ?>" alt="Product Image" class="h-8 w-8 object-cover rounded" />
                                                        <span><?= htmlspecialchars($product['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($product['stock_quantity']) ?></td>
                                                <td>₱<?= number_format($product['price_per_unit'], 2) ?></td>
                                                <td>
                                                    <input type="number" name="sales[<?= $product['id'] ?>]" 
                                                           class="input input-bordered w-24 quantity-input" 
                                                           data-price="<?= $product['price_per_unit'] ?>"
                                                           min="0" max="<?= $product['stock_quantity'] ?>" 
                                                           placeholder="0">
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
                <h2 class="text-xl font-semibold mb-4 text-primarycol">Recent Sales Records</h2>
                
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSales as $sale): 
                                    $total = $sale['quantity_sold'] * $sale['price_per_unit'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                        <td><?= htmlspecialchars($sale['quantity_sold']) ?></td>
                                        <td>₱<?= number_format($sale['price_per_unit'], 2) ?></td>
                                        <td>₱<?= number_format($total, 2) ?></td>
                                        <td><?= htmlspecialchars($sale['sales_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
</body>
</html>