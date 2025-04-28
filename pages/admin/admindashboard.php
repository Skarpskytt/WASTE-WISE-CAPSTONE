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

// Get sort parameters
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'waste_value';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'all';

// Get selected branch for   detailed view
$selectedBranchId = isset($_GET['branch_id']) && is_numeric($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;

// Get branch names
$branchQuery = $pdo->query("SELECT id, name FROM branches ORDER BY name");
$branches = $branchQuery->fetchAll(PDO::FETCH_ASSOC);

// Add a new search parameter
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter branches by search term if provided
$filteredBranches = [];
if (!empty($searchTerm)) {
    foreach ($branches as $branch) {
        if (stripos($branch['name'], $searchTerm) !== false) {
            $filteredBranches[] = $branch;
        }
    }
    
    // If only one branch matches and we're not already in branch view, redirect to branch view
    if (count($filteredBranches) == 1 && $viewMode !== 'branch') {
        header('Location: ?view=branch&branch_id=' . $filteredBranches[0]['id'] . 
               '&period=' . ($_GET['period'] ?? 'month') . 
               (isset($_GET['start_date']) ? '&start_date='.$_GET['start_date'] : '') . 
               (isset($_GET['end_date']) ? '&end_date='.$_GET['end_date'] : ''));
        exit;
    }
} else {
    $filteredBranches = $branches;
}

// Branch statistics data
$branchStats = [];
$aggregatedStats = [
    'total_waste_records' => 0,
    'total_waste_quantity' => 0,
    'total_waste_value' => 0,
    'total_donations' => 0,
    'total_quantity_available' => 0
];

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
    
    // Calculate donation efficiency ratio
    $donationRatio = 0;
    if (!empty($wasteStats['total_waste_quantity']) && $wasteStats['total_waste_quantity'] > 0) {
        $donationRatio = ($donationStats['total_quantity_available'] / $wasteStats['total_waste_quantity']) * 100;
    }
    
    // Get the waste reasons distribution
    $wasteReasonsQuery = $pdo->prepare("
        SELECT waste_reason, COUNT(*) as count
        FROM product_waste
        WHERE branch_id = ? AND waste_date BETWEEN ? AND ?
        GROUP BY waste_reason
        ORDER BY count DESC
    ");
    $wasteReasonsQuery->execute([$branchId, $startDate, $endDate]);
    $wasteReasons = $wasteReasonsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    $branchStats[$branchId] = [
        'name' => $branch['name'],
        'waste' => $wasteStats,
        'top_reason' => $topReason['waste_reason'] ?? 'No data',
        'top_reason_count' => $topReason['count'] ?? 0,
        'donations' => $donationStats,
        'categories' => $categories,
        'trend_data' => $trendData,
        'top_waste_products' => $topWasteProducts,
        'donation_ratio' => $donationRatio,
        'waste_reasons' => $wasteReasons
    ];
    
    // Aggregate statistics for all branches
    $aggregatedStats['total_waste_records'] += $wasteStats['total_waste_records'] ?? 0;
    $aggregatedStats['total_waste_quantity'] += $wasteStats['total_waste_quantity'] ?? 0;
    $aggregatedStats['total_waste_value'] += $wasteStats['total_waste_value'] ?? 0;
    $aggregatedStats['total_donations'] += $donationStats['total_donations'] ?? 0;
    $aggregatedStats['total_quantity_available'] += $donationStats['total_quantity_available'] ?? 0;
}

// Sort branches based on selected criteria
$sortedBranches = [];
foreach ($branchStats as $branchId => $stats) {
    switch ($sortBy) {
        case 'waste_value':
            $sortValue = $stats['waste']['total_waste_value'] ?? 0;
            break;
        case 'waste_quantity':
            $sortValue = $stats['waste']['total_waste_quantity'] ?? 0;
            break;
        case 'waste_records':
            $sortValue = $stats['waste']['total_waste_records'] ?? 0;
            break;
        case 'donations':
            $sortValue = $stats['donations']['total_donations'] ?? 0;
            break;
        case 'donation_ratio':
            $sortValue = $stats['donation_ratio'] ?? 0;
            break;
        case 'name':
            $sortValue = $stats['name'];
            break;
        default:
            $sortValue = $stats['waste']['total_waste_value'] ?? 0;
    }
    
    $sortedBranches[$branchId] = $sortValue;
}

// Apply sort order
if ($sortOrder === 'asc') {
    asort($sortedBranches);
} else {
    arsort($sortedBranches);
}

// Update the sorted branches array to only include filtered branches
if (!empty($searchTerm)) {
    $tempSorted = [];
    foreach ($sortedBranches as $branchId => $sortValue) {
        foreach ($filteredBranches as $branch) {
            if ($branch['id'] == $branchId) {
                $tempSorted[$branchId] = $sortValue;
                break;
            }
        }
    }
    $sortedBranches = $tempSorted;
}

// Generate smart recommendations based on the data
$recommendations = [];

// Find patterns in waste reasons across all branches
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
    
    // Identify outliers (branches with waste values significantly above average)
    $avgWasteValue = $aggregatedStats['total_waste_value'] / count($branchStats);
    if ($stats['waste']['total_waste_value'] > $avgWasteValue * 1.5) {
        $recommendations[] = [
            'type' => 'warning',
            'target' => $stats['name'],
            'message' => "{$stats['name']} has waste value significantly above average. Review inventory management practices.",
            'icon' => 'fa-triangle-exclamation'
        ];
    }
}

