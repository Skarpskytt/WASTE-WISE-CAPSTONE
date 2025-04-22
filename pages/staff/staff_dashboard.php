<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug log
error_log("Staff Dashboard - Session data: " . print_r($_SESSION, true));

require_once '../../config/db_connect.php';
require_once '../../config/app_config.php';

// Check authentication and role
if (!isset($_SESSION['authenticated']) || !isset($_SESSION['role']) || 
    !in_array($_SESSION['role'], ['staff', 'company'])) {
    $_SESSION['error'] = 'Please log in to access this page.';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Check branch assignment
if (!isset($_SESSION['branch_id'])) {
    $_SESSION['error'] = 'No branch assigned to your account.';
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get branch ID from session
$pdo = getPDO();  // Make sure we initialize the database connection
$branchId = $_SESSION['branch_id'];

// Get branch name
$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: "Branch $branchId";

// Make sure we have the user's name for display
if (!isset($_SESSION['fname']) || empty($_SESSION['fname'])) {
    // If name isn't in session, fetch it from the database
    try {
        $userStmt = $pdo->prepare("SELECT fname, lname FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            $_SESSION['fname'] = $userData['fname'];
            $_SESSION['lname'] = $userData['lname'];
        }
    } catch (PDOException $e) {
        // Silently handle error - just use generic name if can't fetch
    }
}

// Fallback to a generic greeting if still can't get name
$userName = isset($_SESSION['fname']) ? $_SESSION['fname'] : 'Staff';

// Today's date
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$lastWeek = date('Y-m-d', strtotime('-7 days'));
$lastMonth = date('Y-m-d', strtotime('-30 days'));

// Get today's waste statistics
$todayWasteStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS record_count,
        SUM(waste_quantity) AS total_quantity,
        SUM(waste_value) AS total_value
    FROM product_waste
    WHERE branch_id = ? AND DATE(waste_date) = ?
");
$todayWasteStmt->execute([$branchId, $today]);
$todayWaste = $todayWasteStmt->fetch(PDO::FETCH_ASSOC);

// Get this month's waste statistics
$monthWasteStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS record_count,
        SUM(waste_quantity) AS total_quantity,
        SUM(waste_value) AS total_value
    FROM product_waste
    WHERE branch_id = ? AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
");
$monthWasteStmt->execute([$branchId]);
$monthWaste = $monthWasteStmt->fetch(PDO::FETCH_ASSOC);

// Get category breakdown for this month
$categoryStmt = $pdo->prepare("
    SELECT 
        pi.category,
        SUM(pw.waste_quantity) AS quantity,
        SUM(pw.waste_value) AS value
    FROM 
        product_waste pw
    JOIN 
        product_info pi ON pw.product_id = pi.id
    WHERE 
        pw.branch_id = ? 
        AND pw.waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY 
        pi.category
    ORDER BY 
        quantity DESC
");
$categoryStmt->execute([$branchId]);
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Get waste reasons breakdown
$reasonStmt = $pdo->prepare("
    SELECT 
        waste_reason,
        COUNT(*) AS count,
        SUM(waste_quantity) AS quantity,
        SUM(waste_value) AS value
    FROM 
        product_waste
    WHERE 
        branch_id = ? 
        AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY 
        waste_reason
    ORDER BY 
        count DESC
");
$reasonStmt->execute([$branchId]);
$reasons = $reasonStmt->fetchAll(PDO::FETCH_ASSOC);

// Get donation statistics
$donationStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_requests,
        SUM(CASE WHEN is_received = 1 THEN 1 ELSE 0 END) AS completed_requests,
        SUM(CASE WHEN status = 'approved' AND is_received = 0 THEN 1 ELSE 0 END) AS pending_pickups
    FROM 
        ngo_donation_requests
    WHERE 
        branch_id = ? 
        AND request_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
");
$donationStmt->execute([$branchId]);
$donations = $donationStmt->fetch(PDO::FETCH_ASSOC);

// Get pending donation pickups for today
$todayPickupsStmt = $pdo->prepare("
    SELECT 
        ndr.id,
        ndr.pickup_date, 
        ndr.pickup_time,
        ndr.quantity_requested,
        pi.name AS product_name,
        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) AS ngo_name
    FROM 
        ngo_donation_requests ndr
    JOIN 
        product_info pi ON ndr.product_id = pi.id
    JOIN 
        users u ON ndr.ngo_id = u.id
    LEFT JOIN 
        ngo_profiles np ON u.id = np.user_id
    WHERE 
        ndr.branch_id = ? 
        AND ndr.status = 'approved' 
        AND ndr.is_received = 0
        AND ndr.pickup_date = CURRENT_DATE()
    ORDER BY 
        ndr.pickup_time ASC
");
$todayPickupsStmt->execute([$branchId]);
$todayPickups = $todayPickupsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity for this branch (last 7 days)
$activityStmt = $pdo->prepare("
    SELECT 
        'waste' AS type,
        pw.id,
        pw.waste_date AS date,
        pi.name AS product_name,
        pw.waste_quantity AS quantity,
        pw.waste_value AS value,
        pw.waste_reason AS reason,
        CONCAT(u.fname, ' ', u.lname) AS staff_name
    FROM 
        product_waste pw
    JOIN 
        product_info pi ON pw.product_id = pi.id
    JOIN
        users u ON pw.staff_id = u.id
    WHERE 
        pw.branch_id = ? 
        AND pw.waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
    
    UNION
    
    SELECT 
        'donation' AS type,
        ndr.id,
        ndr.request_date AS date,
        pi.name AS product_name,
        ndr.quantity_requested AS quantity,
        ndr.quantity_requested * pi.price_per_unit AS value,
        ndr.status AS reason,
        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) AS staff_name
    FROM 
        ngo_donation_requests ndr
    JOIN 
        product_info pi ON ndr.product_id = pi.id
    JOIN
        users u ON ndr.ngo_id = u.id
    LEFT JOIN
        ngo_profiles np ON u.id = np.user_id
    WHERE 
        ndr.branch_id = ? 
        AND ndr.request_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        
    ORDER BY 
        date DESC
    LIMIT 10
");
$activityStmt->execute([$branchId, $branchId]);
$recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Get expiring products (FEFO - First Expire, First Out)
$fefoStmt = $pdo->prepare("
    SELECT 
        ps.id,
        ps.batch_number,
        ps.quantity,
        ps.production_date,
        ps.expiry_date,
        DATEDIFF(ps.expiry_date, CURRENT_DATE()) as days_until_expiry,
        pi.name as product_name,
        pi.category,
        pi.price_per_unit,
        pi.image
    FROM 
        product_stock ps
    JOIN 
        product_info pi ON ps.product_info_id = pi.id
    WHERE 
        ps.branch_id = ? 
        AND ps.quantity > 0
        AND ps.expiry_date >= CURRENT_DATE()
        AND ps.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    ORDER BY 
        ps.expiry_date ASC
    LIMIT 5
");
$fefoStmt->execute([$branchId]);
$expiringProducts = $fefoStmt->fetchAll(PDO::FETCH_ASSOC);

// Get oldest products (FIFO - First In, First Out)
$fifoStmt = $pdo->prepare("
    SELECT 
        ps.id,
        ps.batch_number,
        ps.quantity,
        ps.production_date,
        ps.expiry_date,
        DATEDIFF(CURRENT_DATE(), ps.production_date) as days_since_production,
        pi.name as product_name,
        pi.category,
        pi.price_per_unit,
        pi.image
    FROM 
        product_stock ps
    JOIN 
        product_info pi ON ps.product_info_id = pi.id
    WHERE 
        ps.branch_id = ? 
        AND ps.quantity > 0
        AND ps.expiry_date >= CURRENT_DATE()
    ORDER BY 
        ps.production_date ASC
    LIMIT 5
");
$fifoStmt->execute([$branchId]);
$oldestProducts = $fifoStmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily waste data for chart (last 7 days)
$chartStmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(waste_date, '%Y-%m-%d') AS date,
        SUM(waste_quantity) AS quantity,
        SUM(waste_value) AS value
    FROM 
        product_waste
    WHERE 
        branch_id = ? 
        AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
    GROUP BY 
        DATE_FORMAT(waste_date, '%Y-%m-%d')
    ORDER BY 
        date ASC
");
$chartStmt->execute([$branchId]);
$dailyWasteData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

// Generate smart recommendations based on data
$recommendations = [];

// Check if waste is increasing compared to last week
$lastWeekWasteStmt = $pdo->prepare("
    SELECT SUM(waste_value) AS last_week_value
    FROM product_waste
    WHERE branch_id = ? 
    AND waste_date BETWEEN DATE_SUB(CURRENT_DATE(), INTERVAL 14 DAY) AND DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
");
$lastWeekWasteStmt->execute([$branchId]);
$lastWeekWaste = $lastWeekWasteStmt->fetch(PDO::FETCH_ASSOC);

$thisWeekWasteStmt = $pdo->prepare("
    SELECT SUM(waste_value) AS this_week_value
    FROM product_waste
    WHERE branch_id = ? 
    AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
");
$thisWeekWasteStmt->execute([$branchId]);
$thisWeekWaste = $thisWeekWasteStmt->fetch(PDO::FETCH_ASSOC);

// Check if waste is increasing
if ($lastWeekWaste['last_week_value'] > 0 && $thisWeekWaste['this_week_value'] > 0) {
    $wasteChange = (($thisWeekWaste['this_week_value'] - $lastWeekWaste['last_week_value']) / $lastWeekWaste['last_week_value']) * 100;
    
    if ($wasteChange > 20) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => 'Product waste has increased by ' . number_format($wasteChange, 1) . '% compared to last week. Consider reviewing inventory management practices.',
            'icon' => 'fa-exclamation-triangle'
        ];
    } elseif ($wasteChange < -20) {
        $recommendations[] = [
            'type' => 'success',
            'message' => 'Great job! Product waste has decreased by ' . number_format(abs($wasteChange), 1) . '% compared to last week.',
            'icon' => 'fa-check-circle'
        ];
    }
}

// Check expiring products count
if (count($expiringProducts) > 3) {
    $recommendations[] = [
        'type' => 'urgent',
        'message' => 'You have ' . count($expiringProducts) . ' products expiring within 7 days. Consider prioritizing these for sales or donations.',
        'icon' => 'fa-calendar-times'
    ];
}

// Check if there are pending pickups today
if (count($todayPickups) > 0) {
    $recommendations[] = [
        'type' => 'info',
        'message' => 'You have ' . count($todayPickups) . ' donation pickup' . (count($todayPickups) > 1 ? 's' : '') . ' scheduled for today.',
        'icon' => 'fa-truck'
    ];
}

// Check for common waste reasons
if (!empty($reasons)) {
    $topReason = $reasons[0];
    if ($topReason['waste_reason'] === 'expired') {
        $recommendations[] = [
            'type' => 'action',
            'message' => 'Expiration is your top waste reason. Consider implementing a more robust FEFO inventory system.',
            'icon' => 'fa-clock'
        ];
    } elseif ($topReason['waste_reason'] === 'overproduction') {
        $recommendations[] = [
            'type' => 'action',
            'message' => 'Overproduction is your top waste reason. Consider reviewing your production planning and forecasting.',
            'icon' => 'fa-chart-line'
        ];
    }
}

// Suggest donation opportunities
$donationOpportunityStmt = $pdo->prepare("
    SELECT COUNT(*) AS count
    FROM product_waste pw
    LEFT JOIN donation_products dp ON pw.id = dp.waste_id
    WHERE pw.branch_id = ?
    AND pw.waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    AND pw.waste_reason IN ('overproduction', 'unsold')
    AND dp.id IS NULL
");
$donationOpportunityStmt->execute([$branchId]);
$donationOpportunity = $donationOpportunityStmt->fetch(PDO::FETCH_ASSOC);

if ($donationOpportunity['count'] > 0) {
    $recommendations[] = [
        'type' => 'opportunity',
        'message' => 'You have ' . $donationOpportunity['count'] . ' waste records from overproduction or unsold items that could potentially be donated.',
        'icon' => 'fa-hand-holding-heart'
    ];
}

// Helper function to format time slots
function formatTimeSlot($time) {
    $hour = (int)date('G', strtotime($time));
    
    switch($hour) {
        case 9:
            return '9:00 AM - 10:00 AM';
        case 10:
            return '10:00 AM - 11:00 AM';
        case 11:
            return '11:00 AM - 12:00 PM';
        case 13:
            return '1:00 PM - 2:00 PM';
        case 14:
            return '2:00 PM - 3:00 PM';
        case 15:
            return '3:00 PM - 4:00 PM';
        case 16:
            return '4:00 PM - 5:00 PM';
        default:
            return date('h:i A', strtotime($time));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard - <?= htmlspecialchars($branchName) ?></title>
  <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
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

    // Toggle between FEFO and FIFO tabs
    $('#fefo-tab').click(function() {
        $(this).addClass('bg-primarycol text-white').removeClass('bg-gray-100');
        $('#fifo-tab').removeClass('bg-primarycol text-white').addClass('bg-gray-100');
        $('#fefo-content').removeClass('hidden');
        $('#fifo-content').addClass('hidden');
    });
    
    $('#fifo-tab').click(function() {
        $(this).addClass('bg-primarycol text-white').removeClass('bg-gray-100');
        $('#fefo-tab').removeClass('bg-primarycol text-white').addClass('bg-gray-100');
        $('#fifo-content').removeClass('hidden');
        $('#fefo-content').addClass('hidden');
    });
});
 </script>
 <style>
    .recommendation-card {
        transition: all 0.3s ease;
    }
    .recommendation-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .stat-card {
        transition: transform 0.3s ease;
    }
    .stat-card:hover {
        transform: scale(1.02);
    }
 </style>
</head>

<body class="flex h-auto bg-gray-50">

<?php include ('../layout/staff_nav.php'); ?>

<div class="flex-1 p-6 overflow-y-auto">
    <!-- Header with Welcome and Date -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-primarycol">Welcome, <?= htmlspecialchars($userName) ?>!</h1>
        <p class="text-gray-600"><?= htmlspecialchars($branchName) ?> Dashboard • <?= date('l, F j, Y') ?></p>
    </div>

    <!-- Smart Recommendations Section -->
    <?php if (!empty($recommendations)): ?>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
            Smart Recommendations
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($recommendations as $recommendation): 
                $bgColor = 'bg-blue-50';
                $iconColor = 'text-blue-500';
                $borderColor = 'border-blue-200';
                
                if ($recommendation['type'] === 'warning') {
                    $bgColor = 'bg-amber-50';
                    $iconColor = 'text-amber-500';
                    $borderColor = 'border-amber-200';
                } elseif ($recommendation['type'] === 'success') {
                    $bgColor = 'bg-green-50';
                    $iconColor = 'text-green-500';
                    $borderColor = 'border-green-200';
                } elseif ($recommendation['type'] === 'urgent') {
                    $bgColor = 'bg-red-50';
                    $iconColor = 'text-red-500';
                    $borderColor = 'border-red-200';
                } elseif ($recommendation['type'] === 'action') {
                    $bgColor = 'bg-purple-50';
                    $iconColor = 'text-purple-500';
                    $borderColor = 'border-purple-200';
                } elseif ($recommendation['type'] === 'opportunity') {
                    $bgColor = 'bg-emerald-50';
                    $iconColor = 'text-emerald-500';
                    $borderColor = 'border-emerald-200';
                }
            ?>
            <div class="recommendation-card p-4 rounded-lg border <?= $borderColor ?> <?= $bgColor ?>">
                <div class="flex items-start">
                    <div class="mr-3 mt-1">
                        <i class="fas <?= $recommendation['icon'] ?> text-xl <?= $iconColor ?>"></i>
                    </div>
                    <div>
                        <p class="text-gray-800"><?= htmlspecialchars($recommendation['message']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Key Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
        <!-- Today's Waste -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500 stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Today's Waste</p>
                    <p class="text-2xl font-bold">₱<?= number_format($todayWaste['total_value'] ?? 0, 2) ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <i class="fas fa-trash-alt text-blue-500"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">
                <?= number_format($todayWaste['total_quantity'] ?? 0) ?> units recorded today
            </div>
        </div>
        
        <!-- This Month's Waste -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-amber-500 stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Monthly Waste Value</p>
                    <p class="text-2xl font-bold">₱<?= number_format($monthWaste['total_value'] ?? 0, 2) ?></p>
                </div>
                <div class="p-3 bg-amber-100 rounded-full">
                    <i class="fas fa-calendar-alt text-amber-500"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">
                Last 30 days (<?= number_format($monthWaste['record_count'] ?? 0) ?> records)
            </div>
        </div>
        
        <!-- Donation Metrics -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500 stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Donations Completed</p>
                    <p class="text-2xl font-bold"><?= number_format($donations['completed_requests'] ?? 0) ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    <i class="fas fa-hand-holding-heart text-green-500"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">
                <?= number_format($donations['pending_pickups'] ?? 0) ?> pending pickups
            </div>
        </div>
        
        <!-- Expiring Products Alert -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500 stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">Products Expiring Soon</p>
                    <p class="text-2xl font-bold"><?= number_format(count($expiringProducts)) ?></p>
                </div>
                <div class="p-3 bg-red-100 rounded-full">
                    <i class="fas fa-clock text-red-500"></i>
                </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">
                Within the next 7 days
            </div>
        </div>
    </div>
    
    <!-- Waste and Donation Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Waste Trend Chart -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-800 mb-4">Waste Trends (Last 7 Days)</h3>
            <div id="wasteChart" class="h-80"></div>
        </div>
        
        <!-- Waste by Category Breakdown -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-800 mb-4">Waste by Category (Last 30 Days)</h3>
            <div class="overflow-hidden">
                <div id="categoryChart" class="h-80"></div>
            </div>
        </div>
    </div>
    
    <!-- Inventory Management Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- FEFO/FIFO Section -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="border-b border-gray-200">
                <div class="flex">
                    <button id="fefo-tab" class="px-6 py-3 font-medium text-sm focus:outline-none bg-primarycol text-white">
                        FEFO (First Expired, First Out)
                    </button>
                    <button id="fifo-tab" class="px-6 py-3 font-medium text-sm focus:outline-none bg-gray-100">
                        FIFO (First In, First Out)
                    </button>
                </div>
            </div>
            
            <!-- FEFO Content -->
            <div id="fefo-content" class="p-4">
                <div class="mb-3">
                    <h3 class="font-medium text-gray-900">Expiring Products</h3>
                    <p class="text-sm text-gray-600">Products expiring within 7 days</p>
                </div>
                
                <?php if (count($expiringProducts) > 0): ?>
                    <div class="grid grid-cols-1 gap-3">
                        <?php foreach ($expiringProducts as $product): ?>
                            <div class="border rounded-lg p-3 flex items-center gap-3 bg-white">
                                <div class="h-16 w-16 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
                                    <?php 
                                    // Improved image path handling
                                    $imagePath = '../../assets/images/Company Logo.jpg'; // Default
                                    if (!empty($product['image'])) {
                                        if (strpos($product['image'], '/') !== false) {
                                            $imagePath = $product['image'];
                                        } else {
                                            $imagePath = "../../uploads/products/" . $product['image'];
                                        }
                                    }
                                    ?>
                                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="" 
                                         class="w-full h-full object-cover"
                                         onerror="this.src='../../assets/images/Company Logo.jpg';">
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></h4>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($product['category']) ?></p>
                                        </div>
                                        <div class="bg-amber-100 text-amber-800 text-xs px-2 py-1 rounded-full font-bold">
                                            <?= $product['days_until_expiry'] ?> days left
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm mt-1">
                                        <div>
                                            <span class="text-gray-600">Batch:</span>
                                            <span class="font-medium"><?= htmlspecialchars($product['batch_number']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Qty:</span>
                                            <span class="font-medium"><?= number_format($product['quantity']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Expires:</span>
                                            <span class="font-medium"><?= date('M j, Y', strtotime($product['expiry_date'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="product_stocks.php?expiry=expiring_soon" class="text-primarycol hover:underline text-sm">
                            View all expiring products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-3">
                            <i class="fas fa-check text-green-500 text-xl"></i>
                        </div>
                        <p class="text-gray-600">No products expiring within the next 7 days</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- FIFO Content -->
            <div id="fifo-content" class="p-4 hidden">
                <div class="mb-3">
                    <h3 class="font-medium text-gray-900">Oldest Products</h3>
                    <p class="text-sm text-gray-600">Products to use first by production date</p>
                </div>
                
                <?php if (count($oldestProducts) > 0): ?>
                    <div class="grid grid-cols-1 gap-3">
                        <?php foreach ($oldestProducts as $product): ?>
                            <div class="border rounded-lg p-3 flex items-center gap-3 bg-white">
                                <div class="h-16 w-16 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
                                    <?php 
                                    // Improved image path handling
                                    $imagePath = '../../assets/images/Company Logo.jpg'; // Default
                                    if (!empty($product['image'])) {
                                        if (strpos($product['image'], '/') !== false) {
                                            $imagePath = $product['image'];
                                        } else {
                                            $imagePath = "../../uploads/products/" . $product['image'];
                                        }
                                    }
                                    ?>
                                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="" 
                                         class="w-full h-full object-cover"
                                         onerror="this.src='../../assets/images/Company Logo.jpg';">
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></h4>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($product['category']) ?></p>
                                        </div>
                                        <div class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-bold">
                                            <?= $product['days_since_production'] ?> days old
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm mt-1">
                                        <div>
                                            <span class="text-gray-600">Batch:</span>
                                            <span class="font-medium"><?= htmlspecialchars($product['batch_number']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Qty:</span>
                                            <span class="font-medium"><?= number_format($product['quantity']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Produced:</span>
                                            <span class="font-medium"><?= date('M j, Y', strtotime($product['production_date'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="product_stocks.php?sort=production_date_asc" class="text-primarycol hover:underline text-sm">
                            View all products by production date
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-600">No products available for FIFO recommendation</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    
        <!-- Today's Donation Pickup Schedule -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-3">
                <h3 class="font-medium text-gray-900">Today's Pickup Schedule</h3>
                <p class="text-sm text-gray-600">Scheduled donation pickups for today</p>
            </div>
            
            <div class="p-4">
                <?php if (count($todayPickups) > 0): ?>
                    <div class="divide-y">
                        <?php foreach ($todayPickups as $pickup): ?>
                            <div class="py-3 flex items-center justify-between">
                                <div>
                                    <div class="font-medium"><?= htmlspecialchars($pickup['ngo_name']) ?></div>
                                    <div class="text-sm text-gray-600">
                                        <?= htmlspecialchars($pickup['product_name']) ?> 
                                        (<?= $pickup['quantity_requested'] ?> units)
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium"><?= formatTimeSlot($pickup['pickup_time']) ?></div>
                                    <a href="donation_request.php?request=<?= $pickup['id'] ?>" class="text-xs text-primarycol hover:underline">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                            <i class="fas fa-calendar-check text-gray-400 text-xl"></i>
                        </div>
                        <p class="text-gray-600">No pickups scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Action Links -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <a href="waste_product_input.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition-colors">
            <div class="mx-auto w-12 h-12 flex items-center justify-center bg-red-100 rounded-full mb-2">
                <i class="fas fa-trash-alt text-red-500"></i>
            </div>
            <h3 class="text-sm font-medium">Record Excess</h3>
        </a>
        
        <a href="product_stocks.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition-colors">
            <div class="mx-auto w-12 h-12 flex items-center justify-center bg-blue-100 rounded-full mb-2">
                <i class="fas fa-box text-blue-500"></i>
            </div>
            <h3 class="text-sm font-medium">Manage Stock</h3>
        </a>
        
        <a href="add_stock.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition-colors">
            <div class="mx-auto w-12 h-12 flex items-center justify-center bg-green-100 rounded-full mb-2">
                <i class="fas fa-plus text-green-500"></i>
            </div>
            <h3 class="text-sm font-medium">Add Stock</h3>
        </a>
        
        <a href="donation_request.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition-colors">
            <div class="mx-auto w-12 h-12 flex items-center justify-center bg-purple-100 rounded-full mb-2">
                <i class="fas fa-hand-holding-heart text-purple-500"></i>
            </div>
            <h3 class="text-sm font-medium">Donation Requests</h3>
        </a>
        
        <a href="donation_branch_history.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition-colors">
            <div class="mx-auto w-12 h-12 flex items-center justify-center bg-amber-100 rounded-full mb-2">
                <i class="fas fa-history text-amber-500"></i>
            </div>
            <h3 class="text-sm font-medium">Donation History</h3>
        </a>
        
        <a href="waste_product_record.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition-colors">
            <div class="mx-auto w-12 h-12 flex items-center justify-center bg-emerald-100 rounded-full mb-2">
                <i class="fas fa-clipboard-list text-emerald-500"></i>
            </div>
            <h3 class="text-sm font-medium">Excess Records</h3>
        </a>
    </div>
    
    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="border-b border-gray-200 px-6 py-3">
            <h3 class="font-medium text-gray-900">Recent Activity</h3>
        </div>
        
        <div class="p-4">
            <?php if (count($recentActivity) > 0): ?>
                <div class="divide-y">
                    <?php foreach ($recentActivity as $activity): 
                        $icon = $activity['type'] === 'waste' ? 'fa-trash-alt' : 'fa-hand-holding-heart';
                        $iconClass = $activity['type'] === 'waste' ? 'text-red-500 bg-red-100' : 'text-green-500 bg-green-100';
                    ?>
                        <div class="py-3 flex items-start">
                            <div class="mr-4 mt-1">
                                <div class="w-8 h-8 rounded-full <?= $iconClass ?> flex items-center justify-center">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <div>
                                        <?php if ($activity['type'] === 'waste'): ?>
                                            <span class="font-medium"><?= htmlspecialchars($activity['product_name']) ?></span>
                                            <span class="text-gray-600"> - <?= number_format($activity['quantity']) ?> units recorded as excess</span>
                                        <?php else: ?>
                                            <span class="font-medium"><?= htmlspecialchars($activity['staff_name']) ?></span>
                                            <span class="text-gray-600"> requested <?= number_format($activity['quantity']) ?> units of </span>
                                            <span class="font-medium"><?= htmlspecialchars($activity['product_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= date('M j, g:i A', strtotime($activity['date'])) ?>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    <?php if ($activity['type'] === 'waste'): ?>
                                        Reason: <?= ucfirst(htmlspecialchars($activity['reason'])) ?>
                                        <span class="text-red-500 font-medium ml-2">
                                            ₱<?= number_format($activity['value'], 2) ?>
                                        </span>
                                    <?php else: ?>
                                        Status: 
                                        <?php 
                                            $statusColor = 'text-yellow-500';
                                            if ($activity['reason'] === 'approved') $statusColor = 'text-green-500';
                                            elseif ($activity['reason'] === 'rejected') $statusColor = 'text-red-500';
                                        ?>
                                        <span class="<?= $statusColor ?> font-medium">
                                            <?= ucfirst(htmlspecialchars($activity['reason'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-6">
                    <p class="text-gray-600">No recent activity found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
// Prepare waste data for chart
const dates = <?= json_encode(array_column($dailyWasteData, 'date')) ?>;
const quantities = <?= json_encode(array_map('floatval', array_column($dailyWasteData, 'quantity'))) ?>;
const values = <?= json_encode(array_map('floatval', array_column($dailyWasteData, 'value'))) ?>;

// Format dates for display
const formattedDates = dates.map(date => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});

// Waste trend chart
var wasteChartOptions = {
    series: [{
        name: 'Quantity',
        type: 'column',
        data: quantities
    }, {
        name: 'Value (₱)',
        type: 'line',
        data: values
    }],
    chart: {
        height: 320,
        type: 'line',
        toolbar: {
            show: false
        }
    },
    stroke: {
        width: [0, 3]
    },
    dataLabels: {
        enabled: false
    },
    colors: ['#47663B', '#1F4529'],
    labels: formattedDates,
    xaxis: {
        type: 'category',
        labels: {
            rotate: -45,
            style: {
                fontSize: '12px'
            }
        }
    },
    yaxis: [{
        title: {
            text: 'Quantity',
            style: {
                color: '#47663B'
            }
        },
        labels: {
            formatter: function(val) {
                return val.toFixed(0);
            }
        }
    }, {
        opposite: true,
        title: {
            text: 'Value (₱)',
            style: {
                color: '#1F4529'
            }
        },
        labels: {
            formatter: function(val) {
                return '₱' + val.toFixed(2);
            }
        }
    }],
    tooltip: {
        shared: true,
        intersect: false
    }
};

var wasteChart = new ApexCharts(document.querySelector("#wasteChart"), wasteChartOptions);
wasteChart.render();

// Prepare category data
const categories = <?= json_encode(array_column($categories, 'category')) ?>;
const categoryValues = <?= json_encode(array_map('floatval', array_column($categories, 'value'))) ?>;

// Category chart
var categoryChartOptions = {
    series: categoryValues,
    chart: {
        type: 'pie',
        height: 320,
        toolbar: {
            show: false
        }
    },
    labels: categories,
    colors: ['#47663B', '#5D8A4E', '#1F4529', '#E8ECD7', '#EED3B1', '#BE8A60'],
    legend: {
        position: 'bottom'
    },
    tooltip: {
        y: {
            formatter: function(val) {
                return '₱' + val.toFixed(2);
            }
        }
    }
};

var categoryChart = new ApexCharts(document.querySelector("#categoryChart"), categoryChartOptions);
categoryChart.render();
</script>

</body>
</html>