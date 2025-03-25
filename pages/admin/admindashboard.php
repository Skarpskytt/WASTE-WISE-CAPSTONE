<?php
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
        'total_records' => ($prodTotals['product_waste_count'] ?? 0) + ($ingTotals['ingredient_waste_count'] ?? 0),
        'product_waste_value' => $prodTotals['product_waste_value'] ?? 0,
        'ingredient_waste_value' => $ingTotals['ingredient_waste_value'] ?? 0,
        'product_waste_count' => $prodTotals['product_waste_count'] ?? 0,
        'ingredient_waste_count' => $ingTotals['ingredient_waste_count'] ?? 0
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

// --- DONATION DATA (Added from donation_history.php) ---
$donationTotalStmt = $pdo->prepare("
    SELECT COUNT(*) as total_donations 
    FROM donated_products
    WHERE received_date BETWEEN ? AND ?
");
$donationTotalStmt->execute([$startDateFormatted, $endDateFormatted]);
$totalDonations = $donationTotalStmt->fetchColumn() ?: 0;

// Get total quantity donated
$donationQuantityStmt = $pdo->prepare("
    SELECT SUM(received_quantity) as total_quantity 
    FROM donated_products
    WHERE received_date BETWEEN ? AND ?
");
$donationQuantityStmt->execute([$startDateFormatted, $endDateFormatted]);
$totalDonationQuantity = $donationQuantityStmt->fetchColumn() ?: 0;

// Count unique NGOs receiving donations
$uniqueNgosStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ngo_id) as unique_ngos 
    FROM donated_products
    WHERE received_date BETWEEN ? AND ?
");
$uniqueNgosStmt->execute([$startDateFormatted, $endDateFormatted]);
$uniqueNgos = $uniqueNgosStmt->fetchColumn() ?: 0;

// Get recent donations
$recentDonationsStmt = $pdo->prepare("
    SELECT 
        dp.*,
        COALESCE(u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
        p.name as product_name
    FROM 
        donated_products dp
    JOIN 
        users u ON dp.ngo_id = u.id
    JOIN 
        donation_requests dr ON dp.donation_request_id = dr.id
    JOIN 
        products p ON dr.product_id = p.id
    WHERE 
        dp.received_date BETWEEN ? AND ?
    ORDER BY 
        dp.received_date DESC
    LIMIT 5
");
$recentDonationsStmt->execute([$startDateFormatted, $endDateFormatted]);
$recentDonations = $recentDonationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly donation trend
$monthlyDonationsStmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(received_date, '%Y-%m') as month,
        COUNT(*) as donation_count,
        SUM(received_quantity) as donation_quantity
    FROM 
        donated_products
    WHERE 
        received_date >= DATE_SUB(?, INTERVAL 6 MONTH)
    GROUP BY 
        DATE_FORMAT(received_date, '%Y-%m')
    ORDER BY 
        month ASC
");
$monthlyDonationsStmt->execute([$endDateFormatted]);
$monthlyDonations = $monthlyDonationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Format for chart
$donationMonths = [];
$donationCounts = [];
foreach ($monthlyDonations as $donation) {
    $donationMonths[] = date('M Y', strtotime($donation['month'] . '-01'));
    $donationCounts[] = $donation['donation_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Branch Comparison</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Logo.png">
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

        <!-- Donation Stats Card (New) -->
        <div class="bg-white rounded-lg shadow-sm p-3 border-l-4 border-blue-500 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-gray-500 text-xs uppercase">Total Donations</h3>
                <span class="bg-blue-50 p-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a4 4 0 00-4-4H5.45a4 4 0 00-3.973 3.671L1.318 18H2.5v2a1 1 0 001 1h14a1 1 0 001-1v-2h1.181L19.523 5.67A4 4 0 0015.55 2H14a4 4 0 00-4 4v2m8 7H6" />
                    </svg>
                </span>
            </div>
            <p class="text-lg font-bold text-blue-500"><?= number_format($totalDonations) ?></p>
            <div class="flex items-center text-xs">
                <span class="text-blue-600"><?= number_format($totalDonationQuantity) ?> items</span>
                <span class="text-gray-500 ml-1">to <?= $uniqueNgos ?> NGOs</span>
            </div>
        </div>
    </div>

    <!-- Branch Comparison Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <?php foreach ([1, 2] as $branchId): ?>
        <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-primarycol"><?= htmlspecialchars($branchStats[$branchId]['name']) ?></h2>
                <div class="flex space-x-2">
                    <a href="branch<?= $branchId ?>_product_waste_data.php" class="text-xs bg-primarycol/10 rounded-full py-1 px-3 text-primarycol hover:bg-primarycol/20">
                        View Products
                    </a>
                    <a href="branch<?= $branchId ?>_ingredients_waste_data.php" class="text-xs bg-green-100 rounded-full py-1 px-3 text-green-600 hover:bg-green-200">
                        View Ingredients
                    </a>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="p-3 bg-sec/60 rounded-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500">Product Waste</p>
                        <span class="text-xs text-white bg-primarycol rounded-full px-2 py-0.5">
                            <?= $branchTotals[$branchId]['product_waste_count'] ?> Records
                        </span>
                    </div>
                    <p class="text-xl font-bold text-primarycol">₱<?= number_format($branchTotals[$branchId]['product_waste_value'], 2) ?></p>
                </div>
                
                <div class="p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500">Excess Ingredients</p>
                        <span class="text-xs text-white bg-green-600 rounded-full px-2 py-0.5">
                            <?= $branchTotals[$branchId]['ingredient_waste_count'] ?> Records
                        </span>
                    </div>
                    <p class="text-xl font-bold text-green-600">₱<?= number_format($branchTotals[$branchId]['ingredient_waste_value'], 2) ?></p>
                </div>
            </div>
            
            <div class="p-3 bg-gray-50 rounded-lg mb-3">
                <p class="text-sm font-medium text-gray-700 mb-1">Top Waste Reason:</p>
                <div class="flex justify-between items-center">
                    <span class="text-lg font-semibold text-red-600 capitalize">
                        <?= htmlspecialchars($branchStats[$branchId]['top_reason']) ?>
                    </span>
                    <span class="text-sm text-gray-500">
                        ₱<?= number_format($branchStats[$branchId]['top_reason_value'], 2) ?> loss
                    </span>
                </div>
            </div>
            
            <!-- Waste Trend -->
            <div class="mt-3 flex items-center justify-between text-sm">
                <span class="text-gray-500">Overall waste trend:</span>
                <span class="<?= $branchStats[$branchId]['trend'] >= 0 ? 'text-red-500' : 'text-green-500' ?> font-medium">
                    <?= $branchStats[$branchId]['trend'] >= 0 ? '↑' : '↓' ?> 
                    <?= abs(round($branchStats[$branchId]['trend'], 1)) ?>% vs. previous
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Three Column Layout -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Waste Reasons Pie Chart -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-bold text-primarycol mb-4">Waste Reasons Distribution</h2>
            <div id="wasteReasonsPieChart" class="h-[300px]"></div>
        </div>

        <!-- Donations Activity Chart (New) -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-bold text-blue-600 mb-4">Donation Activity</h2>
            <div id="donationActivityChart" class="h-[300px]"></div>
        </div>

        <!-- Smart Recommendations Panel (Compact Version) -->
        <div class="bg-white rounded-xl shadow-md p-5">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-xl font-bold text-primarycol">Smart Recommendations</h2>
                <div class="flex items-center text-xs space-x-2">
                    <span class="flex items-center"><span class="w-2 h-2 inline-block bg-red-500 rounded-full mr-1"></span> Urgent</span>
                    <span class="flex items-center"><span class="w-2 h-2 inline-block bg-amber-400 rounded-full mr-1"></span> Medium</span>
                </div>
            </div>
            
            <div class="h-[300px] overflow-y-auto pr-1 space-y-3">
                <?php 
                // Sort branches by waste value to prioritize higher waste first
                $sortedBranches = [1, 2];
                usort($sortedBranches, function($a, $b) use ($branchTotals) {
                    return $branchTotals[$b]['total_value'] <=> $branchTotals[$a]['total_value'];
                });
                
                foreach ($sortedBranches as $branchId): 
                    // Determine priority based on trend and value
                    $priority = 'medium';
                    if ($branchStats[$branchId]['trend'] > 5) {
                        $priority = 'urgent';
                    }
                    
                    $borderColor = $priority === 'urgent' ? 'border-red-500' : 'border-amber-400';
                    $bgColor = $priority === 'urgent' ? 'bg-red-50' : 'bg-amber-50';
                ?>
                    <div class="p-3 rounded-lg border-l-4 <?= $borderColor ?> <?= $bgColor ?>">
                        <div class="flex justify-between items-center">
                            <h3 class="font-semibold text-gray-800 text-sm"><?= $branchStats[$branchId]['name'] ?></h3>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $priority === 'urgent' ? 'bg-red-500 text-white' : 'bg-amber-400 text-amber-800' ?>">
                                <?= ucfirst($priority) ?>
                            </span>
                        </div>
                        
                        <div class="mt-2 text-sm">
                            <!-- Main issue -->
                            <div class="flex items-start mb-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-red-600 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-gray-700 leading-tight">
                                    <span class="font-medium capitalize"><?= $branchStats[$branchId]['top_reason'] ?></span> is causing 
                                    <span class="font-medium text-red-600">₱<?= number_format($branchStats[$branchId]['top_reason_value'], 2) ?></span> loss
                                </p>
                            </div>
                            
                            <!-- Focus & trend in one line -->
                            <div class="flex items-start mb-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-blue-600 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z" />
                                </svg>
                                <p class="text-gray-700 leading-tight">
                                    Focus on 
                                    <?php if ($branchTotals[$branchId]['product_waste_value'] > $branchTotals[$branchId]['ingredient_waste_value']): ?>
                                        <span class="font-medium text-primarycol">product waste</span>
                                    <?php else: ?>
                                        <span class="font-medium text-green-600">ingredient waste</span>
                                    <?php endif; ?>
                                    • Waste is 
                                    <span class="<?= $branchStats[$branchId]['trend'] >= 0 ? 'text-red-600' : 'text-green-600' ?> font-medium">
                                        <?= $branchStats[$branchId]['trend'] >= 0 ? 'up' : 'down' ?> <?= abs(round($branchStats[$branchId]['trend'], 1)) ?>%
                                    </span>
                                </p>
                            </div>
                            
                            <!-- Key action -->
                            <div class="flex items-start">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-green-600 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-gray-700 leading-tight">
                                    <?php if (strtolower($branchStats[$branchId]['top_reason']) == 'expired'): ?>
                                        Implement strict FIFO inventory rotation
                                    <?php elseif (strtolower($branchStats[$branchId]['top_reason']) == 'damaged'): ?>
                                        Review item handling procedures
                                    <?php elseif (strtolower($branchStats[$branchId]['top_reason']) == 'overproduction'): ?>
                                        Adjust forecasting using sales data
                                    <?php else: ?>
                                        Review <?= strtolower($branchStats[$branchId]['top_reason']) ?> procedures
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Branch Comparison (Condensed) -->
                <?php if (count($sortedBranches) > 1): 
                    $highBranchId = $branchTotals[1]['total_value'] > $branchTotals[2]['total_value'] ? 1 : 2;
                    $lowBranchId = $highBranchId == 1 ? 2 : 1;
                    $percentDiff = ($branchTotals[$lowBranchId]['total_value'] > 0) 
                        ? round((($branchTotals[$highBranchId]['total_value'] - $branchTotals[$lowBranchId]['total_value']) / $branchTotals[$lowBranchId]['total_value']) * 100)
                        : 0;
                ?>
                <div class="p-3 rounded-lg border-l-4 border-purple-500 bg-purple-50">
                    <p class="text-sm text-gray-700">
                        <span class="font-medium"><?= $branchStats[$highBranchId]['name'] ?></span> has 
                        <span class="font-medium text-purple-700"><?= $percentDiff ?>% higher waste</span> than 
                        <span class="font-medium"><?= $branchStats[$lowBranchId]['name'] ?></span>. Study their waste management practices.
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Donation Opportunity (Condensed) -->
                <div class="p-3 rounded-lg border-l-4 border-green-500 bg-green-50">
                    <p class="text-sm text-gray-700 mb-1.5">
                        <?php if($totalDonations > 0): ?>
                            Working with <span class="font-medium text-green-600"><?= $uniqueNgos ?> NGO partners</span> (<?= number_format($totalDonationQuantity) ?> items)
                        <?php else: ?>
                            <span class="font-medium text-red-600">No donations</span> in current period
                        <?php endif; ?>
                    </p>
                    
                    <?php if(!empty($topItems)): ?>
                    <p class="text-sm text-gray-700">
                        <span class="font-medium">Goal:</span> Redirect 30% of safe waste to donations
                        (<?= number_format(($branchTotals[1]['total_quantity'] + $branchTotals[2]['total_quantity']) * 0.3) ?> items potential)
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bottom link to detailed view -->
            <div class="mt-3 text-center">
                <a href="#" class="text-primarycol hover:text-primarycol/80 text-sm font-medium inline-flex items-center" 
                   onclick="openDetailedRecommendations(); return false;">
                    View detailed recommendations
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Transactions & Donations Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Recent Waste Transactions -->
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
                            <td>₱<?= number_format($tx['waste_value'], 2) ?></td>
                            <td><?= ucfirst(htmlspecialchars($tx['waste_reason'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Donations (New) -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-blue-600">Recent Donations</h2>
                <a href="donation_history.php" class="text-blue-600 hover:underline text-sm">View All →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr class="bg-blue-50">
                            <th>Date</th>
                            <th>NGO</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recentDonations): ?>
                            <?php foreach ($recentDonations as $donation): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($donation['received_date'])) ?></td>
                                <td><?= htmlspecialchars($donation['ngo_name']) ?></td>
                                <td><?= htmlspecialchars($donation['product_name']) ?></td>
                                <td><?= htmlspecialchars($donation['received_quantity']) ?></td>
                                <td><?= htmlspecialchars($donation['received_by']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-500">No recent donations in this period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Scripts for charts -->
<script>
// Waste Reasons Pie Chart
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

// Donation Activity Chart
const donationChart = new ApexCharts(document.querySelector("#donationActivityChart"), {
    series: [{
        name: 'Donations',
        data: [<?= implode(',', $donationCounts) ?>]
    }],
    chart: {
        height: 300,
        type: 'bar',
        toolbar: {
            show: false
        }
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%',
            distributed: true
        }
    },
    dataLabels: {
        enabled: false
    },
    colors: ['#3B82F6', '#60A5FA', '#93C5FD', '#BFDBFE', '#DBEAFE', '#EFF6FF'],
    xaxis: {
        categories: [<?= "'" . implode("','", $donationMonths) . "'" ?>],
        labels: {
            style: {
                fontSize: '12px'
            },
            rotate: -45
        }
    },
    yaxis: {
        title: {
            text: 'Number of Donations'
        }
    },
    fill: {
        opacity: 1
    },
    tooltip: {
        y: {
            formatter: function (val) {
                return val + " donations"
            }
        }
    }
});
donationChart.render();

// Function to open detailed recommendations modal
function openDetailedRecommendations() {
    document.getElementById('recommendationsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

// Function to close modal
function closeModal() {
    document.getElementById('recommendationsModal').classList.add('hidden');
    document.body.style.overflow = 'auto'; // Restore scrolling
}

// Close modal when clicking outside content area
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('recommendationsModal');
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
});
</script>

<!-- Detailed Recommendations Modal -->
<div id="recommendationsModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="bg-primarycol text-white p-4 flex justify-between items-center">
            <h2 class="text-xl font-bold">Detailed Waste Management Recommendations</h2>
            <button onclick="closeModal()" class="text-white hover:text-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <!-- Modal Body with Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6">
            <!-- Introduction Section -->
            <div class="mb-8">
                <h3 class="text-lg font-bold text-primarycol mb-3">Executive Summary</h3>
                <p class="text-gray-700 mb-4">
                    This analysis covers waste data from <span class="font-medium"><?= date('M d, Y', strtotime($startDate)) ?></span> to 
                    <span class="font-medium"><?= date('M d, Y', strtotime($endDate)) ?></span>, with recommendations 
                    tailored to each branch's specific waste patterns.
                </p>
                
                <div class="bg-sec/30 p-4 rounded-lg">
                    <h4 class="font-semibold text-primarycol mb-2">Key Findings:</h4>
                    <ul class="list-disc pl-5 space-y-2 text-gray-700">
                        <li>Total waste value across all branches: <span class="font-medium">₱<?= number_format($totalCurrentValue, 2) ?></span></li>
                        <li>Overall waste trend: 
                            <span class="<?= $valuePercentChange >= 0 ? 'text-red-600' : 'text-green-600' ?> font-medium">
                                <?= $valuePercentChange >= 0 ? 'increased' : 'decreased' ?> by <?= abs(round($valuePercentChange, 1)) ?>%
                            </span> compared to previous period
                        </li>
                        <li>Most common waste reason: 
                            <span class="font-medium capitalize">
                                <?php 
                                    $allReasonCounts = [];
                                    foreach ($allReasons as $reason) {
                                        if (!isset($allReasonCounts[$reason['waste_reason']])) {
                                            $allReasonCounts[$reason['waste_reason']] = 0;
                                        }
                                        $allReasonCounts[$reason['waste_reason']] += $reason['count'];
                                    }
                                    arsort($allReasonCounts);
                                    echo key($allReasonCounts) ?: 'Not available';
                                ?>
                            </span>
                        </li>
                        <li>Donation activity: <span class="font-medium"><?= $totalDonations ?> donations</span> to <?= $uniqueNgos ?> NGO partners</li>
                    </ul>
                </div>
            </div>
            
            <!-- Branch-specific Recommendations -->
            <div class="mb-8">
                <h3 class="text-lg font-bold text-primarycol mb-3">Branch-Specific Recommendations</h3>
                
                <div class="space-y-6">
                    <?php foreach ([1, 2] as $branchId): 
                        // Determine branch performance
                        $wasteLevel = '';
                        $wasteLevelColor = '';
                        
                        if ($branchStats[$branchId]['trend'] > 10) {
                            $wasteLevel = 'Critical Attention Required';
                            $wasteLevelColor = 'text-red-600';
                        } elseif ($branchStats[$branchId]['trend'] > 0) {
                            $wasteLevel = 'Needs Improvement';
                            $wasteLevelColor = 'text-orange-500';
                        } elseif ($branchStats[$branchId]['trend'] < -5) {
                            $wasteLevel = 'Good Progress';
                            $wasteLevelColor = 'text-green-600';
                        } else {
                            $wasteLevel = 'Stable';
                            $wasteLevelColor = 'text-blue-600';
                        }
                    ?>
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b">
                            <div class="flex justify-between items-center">
                                <h4 class="text-lg font-bold text-primarycol"><?= htmlspecialchars($branchStats[$branchId]['name']) ?></h4>
                                <span class="px-3 py-1 rounded-full text-sm font-medium <?= $wasteLevelColor ?> bg-opacity-10 bg-current">
                                    <?= $wasteLevel ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <!-- Key Metrics -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="bg-gray-50 p-3 rounded border">
                                    <p class="text-sm text-gray-500">Total Waste Value</p>
                                    <p class="text-xl font-bold text-primarycol">₱<?= number_format($branchTotals[$branchId]['total_value'], 2) ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded border">
                                    <p class="text-sm text-gray-500">Trend vs Previous</p>
                                    <p class="text-xl font-bold <?= $branchStats[$branchId]['trend'] >= 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= $branchStats[$branchId]['trend'] >= 0 ? '↑' : '↓' ?> <?= abs(round($branchStats[$branchId]['trend'], 1)) ?>%
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded border">
                                    <p class="text-sm text-gray-500">Primary Waste Reason</p>
                                    <p class="text-xl font-bold text-gray-800 capitalize"><?= htmlspecialchars($branchStats[$branchId]['top_reason']) ?></p>
                                </div>
                            </div>
                            
                            <!-- Detailed Recommendations -->
                            <div class="space-y-3">
                                <h5 class="font-semibold text-gray-700">Primary Focus Areas:</h5>
                                <ul class="list-disc pl-5 space-y-3 text-gray-700">
                                    <?php
                                    // Generate recommendations based on top waste reason
                                    $topReason = strtolower($branchStats[$branchId]['top_reason']);
                                    
                                    if ($topReason == 'expired') {
                                    ?>
                                        <li><span class="font-medium">Inventory Management:</span> Implement strict First-In-First-Out (FIFO) inventory rotation. Place newer items at the back and older items at the front of storage areas.</li>
                                        <li><span class="font-medium">Product Dating:</span> Add colored stickers to products approaching expiration (yellow = 1 week left, red = 3 days left).</li>
                                        <li><span class="font-medium">Forecasting:</span> Review sales data for seasonal fluctuations and adjust ordering accordingly to prevent overstocking.</li>
                                    <?php } elseif ($topReason == 'damaged') { ?>
                                        <li><span class="font-medium">Staff Training:</span> Conduct refresher training on proper handling procedures for fragile items.</li>
                                        <li><span class="font-medium">Packaging Review:</span> Assess if current packaging is sufficient for product protection. Consider reinforcement for frequently damaged items.</li>
                                        <li><span class="font-medium">Storage Audit:</span> Ensure storage areas have proper shelving and organization to prevent crushing or dropping.</li>
                                    <?php } elseif ($topReason == 'overproduction') { ?>
                                        <li><span class="font-medium">Production Planning:</span> Review sales patterns and adjust batch sizes based on historical data.</li>
                                        <li><span class="font-medium">Cross-Utilization:</span> Develop recipes that use excess ingredients for staff meals or specials.</li>
                                        <li><span class="font-medium">Communication:</span> Improve communication between sales forecasting and production teams.</li>
                                    <?php } elseif ($topReason == 'quality issues') { ?>
                                        <li><span class="font-medium">Supplier Review:</span> Evaluate supplier reliability and product consistency.</li>
                                        <li><span class="font-medium">Quality Checks:</span> Implement additional quality control at receiving stage.</li>
                                        <li><span class="font-medium">Storage Conditions:</span> Check temperature and humidity levels in storage areas.</li>
                                    <?php } else { ?>
                                        <li><span class="font-medium">Process Review:</span> Analyze the specific factors contributing to '<?= htmlspecialchars($topReason) ?>' waste.</li>
                                        <li><span class="font-medium">Staff Feedback:</span> Conduct interviews with staff to understand challenges in preventing waste.</li>
                                        <li><span class="font-medium">Data Monitoring:</span> Implement more detailed tracking of this waste category to identify patterns.</li>
                                    <?php } ?>
                                    
                                    <?php 
                                    // Additional recommendations based on branch-specific data
                                    if ($branchTotals[$branchId]['product_waste_value'] > $branchTotals[$branchId]['ingredient_waste_value']):
                                    ?>
                                        <li><span class="font-medium">Product vs. Ingredient Focus:</span> Your product waste (₱<?= number_format($branchTotals[$branchId]['product_waste_value'], 2) ?>) 
                                        exceeds ingredient waste (₱<?= number_format($branchTotals[$branchId]['ingredient_waste_value'], 2) ?>). 
                                        Focus on finished product management through proper rotation, display techniques, and portioning.</li>
                                    <?php else: ?>
                                        <li><span class="font-medium">Product vs. Ingredient Focus:</span> Your ingredient waste (₱<?= number_format($branchTotals[$branchId]['ingredient_waste_value'], 2) ?>) 
                                        exceeds product waste (₱<?= number_format($branchTotals[$branchId]['product_waste_value'], 2) ?>). 
                                        Improve ingredient handling, storage, and preparation processes.</li>
                                    <?php endif; ?>
                                </ul>
                                
                                <!-- Donation Opportunities -->
                                <h5 class="font-semibold text-gray-700 mt-4">Donation Opportunities:</h5>
                                <p class="text-gray-700 mb-2">
                                    Estimated <?= round(($branchTotals[$branchId]['total_quantity'] * 0.3)) ?> items at this branch could potentially be redirected to donations instead of waste.
                                </p>
                                <ul class="list-disc pl-5 space-y-2 text-gray-700">
                                    <li>Review the <?= ucfirst(htmlspecialchars($topReason)) ?> items to identify those suitable for donation before quality deteriorates.</li>
                                    <li>Schedule regular pickups with NGO partners to ensure timely collection of donation-eligible items.</li>
                                    <li>Train staff to properly identify and separate donation-eligible items from actual waste.</li>
                                </ul>
                                
                                <!-- Implementation Plan -->
                                <h5 class="font-semibold text-gray-700 mt-4">Implementation Plan:</h5>
                                <div class="border-l-4 border-primarycol pl-4 space-y-2">
                                    <p><span class="font-medium">Immediate (1-2 weeks):</span> Address the top waste reason (<?= ucfirst(htmlspecialchars($topReason)) ?>) through staff training and process adjustments.</p>
                                    <p><span class="font-medium">Short-term (1 month):</span> Implement improved tracking systems and establish donation partnerships.</p>
                                    <p><span class="font-medium">Long-term (3+ months):</span> Review inventory systems, supplier relationships, and develop comprehensive waste reduction targets.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Cross-Branch Comparison -->
            <?php if (count($branchStats) > 1): ?>
            <div class="mb-8">
                <h3 class="text-lg font-bold text-primarycol mb-3">Cross-Branch Analysis</h3>
                
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-semibold text-gray-700 mb-3">Branch Performance Comparison</h4>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full border">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-2 px-3 border text-left text-xs font-medium text-gray-500 uppercase">Metric</th>
                                    <?php foreach ($branchStats as $branchId => $stats): ?>
                                        <th class="py-2 px-3 border text-left text-xs font-medium text-gray-500 uppercase"><?= htmlspecialchars($stats['name']) ?></th>
                                    <?php endforeach; ?>
                                    <th class="py-2 px-3 border text-left text-xs font-medium text-gray-500 uppercase">Learning Opportunity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <!-- Total Waste Value -->
                                <tr>
                                    <td class="py-2 px-3 border text-sm font-medium">Total Waste Value</td>
                                    <?php 
                                    $lowValueBranch = $branchTotals[1]['total_value'] <= $branchTotals[2]['total_value'] ? 1 : 2;
                                    $highValueBranch = $lowValueBranch == 1 ? 2 : 1;
                                    
                                    foreach ($branchStats as $branchId => $stats): 
                                        $highlightClass = $branchId == $lowValueBranch ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800';
                                    ?>
                                        <td class="py-2 px-3 border text-sm <?= $highlightClass ?>">
                                            ₱<?= number_format($branchTotals[$branchId]['total_value'], 2) ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="py-2 px-3 border text-sm">
                                        <span class="text-green-600 font-medium"><?= $branchStats[$lowValueBranch]['name'] ?></span> has 
                                        <?php 
                                            $percentDiff = ($branchTotals[$lowValueBranch]['total_value'] > 0) 
                                                ? round((($branchTotals[$highValueBranch]['total_value'] - $branchTotals[$lowValueBranch]['total_value']) / $branchTotals[$lowValueBranch]['total_value']) * 100)
                                                : 0;
                                            echo $percentDiff . '%';
                                        ?> 
                                        less waste by value.
                                    </td>
                                </tr>
                                
                                <!-- Waste Trend -->
                                <tr>
                                    <td class="py-2 px-3 border text-sm font-medium">Waste Trend</td>
                                    <?php 
                                    $bestTrendBranch = $branchStats[1]['trend'] <= $branchStats[2]['trend'] ? 1 : 2;
                                    $worstTrendBranch = $bestTrendBranch == 1 ? 2 : 1;
                                    
                                    foreach ($branchStats as $branchId => $stats): 
                                        $trendClass = $stats['trend'] < 0 ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800';
                                    ?>
                                        <td class="py-2 px-3 border text-sm <?= $trendClass ?>">
                                            <?= $stats['trend'] >= 0 ? '↑' : '↓' ?> <?= abs(round($stats['trend'], 1)) ?>%
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="py-2 px-3 border text-sm">
                                        Study <span class="font-medium"><?= $branchStats[$bestTrendBranch]['name'] ?>'s</span> process improvements.
                                    </td>
                                </tr>
                                
                                <!-- Top Waste Reason -->
                                <tr>
                                    <td class="py-2 px-3 border text-sm font-medium">Primary Waste Reason</td>
                                    <?php foreach ($branchStats as $branchId => $stats): ?>
                                        <td class="py-2 px-3 border text-sm capitalize">
                                            <?= htmlspecialchars($stats['top_reason']) ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="py-2 px-3 border text-sm">
                                        <?php if ($branchStats[1]['top_reason'] == $branchStats[2]['top_reason']): ?>
                                            Both branches struggle with the same issue. Consider company-wide training.
                                        <?php else: ?>
                                            Branches have different primary issues. Share best practices across locations.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Donation Integration Strategy -->
            <div class="mb-8">
                <h3 class="text-lg font-bold text-blue-600 mb-3">Donation Integration Strategy</h3>
                
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                    <h4 class="font-semibold text-blue-800 mb-3">Maximizing Social Impact</h4>
                    
                    <div class="space-y-4">
                        <p class="text-gray-700">
                            Current donation performance: <span class="font-medium"><?= $totalDonations ?> donations</span> to 
                            <span class="font-medium"><?= $uniqueNgos ?> NGOs</span> during this period.
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h5 class="font-medium text-blue-800 mb-2">Opportunity Assessment</h5>
                                <ul class="list-disc pl-5 space-y-2 text-gray-700">
                                    <li>Approximately <span class="font-medium"><?= number_format(($branchTotals[1]['total_quantity'] + $branchTotals[2]['total_quantity']) * 0.3) ?> items</span> could potentially be redirected to donations.</li>
                                    <li>Focus on early identification of items approaching expiration but still safe for consumption.</li>
                                    <li>Estimated social impact value: ₱<?= number_format($totalCurrentValue * 0.3, 2) ?></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h5 class="font-medium text-blue-800 mb-2">Implementation Steps</h5>
                                <ul class="list-disc pl-5 space-y-2 text-gray-700">
                                    <li>Establish clear criteria for donation-eligible items at each branch.</li>
                                    <li>Create dedicated "donation staging" areas in storage rooms.</li>
                                    <li>Develop relationships with additional <?= max(0, 5 - $uniqueNgos) ?> NGO partners.</li>
                                    <li>Set quarterly donation targets with branch manager incentives.</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="p-3 bg-white rounded border border-blue-200">
                            <h5 class="font-medium text-blue-800 mb-2">Key Performance Indicators</h5>
                            <p class="text-gray-700 mb-2">Track these metrics to measure donation program success:</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="p-2 bg-blue-50 rounded">
                                    <p class="text-sm text-gray-600">Donation Ratio Target:</p>
                                    <p class="font-bold text-blue-800">30% of eligible waste</p>
                                </div>
                                <div class="p-2 bg-blue-50 rounded">
                                    <p class="text-sm text-gray-600">NGO Partner Goal:</p>
                                    <p class="font-bold text-blue-800"><?= $uniqueNgos + 3 ?> partners</p>
                                </div>
                                <div class="p-2 bg-blue-50 rounded">
                                    <p class="text-sm text-gray-600">Monthly Donation Target:</p>
                                    <p class="font-bold text-blue-800"><?= ceil($totalDonations / ((strtotime($endDate) - strtotime($startDate)) / (30*86400)) * 1.5) ?> donations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Training & Education Recommendations -->
            <div class="mb-6">
                <h3 class="text-lg font-bold text-primarycol mb-3">Training & Education Plan</h3>
                
                <div class="bg-white rounded-lg p-4 border">
                    <div class="space-y-4">
                        <p class="text-gray-700">
                            Based on the waste patterns observed, the following training initiatives are recommended:
                        </p>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full border">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-2 px-3 border text-left text-xs font-medium text-gray-500 uppercase">Training Topic</th>
                                        <th class="py-2 px-3 border text-left text-xs font-medium text-gray-500 uppercase">Target Audience</th>
                                        <th class="py-2 px-3 border text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                                        <th class="py-2 px-3 border text-left text-xs font-medium text-gray-500 uppercase">Expected Impact</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <!-- Generate training recommendations based on top waste reasons -->
                                    <?php
                                    $topReasons = array_keys($allReasonCounts);
                                    $trainings = [
                                        'expired' => [
                                            'topic' => 'Inventory Management & FIFO Principles',
                                            'audience' => 'All inventory handlers, stockroom staff',
                                            'priority' => 'High',
                                            'impact' => 'Reduce expired waste by 40-50%'
                                        ],
                                        'damaged' => [
                                            'topic' => 'Product Handling & Storage Techniques',
                                            'audience' => 'Warehouse staff, stockers, receivers',
                                            'priority' => 'High',
                                            'impact' => 'Reduce damaged items by 30-40%'
                                        ],
                                        'overproduction' => [
                                            'topic' => 'Demand Forecasting & Production Planning',
                                            'audience' => 'Managers, production staff, planners',
                                            'priority' => 'High',
                                            'impact' => 'Reduce overproduction by 25-35%'
                                        ],
                                        'quality issues' => [
                                            'topic' => 'Quality Control Protocols',
                                            'audience' => 'QA staff, receiving personnel',
                                            'priority' => 'Medium',
                                            'impact' => 'Reduce quality-related waste by 20-30%'
                                        ]
                                    ];
                                    
                                    // Add default training
                                    $trainings['donation program'] = [
                                        'topic' => 'Donation Program Guidelines & Procedures',
                                        'audience' => 'All staff, especially floor managers',
                                        'priority' => 'Medium',
                                        'impact' => 'Increase donation conversion by 25%'
                                    ];
                                    
                                    // Display top three training recommendations
                                    $displayedTrainings = [];
                                    
                                    // First add trainings for top reasons
                                    foreach ($topReasons as $reason) {
                                        $reason = strtolower($reason);
                                        if (isset($trainings[$reason]) && count($displayedTrainings) < 3) {
                                            $displayedTrainings[] = $reason;
                                        }
                                    }
                                    
                                    // Add donation training if not already included
                                    if (count($displayedTrainings) < 3 && !in_array('donation program', $displayedTrainings)) {
                                        $displayedTrainings[] = 'donation program';
                                    }
                                    
                                    // Add other trainings if needed to reach 3
                                    foreach ($trainings as $reason => $training) {
                                        if (count($displayedTrainings) >= 3) break;
                                        if (!in_array($reason, $displayedTrainings)) {
                                            $displayedTrainings[] = $reason;
                                        }
                                    }
                                    
                                    foreach ($displayedTrainings as $reason):
                                        $training = $trainings[$reason];
                                        $priorityClass = '';
                                        if ($training['priority'] == 'High') {
                                            $priorityClass = 'bg-red-50 text-red-700';
                                        } elseif ($training['priority'] == 'Medium') {
                                            $priorityClass = 'bg-amber-50 text-amber-700';
                                        } else {
                                            $priorityClass = 'bg-blue-50 text-blue-700';
                                        }
                                    ?>
                                    <tr>
                                        <td class="py-2 px-3 border text-sm"><?= $training['topic'] ?></td>
                                        <td class="py-2 px-3 border text-sm"><?= $training['audience'] ?></td>
                                        <td class="py-2 px-3 border text-sm">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $priorityClass ?>">
                                                <?= $training['priority'] ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 border text-sm"><?= $training['impact'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-4 border-t flex justify-between items-center">
            <div class="text-gray-500 text-sm">
                Generated on <?= date('M d, Y') ?> • Data period: <?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
            </div>
            <button onclick="closeModal()" class="btn btn-sm bg-primarycol text-white hover:bg-primarycol/90">
                Close
            </button>
        </div>
    </div>
</div>
</body>
</html>