// Identify branches with extremely low donation ratios
foreach ($branchStats as $branchId => $stats) {
    if ($stats['donation_ratio'] < 10 && $stats['waste']['total_waste_quantity'] > 50) { // Less than 10% donation ratio with significant waste
        $recommendations[] = [
            'type' => 'opportunity',
            'target' => $stats['name'],
            'message' => "Very low donation ratio in {$stats['name']}. Significant opportunity to improve sustainability by increasing donations.",
            'icon' => 'fa-recycle'
        ];
    }
}

// Filter recommendations for branch detail view
$branchRecommendations = [];
if ($viewMode === 'branch' && $selectedBranchId) {
    $branchName = $branchStats[$selectedBranchId]['name'] ?? null;
    if ($branchName) {
        foreach ($recommendations as $rec) {
            if ($rec['target'] === $branchName) {
                $branchRecommendations[] = $rec;
            }
        }
    }
    // Add a few more targeted recommendations for the specific branch
    $stats = $branchStats[$selectedBranchId];
    if ($stats) {
        // Add branch-specific recommendations based on detailed analysis
        if (!empty($stats['top_waste_products'])) {
            $branchRecommendations[] = [
                'type' => 'insight',
                'target' => $stats['name'],
                'message' => "Your top wasted product is " . $stats['top_waste_products'][0]['product_name'] . ". Consider adjusting inventory levels for this item.",
                'icon' => 'fa-box'
            ];
        }
        
        // Analyze waste trend patterns
        if (count($stats['trend_data']) >= 3) {
            $lastThreePoints = array_slice($stats['trend_data'], -3);
            $increasing = true;
            for ($i = 1; $i < count($lastThreePoints); $i++) {
                if ($lastThreePoints[$i]['quantity'] <= $lastThreePoints[$i-1]['quantity']) {
                    $increasing = false;
                    break;
                }
            }
            
            if ($increasing) {
                $branchRecommendations[] = [
                    'type' => 'warning',
                    'target' => $stats['name'],
                    'message' => "Waste quantities have been increasing over the last three periods. Review recent inventory management changes.",
                    'icon' => 'fa-arrow-trend-up'
                ];
            }
        }
    }
}

