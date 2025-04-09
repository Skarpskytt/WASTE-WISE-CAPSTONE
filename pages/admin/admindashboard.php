<?php
session_start();
// Enable full error reporting (add at the very top, before other code)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Keep only auth middleware and DB connection
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Debugging: Log session data
error_log("Admin Dashboard - Session data: " . print_r($_SESSION, true));

$pdo = getPDO();

// Maintain the authentication check
checkAuth(['admin']);

// Get date range for filtering (default to last 30 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

if (isset($_GET['period'])) {
    switch ($_GET['period']) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'quarter':
            $startDate = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-365 days'));
            break;
        case 'custom':
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                $startDate = $_GET['start_date'];
                $endDate = $_GET['end_date'];
            }
            break;
    }
}

// Get branch names
$branchQuery = $pdo->query("SELECT id, name FROM branches ORDER BY id");
$branches = $branchQuery->fetchAll(PDO::FETCH_ASSOC);

// For branch IDs that we know exist
$branch1Id = 1;
$branch2Id = 2;

// Branch comparison data
$branchStats = [];
foreach ($branches as $branch) {
    $branchId = $branch['id'];
    
    // Product waste statistics
    $wasteQuery = $pdo->prepare("
        SELECT 
            COUNT(*) as total_waste_records,
            SUM(waste_quantity) as total_waste_quantity,
            SUM(waste_value) as total_waste_value,
            COUNT(DISTINCT product_id) as unique_products_wasted,
            AVG(waste_quantity) as avg_waste_quantity
        FROM product_waste 
        WHERE branch_id = ? AND waste_date BETWEEN ? AND ?
    ");
    $wasteQuery->execute([$branchId, $startDate, $endDate]);
    $wasteStats = $wasteQuery->fetch(PDO::FETCH_ASSOC);
    
    // Get the most common waste reason for this branch
    $reasonQuery = $pdo->prepare("
        SELECT waste_reason, COUNT(*) as count
        FROM product_waste
        WHERE branch_id = ? AND waste_date BETWEEN ? AND ?
        GROUP BY waste_reason
        ORDER BY count DESC
        LIMIT 1
    ");
    $reasonQuery->execute([$branchId, $startDate, $endDate]);
    $topReason = $reasonQuery->fetch(PDO::FETCH_ASSOC);
    
    // Donation statistics
    $donationQuery = $pdo->prepare("
        SELECT 
            COUNT(*) as total_donations,
            SUM(quantity_available) as total_quantity_available
        FROM donation_products
        WHERE branch_id = ? AND creation_date BETWEEN ? AND ?
    ");
    $donationQuery->execute([$branchId, $startDate, $endDate]);
    $donationStats = $donationQuery->fetch(PDO::FETCH_ASSOC);
    
    // Category distribution
    $categoryQuery = $pdo->prepare("
        SELECT pi.category, SUM(pw.waste_quantity) as quantity
        FROM product_waste pw
        JOIN product_info pi ON pw.product_id = pi.id
        WHERE pw.branch_id = ? AND pw.waste_date BETWEEN ? AND ?
        GROUP BY pi.category
        ORDER BY quantity DESC
    ");
    $categoryQuery->execute([$branchId, $startDate, $endDate]);
    $categories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Weekly trend data
    $trendQuery = $pdo->prepare("
        SELECT 
            DATE_FORMAT(waste_date, '%Y-%m-%d') as date,
            SUM(waste_quantity) as quantity
        FROM product_waste
        WHERE branch_id = ? AND waste_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(waste_date, '%Y-%m-%d')
        ORDER BY date
    ");
    $trendQuery->execute([$branchId, $startDate, $endDate]);
    $trendData = $trendQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the products with highest waste quantities
    $topWasteQuery = $pdo->prepare("
        SELECT 
            pi.name as product_name,
            pi.category,
            SUM(pw.waste_quantity) as total_quantity,
            SUM(pw.waste_value) as total_value
        FROM product_waste pw
        JOIN product_info pi ON pw.product_id = pi.id
        WHERE pw.branch_id = ? AND pw.waste_date BETWEEN ? AND ?
        GROUP BY pw.product_id
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $topWasteQuery->execute([$branchId, $startDate, $endDate]);
    $topWasteProducts = $topWasteQuery->fetchAll(PDO::FETCH_ASSOC);
    
    $branchStats[$branchId] = [
        'name' => $branch['name'],
        'waste' => $wasteStats,
        'top_reason' => $topReason['waste_reason'] ?? 'No data',
        'top_reason_count' => $topReason['count'] ?? 0,
        'donations' => $donationStats,
        'categories' => $categories,
        'trend_data' => $trendData,
        'top_waste_products' => $topWasteProducts
    ];
}

// Generate smart recommendations based on the data
$recommendations = [];

// Compare waste values between branches
if (count($branchStats) >= 2) {
    $branch1 = $branchStats[$branch1Id] ?? null;
    $branch2 = $branchStats[$branch2Id] ?? null;
    
    if ($branch1 && $branch2) {
        // Compare waste values
        if ($branch1['waste']['total_waste_value'] > $branch2['waste']['total_waste_value'] * 1.2) {
            $recommendations[] = [
                'type' => 'warning',
                'target' => $branch1['name'],
                'message' => "The waste value in {$branch1['name']} is significantly higher than in {$branch2['name']}. Consider investigating the inventory management practices.",
                'icon' => 'fa-triangle-exclamation'
            ];
        } elseif ($branch2['waste']['total_waste_value'] > $branch1['waste']['total_waste_value'] * 1.2) {
            $recommendations[] = [
                'type' => 'warning',
                'target' => $branch2['name'],
                'message' => "The waste value in {$branch2['name']} is significantly higher than in {$branch1['name']}. Consider investigating the inventory management practices.",
                'icon' => 'fa-triangle-exclamation'
            ];
        }
        
        // Compare donation utilization
        if ($branch1['waste']['total_waste_quantity'] > 0 && $branch1['donations']['total_quantity_available'] / $branch1['waste']['total_waste_quantity'] < 0.5) {
            $recommendations[] = [
                'type' => 'opportunity',
                'target' => $branch1['name'],
                'message' => "The donation rate in {$branch1['name']} is low compared to waste quantity. Consider increasing donation partnerships.",
                'icon' => 'fa-lightbulb'
            ];
        }
        if ($branch2['waste']['total_waste_quantity'] > 0 && $branch2['donations']['total_quantity_available'] / $branch2['waste']['total_waste_quantity'] < 0.5) {
            $recommendations[] = [
                'type' => 'opportunity',
                'target' => $branch2['name'],
                'message' => "The donation rate in {$branch2['name']} is low compared to waste quantity. Consider increasing donation partnerships.",
                'icon' => 'fa-lightbulb'
            ];
        }
    }
}

// Find patterns in waste reasons
foreach ($branchStats as $branchId => $stats) {
    if ($stats['top_reason'] === 'expired') {
        $recommendations[] = [
            'type' => 'action',
            'target' => $stats['name'],
            'message' => "High expiration waste in {$stats['name']}. Consider improving inventory rotation or implementing dynamic pricing for nearing-expiry products.",
            'icon' => 'fa-clock'
        ];
    }
    
    // Check if there are excessive waste items in specific categories
    foreach ($stats['categories'] as $category) {
        if ($category['quantity'] > 100) { // Threshold for recommendation
            $recommendations[] = [
                'type' => 'insight',
                'target' => $stats['name'],
                'message' => "High waste in '{$category['category']}' category at {$stats['name']}. Consider reviewing ordering quantities or storage conditions.",
                'icon' => 'fa-chart-pie'
            ];
            break; // Limit to one category recommendation per branch
        }
    }
}

// Get donation effectiveness ratio
$donationEfficiencyQuery = $pdo->query("
    SELECT 
        b.id as branch_id,
        b.name as branch_name,
        SUM(pw.waste_quantity) as total_waste,
        COALESCE(SUM(dp.quantity_available), 0) as total_donations,
        CASE 
            WHEN SUM(pw.waste_quantity) > 0 THEN COALESCE(SUM(dp.quantity_available), 0) / SUM(pw.waste_quantity) * 100
            ELSE 0
        END as donation_ratio
    FROM branches b
    LEFT JOIN product_waste pw ON b.id = pw.branch_id AND pw.waste_date BETWEEN '$startDate' AND '$endDate'
    LEFT JOIN donation_products dp ON b.id = dp.branch_id AND dp.creation_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY b.id, b.name
    ORDER BY b.id
");
$donationEfficiency = $donationEfficiencyQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Branch Comparison</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
    <style>
        .recommendation-card {
            transition: all 0.3s ease;
        }
        .recommendation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: scale(1.03);
        }
    </style>
</head>

<body class="flex h-screen bg-slate-100">
<?php include '../layout/nav.php' ?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <!-- Header with Time Period Filter -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-primarycol">Branch Comparison Dashboard</h1>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">
                    Comparing data from <span class="font-medium"><?= date('M j, Y', strtotime($startDate)) ?></span> to <span class="font-medium"><?= date('M j, Y', strtotime($endDate)) ?></span>
                </p>
                
                <!-- Time Period Filter -->
                <form method="GET" class="flex flex-wrap gap-2 items-center">
                    <select name="period" class="select select-sm select-bordered">
                        <option value="week" <?= ($_GET['period'] ?? '') == 'week' ? 'selected' : '' ?>>Last Week</option>
                        <option value="month" <?= (!isset($_GET['period']) || $_GET['period'] == 'month') ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="quarter" <?= ($_GET['period'] ?? '') == 'quarter' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="year" <?= ($_GET['period'] ?? '') == 'year' ? 'selected' : '' ?>>Last Year</option>
                        <option value="custom" <?= ($_GET['period'] ?? '') == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                    
                    <div id="customDateRange" class="<?= (isset($_GET['period']) && $_GET['period'] == 'custom') ? 'flex' : 'hidden' ?> gap-2">
                        <input type="date" name="start_date" class="input input-sm input-bordered" value="<?= $_GET['start_date'] ?? $startDate ?>">
                        <span class="text-gray-500">to</span>
                        <input type="date" name="end_date" class="input input-sm input-bordered" value="<?= $_GET['end_date'] ?? $endDate ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-sm btn-primary bg-primarycol hover:bg-primarycol/80 text-white border-none">
                        Apply
                    </button>
                </form>
            </div>
        </div>

        <!-- Smart Recommendations Section -->
        <?php if (!empty($recommendations)): ?>
        <section class="mb-8">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fa-solid fa-lightbulb text-yellow-500 mr-2"></i>
                Smart Recommendations
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach($recommendations as $index => $rec): 
                    $bgColor = 'bg-blue-50 border-blue-200';
                    $iconColor = 'text-blue-500';
                    
                    if ($rec['type'] == 'warning') {
                        $bgColor = 'bg-amber-50 border-amber-200';
                        $iconColor = 'text-amber-500';
                    } elseif ($rec['type'] == 'action') {
                        $bgColor = 'bg-green-50 border-green-200';
                        $iconColor = 'text-green-500';
                    } elseif ($rec['type'] == 'opportunity') {
                        $bgColor = 'bg-purple-50 border-purple-200';
                        $iconColor = 'text-purple-500';
                    }
                ?>
                <div class="recommendation-card p-4 border rounded-lg <?= $bgColor ?>">
                    <div class="flex items-start">
                        <div class="mr-3">
                            <i class="fa-solid <?= $rec['icon'] ?> text-xl <?= $iconColor ?>"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($rec['target']) ?></h3>
                            <p class="text-sm text-gray-700 mt-1"><?= htmlspecialchars($rec['message']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Key Metrics Comparison -->
        <section class="mb-8">
            <h2 class="text-lg font-bold mb-4">Key Performance Metrics</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Total Waste Comparison -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Total Waste Value</h3>
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <?php foreach($branches as $branch): 
                            $branchId = $branch['id'];
                            $stats = $branchStats[$branchId] ?? null;
                            if (!$stats) continue;
                            
                            $wasteValue = $stats['waste']['total_waste_value'] ?? 0;
                        ?>
                        <div class="text-center">
                            <div class="text-2xl font-bold">₱<?= number_format($wasteValue, 2) ?></div>
                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($branch['name']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php 
                    // Calculate percentage difference
                    $branch1Value = $branchStats[$branch1Id]['waste']['total_waste_value'] ?? 0;
                    $branch2Value = $branchStats[$branch2Id]['waste']['total_waste_value'] ?? 0;
                    $difference = 0;
                    $isIncrease = false;
                    
                    if ($branch1Value > 0 && $branch2Value > 0) {
                        // Calculate which branch has higher waste and by what percentage
                        if ($branch1Value > $branch2Value) {
                            $difference = ($branch1Value - $branch2Value) / $branch2Value * 100;
                            $isIncrease = true;
                            $higherBranch = $branch1Id;
                        } else {
                            $difference = ($branch2Value - $branch1Value) / $branch1Value * 100;
                            $isIncrease = true;
                            $higherBranch = $branch2Id;
                        }
                    }
                    ?>
                    
                    <?php if ($difference > 5): ?>
                    <div class="mt-3 text-xs <?= $isIncrease ? 'text-red-600' : 'text-green-600' ?> flex items-center justify-center">
                        <i class="fa-solid <?= $isIncrease ? 'fa-arrow-up' : 'fa-arrow-down' ?> mr-1"></i>
                        <?= number_format($difference, 1) ?>% <?= $isIncrease ? 'higher' : 'lower' ?> in <?= htmlspecialchars($branchStats[$higherBranch]['name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Waste Quantity Comparison -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Total Waste Quantity</h3>
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <?php foreach($branches as $branch): 
                            $branchId = $branch['id'];
                            $stats = $branchStats[$branchId] ?? null;
                            if (!$stats) continue;
                            
                            $wasteQty = $stats['waste']['total_waste_quantity'] ?? 0;
                        ?>
                        <div class="text-center">
                            <div class="text-2xl font-bold"><?= number_format($wasteQty) ?></div>
                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($branch['name']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php 
                    // Calculate percentage difference for quantity
                    $branch1Qty = $branchStats[$branch1Id]['waste']['total_waste_quantity'] ?? 0;
                    $branch2Qty = $branchStats[$branch2Id]['waste']['total_waste_quantity'] ?? 0;
                    $qtyDifference = 0;
                    $qtyIsIncrease = false;
                    
                    if ($branch1Qty > 0 && $branch2Qty > 0) {
                        if ($branch1Qty > $branch2Qty) {
                            $qtyDifference = ($branch1Qty - $branch2Qty) / $branch2Qty * 100;
                            $qtyIsIncrease = true;
                            $qtyHigherBranch = $branch1Id;
                        } else {
                            $qtyDifference = ($branch2Qty - $branch1Qty) / $branch1Qty * 100;
                            $qtyIsIncrease = true;
                            $qtyHigherBranch = $branch2Id;
                        }
                    }
                    ?>
                    
                    <?php if ($qtyDifference > 5): ?>
                    <div class="mt-3 text-xs <?= $qtyIsIncrease ? 'text-red-600' : 'text-green-600' ?> flex items-center justify-center">
                        <i class="fa-solid <?= $qtyIsIncrease ? 'fa-arrow-up' : 'fa-arrow-down' ?> mr-1"></i>
                        <?= number_format($qtyDifference, 1) ?>% <?= $qtyIsIncrease ? 'higher' : 'lower' ?> in <?= htmlspecialchars($branchStats[$qtyHigherBranch]['name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Donation Efficiency -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Donation Efficiency</h3>
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <?php foreach($donationEfficiency as $branch): 
                            $efficiency = $branch['donation_ratio'] ?? 0;
                        ?>
                        <div class="text-center">
                            <div class="text-2xl font-bold"><?= number_format($efficiency, 1) ?>%</div>
                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($branch['branch_name']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-2">
                        <div class="text-xs text-gray-500 text-center">Percentage of waste converted to donations</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Branch Comparison Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Waste Trend Chart -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold mb-4">Waste Trends Comparison</h3>
                <div id="wasteTrendChart" class="h-64"></div>
            </div>
            
            <!-- Category Distribution Comparison -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold mb-4">Category Distribution Comparison</h3>
                <div id="categoryComparisonChart" class="h-64"></div>
            </div>
        </div>
        
        <!-- Top Waste Products By Branch -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <?php foreach($branches as $branch): 
                $branchId = $branch['id'];
                $stats = $branchStats[$branchId] ?? null;
                if (!$stats || empty($stats['top_waste_products'])) continue;
            ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold flex items-center mb-4">
                    <span class="mr-2">Top Waste Products - <?= htmlspecialchars($branch['name']) ?></span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach($stats['top_waste_products'] as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($product['category']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500"><?= number_format($product['total_quantity']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">₱<?= number_format($product['total_value'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // Show/hide custom date range based on selection
    document.querySelector('select[name="period"]').addEventListener('change', function() {
        const customRangeDiv = document.getElementById('customDateRange');
        if (this.value === 'custom') {
            customRangeDiv.classList.remove('hidden');
            customRangeDiv.classList.add('flex');
        } else {
            customRangeDiv.classList.add('hidden');
            customRangeDiv.classList.remove('flex');
        }
    });
    
    // Prepare data for waste trend chart
    const branch1TrendData = <?= json_encode(array_map(function($item) {
        return [
            'x' => $item['date'], 
            'y' => floatval($item['quantity'])
        ];
    }, $branchStats[$branch1Id]['trend_data'] ?? [])) ?>;
    
    const branch2TrendData = <?= json_encode(array_map(function($item) {
        return [
            'x' => $item['date'], 
            'y' => floatval($item['quantity'])
        ];
    }, $branchStats[$branch2Id]['trend_data'] ?? [])) ?>;
    
    // Waste Trend Chart
    const wasteTrendOptions = {
        series: [
            {
                name: '<?= htmlspecialchars($branchStats[$branch1Id]['name'] ?? "Branch 1") ?>',
                data: branch1TrendData
            },
            {
                name: '<?= htmlspecialchars($branchStats[$branch2Id]['name'] ?? "Branch 2") ?>',
                data: branch2TrendData
            }
        ],
        chart: {
            type: 'line',
            height: 250,
            toolbar: {
                show: false
            },
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        colors: ['#47663B', '#3B82F6'],
        xaxis: {
            type: 'datetime',
            labels: {
                format: 'dd MMM',
            }
        },
        yaxis: {
            title: {
                text: 'Waste Quantity'
            }
        },
        legend: {
            position: 'top'
        },
        tooltip: {
            x: {
                format: 'dd MMM'
            }
        }
    };
    
    const wasteTrendChart = new ApexCharts(document.querySelector("#wasteTrendChart"), wasteTrendOptions);
    wasteTrendChart.render();
    
    // Prepare data for category comparison chart
    const branch1Categories = <?= json_encode(array_map(function($cat) {
        return [
            'x' => $cat['category'], 
            'y' => floatval($cat['quantity'])
        ];
    }, array_slice($branchStats[$branch1Id]['categories'] ?? [], 0, 5))) ?>;
    
    const branch2Categories = <?= json_encode(array_map(function($cat) {
        return [
            'x' => $cat['category'], 
            'y' => floatval($cat['quantity'])
        ];
    }, array_slice($branchStats[$branch2Id]['categories'] ?? [], 0, 5))) ?>;
    
    // Category Comparison Chart
    const categoryOptions = {
        series: [
            {
                name: '<?= htmlspecialchars($branchStats[$branch1Id]['name'] ?? "Branch 1") ?>',
                data: branch1Categories
            },
            {
                name: '<?= htmlspecialchars($branchStats[$branch2Id]['name'] ?? "Branch 2") ?>',
                data: branch2Categories
            }
        ],
        chart: {
            type: 'bar',
            height: 250,
            toolbar: {
                show: false
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '70%',
                borderRadius: 3
            },
        },
        dataLabels: {
            enabled: false
        },
        colors: ['#47663B', '#3B82F6'],
        xaxis: {
            type: 'category'
        },
        yaxis: {
            title: {
                text: 'Quantity'
            }
        },
        legend: {
            position: 'top'
        },
    };
    
    const categoryComparisonChart = new ApexCharts(document.querySelector("#categoryComparisonChart"), categoryOptions);
    categoryComparisonChart.render();
</script>

</body>
</html>