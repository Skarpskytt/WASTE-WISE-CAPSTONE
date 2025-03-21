<?php
// Keep only auth middleware and DB connection
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Maintain the authentication check
checkAuth(['admin']);

// Get date range from URL parameters or use default (last 30 days)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Format dates for SQL queries
$startDateFormatted = date('Y-m-d 00:00:00', strtotime($startDate));
$endDateFormatted = date('Y-m-d 23:59:59', strtotime($endDate));

// --- BRANCH COMPARISON DATA ---

// Get total waste statistics for both branches
$branchStats = [];
for ($branchId = 1; $branchId <= 2; $branchId++) {
    // Get branch name
    $branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
    $branchStmt->execute([$branchId]);
    $branchName = $branchStmt->fetchColumn() ?: "Branch $branchId";
    
    // Get waste statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            SUM(waste_quantity) as total_quantity,
            SUM(waste_value) as total_value,
            COUNT(*) as record_count
        FROM 
            product_waste
        WHERE 
            branch_id = ? AND
            waste_date BETWEEN ? AND ?
    ");
    $statsStmt->execute([$branchId, $startDateFormatted, $endDateFormatted]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get previous period stats for comparison
    $prevStartDate = date('Y-m-d 00:00:00', strtotime($startDate . ' -30 days'));
    $prevEndDate = date('Y-m-d 23:59:59', strtotime($startDate . ' -1 day'));
    
    $prevStatsStmt = $pdo->prepare("
        SELECT 
            SUM(waste_value) as prev_total_value
        FROM 
            product_waste
        WHERE 
            branch_id = ? AND
            waste_date BETWEEN ? AND ?
    ");
    $prevStatsStmt->execute([$branchId, $prevStartDate, $prevEndDate]);
    $prevStats = $prevStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate trend
    $currentValue = (float)($stats['total_value'] ?? 0);
    $previousValue = (float)($prevStats['prev_total_value'] ?? 0);
    $trend = 0;
    if ($previousValue > 0) {
        $trend = (($currentValue - $previousValue) / $previousValue) * 100;
    }
    
    // Get top waste reason
    $reasonStmt = $pdo->prepare("
        SELECT 
            waste_reason,
            COUNT(*) as reason_count,
            SUM(waste_quantity) as reason_quantity,
            SUM(waste_value) as reason_value
        FROM 
            product_waste
        WHERE 
            branch_id = ? AND
            waste_date BETWEEN ? AND ?
        GROUP BY 
            waste_reason
        ORDER BY 
            reason_count DESC
        LIMIT 1
    ");
    $reasonStmt->execute([$branchId, $startDateFormatted, $endDateFormatted]);
    $topReason = $reasonStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get disposal method distribution
    $disposalStmt = $pdo->prepare("
        SELECT 
            disposal_method, 
            COUNT(*) as count
        FROM 
            product_waste
        WHERE 
            branch_id = ? AND
            waste_date BETWEEN ? AND ?
        GROUP BY 
            disposal_method
    ");
    $disposalStmt->execute([$branchId, $startDateFormatted, $endDateFormatted]);
    $disposalMethods = $disposalStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Store all branch data
    $branchStats[$branchId] = [
        'name' => $branchName,
        'total_quantity' => $stats['total_quantity'] ?? 0,
        'total_value' => $stats['total_value'] ?? 0,
        'record_count' => $stats['record_count'] ?? 0,
        'trend' => $trend,
        'top_reason' => $topReason['waste_reason'] ?? 'N/A',
        'top_reason_count' => $topReason['reason_count'] ?? 0,
        'top_reason_value' => $topReason['reason_value'] ?? 0,
        'disposal_methods' => $disposalMethods,
    ];
}

// Get branch-specific totals for both product and ingredient waste
$branchTotals = [];
for ($branchId = 1; $branchId <= 2; $branchId++) {
    // Product waste totals
    $prodStmt = $pdo->prepare("
        SELECT 
            SUM(waste_value) as product_waste_value,
            SUM(waste_quantity) as product_waste_quantity,
            COUNT(*) as product_waste_count
        FROM product_waste 
        WHERE branch_id = ? AND waste_date BETWEEN ? AND ?
    ");
    $prodStmt->execute([$branchId, $startDateFormatted, $endDateFormatted]);
    $prodTotals = $prodStmt->fetch(PDO::FETCH_ASSOC);

    // Ingredient waste totals
    $ingStmt = $pdo->prepare("
        SELECT 
            SUM(waste_value) as ingredient_waste_value,
            SUM(waste_quantity) as ingredient_waste_quantity,
            COUNT(*) as ingredient_waste_count
        FROM ingredients_waste 
        WHERE branch_id = ? AND waste_date BETWEEN ? AND ?
    ");
    $ingStmt->execute([$branchId, $startDateFormatted, $endDateFormatted]);
    $ingTotals = $ingStmt->fetch(PDO::FETCH_ASSOC);

    $branchTotals[$branchId] = [
        'total_value' => ($prodTotals['product_waste_value'] ?? 0) + ($ingTotals['ingredient_waste_value'] ?? 0),
        'total_quantity' => ($prodTotals['product_waste_quantity'] ?? 0) + ($ingTotals['ingredient_waste_quantity'] ?? 0),
        'total_records' => ($prodTotals['product_waste_count'] ?? 0) + ($ingTotals['ingredient_waste_count'] ?? 0)
    ];
}

// Calculate total waste value percent change
$totalCurrentValue = $branchTotals[1]['total_value'] + $branchTotals[2]['total_value'];
$prevStartDate = date('Y-m-d 00:00:00', strtotime($startDate . ' -30 days'));
$prevEndDate = date('Y-m-d 23:59:59', strtotime($startDate . ' -1 day'));

$prevValueStmt = $pdo->prepare("
    SELECT SUM(waste_value) as total_prev_value
    FROM (
        SELECT waste_value FROM product_waste 
        WHERE waste_date BETWEEN ? AND ?
        UNION ALL
        SELECT waste_value FROM ingredients_waste 
        WHERE waste_date BETWEEN ? AND ?
    ) combined_waste
");
$prevValueStmt->execute([$prevStartDate, $prevEndDate, $prevStartDate, $prevEndDate]);
$prevTotalValue = $prevValueStmt->fetchColumn() ?: 0;

$valuePercentChange = $prevTotalValue > 0 
    ? (($totalCurrentValue - $prevTotalValue) / $prevTotalValue) * 100 
    : 0;

// Get top wasted items across all branches for selected period
$topItemsStmt = $pdo->prepare("
    SELECT 
        p.name as item_name,
        p.category,
        SUM(pw.waste_quantity) as waste_quantity,
        SUM(pw.waste_value) as waste_value,
        b.name as branch_name,
        COUNT(*) as waste_count
    FROM 
        product_waste pw
    JOIN 
        products p ON pw.product_id = p.id
    JOIN
        branches b ON pw.branch_id = b.id
    WHERE 
        pw.waste_date BETWEEN ? AND ?
    GROUP BY 
        p.id, pw.branch_id
    ORDER BY 
        waste_value DESC
    LIMIT 5
");
$topItemsStmt->execute([$startDateFormatted, $endDateFormatted]);
$topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get waste reasons statistics
$reasonsStmt = $pdo->prepare("
    SELECT 
        pw.waste_reason,
        COUNT(*) as count,
        b.name as branch_name,
        b.id as branch_id
    FROM 
        product_waste pw
    JOIN
        branches b ON pw.branch_id = b.id
    WHERE 
        pw.waste_date BETWEEN ? AND ?
    GROUP BY 
        pw.waste_reason, pw.branch_id
    ORDER BY 
        count DESC
");
$reasonsStmt->execute([$startDateFormatted, $endDateFormatted]);
$allReasons = $reasonsStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize reasons by branch
$reasonsByBranch = [];
foreach ($allReasons as $reason) {
    $branchId = $reason['branch_id'];
    if (!isset($reasonsByBranch[$branchId])) {
        $reasonsByBranch[$branchId] = [];
    }
    $reasonsByBranch[$branchId][] = $reason;
}

// Get recent waste transactions
$recentStmt = $pdo->prepare("
    SELECT 
        pw.id,
        pw.waste_date,
        p.name as item_name,
        pw.waste_quantity,
        pw.waste_value,
        pw.waste_reason,
        b.name as branch_name
    FROM 
        product_waste pw
    JOIN 
        products p ON pw.product_id = p.id
    JOIN
        branches b ON pw.branch_id = b.id
    WHERE 
        pw.waste_date BETWEEN ? AND ?
    ORDER BY 
        pw.waste_date DESC
    LIMIT 6
");
$recentStmt->execute([$startDateFormatted, $endDateFormatted]);
$recentTransactions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Branch Comparison</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
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
</head>

<body class="flex h-screen bg-slate-100">
<?php include '../layout/nav.php' ?>

<!-- Update the main content div -->
<div class="p-6 overflow-y-auto w-full bg-gradient-to-br from-gray-50 to-sec/10">
    <!-- Enhanced Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-primarycol">Branch Monitoring Dashboard</h1>
        <form method="GET" class="flex space-x-3">
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate); ?>" 
                   class="input input-bordered input-sm">
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate); ?>" 
                   class="input input-bordered input-sm">
            <button type="submit" class="btn btn-sm bg-primarycol hover:bg-primarycol/90 text-white">
                Filter
            </button>
        </form>
    </div>

    <!-- Four Main Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Waste Value Card -->
        <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-primarycol hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-gray-500 text-xs uppercase">Total Waste Value</h3>
                <span class="bg-primarycol/10 p-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primarycol" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <p class="text-lg font-bold text-primarycol">₱<?= number_format($totalCurrentValue, 2) ?></p>
            <div class="flex items-center text-xs">
                <span class="<?= $valuePercentChange >= 0 ? 'text-red-500' : 'text-green-500' ?>">
                    <?= $valuePercentChange >= 0 ? '↑' : '↓' ?> <?= abs(round($valuePercentChange, 1)) ?>%
                </span>
                <span class="text-gray-500 ml-1">vs previous period</span>
            </div>
        </div>

        <!-- Total Waste Quantity Card -->
        <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-third hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-gray-500 text-xs uppercase">Total Waste Items</h3>
                <span class="bg-third/10 p-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-third" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                </span>
            </div>
            <p class="text-lg font-bold text-third">
                <?= number_format($branchTotals[1]['total_quantity'] + $branchTotals[2]['total_quantity']) ?>
            </p>
            <p class="text-xs text-gray-500">Combined waste items</p>
        </div>

        <!-- Records Count Card -->
        <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-fourth hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-gray-500 text-xs uppercase">Total Records</h3>
                <span class="bg-fourth/10 p-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
            </div>
            <p class="text-lg font-bold text-fourth">
                <?= number_format($branchTotals[1]['total_records'] + $branchTotals[2]['total_records']) ?>
            </p>
            <p class="text-xs text-gray-500">Waste entries logged</p>
        </div>

        <!-- Branch Comparison Card -->
        <?php
        $branch1Value = $branchTotals[1]['total_value'];
        $branch2Value = $branchTotals[2]['total_value'];
        $totalValue = $branch1Value + $branch2Value;
        $branch1Percent = $totalValue > 0 ? ($branch1Value / $totalValue) * 100 : 0;
        $branch2Percent = $totalValue > 0 ? ($branch2Value / $totalValue) * 100 : 0;
        ?>
        <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-purple-500 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-gray-500 text-xs uppercase">Branch Comparison</h3>
                <span class="bg-purple-50 p-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </span>
            </div>
            <div class="flex justify-between text-xs mb-1">
                <span>Branch 1</span>
                <span>Branch 2</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                <div class="bg-purple-500 h-2 rounded-full" style="width: <?= $branch1Percent ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
                <span><?= round($branch1Percent) ?>%</span>
                <span><?= round($branch2Percent) ?>%</span>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Waste Reasons Pie Chart -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-bold text-primarycol mb-4">Waste Reasons Distribution</h2>
            <div id="wasteReasonsPieChart" class="h-[300px]"></div>
        </div>

        <!-- Smart Recommendations Panel -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-bold text-primarycol mb-4">Smart Recommendations</h2>
            <div class="space-y-4">
                <?php foreach ($reasonsByBranch as $branchId => $reasons): ?>
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <h3 class="font-semibold text-blue-700 mb-2"><?= $branchStats[$branchId]['name'] ?> Insights:</h3>
                        <ul class="list-disc pl-5 text-blue-600">
                            <li class="mb-2">Primary waste reason: <?= ucfirst($branchStats[$branchId]['top_reason']) ?></li>
                            <li class="mb-2">Estimated loss: ₱<?= number_format($branchStats[$branchId]['top_reason_value'], 2) ?></li>
                            <li>Suggested action: Review <?= strtolower($branchStats[$branchId]['top_reason']) ?> handling procedures</li>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primarycol">Recent Waste Transactions</h2>
            <a href="waste_transactions.php" class="text-primarycol hover:underline text-sm">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="bg-sec/50">
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Value</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recentTransactions, 0, 5) as $tx): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($tx['waste_date'])) ?></td>
                        <td><?= htmlspecialchars($tx['branch_name']) ?></td>
                        <td><?= htmlspecialchars($tx['item_name']) ?></td>
                        <td><?= number_format($tx['waste_quantity']) ?></td>
                        <td>₱<?= number_format($tx['waste_value'], 2) ?></td>
                        <td><?= ucfirst(htmlspecialchars($tx['waste_reason'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add this script for the pie chart -->
<script>
const pieChart = new ApexCharts(document.querySelector("#wasteReasonsPieChart"), {
    series: [
        <?php
        $reasonCounts = [];
        foreach ($allReasons as $reason) {
            if (!isset($reasonCounts[$reason['waste_reason']])) {
                $reasonCounts[$reason['waste_reason']] = 0;
            }
            $reasonCounts[$reason['waste_reason']] += $reason['count'];
        }
        echo implode(',', array_values($reasonCounts));
        ?>
    ],
    chart: {
        type: 'pie',
        height: 300
    },
    labels: [<?php echo "'" . implode("','", array_map('ucfirst', array_keys($reasonCounts))) . "'"; ?>],
    colors: ['#47663B', '#7C9473', '#96B6C5', '#ADC4CE', '#EEE0C9', '#F1F0E8'],
    legend: {
        position: 'bottom',
        fontSize: '14px'
    },
    plotOptions: {
        pie: {
            donut: {
                size: '50%'
            }
        }
    },
    stroke: {
        width: 0
    },
    dataLabels: {
        enabled: true,
        style: {
            fontSize: '12px',
            fontFamily: 'Arial, sans-serif',
            fontWeight: 'normal'
        }
    }
});
pieChart.render();
</script>
</body>
</html>
