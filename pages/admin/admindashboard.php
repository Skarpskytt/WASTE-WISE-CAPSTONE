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

<div class="p-6 overflow-y-auto w-full">
    <!-- Date filter form -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-primarycol">Branch Comparison Dashboard</h1>
        <form method="GET" class="flex space-x-2">
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate); ?>" class="input input-bordered" placeholder="Start Date">
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate); ?>" class="input input-bordered" placeholder="End Date">
            <button type="submit" class="btn btn-primary bg-primarycol">Filter</button>
            <a href="admindashboard.php" class="btn">Reset</a>
        </form>
    </div>

    <!-- Recommendations Panel -->
    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
        <h3 class="font-bold text-lg mb-2">Smart Recommendations</h3>
        <ul class="list-disc pl-5">
            <?php
            $highWasteBranch = ($branchStats[1]['total_value'] > $branchStats[2]['total_value']) ? $branchStats[1]['name'] : $branchStats[2]['name'];
            $mostWastedReason = ($branchStats[1]['top_reason_value'] > $branchStats[2]['top_reason_value']) ? 
                $branchStats[1]['top_reason'] : $branchStats[2]['top_reason'];
            ?>
            <li class="mb-1">Focus on waste reduction strategies at <?= $highWasteBranch ?></li>
            <li class="mb-1">Review <?= $mostWastedReason ?> waste reason across branches</li>
            <li class="mb-1">Compare disposal methods between branches to standardize best practices</li>
        </ul>
    </div>

    <!-- Branch Comparison Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <?php foreach ($branchStats as $branchId => $stats): ?>
        <div class="bg-white rounded-lg shadow-md p-5 border-t-4 <?= $branchId == 1 ? 'border-primarycol' : 'border-third' ?>">
            <h2 class="text-xl font-bold mb-4"><?= htmlspecialchars($stats['name']) ?> Overview</h2>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-sec rounded-lg p-3">
                    <h3 class="text-sm text-gray-600">Total Waste Value</h3>
                    <p class="text-xl font-bold">₱<?= number_format($stats['total_value'], 2) ?></p>
                    
                    <?php if ($stats['trend'] > 0): ?>
                        <p class="text-sm text-red-500">↑ <?= number_format(abs($stats['trend']), 1) ?>%</p>
                    <?php elseif ($stats['trend'] < 0): ?>
                        <p class="text-sm text-green-500">↓ <?= number_format(abs($stats['trend']), 1) ?>%</p>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">— 0.0%</p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-sec rounded-lg p-3">
                    <h3 class="text-sm text-gray-600">Total Waste Quantity</h3>
                    <p class="text-xl font-bold"><?= number_format($stats['total_quantity']) ?> units</p>
                    <p class="text-sm text-gray-500"><?= $stats['record_count'] ?> records</p>
                </div>
                
                <div class="bg-sec rounded-lg p-3 col-span-2">
                    <h3 class="text-sm text-gray-600 mb-1">Most Common Waste Reason</h3>
                    <p class="text-lg font-bold"><?= ucfirst(htmlspecialchars($stats['top_reason'])) ?></p>
                    <p class="text-sm text-gray-500">
                        <?= $stats['top_reason_count'] ?> occurrences, 
                        ₱<?= number_format($stats['top_reason_value'], 2) ?> loss
                    </p>
                </div>
            </div>
            
            <div class="mt-4">
                <h3 class="text-sm text-gray-600 mb-2">Disposal Methods</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($stats['disposal_methods'] as $method): ?>
                    <span class="px-2 py-1 bg-gray-100 rounded-full text-xs">
                        <?= ucfirst(htmlspecialchars($method['disposal_method'])) ?>: <?= $method['count'] ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="branch<?= $branchId ?>_data.php" class="text-primarycol hover:underline">
                    View detailed branch data →
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Branch Comparison Chart -->
    <div class="bg-white rounded-lg shadow-md p-5 mb-8">
        <h2 class="text-xl font-bold mb-4">Branch Waste Comparison</h2>
        <div id="branchComparisonChart" class="w-full h-[300px]"></div>
    </div>

    <!-- Waste Reasons Comparison -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <?php foreach ($reasonsByBranch as $branchId => $reasons): ?>
            <?php if (count($reasons) > 0): ?>
            <div class="bg-white rounded-lg shadow-md p-5">
                <h2 class="text-xl font-bold mb-4"><?= htmlspecialchars($reasons[0]['branch_name']) ?> - Top Waste Reasons</h2>
                <table class="table table-zebra w-full">
                    <thead>
                        <tr class="bg-sec">
                            <th>Reason</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $displayReasons = array_slice($reasons, 0, 5); // Show top 5 reasons
                        foreach ($displayReasons as $reason): 
                        ?>
                        <tr>
                            <td><?= ucfirst(htmlspecialchars($reason['waste_reason'])) ?></td>
                            <td><?= $reason['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Top Wasted Food Items Table -->
    <div class="bg-white shadow-md rounded-lg p-5 mb-8">
        <h3 class="text-xl font-bold mb-3">Top Wasted Food Items</h3>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="bg-sec">
                        <th>Branch</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Waste Quantity</th>
                        <th>Waste Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topItems as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['branch_name']) ?></td>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= htmlspecialchars($item['category']) ?></td>
                        <td><?= number_format($item['waste_quantity'], 2) ?></td>
                        <td>₱<?= number_format($item['waste_value'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($topItems) === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">No waste data found for selected period</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Waste Transactions Table -->
    <div class="overflow-x-auto mb-10">
        <h2 class="text-xl font-bold mb-4">Recent Waste Transactions</h2>
        <table class="table table-zebra w-full">
            <thead>
                <tr class="bg-sec">
                    <th>ID</th>
                    <th>Branch</th>
                    <th>Waste Date</th>
                    <th>Item Name</th>
                    <th>Waste Quantity</th>
                    <th>Waste Value</th>
                    <th>Waste Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTransactions as $tx): ?>
                <tr>
                    <td><?= $tx['id'] ?></td>
                    <td><?= htmlspecialchars($tx['branch_name']) ?></td>
                    <td><?= date('M d, Y H:i', strtotime($tx['waste_date'])) ?></td>
                    <td><?= htmlspecialchars($tx['item_name']) ?></td>
                    <td><?= number_format($tx['waste_quantity'], 2) ?></td>
                    <td>₱<?= number_format($tx['waste_value'], 2) ?></td>
                    <td><?= ucfirst(htmlspecialchars($tx['waste_reason'])) ?></td>
                    <td>
                        <a href="view_waste_transaction.php?id=<?= $tx['id'] ?>" class="btn btn-sm btn-outline">Analyze</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($recentTransactions) === 0): ?>
                <tr>
                    <td colspan="8" class="text-center py-4">No transactions found for selected period</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Branch Comparison Chart
