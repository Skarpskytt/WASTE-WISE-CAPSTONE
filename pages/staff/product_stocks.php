<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

checkAuth(['staff', 'company']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Set default filters and pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get filter values from GET parameters
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$expiryFilter = isset($_GET['expiry']) ? $_GET['expiry'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'expiry_asc';
$algorithm = isset($_GET['algorithm']) ? $_GET['algorithm'] : 'fefo'; // Default to FEFO
$showZeroQuantity = isset($_GET['zero_qty']) ? $_GET['zero_qty'] === '1' : false;

// Build the WHERE clause for filters
$whereClause = "WHERE ps.branch_id = :branchId AND (ps.archived_at IS NULL OR ps.is_archived = 0)";
$params = [':branchId' => $branchId];

// Only filter out zero quantity items if the showZeroQuantity flag is false
if (!$showZeroQuantity) {
    $whereClause .= " AND ps.quantity > 0";
}

if (!empty($categoryFilter)) {
    $whereClause .= " AND pi.category = :category";
    $params[':category'] = $categoryFilter;
}

if (!empty($search)) {
    $whereClause .= " AND (pi.name LIKE :search OR ps.batch_number LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($expiryFilter)) {
    $today = date('Y-m-d');
    switch($expiryFilter) {
        case 'expired':
            $whereClause .= " AND ps.expiry_date < :today";
            $params[':today'] = $today;
            break;
        case 'expiring_soon':
            $nextWeek = date('Y-m-d', strtotime('+7 days'));
            $whereClause .= " AND ps.expiry_date BETWEEN :today AND :nextWeek";
            $params[':today'] = $today;
            $params[':nextWeek'] = $nextWeek;
            break;
        case 'good':
            $nextWeek = date('Y-m-d', strtotime('+7 days'));
            $whereClause .= " AND ps.expiry_date > :nextWeek";
            $params[':nextWeek'] = $nextWeek;
            break;
    }
}

// Build the ORDER BY clause
$orderClause = "ORDER BY ";
switch($sortBy) {
    case 'name_asc':
        $orderClause .= "pi.name ASC";
        break;
    case 'name_desc':
        $orderClause .= "pi.name DESC";
        break;
    case 'expiry_asc':
        $orderClause .= "ps.expiry_date ASC";
        break;
    case 'expiry_desc':
        $orderClause .= "ps.expiry_date DESC";
        break;
    case 'quantity_asc':
        $orderClause .= "ps.quantity ASC";
        break;
    case 'quantity_desc':
        $orderClause .= "ps.quantity DESC";
        break;
    case 'production_date_asc':
        $orderClause .= "ps.production_date ASC";
        break;
    case 'production_date_desc':
        $orderClause .= "ps.production_date DESC";
        break;
    default:
        $orderClause .= "ps.expiry_date ASC";
}

// Get total count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM product_stock ps 
    JOIN product_info pi ON ps.product_info_id = pi.id 
    $whereClause
");
$countStmt->execute($params);
$totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $limit);

// Get product stocks with pagination
$sql = "
    SELECT 
        ps.id,
        ps.batch_number,
        ps.quantity,
        ps.production_date,
        ps.expiry_date,
        ps.best_before,
        ps.production_time,
        ps.pieces_per_box,
        ps.unit_type,
        pi.id as product_id,
        pi.name,
        pi.category,
        pi.price_per_unit,
        pi.image
    FROM product_stock ps
    JOIN product_info pi ON ps.product_info_id = pi.id
    $whereClause
    $orderClause
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter dropdown
$categoriesStmt = $pdo->prepare("
    SELECT DISTINCT pi.category 
    FROM product_info pi 
    JOIN product_stock ps ON pi.id = ps.product_info_id 
    WHERE ps.branch_id = ? 
    ORDER BY pi.category
");
$categoriesStmt->execute([$branchId]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get total stock stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_batches,
        SUM(ps.quantity) as total_items,
        COUNT(CASE WHEN ps.expiry_date < CURRENT_DATE() THEN 1 END) as expired_batches
    FROM product_stock ps
    WHERE ps.branch_id = ?
");
$statsStmt->execute([$branchId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get expiring soon count
$expiringStmt = $pdo->prepare("
    SELECT COUNT(*) as expiring_soon
    FROM product_stock ps
    WHERE ps.branch_id = ? AND ps.expiry_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
");
$expiringStmt->execute([$branchId]);
$expiringStats = $expiringStmt->fetch(PDO::FETCH_ASSOC);
$stats['expiring_soon'] = $expiringStats['expiring_soon'];

// Enhanced FEFO algorithm with category-specific urgency scoring and value prioritization
$fefoSql = "
    SELECT 
        ps.id,
        ps.batch_number,
        ps.quantity,
        ps.production_date,
        ps.expiry_date,
        pi.name,
        pi.category,
        pi.image,
        DATEDIFF(ps.expiry_date, CURRENT_DATE()) as days_until_expiry,
        ps.quantity * pi.price_per_unit as stock_value,
        CASE 
            -- High priority for very short shelf life items near expiry (like donuts, cheesecakes)
            WHEN pi.category IN ('Donuts', 'Cheesecakes') AND DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= 1 THEN 10
            WHEN pi.category IN ('Donuts', 'Cheesecakes') AND DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= 2 THEN 8
            -- Dynamic priority based on product shelf life percentage
            WHEN DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= GREATEST(1, pi.shelf_life_days * 0.2) THEN 7
            WHEN DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= GREATEST(2, pi.shelf_life_days * 0.4) THEN 5
            WHEN DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= GREATEST(3, pi.shelf_life_days * 0.6) THEN 3
            ELSE 1
        END as urgency_score
    FROM product_stock ps
    JOIN product_info pi ON ps.product_info_id = pi.id
    WHERE ps.branch_id = ? 
    AND ps.quantity > 0
    AND ps.expiry_date >= CURRENT_DATE()
    AND (ps.archived_at IS NULL AND ps.is_archived = 0)
    ORDER BY urgency_score DESC, ps.expiry_date ASC, stock_value DESC
    LIMIT 8
";

$fefoStmt = $pdo->prepare($fefoSql);
$fefoStmt->execute([$branchId]);
$fefoRecommendations = $fefoStmt->fetchAll(PDO::FETCH_ASSOC);

// Enhanced FIFO algorithm with aging factor and value prioritization
$fifoSql = "
    SELECT 
        ps.id,
        ps.batch_number,
        ps.quantity,
        ps.production_date,
        ps.expiry_date,
        pi.name,
        pi.category,
        pi.image,
        DATEDIFF(CURRENT_DATE(), ps.production_date) as days_since_production,
        ps.quantity * pi.price_per_unit as stock_value,
        CASE 
            -- Age relative to the product's shelf life
            WHEN DATEDIFF(CURRENT_DATE(), ps.production_date) > GREATEST(5, pi.shelf_life_days * 0.7) THEN 5
            WHEN DATEDIFF(CURRENT_DATE(), ps.production_date) > GREATEST(3, pi.shelf_life_days * 0.5) THEN 4
            WHEN DATEDIFF(CURRENT_DATE(), ps.production_date) > GREATEST(2, pi.shelf_life_days * 0.3) THEN 3
            WHEN DATEDIFF(CURRENT_DATE(), ps.production_date) > 7 THEN 2
            ELSE 1
        END as aging_score
    FROM product_stock ps
    JOIN product_info pi ON ps.product_info_id = pi.id
    WHERE ps.branch_id = ? 
    AND ps.quantity > 0
    AND ps.expiry_date >= CURRENT_DATE()
    AND (ps.archived_at IS NULL AND ps.is_archived = 0)
    ORDER BY aging_score DESC, ps.production_date ASC, stock_value DESC
    LIMIT 8
";

$fifoStmt = $pdo->prepare($fifoSql);
$fifoStmt->execute([$branchId]);
$fifoRecommendations = $fifoStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Stocks - Bea Bakes</title>
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
    
    // Toggle between FEFO and FIFO tabs
    document.addEventListener('DOMContentLoaded', function() {
        const fefoTab = document.getElementById('fefo-tab');
        const fifoTab = document.getElementById('fifo-tab');
        const fefoContent = document.getElementById('fefo-content');
        const fifoContent = document.getElementById('fifo-content');
        
        if (fefoTab && fifoTab) {
            fefoTab.addEventListener('click', function() {
                fefoTab.classList.add('bg-primarycol', 'text-white');
                fifoTab.classList.remove('bg-primarycol', 'text-white');
                fefoTab.classList.remove('bg-gray-100', 'text-gray-700');
                fifoTab.classList.add('bg-gray-100', 'text-gray-700');
                
                fefoContent.classList.remove('hidden');
                fifoContent.classList.add('hidden');
            });
            
            fifoTab.addEventListener('click', function() {
                fifoTab.classList.add('bg-primarycol', 'text-white');
                fefoTab.classList.remove('bg-primarycol', 'text-white');
                fifoTab.classList.remove('bg-gray-100', 'text-gray-700');
                fefoTab.classList.add('bg-gray-100', 'text-gray-700');
                
                fifoContent.classList.remove('hidden');
                fefoContent.classList.add('hidden');
            });
            
            // Initialize based on URL param
            const urlParams = new URLSearchParams(window.location.search);
            const algorithm = urlParams.get('algorithm') || 'fefo';
            
            if (algorithm === 'fifo') {
                fifoTab.click();
            } else {
                fefoTab.click();
            }
        }
    });
    </script>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include ('../layout/staff_nav.php'); ?>

    <div class="p-7 w-full overflow-y-auto">
        <nav class="mb-4">
            <ol class="flex items-center gap-2 text-gray-600">
                <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="add_stock.php" class="hover:text-primarycol">Add Stock</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="product_stocks.php" class="text-primarycol font-medium">Product Stocks</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Excess</a></li>
            </ol>
        </nav>
        
        <h1 class="text-3xl font-bold mb-2 text-primarycol">Product Stocks</h1>
        <p class="text-gray-600 mb-6">Manage your inventory and track product batches</p>

        <!-- Inventory Risk Assessment -->
        <?php
        // Get inventory risk assessment data
        $riskSql = "
            SELECT 
                SUM(ps.quantity * pi.price_per_unit) as total_inventory_value,
                SUM(CASE WHEN DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= 3 
                        THEN ps.quantity * pi.price_per_unit ELSE 0 END) as critical_inventory_value,
                COUNT(DISTINCT pi.category) as total_categories,
                COUNT(DISTINCT CASE WHEN DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= 3 
                                  THEN pi.category ELSE NULL END) as categories_at_risk
            FROM product_stock ps
            JOIN product_info pi ON ps.product_info_id = pi.id
            WHERE ps.branch_id = ? AND ps.quantity > 0 AND (ps.archived_at IS NULL AND ps.is_archived = 0)
        ";
        $riskStmt = $pdo->prepare($riskSql);
        $riskStmt->execute([$branchId]);
        $riskData = $riskStmt->fetch(PDO::FETCH_ASSOC);

        // Get category risk breakdown
        $categorySql = "
            SELECT 
                pi.category,
                COUNT(*) as batch_count,
                SUM(ps.quantity) as total_items,
                SUM(CASE WHEN DATEDIFF(ps.expiry_date, CURRENT_DATE()) <= 3 
                        THEN ps.quantity ELSE 0 END) as at_risk_items,
                SUM(ps.quantity * pi.price_per_unit) as category_value
            FROM product_stock ps
            JOIN product_info pi ON ps.product_info_id = pi.id
            WHERE ps.branch_id = ? AND ps.quantity > 0 AND (ps.archived_at IS NULL AND ps.is_archived = 0)
            GROUP BY pi.category
            ORDER BY at_risk_items DESC
        ";
        $categoryStmt = $pdo->prepare($categorySql);
        $categoryStmt->execute([$branchId]);
        $categoryRisks = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

        // Display Inventory Risk Dashboard
        if ($riskData['total_inventory_value'] > 0):
        ?>
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Inventory Risk Assessment</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="p-4 border rounded-lg bg-gradient-to-r from-blue-50 to-blue-100">
                    <div class="text-sm text-gray-500">Total Inventory Value</div>
                    <div class="text-2xl font-bold">₱<?= number_format($riskData['total_inventory_value'], 2) ?></div>
                </div>
                
                <div class="p-4 border rounded-lg <?= $riskData['critical_inventory_value'] > 0 ? 'bg-gradient-to-r from-amber-50 to-amber-100' : 'bg-gradient-to-r from-green-50 to-green-100' ?>">
                    <div class="text-sm text-gray-500">At-Risk Inventory (3 days)</div>
                    <div class="text-2xl font-bold">₱<?= number_format($riskData['critical_inventory_value'], 2) ?></div>
                    <?php if($riskData['total_inventory_value'] > 0): ?>
                        <div class="text-sm mt-1">
                            <?php 
                            $riskPercentage = ($riskData['critical_inventory_value'] / $riskData['total_inventory_value']) * 100;
                            echo number_format($riskPercentage, 1) . "% of inventory";
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="p-4 border rounded-lg bg-gradient-to-r from-indigo-50 to-indigo-100">
                    <div class="text-sm text-gray-500">Categories at Risk</div>
                    <div class="text-2xl font-bold"><?= $riskData['categories_at_risk'] ?> of <?= $riskData['total_categories'] ?></div>
                </div>
            </div>
            
            <?php if(count($categoryRisks) > 0): ?>
            <h3 class="text-lg font-medium text-gray-700 mb-2">Category Risk Analysis</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total Items</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">At-Risk Items</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Risk %</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($categoryRisks as $cat): 
                            $riskPercent = $cat['total_items'] > 0 ? ($cat['at_risk_items'] / $cat['total_items']) * 100 : 0;
                            $riskClass = $riskPercent > 50 ? 'text-red-600' : ($riskPercent > 20 ? 'text-amber-600' : 'text-green-600');
                        ?>
                        <tr class="border-t">
                            <td class="px-4 py-2"><?= htmlspecialchars($cat['category']) ?></td>
                            <td class="px-4 py-2 text-right"><?= number_format($cat['total_items']) ?></td>
                            <td class="px-4 py-2 text-right <?= $cat['at_risk_items'] > 0 ? 'font-medium ' . $riskClass : '' ?>">
                                <?= number_format($cat['at_risk_items']) ?>
                            </td>
                            <td class="px-4 py-2 text-right <?= $riskClass ?>">
                                <?= number_format($riskPercent, 1) ?>%
                            </td>
                            <td class="px-4 py-2 text-right">₱<?= number_format($cat['category_value'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Inventory Priority Recommendations Section -->
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <div class="border-b border-gray-200">
                <div class="flex">
                    <button id="fefo-tab" class="px-6 py-3 font-medium text-sm focus:outline-none <?= $algorithm === 'fefo' ? 'bg-primarycol text-white' : 'bg-gray-100 text-gray-700' ?>">
                        FEFO (First Expired, First Out)
                    </button>
                    <button id="fifo-tab" class="px-6 py-3 font-medium text-sm focus:outline-none <?= $algorithm === 'fifo' ? 'bg-primarycol text-white' : 'bg-gray-100 text-gray-700' ?>">
                        FIFO (First In, First Out)
                    </button>
                </div>
            </div>
            
            <!-- FEFO Content -->
            <div id="fefo-content" class="p-4 <?= $algorithm === 'fifo' ? 'hidden' : '' ?>">
                <div class="mb-3">
                    <h3 class="font-medium text-gray-900">First Expired, First Out (FEFO)</h3>
                    <p class="text-sm text-gray-600">Products listed below should be used first based on their expiration dates</p>
                </div>
                
                <?php if (count($fefoRecommendations) > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                        <?php foreach ($fefoRecommendations as $index => $item): ?>
                            <div class="border rounded-lg overflow-hidden bg-white relative">
                                <div class="absolute top-2 right-2 px-2 py-1 bg-amber-100 text-amber-800 text-xs font-bold rounded-full">
                                    <?= $item['days_until_expiry'] ?> days left
                                </div>
                                <div class="h-32 overflow-hidden bg-gray-100">
                                    <?php 
                                    // Improved image path handling
                                    $imagePath = '';
                                    if (!empty($item['image'])) {
                                        if (strpos($item['image'], '/') !== false) {
                                            // Path contains slashes - use as is
                                            $imagePath = $item['image'];
                                        } else {
                                            // Just a filename - add the full path
                                            $imagePath = "../../assets/uploads/products/" . $item['image'];
                                        }
                                    } else {
                                        // Default image
                                        $imagePath = "../../assets/images/Company Logo.jpg";
                                    }
                                    $cacheBuster = "?v=" . time();
                                    ?>
                                    <img src="<?= htmlspecialchars($imagePath . $cacheBuster) ?>" alt="" 
                                         class="w-full h-full object-cover"
                                         onerror="this.src='../../assets/images/Company Logo.jpg';">
                                </div>
                                <div class="p-3">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></h4>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($item['category']) ?></p>
                                        </div>
                                        <div class="bg-primarycol text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">
                                            <?= $index + 1 ?>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Batch:</span>
                                            <span class="font-mono"><?= htmlspecialchars($item['batch_number']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Qty:</span>
                                            <span><?= number_format($item['quantity']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Expires:</span>
                                            <span class="text-amber-600"><?= date('M d, Y', strtotime($item['expiry_date'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <p class="text-gray-500">No products available for FEFO recommendations</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- FIFO Content -->
            <div id="fifo-content" class="p-4 <?= $algorithm === 'fefo' ? 'hidden' : '' ?>">
                <div class="mb-3">
                    <h3 class="font-medium text-gray-900">First In, First Out (FIFO)</h3>
                    <p class="text-sm text-gray-600">Products listed below should be used first based on their production dates</p>
                </div>
                
                <?php if (count($fifoRecommendations) > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                        <?php foreach ($fifoRecommendations as $index => $item): ?>
                            <div class="border rounded-lg overflow-hidden bg-white relative">
                                <div class="absolute top-2 right-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                                    <?= $item['days_since_production'] ?> days old
                                </div>
                                <div class="h-32 overflow-hidden bg-gray-100">
                                    <?php 
                                    // Improved image path handling
                                    $imagePath = '';
                                    if (!empty($item['image'])) {
                                        if (strpos($item['image'], '/') !== false) {
                                            // Path contains slashes - use as is
                                            $imagePath = $item['image'];
                                        } else {
                                            // Just a filename - add the full path
                                            $imagePath = "../../assets/uploads/products/" . $item['image'];
                                        }
                                    } else {
                                        // Default image
                                        $imagePath = "../../assets/images/Company Logo.jpg";
                                    }
                                    $cacheBuster = "?v=" . time();
                                    ?>
                                    <img src="<?= htmlspecialchars($imagePath . $cacheBuster) ?>" alt="" 
                                         class="w-full h-full object-cover"
                                         onerror="this.src='../../assets/images/Company Logo.jpg';">
                                </div>
                                <div class="p-3">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></h4>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($item['category']) ?></p>
                                        </div>
                                        <div class="bg-primarycol text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">
                                            <?= $index + 1 ?>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Batch:</span>
                                            <span class="font-mono"><?= htmlspecialchars($item['batch_number']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Qty:</span>
                                            <span><?= number_format($item['quantity']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Produced:</span>
                                            <span class="text-blue-600"><?= date('M d, Y', strtotime($item['production_date'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <p class="text-gray-500">No products available for FIFO recommendations</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form class="flex flex-wrap gap-4 items-end" method="GET">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or batch #" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primarycol focus:ring focus:ring-primarycol focus:ring-opacity-50 px-3 py-2 border">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="rounded-md border-gray-300 shadow-sm focus:border-primarycol focus:ring focus:ring-primarycol focus:ring-opacity-50 px-3 py-2 border">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Status</label>
                    <select name="expiry" class="rounded-md border-gray-300 shadow-sm focus:border-primarycol focus:ring focus:ring-primarycol focus:ring-opacity-50 px-3 py-2 border">
                        <option value="">All</option>
                        <option value="expired" <?= $expiryFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="expiring_soon" <?= $expiryFilter === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon (7 days)</option>
                        <option value="good" <?= $expiryFilter === 'good' ? 'selected' : '' ?>>Good</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                    <select name="sort" class="rounded-md border-gray-300 shadow-sm focus:border-primarycol focus:ring focus:ring-primarycol focus:ring-opacity-50 px-3 py-2 border">
                        <option value="expiry_asc" <?= $sortBy === 'expiry_asc' ? 'selected' : '' ?>>Expiry Date (Earliest First)</option>
                        <option value="expiry_desc" <?= $sortBy === 'expiry_desc' ? 'selected' : '' ?>>Expiry Date (Latest First)</option>
                        <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Product Name (A-Z)</option>
                        <option value="name_desc" <?= $sortBy === 'name_desc' ? 'selected' : '' ?>>Product Name (Z-A)</option>
                        <option value="quantity_asc" <?= $sortBy === 'quantity_asc' ? 'selected' : '' ?>>Quantity (Low to High)</option>
                        <option value="quantity_desc" <?= $sortBy === 'quantity_desc' ? 'selected' : '' ?>>Quantity (High to Low)</option>
                        <option value="production_date_asc" <?= $sortBy === 'production_date_asc' ? 'selected' : '' ?>>Production Date (Oldest First)</option>
                        <option value="production_date_desc" <?= $sortBy === 'production_date_desc' ? 'selected' : '' ?>>Production Date (Newest First)</option>
                    </select>
                </div>

                <input type="hidden" name="algorithm" value="<?= htmlspecialchars($algorithm) ?>">

                <div class="flex space-x-2">
                    <button type="submit" class="px-4 py-2 bg-primarycol text-white rounded-md hover:bg-fourth">
                        Apply Filters
                    </button>
                    <a href="product_stocks.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Add Stock Button -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Stock Inventory</h2>
            <a href="add_stock.php" class="px-4 py-2 bg-primarycol text-white rounded-lg hover:bg-fourth flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Add New Stock
            </a>
        </div>

        <!-- Stock Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <?php if (count($stocks) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Batch #
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantity
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Production Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Best Before
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Expiry Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($stocks as $stock): 
                                $today = strtotime(date('Y-m-d'));
                                $expiryDate = strtotime($stock['expiry_date']);
                                $daysUntilExpiry = round(($expiryDate - $today) / (60 * 60 * 24));
                                
                                $expiryClass = '';
                                $expiryBadge = '';
                                
                                // Modified thresholds for pastries
                                if ($daysUntilExpiry < 0) {
                                    // Critical pastries
                                    $expiryClass = 'bg-red-50 text-red-800';
                                    $expiryBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Critical</span>';
                                } elseif ($daysUntilExpiry <= 1) {
                                    // Last day to sell - urgent
                                    $expiryClass = 'bg-red-50 text-red-800';
                                    $expiryBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Last Day</span>';
                                } elseif ($daysUntilExpiry <= 3) {
                                    // Sell soon - within 3 days
                                    $expiryClass = 'bg-amber-50 text-amber-800';
                                    $expiryBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Sell Soon</span>';
                                } elseif ($daysUntilExpiry <= 5) {
                                    // Getting older but still good
                                    $expiryClass = 'bg-yellow-50 text-yellow-800';
                                    $expiryBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Fresh</span>';
                                }
                                
                                // Check if this product is in the FEFO or FIFO top recommendations
                                $isFefoRecommended = false;
                                $isFifoRecommended = false;
                                
                                foreach ($fefoRecommendations as $fefoItem) {
                                    if ($fefoItem['id'] == $stock['id']) {
                                        $isFefoRecommended = true;
                                        break;
                                    }
                                }
                                
                                foreach ($fifoRecommendations as $fifoItem) {
                                    if ($fifoItem['id'] == $stock['id']) {
                                        $isFifoRecommended = true;
                                        break;
                                    }
                                }
                            ?>
                                <tr class="<?= $expiryClass ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 relative">
                                                <?php 
                                                // Improved image path handling
                                                $imagePath = '';
                                                if (!empty($stock['image'])) {
                                                    if (strpos($stock['image'], '/') !== false) {
                                                        // Path contains slashes - use as is
                                                        $imagePath = $stock['image'];
                                                    } else {
                                                        // Just a filename - add the full path
                                                        $imagePath = "../../assets/uploads/products/" . $stock['image'];
                                                    }
                                                } else {
                                                    // Default image
                                                    $imagePath = "../../assets/images/Company Logo.jpg";
                                                }
                                                $cacheBuster = "?v=" . time();
                                                ?>
                                                <img class="h-10 w-10 rounded-full object-cover" 
                                                     src="<?= htmlspecialchars($imagePath . $cacheBuster) ?>" 
                                                     alt=""
                                                     onerror="this.src='../../assets/images/Company Logo.jpg';">
                                                
                                                <?php if ($isFefoRecommended): ?>
                                                <!-- FEFO badge -->
                                                <?php endif; ?>
                                                
                                                <?php if ($isFifoRecommended): ?>
                                                <!-- FIFO badge -->
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($stock['name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($stock['category']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-mono text-gray-900"><?= htmlspecialchars($stock['batch_number']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium"><?= number_format($stock['quantity']) ?> 
                                        <?php if (!empty($stock['unit_type'])): ?>
                                            <span class="font-normal text-gray-500"><?= $stock['unit_type'] ?><?= $stock['quantity'] > 1 ? 's' : '' ?></span>
                                        <?php endif; ?>
                                        </div>
                                        <?php if (!empty($stock['pieces_per_box']) && $stock['pieces_per_box'] > 1): ?>
                                            <div class="text-xs text-gray-500">(<?= $stock['pieces_per_box'] ?> pcs/box)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($stock['production_date'])) ?></div>
                                        <?php if (!empty($stock['production_time'])): ?>
                                            <div class="text-xs text-gray-500"><?= date('h:i A', strtotime($stock['production_time'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($stock['best_before'])): ?>
                                            <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($stock['best_before'])) ?></div>
                                        <?php else: ?>
                                            <div class="text-sm text-gray-400">-</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($stock['expiry_date'])) ?></div>
                                        <?= $expiryBadge ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <a href="view_stock.php?id=<?= $stock['id'] ?>" class="text-primarycol hover:text-fourth">
                                                View
                                            </a>
                                            <a href="archive_stock.php?id=<?= $stock['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to archive this stock item?')"
                                               class="text-gray-600 hover:text-gray-800">
                                                Archive
                                            </a>
                                            <?php if ($daysUntilExpiry <= 3 && $daysUntilExpiry >= 0): ?>
                                                <a href="waste_product_input.php?stock_id=<?= $stock['id'] ?>&action=donate" 
                                                   class="text-amber-600 hover:text-amber-800 font-medium">
                                                    Donate
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($daysUntilExpiry < 0): ?>
                                            <a href="waste_product_input.php?stock_id=<?= $stock['id'] ?>" class="text-red-600 hover:text-red-800">
                                                Record as Waste
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> to 
                                <span class="font-medium"><?= min($page * $limit, $totalRows) ?></span> of 
                                <span class="font-medium"><?= $totalRows ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&expiry=<?= urlencode($expiryFilter) ?>&sort=<?= urlencode($sortBy) ?>&algorithm=<?= urlencode($algorithm) ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                                <?php endif; ?>
                                
                                <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): 
                                ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&expiry=<?= urlencode($expiryFilter) ?>&sort=<?= urlencode($sortBy) ?>&algorithm=<?= $algorithm ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i === $page ? 'text-primarycol bg-sec z-10' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&expiry=<?= urlencode($expiryFilter) ?>&sort=<?= urlencode($sortBy) ?>&algorithm=<?= $algorithm ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No stock entries found</h3>
                    <p class="text-gray-500 mb-6">No product stock entries match your filters</p>
                    <a href="add_stock.php" class="inline-flex items-center px-4 py-2 bg-primarycol text-white rounded-md hover:bg-fourth">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add New Stock
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Quick Tips</h3>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <ul class="list-disc list-inside space-y-1 text-sm text-blue-800">
                    <li>Items highlighted in <span class="text-amber-800">amber</span> will expire within 7 days</li>
                    <li>Items highlighted in <span class="text-red-800">red</span> are already expired</li>
                    <li>Use the <span class="font-medium">FEFO</span> method for perishable products to reduce waste</li>
                    <li>Use the <span class="font-medium">FIFO</span> method for non-perishable products for inventory management</li>
                    <li>Products with <span class="font-medium">priority badges</span> should be used first</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>