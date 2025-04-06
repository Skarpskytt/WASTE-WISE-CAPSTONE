<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

checkAuth(['staff']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Get stock ID from URL parameter
$stockId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$stockId) {
    // No stock ID provided, redirect back to stock list
    header('Location: product_stocks.php');
    exit;
}

// Get stock details with product info
$sql = "
    SELECT 
        ps.*,
        pi.name,
        pi.category,
        pi.price_per_unit,
        pi.image,
        b.name as branch_name
    FROM product_stock ps
    JOIN product_info pi ON ps.product_info_id = pi.id
    JOIN branches b ON ps.branch_id = b.id
    WHERE ps.id = :stockId AND ps.branch_id = :branchId
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':stockId' => $stockId,
    ':branchId' => $branchId
]);

$stock = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stock) {
    // Stock not found or doesn't belong to user's branch
    header('Location: product_stocks.php');
    exit;
}

// Calculate expiry status and days remaining
$today = strtotime(date('Y-m-d'));
$expiryDate = strtotime($stock['expiry_date']);
$daysUntilExpiry = round(($expiryDate - $today) / (60 * 60 * 24));
$productionDate = strtotime($stock['production_date']);
$daysSinceProduction = round(($today - $productionDate) / (60 * 60 * 24));

// Determine expiry status for styling
$expiryStatus = '';
$expiryStatusText = '';
$expiryStatusClass = '';