const branchChart = new ApexCharts(document.querySelector("#branchComparisonChart"), {
    series: [{
        name: 'Waste Quantity',
        data: [
            <?= $branchStats[1]['total_quantity'] ?>, 
            <?= $branchStats[2]['total_quantity'] ?>
        ]
    }, {
        name: 'Waste Value (₱)',
        data: [
            <?= $branchStats[1]['total_value'] ?>, 
            <?= $branchStats[2]['total_value'] ?>
        ]
    }],
    chart: {
        type: 'bar',
        height: 300,
        stacked: false,
        toolbar: {
            show: false
        }
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '55%',
            borderRadius: 5,
        },
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    xaxis: {
        categories: [
            '<?= $branchStats[1]['name'] ?>', 
            '<?= $branchStats[2]['name'] ?>'
        ],
    },
    yaxis: [{
        title: {
            text: 'Waste Quantity'
        },
    }, {
        opposite: true,
        title: {
            text: 'Waste Value (₱)'
        }
    }],
    fill: {
        opacity: 1
    },
    tooltip: {
        y: {
            formatter: function (val, { seriesIndex }) {
                return seriesIndex === 0 
                    ? val + ' units'
                    : '₱' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
    },
    colors: ['#47663B', '#EED3B1'],
    legend: {
        position: 'top'
    }
});
branchChart.render();
</script>
</body>
</html>
