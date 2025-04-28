<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access
checkAuth(['admin']);

$pdo = getPDO();

// Get filters from URL
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$dateFilter = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$branchFilter = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$ngoFilter = isset($_GET['ngo']) ? (int)$_GET['ngo'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Date range for filtering
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

if ($dateFilter === 'today') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($dateFilter === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($dateFilter === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($dateFilter === 'custom' && isset($_GET['start_date'], $_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// Get donation product statistics - REMOVED quantity_allocated
$donationStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(quantity_available) as total_quantity
    FROM donation_products
")->fetch(PDO::FETCH_ASSOC);

// Get NGO request statistics
$requestStats = $pdo->query("
    SELECT 
        COUNT(*) AS total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN is_received = 1 THEN 1 ELSE 0 END) AS received
    FROM ngo_donation_requests
")->fetch(PDO::FETCH_ASSOC);

// Get donated product statistics
$receivedStats = $pdo->query("
    SELECT 
        COUNT(*) as total_donations,
        SUM(received_quantity) as total_donated_qty,
        COUNT(DISTINCT ngo_id) as total_ngos
    FROM donated_products
")->fetch(PDO::FETCH_ASSOC);

// Build the query - FIXED the JOIN for NGO filtering and improved filter logic
$baseQuery = "
    SELECT 
        dp.id as donation_id,
        dp.product_id,
        dp.waste_id,
        dp.branch_id,
        dp.quantity_available,
        dp.expiry_date,
        dp.auto_approval,
        dp.donation_priority,
        dp.status,
        dp.creation_date,
        pi.name as product_name,
        pi.category,
        pi.image,
        b.name as branch_name,
        b.address as branch_address,
        
        (SELECT COUNT(*) FROM ngo_donation_requests WHERE waste_id = dp.waste_id) as request_count,
        (SELECT COUNT(*) FROM ngo_donation_requests WHERE waste_id = dp.waste_id AND is_received = 1) as received_count,
        
        (SELECT SUM(received_quantity) FROM donated_products dp JOIN ngo_donation_requests ndr 
         ON dp.donation_request_id = ndr.id WHERE ndr.waste_id = dp.waste_id) as received_quantity
    FROM 
        donation_products dp
    JOIN 
        product_info pi ON dp.product_id = pi.id
    JOIN 
        branches b ON dp.branch_id = b.id
    LEFT JOIN
        ngo_donation_requests ndr ON ndr.waste_id = dp.waste_id
    WHERE 
        1=1
";

$params = [];

// Apply filters
if ($statusFilter !== 'all') {
    $baseQuery .= " AND dp.status = ?";
    $params[] = $statusFilter;
}

// Apply date filter
$baseQuery .= " AND DATE(dp.creation_date) BETWEEN ? AND ?";
$params[] = $startDate;
$params[] = $endDate;

// Apply branch filter
if ($branchFilter > 0) {
    $baseQuery .= " AND dp.branch_id = ?";
    $params[] = $branchFilter;
}

// Apply NGO filter - FIXED: Add NGO filtering
if ($ngoFilter > 0) {
    $baseQuery .= " AND EXISTS (SELECT 1 FROM ngo_donation_requests WHERE waste_id = dp.waste_id AND ngo_id = ?)";
    $params[] = $ngoFilter;
}

// Apply search filter
if (!empty($search)) {
    $baseQuery .= " AND (pi.name LIKE ? OR pi.category LIKE ? OR b.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Make sure we use GROUP BY to avoid duplicate records
$baseQuery .= " GROUP BY dp.id";

// Add ordering
$baseQuery .= " ORDER BY dp.creation_date DESC";

// Execute the query
$stmt = $pdo->prepare($baseQuery);
$stmt->execute($params);
$donationProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get list of branches for filter dropdown
$branchesQuery = $pdo->query("SELECT id, name FROM branches ORDER BY name");
$branches = $branchesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get list of NGOs for filter dropdown
$ngosQuery = $pdo->query("
    SELECT u.id, COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name
    FROM users u
    LEFT JOIN ngo_profiles np ON u.id = np.user_id
    WHERE u.role = 'ngo'
    ORDER BY ngo_name
");
$ngos = $ngosQuery->fetchAll(PDO::FETCH_ASSOC);

// Group donations by date for easier display
$donationsByDate = [];
foreach ($donationProducts as $donation) {
    $date = date('Y-m-d', strtotime($donation['creation_date']));
    if (!isset($donationsByDate[$date])) {
        $donationsByDate[$date] = [];
    }
    $donationsByDate[$date][] = $donation;
}

// Sort by date descending
krsort($donationsByDate);

// Get top performing NGOs for chart
$topNGOs = $pdo->query("
    SELECT 
        u.id,
        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
        COUNT(*) as pickup_count,
        SUM(dp.received_quantity) as total_received
    FROM donated_products dp
    JOIN ngo_donation_requests ndr ON dp.donation_request_id = ndr.id
    JOIN users u ON ndr.ngo_id = u.id
    LEFT JOIN ngo_profiles np ON u.id = np.user_id
    GROUP BY u.id
    ORDER BY total_received DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Donation trends for chart (last 7 days)
$donationTrends = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    // Get donated quantity for this date
    $trendQuery = $pdo->prepare("
        SELECT 
            COALESCE(SUM(dp.received_quantity), 0) as donated_qty,
            COUNT(DISTINCT ndr.ngo_id) as ngo_count
        FROM donated_products dp
        JOIN ngo_donation_requests ndr ON dp.donation_request_id = ndr.id
        WHERE DATE(dp.received_date) = ?
    ");
    $trendQuery->execute([$date]);
    $result = $trendQuery->fetch(PDO::FETCH_ASSOC);
    
    $donationTrends[] = [
        'date' => date('M d', strtotime($date)),
        'quantity' => (float) $result['donated_qty'],
        'ngo_count' => (int) $result['ngo_count']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History | WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/LGU.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primarycol: '#47663B',
                        primarylight: '#5d8a4e',
                        primarydark: '#385029',
                        sec: '#E8ECD7',
                        third: '#EED3B1',
                        fourth: '#1F4529',
                        accent: '#ffa62b',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .priority-item {
            cursor: grab;
            transition: all 0.2s ease;
        }
        
        .priority-item:hover {
            transform: translateY(-2px);
        }
        
        .priority-item:active {
            cursor: grabbing;
        }
        
        .sortable-ghost {
            opacity: 0.4;
        }
        
        .rank-badge {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-weight: bold;
        }
        
        .rank-badge-1 {
            background-color: #FFD700;
            color: #333;
        }
        
        .rank-badge-2 {
            background-color: #C0C0C0;
            color: #333;
        }
        
        .rank-badge-3 {
            background-color: #CD7F32;
        }
        
        .rank-badge-4, .rank-badge-5 {
            background-color: #47663B;
        }
        
        /* Status badge styles */
        .status-badge {
            @apply px-2 py-1 rounded-full text-xs font-medium;
        }
        
        .status-available {
            @apply bg-green-100 text-green-800;
        }
        
        .status-expired {
            @apply bg-red-100 text-red-800;
        }
        
        /* Priority badge styles */
        .priority-normal {
            @apply bg-gray-100 text-gray-800;
        }
    </style>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include '../layout/nav.php' ?>
    
    <div class="flex-1 overflow-auto p-6">
        <div class="max-w-screen-2xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-primarycol">Donation History</h1>
                    <p class="text-sm text-gray-500">Comprehensive view of donation activities across all branches</p>
                </div>
                <a href="export_donation.php" class="btn btn-sm bg-primarycol text-white hover:bg-primarydark flex items-center gap-2">
                    <i class="fas fa-file-export"></i> Export Reports
                </a>
            </div>

            <!-- Dashboard Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-primarycol">
                    <h3 class="text-sm font-medium text-gray-500">Total Donation Products</h3>
                    <div class="flex items-center mt-2">
                        <div class="text-2xl font-bold text-gray-800"><?= number_format($donationStats['total']) ?></div>
                        <div class="ml-2 flex flex-col">
                            <span class="text-xs text-gray-500">Available: <span class="font-medium text-green-600"><?= number_format($donationStats['available']) ?></span></span>
                            <span class="text-xs text-gray-500">Expired: <span class="font-medium text-red-600"><?= number_format($donationStats['expired']) ?></span></span>
                        </div>
                        <div class="ml-auto bg-primarycol bg-opacity-10 p-2 rounded-full">
                            <i class="fas fa-box-open text-primarycol"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                    <h3 class="text-sm font-medium text-gray-500">NGO Requests</h3>
                    <div class="flex items-center mt-2">
                        <div class="text-2xl font-bold text-gray-800"><?= number_format($requestStats['total_requests']) ?></div>
                        <div class="ml-2 flex flex-col">
                            <span class="text-xs text-gray-500">Pending: <span class="font-medium text-amber-600"><?= number_format($requestStats['pending']) ?></span></span>
                            <span class="text-xs text-gray-500">Proccessed: <span class="font-medium text-green-600"><?= number_format($requestStats['approved']) ?></span></span>
                        </div>
                        <div class="ml-auto bg-blue-500 bg-opacity-10 p-2 rounded-full">
                            <i class="fas fa-clipboard-list text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                    <h3 class="text-sm font-medium text-gray-500">Total Donated Quantity</h3>
                    <div class="flex items-center mt-2">
                        <div class="text-2xl font-bold text-gray-800"><?= number_format($receivedStats['total_donated_qty'] ?? 0) ?></div>
                        <div class="ml-2 flex flex-col">
                            <span class="text-xs text-gray-500">Units</span>
                            <span class="text-xs text-gray-500">Total Value: <span class="font-medium text-green-600">₱<?= number_format($donationStats['total_quantity'] * 50, 2) ?></span></span>
                        </div>
                        <div class="ml-auto bg-green-500 bg-opacity-10 p-2 rounded-full">
                            <i class="fas fa-hand-holding-heart text-green-500"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                    <h3 class="text-sm font-medium text-gray-500">NGOs Served</h3>
                    <div class="flex items-center mt-2">
                        <div class="text-2xl font-bold text-gray-800"><?= number_format($receivedStats['total_ngos'] ?? 0) ?></div>
                        <div class="ml-2 flex flex-col">
                            <span class="text-xs text-gray-500">Organizations</span>
                            <span class="text-xs text-gray-500">Success Rate: <span class="font-medium text-purple-600">78%</span></span>
                        </div>
                        <div class="ml-auto bg-purple-500 bg-opacity-10 p-2 rounded-full">
                            <i class="fas fa-users text-purple-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <!-- Donation Trends Chart -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-sm font-medium text-gray-700 mb-4">Donation Trends (Last 7 Days)</h3>
                    <div class="h-64">
                        <canvas id="donationTrendsChart"></canvas>
                    </div>
                </div>

                <!-- Top NGOs Chart -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-sm font-medium text-gray-700 mb-4">Top Performing NGOs</h3>
                    <div class="h-64">
                        <canvas id="topNGOsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Filter Donations</h3>
                
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <!-- Status Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select name="status" class="select select-bordered w-full text-sm h-10">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                        </select>
                    </div>
                    
                    <!-- Date Range Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
                        <select name="date_range" id="date_range" class="select select-bordered w-full text-sm h-10">
                            <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="this_week" <?= $dateFilter === 'this_week' ? 'selected' : '' ?>>This Week</option>
                            <option value="this_month" <?= $dateFilter === 'this_month' ? 'selected' : '' ?>>This Month</option>
                            <option value="custom" <?= $dateFilter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <!-- Branch Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                        <select name="branch" class="select select-bordered w-full text-sm h-10">
                            <option value="0">All Branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $branchFilter === $branch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- NGO Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">NGO</label>
                        <select name="ngo" class="select select-bordered w-full text-sm h-10">
                            <option value="0">All NGOs</option>
                            <?php foreach ($ngos as $ngo): ?>
                                <option value="<?= $ngo['id'] ?>" <?= $ngoFilter === $ngo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ngo['ngo_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Search -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                placeholder="Search products or branches" 
                                class="input input-bordered w-full pl-10 text-sm h-10">
                            <button type="submit" class="absolute inset-y-0 left-0 px-3 flex items-center">
                                <i class="fas fa-search text-gray-400"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Custom Date Range (initially hidden) -->
                    <div id="custom_date_container" class="<?= $dateFilter === 'custom' ? '' : 'hidden' ?> md:col-span-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="input input-bordered w-full text-sm h-10">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="input input-bordered w-full text-sm h-10">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add a clear Submit button -->
                    <div class="lg:col-span-5 flex justify-end mt-2">
                        <button type="submit" class="btn btn-sm bg-primarycol text-white hover:bg-primarydark">
                            <i class="fas fa-filter mr-1"></i> Apply Filters
                        </button>
                        <a href="donation_history_admin.php" class="btn btn-sm bg-gray-200 text-gray-700 hover:bg-gray-300 ml-2">
                            <i class="fas fa-times mr-1"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Donations List -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b border-gray-200 px-5 py-4 flex justify-between items-center">
                    <h3 class="text-base font-medium text-gray-700">Donation Records</h3>
                    <div class="badge bg-primarycol text-white">
                        <?= count($donationProducts) ?> records
                    </div>
                </div>
                
                <?php if (empty($donationProducts)): ?>
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-box-open text-gray-400 text-4xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">No donation records found</h3>
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your filters to see more results</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($donationsByDate as $date => $dayDonations): ?>
                        <div class="px-5 py-3">
                            <h3 class="text-sm font-medium text-gray-500 mb-3 flex items-center">
                                <i class="fas fa-calendar-day mr-2 text-primarycol"></i>
                                <?= date('l, F j, Y', strtotime($date)) ?>
                                <span class="ml-2 text-xs text-gray-400">(<?= count($dayDonations) ?> items)</span>
                            </h3>
                            
                            <div class="space-y-3">
                                <?php foreach ($dayDonations as $donation): ?>
                                    <div class="border rounded-lg overflow-hidden">
                                        <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center">
                                            <div class="flex items-center">
                                                <?php 
                                                // Determine status badge style
                                                $statusClass = 'status-' . $donation['status'];
                                                $statusText = str_replace('_', ' ', ucfirst($donation['status']));
                                                ?>
                                                <span class="status-badge <?= $statusClass ?> mr-2"><?= $statusText ?></span>
                                                
                                                <h4 class="font-medium text-gray-700">
                                                    <?= htmlspecialchars($donation['product_name']) ?>
                                                    <span class="text-xs text-gray-500 ml-2"><?= htmlspecialchars($donation['category']) ?></span>
                                                </h4>
                                            </div>
                                            <div class="text-sm text-gray-500">ID: <?= $donation['donation_id'] ?></div>
                                        </div>
                                        
                                        <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <!-- Left Column: Product Details -->
                                            <div class="flex">
                                                <div class="w-16 h-16 bg-gray-100 rounded overflow-hidden mr-3 flex-shrink-0">
                                                    <?php if (!empty($donation['image'])): ?>
                                                        <?php
                                                        // Determine proper image path
                                                        $imagePath = "../../assets/images/default-product.jpg";
                                                        
                                                        if (!empty($donation['image'])) {
                                                            if (strpos($donation['image'], '/') !== false) {
                                                                if (strpos($donation['image'], '../../') === 0) {
                                                                    $imagePath = $donation['image'];
                                                                } else if (strpos($donation['image'], 'assets/') === 0) {
                                                                    $imagePath = '../../' . $donation['image'];
                                                                } else {
                                                                    $imagePath = $donation['image'];
                                                                }
                                                            } else {
                                                                $imagePath = "../../uploads/products/" . $donation['image'];
                                                            }
                                                        }
                                                        ?>
                                                        <img src="<?= htmlspecialchars($imagePath) ?>" 
                                                             alt="<?= htmlspecialchars($donation['product_name']) ?>"
                                                             class="w-full h-full object-cover"
                                                             onerror="this.src='../../assets/images/default-product.jpg'">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <i class="fas fa-box text-gray-400"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="text-sm">
                                                        <span class="font-medium text-gray-600">Available:</span> 
                                                        <?= $donation['quantity_available'] ?> units
                                                    </div>
                                                    <div class="text-sm">
                                                        <span class="font-medium text-gray-600">Requested:</span> 
                                                        <?= $donation['request_count'] ?? 0 ?> requests
                                                    </div>
                                                    <div class="text-sm">
                                                        <span class="font-medium text-gray-600">Expiry:</span> 
                                                        <?= date('M j, Y', strtotime($donation['expiry_date'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Middle Column: Branch & Request Info -->
                                            <div>
                                                <div class="text-sm">
                                                    <span class="font-medium text-gray-600">Branch:</span> 
                                                    <?= htmlspecialchars($donation['branch_name']) ?>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="font-medium text-gray-600">Address:</span> 
                                                    <?= htmlspecialchars($donation['branch_address']) ?>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="font-medium text-gray-600">Created:</span> 
                                                    <?= date('M j, g:i A', strtotime($donation['creation_date'])) ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Right Column: Stats & Actions -->
                                            <div>
                                                <div class="flex space-x-2 mb-2">
                                                    <div class="flex-1 bg-blue-50 rounded p-2 text-center">
                                                        <div class="text-xs text-blue-600 font-medium">Requests</div>
                                                        <div class="text-lg font-bold text-blue-700"><?= $donation['request_count'] ?? 0 ?></div>
                                                    </div>
                                                    <div class="flex-1 bg-green-50 rounded p-2 text-center">
                                                        <div class="text-xs text-green-600 font-medium">Received</div>
                                                        <div class="text-lg font-bold text-green-700"><?= $donation['received_count'] ?? 0 ?></div>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex justify-end mt-2">
                                                    <a href="ngo.php?tab=<?= $donation['status'] === 'pending' ? 'pending' : ($donation['status'] === 'fully_allocated' ? 'approved' : 'all') ?>&donation_id=<?= $donation['donation_id'] ?>" 
                                                       class="btn btn-sm bg-primarycol text-white hover:bg-primarydark flex items-center gap-1">
                                                        <i class="fas fa-search text-xs"></i> View Requests
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Pagination (if needed) -->
                <?php if (count($donationProducts) > 0): ?>
                <div class="border-t border-gray-200 px-5 py-4">
                    <div class="flex justify-center">
                        <div class="join">
                            <button class="join-item btn btn-sm">«</button>
                            <button class="join-item btn btn-sm bg-primarycol text-white">1</button>
                            <button class="join-item btn btn-sm">»</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide custom date range fields
        document.getElementById('date_range').addEventListener('change', function() {
            const customDateContainer = document.getElementById('custom_date_container');
            if (this.value === 'custom') {
                customDateContainer.classList.remove('hidden');
            } else {
                customDateContainer.classList.add('hidden');
            }
        });
        
        // Donation Trends Chart
        const trendsCtx = document.getElementById('donationTrendsChart').getContext('2d');
        const donationTrendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($donationTrends as $trend): ?>
                        '<?= $trend['date'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    {
                        label: 'Donated Quantity',
                        data: [
                            <?php foreach ($donationTrends as $trend): ?>
                                <?= $trend['quantity'] ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#47663B',
                        backgroundColor: 'rgba(71, 102, 59, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'NGOs Served',
                        data: [
                            <?php foreach ($donationTrends as $trend): ?>
                                <?= $trend['ngo_count'] ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#3B82F6',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Top NGOs Chart
        const ngosCtx = document.getElementById('topNGOsChart').getContext('2d');
        const topNGOsChart = new Chart(ngosCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($topNGOs as $ngo): ?>
                        '<?= htmlspecialchars(substr($ngo['ngo_name'], 0, 20)) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Items Received',
                    data: [
                        <?php foreach ($topNGOs as $ngo): ?>
                            <?= $ngo['total_received'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        'rgba(71, 102, 59, 0.8)',
                        'rgba(71, 102, 59, 0.7)',
                        'rgba(71, 102, 59, 0.6)',
                        'rgba(71, 102, 59, 0.5)',
                        'rgba(71, 102, 59, 0.4)'
                    ],
                    borderColor: [
                        'rgba(71, 102, 59, 1)',
                        'rgba(71, 102, 59, 1)',
                        'rgba(71, 102, 59, 1)',
                        'rgba(71, 102, 59, 1)',
                        'rgba(71, 102, 59, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Units Received'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>