if ($daysUntilExpiry < 0) {
    $expiryStatus = 'expired';
    $expiryStatusText = 'Expired';
    $expiryStatusClass = 'bg-red-100 text-red-800';
} elseif ($daysUntilExpiry <= 7) {
    $expiryStatus = 'expiring-soon';
    $expiryStatusText = 'Expiring Soon';
    $expiryStatusClass = 'bg-amber-100 text-amber-800';
} else {
    $expiryStatus = 'good';
    $expiryStatusText = 'Good';
    $expiryStatusClass = 'bg-green-100 text-green-800';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Stock Details - Bea Bakes</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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
    </script>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include ('../layout/staff_nav.php'); ?>

    <div class="p-7 w-full overflow-y-auto">
        <nav class="mb-4">
            <ol class="flex items-center gap-2 text-gray-600">
                <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="product_stocks.php" class="hover:text-primarycol">Product Stocks</a></li>
                <li class="text-gray-400">/</li>
                <li class="text-primarycol font-medium">View Stock</li>
            </ol>
        </nav>
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-primarycol">Stock Details</h1>
            <div class="flex gap-2">
                <a href="product_stocks.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Back to Stock List
                </a>
                <?php if ($daysUntilExpiry < 0): ?>
                <a href="waste_product_input.php?stock_id=<?= $stockId ?>" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Record as Waste
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Product Image & Basic Info -->
            <div class="col-span-1 bg-white rounded-lg shadow p-6">
                <div class="mb-4">
                    <?php 
                    $imagePath = !empty($stock['image']) ? $stock['image'] : '../../assets/images/Company Logo.jpg';
                    ?>
                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($stock['name']) ?>" 
                         class="w-full h-60 object-cover rounded-lg">
                </div>

                <h2 class="text-xl font-bold mb-1"><?= htmlspecialchars($stock['name']) ?></h2>
                <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($stock['category']) ?></p>
                
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Price per Unit:</span>
                    <span class="font-medium">₱<?= number_format($stock['price_per_unit'], 2) ?></span>
                </div>

                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Branch:</span>
                    <span class="font-medium"><?= htmlspecialchars($stock['branch_name']) ?></span>
                </div>

            </div>

            <!-- Stock Details -->
            <div class="col-span-2 bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold">Stock Information</h2>
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?= $expiryStatusClass ?>">
                        <?= $expiryStatusText ?>
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-medium text-gray-700 mb-4">Batch Information</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Batch Number:</span>
                                <span class="font-mono font-medium"><?= htmlspecialchars($stock['batch_number']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Quantity:</span>
                                <span class="font-medium">
                                    <?= number_format($stock['quantity']) ?> 
                                    <?= $stock['unit_type'] ?><?= $stock['quantity'] > 1 ? 's' : '' ?>
                                </span>
                            </div>
                            <?php if (!empty($stock['pieces_per_box']) && $stock['pieces_per_box'] > 1): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Pieces per Box:</span>
                                <span class="font-medium"><?= $stock['pieces_per_box'] ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Value:</span>
                                <span class="font-medium">₱<?= number_format($stock['quantity'] * $stock['price_per_unit'], 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-medium text-gray-700 mb-4">Dates</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Production Date:</span>
                                <span><?= date('M d, Y', strtotime($stock['production_date'])) ?></span>
                            </div>
                            <?php if (!empty($stock['production_time'])): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Production Time:</span>
                                <span><?= date('h:i A', strtotime($stock['production_time'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($stock['best_before'])): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Best Before:</span>
                                <span><?= date('M d, Y', strtotime($stock['best_before'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Expiry Date:</span>
                                <span class="<?= $daysUntilExpiry < 7 ? ($daysUntilExpiry < 0 ? 'text-red-600 font-medium' : 'text-amber-600 font-medium') : '' ?>">
                                    <?= date('M d, Y', strtotime($stock['expiry_date'])) ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Created:</span>
                                <span><?= date('M d, Y h:i A', strtotime($stock['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline/Summary -->
                <div class="mt-8 border-t pt-6">
                    <h3 class="font-medium text-gray-700 mb-4">Timeline</h3>
                    
                    <div class="relative">
                        <div class="absolute h-full w-0.5 bg-gray-200 left-3.5 top-0"></div>
                        
                        <div class="relative flex items-start mb-6">
                            <div class="h-7 w-7 rounded-full border-2 border-primarycol bg-white flex items-center justify-center z-10 mt-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primarycol" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium">Production Date</h4>
                                <p class="text-xs text-gray-500">
                                    <?= date('M d, Y', strtotime($stock['production_date'])) ?>
                                    <?= !empty($stock['production_time']) ? ' at ' . date('h:i A', strtotime($stock['production_time'])) : '' ?>
                                </p>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?= $daysSinceProduction ?> days ago
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($stock['best_before'])): ?>
                        <div class="relative flex items-start mb-6">
                            <div class="h-7 w-7 rounded-full border-2 border-blue-500 bg-white flex items-center justify-center z-10 mt-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium">Best Before</h4>
                                <p class="text-xs text-gray-500"><?= date('M d, Y', strtotime($stock['best_before'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="relative flex items-start">
                            <div class="h-7 w-7 rounded-full border-2 <?= $daysUntilExpiry < 0 ? 'border-red-500' : ($daysUntilExpiry <= 7 ? 'border-amber-500' : 'border-green-500') ?> bg-white flex items-center justify-center z-10 mt-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 <?= $daysUntilExpiry < 0 ? 'text-red-500' : ($daysUntilExpiry <= 7 ? 'text-amber-500' : 'text-green-500') ?>" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium">Expiry Date</h4>
                                <p class="text-xs text-gray-500"><?= date('M d, Y', strtotime($stock['expiry_date'])) ?></p>
                                <p class="text-xs <?= $daysUntilExpiry < 0 ? 'text-red-600 font-medium' : ($daysUntilExpiry <= 7 ? 'text-amber-600 font-medium' : 'text-gray-600') ?> mt-1">
                                    <?php if ($daysUntilExpiry < 0): ?>
                                        Expired <?= abs($daysUntilExpiry) ?> days ago
                                    <?php else: ?>
                                        <?= $daysUntilExpiry ?> days remaining
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="mt-8 flex space-x-3">
                    <a href="edit_stock.php?id=<?= $stockId ?>" class="px-4 py-2 bg-primarycol text-white rounded-lg hover:bg-fourth flex-1 text-center">
                        Edit Stock
                    </a>
                    <?php if ($daysUntilExpiry < 0): ?>
                    <a href="waste_product_input.php?stock_id=<?= $stockId ?>" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex-1 text-center">
                        Record as Waste
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Tips -->
        <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Stock Management Tips</h3>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <ul class="list-disc list-inside space-y-1 text-sm text-blue-800">
                    <li>For perishable products, follow <span class="font-medium">FEFO</span> (First Expired, First Out) method</li>
                    <li>For non-perishable products, follow <span class="font-medium">FIFO</span> (First In, First Out) method</li>
                    <li>Check expiry dates regularly to minimize waste</li>
                    <li>Record expired products as waste for proper tracking</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>