// Limit to 6 recommendations to avoid cluttering the dashboard
$recommendations = array_slice($recommendations, 0, 6);
$branchRecommendations = array_slice($branchRecommendations, 0, 4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Branch Analytics</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/LGU.png">
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
        .branch-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .branch-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .branch-card.high-waste {
            border-left-color: #ef4444;
        }
        .branch-card.medium-waste {
            border-left-color: #f59e0b;
        }
        .branch-card.low-waste {
            border-left-color: #10b981;
        }
        .tab-active {
            background-color: #47663B !important;
            color: white !important;
        }
    </style>
</head>

<body class="flex h-screen bg-slate-100">
<?php include '../layout/nav.php' ?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <!-- Header with Time Period Controls -->
        <div class="mb-6">
            <?php if ($viewMode === 'branch' && $selectedBranchId && isset($branchStats[$selectedBranchId])): ?>
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-primarycol flex items-center">
                        <i class="fa-solid fa-store mr-2"></i>
                        <?= htmlspecialchars($branchStats[$selectedBranchId]['name']) ?> Dashboard
                    </h1>
                    <a href="?view=all<?= isset($_GET['period']) ? '&period='.$_GET['period'] : '' ?><?= isset($_GET['start_date']) ? '&start_date='.$_GET['start_date'] : '' ?><?= isset($_GET['end_date']) ? '&end_date='.$_GET['end_date'] : '' ?>" 
                       class="btn btn-sm bg-gray-200 hover:bg-gray-300 text-gray-800">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Back to All Branches
                    </a>
                </div>
            <?php else: ?>
                <h1 class="text-2xl font-bold text-primarycol flex items-center">
                    <i class="fa-solid fa-chart-line mr-2"></i>
                    Branch Analytics Dashboard
                </h1>
            <?php endif; ?>
            
            <div class="mt-4 flex flex-wrap gap-4">
                <!-- Time Period Filter -->
                <form method="GET" class="flex flex-wrap gap-2 items-center ml-auto">
                    <?php if ($viewMode === 'branch'): ?>
                        <input type="hidden" name="view" value="branch">
                        <input type="hidden" name="branch_id" value="<?= $selectedBranchId ?>">
                    <?php else: ?>
                        <input type="hidden" name="view" value="all">
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['sort'])): ?>
                        <input type="hidden" name="sort" value="<?= $_GET['sort'] ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['order'])): ?>
                        <input type="hidden" name="order" value="<?= $_GET['order'] ?>">
                    <?php endif; ?>
                    
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
            
            <div class="mt-2 text-sm text-gray-600">
                Showing data from <span class="font-medium"><?= date('M j, Y', strtotime($startDate)) ?></span> to <span class="font-medium"><?= date('M j, Y', strtotime($endDate)) ?></span>
            </div>
        </div>

        <?php if ($viewMode === 'all'): ?>
        <!-- NEW SECTION: Branch Search and Filter -->
        <section class="mb-6">
            <div class="bg-white rounded-lg shadow-md p-4">
                <h2 class="text-lg font-semibold mb-4">Find a Branch</h2>
                
                <form method="GET" action="" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="view" value="all">
                    
                    <?php if (isset($_GET['period'])): ?>
                        <input type="hidden" name="period" value="<?= $_GET['period'] ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['start_date'])): ?>
                        <input type="hidden" name="start_date" value="<?= $_GET['start_date'] ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['end_date'])): ?>
                        <input type="hidden" name="end_date" value="<?= $_GET['end_date'] ?>">
                    <?php endif; ?>
                    
                    <div class="flex-1">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" 
                               placeholder="Search by branch name..." 
                               class="input input-bordered w-full max-w-xl focus:ring-primarycol">
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <select name="sort" class="select select-bordered select-sm">
                            <option value="name" <?= ($sortBy === 'name') ? 'selected' : '' ?>>Branch Name</option>
                            <option value="waste_value" <?= ($sortBy === 'waste_value') ? 'selected' : '' ?>>Waste Value</option>
                            <option value="waste_quantity" <?= ($sortBy === 'waste_quantity') ? 'selected' : '' ?>>Waste Quantity</option>
                            <option value="waste_records" <?= ($sortBy === 'waste_records') ? 'selected' : '' ?>>Waste Records</option>
                            <option value="donations" <?= ($sortBy === 'donations') ? 'selected' : '' ?>>Donations</option>
                            <option value="donation_ratio" <?= ($sortBy === 'donation_ratio') ? 'selected' : '' ?>>Donation Ratio</option>
                        </select>
                        
                        <select name="order" class="select select-bordered select-sm">
                            <option value="asc" <?= ($sortOrder === 'asc') ? 'selected' : '' ?>>A-Z / Lowest First</option>
                            <option value="desc" <?= ($sortOrder === 'desc') ? 'selected' : '' ?>>Z-A / Highest First</option>
                        </select>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-sm bg-primarycol hover:bg-primarycol/80 text-white border-none">
                            <i class="fa-solid fa-search mr-1"></i> Search
                        </button>
                        
                        <?php if (!empty($searchTerm)): ?>
                            <a href="?view=all<?= isset($_GET['period']) ? '&period='.$_GET['period'] : '' ?><?= isset($_GET['start_date']) ? '&start_date='.$_GET['start_date'] : '' ?><?= isset($_GET['end_date']) ? '&end_date='.$_GET['end_date'] : '' ?>" 
                               class="btn btn-sm bg-gray-200 hover:bg-gray-300 text-gray-800">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <?php if (!empty($searchTerm)): ?>
                    <div class="mt-3 text-sm">
                        <p class="text-gray-600">
                            Showing <?= count($sortedBranches) ?> branch<?= count($sortedBranches) !== 1 ? 'es' : '' ?> 
                            matching "<?= htmlspecialchars($searchTerm) ?>"
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
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

        <!-- All Branches Overview -->
        <section class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold">Branch Performance</h2>
            </div>
            
            <!-- Aggregate Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <!-- Total Waste Value -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Total Waste Value</div>
                    <div class="text-2xl font-bold mt-1">₱<?= number_format($aggregatedStats['total_waste_value'], 2) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Across all branches</div>
                </div>
                
                <!-- Total Waste Quantity -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Total Waste Quantity</div>
                    <div class="text-2xl font-bold mt-1"><?= number_format($aggregatedStats['total_waste_quantity']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Items discarded</div>
                </div>
                
                <!-- Total Waste Records -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Waste Records</div>
                    <div class="text-2xl font-bold mt-1"><?= number_format($aggregatedStats['total_waste_records']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Total entries</div>
                </div>
                
                <!-- Donation Efficiency -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Donation Rate</div>
                    <?php 
                    $overallDonationRate = 0;
                    if ($aggregatedStats['total_waste_quantity'] > 0) {
                        $overallDonationRate = ($aggregatedStats['total_quantity_available'] / $aggregatedStats['total_waste_quantity']) * 100;
                    }
                    ?>
                    <div class="text-2xl font-bold mt-1"><?= number_format($overallDonationRate, 1) ?>%</div>
                    <div class="text-xs text-gray-500 mt-1">Average across branches</div>
                </div>
            </div>
            
            <!-- Branch Cards -->
            <?php if (empty($sortedBranches)): ?>
                <div class="bg-white p-6 rounded-lg shadow text-center">
                    <i class="fa-solid fa-store text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-lg font-bold text-gray-700">No branches found</h3>
                    <p class="text-gray-500">Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <?php foreach ($sortedBranches as $branchId => $sortValue): 
                        $stats = $branchStats[$branchId];
                        // Determine card border color based on waste value
                        $cardClass = 'branch-card';
                        $avgWasteValue = $aggregatedStats['total_waste_value'] / count($branchStats);
                        
                        if ($stats['waste']['total_waste_value'] > $avgWasteValue * 1.2) {
                            $cardClass .= ' high-waste';
                        } elseif ($stats['waste']['total_waste_value'] > $avgWasteValue * 0.8) {
                            $cardClass .= ' medium-waste';
                        } else {
                            $cardClass .= ' low-waste';
                        }
                    ?>
                    <div class="<?= $cardClass ?> bg-white p-4 rounded-lg shadow">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-lg"><?= htmlspecialchars($stats['name']) ?></h3>
                                <p class="text-xs text-gray-500"><?= $stats['waste']['total_waste_records'] ?? 0 ?> waste records</p>
                            </div>
                            
                            <a href="?view=branch&branch_id=<?= $branchId ?>&period=<?= $_GET['period'] ?? 'month' ?><?= isset($_GET['start_date']) ? '&start_date='.$_GET['start_date'] : '' ?><?= isset($_GET['end_date']) ? '&end_date='.$_GET['end_date'] : '' ?>" 
                               class="btn btn-xs bg-primarycol hover:bg-primarycol/80 text-white border-none">
                                View Details
                            </a>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <div class="text-sm text-gray-500">Waste Value</div>
                                <div class="text-xl font-semibold">₱<?= number_format($stats['waste']['total_waste_value'] ?? 0, 2) ?></div>
                            </div>
                            
                            <div>
                                <div class="text-sm text-gray-500">Waste Quantity</div>
                                <div class="text-xl font-semibold"><?= number_format($stats['waste']['total_waste_quantity'] ?? 0) ?></div>
                            </div>
                            
                            <div>
                                <div class="text-sm text-gray-500">Donations</div>
                                <div class="text-xl font-semibold"><?= number_format($stats['donations']['total_quantity_available'] ?? 0) ?></div>
                            </div>
                            
                            <div>
                                <div class="text-sm text-gray-500">Donation Ratio</div>
                                <div class="text-xl font-semibold"><?= number_format($stats['donation_ratio'], 1) ?>%</div>
                            </div>
                        </div>
                        
                        <?php if (!empty($stats['top_reason']) && $stats['top_reason'] != 'No data'): ?>
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="flex items-center">
                                <div class="text-sm">
                                    <span class="text-gray-500">Top waste reason:</span> 
                                    <span class="font-medium"><?= htmlspecialchars(ucfirst($stats['top_reason'])) ?></span>
                                    (<?= number_format($stats['top_reason_count']) ?> occurrences)
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($stats['top_waste_products'])): ?>
                        <div class="mt-3">
                            <div class="text-sm font-medium text-gray-500">Top wasted product:</div>
                            <div class="mt-1">
                                <span class="font-medium"><?= htmlspecialchars($stats['top_waste_products'][0]['product_name']) ?></span>
                                <span class="text-sm text-gray-500">
                                    (<?= number_format($stats['top_waste_products'][0]['total_quantity']) ?> items, 
                                    ₱<?= number_format($stats['top_waste_products'][0]['total_value'], 2) ?>)
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- Overall Charts -->
        <section class="mb-8">
            <h2 class="text-lg font-bold mb-4">Overall Analytics</h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Overall Waste Trend Chart -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="font-bold mb-4">Waste Trends All Branches</h3>
                    <div id="overallTrendChart" class="h-64"></div>
                </div>
                
                <!-- Branch Comparison Chart -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="font-bold mb-4">Waste Value by Branch</h3>
                    <div id="branchComparisonChart" class="h-64"></div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if ($viewMode === 'branch' && $selectedBranchId && isset($branchStats[$selectedBranchId])): 
            $branchData = $branchStats[$selectedBranchId];
        ?>
        <!-- Individual Branch Dashboard -->
        
        <!-- Branch Recommendations -->
        <?php if (!empty($branchRecommendations)): ?>
        <section class="mb-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fa-solid fa-lightbulb text-yellow-500 mr-2"></i>
                Recommendations for <?= htmlspecialchars($branchData['name']) ?>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach($branchRecommendations as $index => $rec): 
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
                            <p class="text-sm text-gray-700"><?= htmlspecialchars($rec['message']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Branch Statistics Summary -->
        <section class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Waste Value -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Total Waste Value</div>
                    <div class="text-2xl font-bold mt-1">₱<?= number_format($branchData['waste']['total_waste_value'] ?? 0, 2) ?></div>
                    <div class="text-xs text-gray-500 mt-1">For selected period</div>
                </div>
                
                <!-- Waste Quantity -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Waste Quantity</div>
                    <div class="text-2xl font-bold mt-1"><?= number_format($branchData['waste']['total_waste_quantity'] ?? 0) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Items discarded</div>
                </div>
                
                <!-- Records Count -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Waste Records</div>
                    <div class="text-2xl font-bold mt-1"><?= number_format($branchData['waste']['total_waste_records'] ?? 0) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Total entries</div>
                </div>
                
                <!-- Donation Ratio -->
                <div class="stat-card bg-white p-4 rounded-lg shadow">
                    <div class="text-gray-500 text-sm">Donation Ratio</div>
                    <div class="text-2xl font-bold mt-1"><?= number_format($branchData['donation_ratio'], 1) ?>%</div>
                    <div class="text-xs text-gray-500 mt-1">Of waste converted to donations</div>
                </div>
            </div>
        </section>
        
        <!-- Branch Charts -->
        <section class="mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Waste Trend Chart -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="font-bold mb-4">Waste Trend Over Time</h3>
                    <div id="branchTrendChart" class="h-64"></div>
                </div>
                
                <!-- Category Distribution -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="font-bold mb-4">Waste by Category</h3>
                    <div id="branchCategoryChart" class="h-64"></div>
                </div>
            </div>
        </section>
        
        <!-- Additional Charts -->
        <section class="mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Waste Reasons Distribution -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="font-bold mb-4">Waste Reasons Distribution</h3>
                    <div id="wasteReasonsPieChart" class="h-64"></div>
                </div>
                
                <!-- Top Waste Products -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="font-bold mb-4">Top Wasted Products</h3>
                    <div id="topProductsChart" class="h-64"></div>
                </div>
            </div>
        </section>
        
        <!-- Top Waste Products Table -->
        <section class="mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold mb-4">Top Waste Products Details</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% of Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $totalQty = $branchData['waste']['total_waste_quantity'] ?? 1; // Avoid division by zero
                            foreach($branchData['top_waste_products'] as $index => $product): 
                                $percentOfTotal = ($product['total_quantity'] / $totalQty) * 100;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($product['category']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($product['total_quantity']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    ₱<?= number_format($product['total_value'], 2) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($percentOfTotal, 1) ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>
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
    
    <?php if ($viewMode === 'branch' && $selectedBranchId && isset($branchStats[$selectedBranchId])): 
        $branchData = $branchStats[$selectedBranchId];
    ?>
    // Branch Detail Charts
    
    // Waste Trend Chart
    const branchTrendData = <?= json_encode(array_map(function($item) {
        return [
            'x' => $item['date'], 
            'y' => floatval($item['quantity'])
        ];
    }, $branchData['trend_data'] ?? [])) ?>;
    
    const branchTrendOptions = {
        series: [{
            name: 'Waste Quantity',
            data: branchTrendData
        }],
        chart: {
            type: 'area',
            height: 250,
            toolbar: {
                show: false
            },
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        colors: ['#47663B'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.2,
                stops: [0, 90, 100]
            }
        },
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
        tooltip: {
            x: {
                format: 'dd MMM'
            }
        }
    };
    
    const branchTrendChart = new ApexCharts(document.querySelector("#branchTrendChart"), branchTrendOptions);
    branchTrendChart.render();
    
    // Category Distribution Chart
    const categoryData = <?= json_encode(array_map(function($item) {
        return [
            'x' => $item['category'], 
            'y' => floatval($item['quantity'])
        ];
    }, array_slice($branchData['categories'] ?? [], 0, 5))) ?>;
    
    const categoryOptions = {
        series: [{
            name: 'Quantity',
            data: categoryData
        }],
        chart: {
            type: 'bar',
            height: 250,
            toolbar: {
                show: false
            },
        },
        plotOptions: {
            bar: {
                borderRadius: 3,
                columnWidth: '70%',
                distributed: true,
                dataLabels: {
                    position: 'top'
                },
            }
        },
        dataLabels: {
            enabled: false
        },
        colors: ['#47663B', '#7C9473', '#AEBF92', '#D0DCAC', '#E8ECD7'],
        xaxis: {
            type: 'category',
            labels: {
                rotate: -45,
                style: {
                    fontSize: '12px'
                }
            }
        },
        yaxis: {
            title: {
                text: 'Quantity'
            }
        },
        title: {
            text: 'Waste by Category',
            align: 'center',
            style: {
                fontSize: '14px'
            }
        }
    };
    
    const branchCategoryChart = new ApexCharts(document.querySelector("#branchCategoryChart"), categoryOptions);
    branchCategoryChart.render();
    
    // Waste Reasons Pie Chart
    const wasteReasonsData = <?= json_encode(array_map(function($item) {
        return floatval($item['count']);
    }, $branchData['waste_reasons'] ?? [])) ?>;
    
    const wasteReasonsLabels = <?= json_encode(array_map(function($item) {
        return ucfirst($item['waste_reason']);
    }, $branchData['waste_reasons'] ?? [])) ?>;
    
    const wasteReasonsPieOptions = {
        series: wasteReasonsData,
        chart: {
            type: 'pie',
            height: 250,
            toolbar: {
                show: false
            },
        },
        labels: wasteReasonsLabels,
        colors: ['#47663B', '#7C9473', '#AEBF92', '#D0DCAC', '#E8ECD7', '#b7c9a8'],
        legend: {
            position: 'bottom'
        },
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                },
                legend: {
                    position: 'bottom'
                }
            }
        }]
    };
    
    const wasteReasonsPieChart = new ApexCharts(document.querySelector("#wasteReasonsPieChart"), wasteReasonsPieOptions);
    wasteReasonsPieChart.render();
    
    // Top Products Chart
    const topProductsData = <?= json_encode(array_map(function($item) {
        return [
            'x' => $item['product_name'],
            'y' => floatval($item['total_quantity'])
        ];
    }, array_slice($branchData['top_waste_products'] ?? [], 0, 5))) ?>;
    
    const topProductsOptions = {
        series: [{
            name: 'Quantity',
            data: topProductsData
        }],
        chart: {
            type: 'bar',
            height: 250,
            toolbar: {
                show: false
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 3,
                distributed: true,
                dataLabels: {
                    position: 'top'
                },
            }
        },
        colors: ['#47663B', '#7C9473', '#AEBF92', '#D0DCAC', '#E8ECD7'],
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val.toFixed(0);
            },
            offsetX: 20,
            style: {
                fontSize: '12px',
                colors: ['#333']
            }
        },
        xaxis: {
            type: 'category',
            labels: {
                style: {
                    fontSize: '12px'
                }
            }
        }
    };
    
    const topProductsChart = new ApexCharts(document.querySelector("#topProductsChart"), topProductsOptions);
    topProductsChart.render();
    
    <?php endif; ?>
    
    <?php if ($viewMode === 'all'): ?>
    // Overall waste trend chart for all branches
    // Combine all branch data and group by date
    const overallTrendData = {};
    
    <?php foreach ($branchStats as $branchId => $stats): 
        if (empty($stats['trend_data'])) continue;
    ?>
        <?php foreach ($stats['trend_data'] as $dataPoint): ?>
            if (!overallTrendData['<?= $dataPoint['date'] ?>']) {
                overallTrendData['<?= $dataPoint['date'] ?>'] = 0;
            }
            overallTrendData['<?= $dataPoint['date'] ?>'] += <?= floatval($dataPoint['quantity']) ?>;
        <?php endforeach; ?>
    <?php endforeach; ?>
    
    const overallTrendSeries = Object.keys(overallTrendData).map(date => ({
        x: date,
        y: overallTrendData[date]
    })).sort((a, b) => new Date(a.x) - new Date(b.x));
    
    // Overall waste trend chart
    const overallTrendOptions = {
        series: [{
            name: 'All Branches',
            data: overallTrendSeries
        }],
        chart: {
            type: 'area',
            height: 250,
            toolbar: {
                show: false
            },
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        colors: ['#47663B'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.2,
                stops: [0, 90, 100]
            }
        },
        xaxis: {
            type: 'datetime',
            labels: {
                format: 'dd MMM',
            }
        },
        yaxis: {
            title: {
                text: 'Total Waste Quantity'
            }
        },
        tooltip: {
            x: {
                format: 'dd MMM'
            }
        }
    };
    
    const overallTrendChart = new ApexCharts(document.querySelector("#overallTrendChart"), overallTrendOptions);
    overallTrendChart.render();
    
    // Branch comparison chart
    const branchComparisonData = <?= json_encode(array_map(function($branchId) use ($branchStats) {
        return [
            'x' => $branchStats[$branchId]['name'],
            'y' => floatval($branchStats[$branchId]['waste']['total_waste_value'] ?? 0)
        ];
    }, array_keys($sortedBranches))) ?>;
    
    const branchComparisonOptions = {
        series: [{
            name: 'Waste Value',
            data: branchComparisonData
        }],
        chart: {
            type: 'bar',
            height: 250,
            toolbar: {
                show: false
            },
        },
        plotOptions: {
            bar: {
                borderRadius: 3,
                horizontal: true,
                distributed: true,
                dataLabels: {
                    position: 'top'
                },
            }
        },
        colors: ['#47663B', '#7C9473', '#AEBF92', '#D0DCAC', '#E8ECD7'],
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return '₱' + val.toFixed(2);
            },
            offsetX: 20,
            style: {
                fontSize: '12px',
                colors: ['#333']
            }
        },
        xaxis: {
            categories: branchComparisonData.map(item => item.x),
            labels: {
                formatter: function(val) {
                    return '₱' + parseFloat(val).toFixed(2);
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return '₱' + val.toFixed(2);
                }
            }
        }
    };
    
    const branchComparisonChart = new ApexCharts(document.querySelector("#branchComparisonChart"), branchComparisonOptions);
    branchComparisonChart.render();
    <?php endif; ?>
    
    // Add highlight search term functionality
    function highlightSearchTerm() {
        const searchTerm = "<?= addslashes($searchTerm) ?>";
        if (!searchTerm) return;
        
        document.querySelectorAll('.branch-card h3').forEach(heading => {
            if (heading.innerText.toLowerCase().includes(searchTerm.toLowerCase())) {
                const highlighted = heading.innerText.replace(
                    new RegExp(searchTerm, 'gi'), 
                    match => `<span class="bg-yellow-100 px-1 rounded">${match}</span>`
                );
                heading.innerHTML = highlighted;
            }
        });
    }
    
    // Call highlight function after page loads
    document.addEventListener('DOMContentLoaded', highlightSearchTerm);
</script>

</body>
</html>