<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Fix: Update to check for both branch staff roles
checkAuth(['branch1_staff', 'branch2_staff']);

// Get branch ID from session
$pdo = getPDO();  // Make sure we initialize the database connection
$branchId = $_SESSION['branch_id'];

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

// Fetch waste data for today
$today = date('Y-m-d');

// Get total products wasted today
try {
    $prodWasteStmt = $pdo->prepare("
        SELECT SUM(waste_quantity) as total_product_waste 
        FROM product_waste 
        WHERE branch_id = ? AND DATE(waste_date) = ?
    ");
    $prodWasteStmt->execute([$branchId, $today]);
    $productWaste = $prodWasteStmt->fetch(PDO::FETCH_ASSOC);
    $totalProductWaste = $productWaste['total_product_waste'] ?? 0;
} catch (PDOException $e) {
    $totalProductWaste = 0;
}

// Get total ingredients wasted today
try {
    $ingWasteStmt = $pdo->prepare("
        SELECT SUM(waste_quantity) as total_ing_waste 
        FROM ingredients_waste 
        WHERE branch_id = ? AND DATE(waste_date) = ?
    ");
    $ingWasteStmt->execute([$branchId, $today]);
    $ingredientWaste = $ingWasteStmt->fetch(PDO::FETCH_ASSOC);
    $totalIngredientWaste = $ingredientWaste['total_ing_waste'] ?? 0;
} catch (PDOException $e) {
    $totalIngredientWaste = 0;
}

// Get total sales for the week
try {
    $weekStartDate = date('Y-m-d', strtotime('monday this week'));
    $weekEndDate = date('Y-m-d', strtotime('sunday this week'));
    
    $weekSalesStmt = $pdo->prepare("
        SELECT SUM(s.quantity_sold * pi.price_per_unit) as total_sales
        FROM sales s
        JOIN product_info pi ON s.product_id = pi.id
        WHERE s.branch_id = ? 
        AND DATE(s.sales_date) BETWEEN ? AND ?
    ");
    $weekSalesStmt->execute([$branchId, $weekStartDate, $weekEndDate]);
    $weekSalesData = $weekSalesStmt->fetch(PDO::FETCH_ASSOC);
    $totalWeekSales = $weekSalesData['total_sales'] ?? 0;
} catch (PDOException $e) {
    $totalWeekSales = 0;
}

// Get top selling products this week
try {
    $topProductsStmt = $pdo->prepare("
        SELECT pi.id, pi.name, pi.category, pi.image, 
               SUM(s.quantity_sold) as total_sold,
               SUM(s.quantity_sold * pi.price_per_unit) as total_revenue
        FROM sales s
        JOIN product_info pi ON s.product_id = pi.id
        WHERE s.branch_id = ? 
        AND s.sales_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        GROUP BY s.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $topProductsStmt->execute([$branchId]);
    $topSellingProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topSellingProducts = [];
}

// Get top 3 wasted products this week
try {
    $topWastedStmt = $pdo->prepare("
        SELECT pi.id, pi.name, pi.category, pi.image, 
               SUM(w.waste_quantity) as total_waste,
               SUM(w.waste_quantity * pi.price_per_unit) as waste_cost
        FROM product_waste w
        JOIN product_info pi ON w.product_id = pi.id
        WHERE w.branch_id = ? 
        AND w.waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        GROUP BY w.product_id
        ORDER BY total_waste DESC
        LIMIT 3
    ");
    $topWastedStmt->execute([$branchId]);
    $topWastedProducts = $topWastedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topWastedProducts = [];
}

// Get waste reasons distribution
try {
    $reasonsStmt = $pdo->prepare("
        SELECT waste_reason, COUNT(*) as count
        FROM product_waste
        WHERE branch_id = ?
        AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        GROUP BY waste_reason
        ORDER BY count DESC
    ");
    $reasonsStmt->execute([$branchId]);
    $wasteReasons = $reasonsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $wasteReasons = [];
}

// Get sales data by day for the last 7 days
try {
    $salesByDayStmt = $pdo->prepare("
        SELECT DATE(s.sales_date) as sale_day, 
               SUM(s.quantity_sold) as items_sold,
               SUM(s.quantity_sold * pi.price_per_unit) as day_revenue
        FROM sales s
        JOIN product_info pi ON s.product_id = pi.id
        WHERE s.branch_id = ? 
        AND s.sales_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        GROUP BY DATE(s.sales_date)
        ORDER BY sale_day ASC
    ");
    $salesByDayStmt->execute([$branchId]);
    $salesByDay = $salesByDayStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $salesByDay = [];
}

// ENHANCED DATA-DRIVEN FEATURES

// 1. Products expiring soon (within 7 days)
try {
    $expiringStmt = $pdo->prepare("
        SELECT 
            ps.id, 
            pi.name,
            pi.category,
            pi.image,
            ps.expiry_date,
            ps.quantity,
            ps.batch_number,
            DATEDIFF(ps.expiry_date, CURRENT_DATE()) AS days_until_expiry
        FROM 
            product_stock ps
            JOIN product_info pi ON ps.product_info_id = pi.id
        WHERE 
            ps.branch_id = ?
            AND ps.expiry_date > CURRENT_DATE() 
            AND ps.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
            AND ps.quantity > 0
        ORDER BY
            ps.expiry_date ASC
        LIMIT 5
    ");
    
    $expiringStmt->execute([$branchId]);
    $expiringProducts = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expiringProducts = [];
}

// 2. Inventory levels at risk (low stock items with high demand)
try {
    $riskInventoryStmt = $pdo->prepare("
        WITH product_sales AS (
            SELECT 
                s.product_id,
                SUM(s.quantity_sold) AS total_sold,
                COUNT(DISTINCT DATE(s.sales_date)) AS days_with_sales
            FROM sales s
            WHERE s.branch_id = ? 
            AND s.sales_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY s.product_id
        ),
        product_total_stock AS (
            SELECT 
                ps.product_info_id,
                SUM(ps.quantity) AS total_stock
            FROM product_stock ps
            WHERE ps.branch_id = ? 
            AND ps.expiry_date > CURRENT_DATE()
            GROUP BY ps.product_info_id
        )
        SELECT 
            pi.id,
            pi.name,
            pi.category,
            pi.image,
            COALESCE(pts.total_stock, 0) AS current_stock,
            COALESCE(ps.total_sold, 0) AS month_sales,
            CASE 
                WHEN ps.days_with_sales > 0 THEN ROUND(ps.total_sold / ps.days_with_sales, 2)
                ELSE 0 
            END AS daily_avg_sales,
            CASE 
                WHEN ps.days_with_sales > 0 THEN
                    CASE 
                        WHEN COALESCE(pts.total_stock, 0) = 0 THEN 0
                        ELSE FLOOR(COALESCE(pts.total_stock, 0) / (ps.total_sold / ps.days_with_sales))
                    END
                ELSE NULL
            END AS estimated_days_left
        FROM 
            product_info pi
            LEFT JOIN product_total_stock pts ON pi.id = pts.product_info_id
            LEFT JOIN product_sales ps ON pi.id = ps.product_id
        WHERE
            pi.id IN (SELECT product_info_id FROM product_stock WHERE branch_id = ?)
            AND (
                (COALESCE(pts.total_stock, 0) = 0 AND COALESCE(ps.total_sold, 0) > 0) OR
                (ps.days_with_sales > 0 AND 
                 COALESCE(pts.total_stock, 0) / (ps.total_sold / ps.days_with_sales) < 5)
            )
        ORDER BY 
            estimated_days_left ASC
        LIMIT 5
    ");
    $riskInventoryStmt->execute([$branchId, $branchId, $branchId]);
    $riskInventory = $riskInventoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $riskInventory = [];
}

// 3. FEFO and FIFO Recommendations from product_stock table
try {
    // Get FEFO recommendations (soonest to expire first)
    $fefoStmt = $pdo->prepare("
        SELECT 
            ps.id,
            ps.batch_number,
            ps.quantity,
            ps.production_date,
            ps.expiry_date,
            ps.unit_type,
            pi.name,
            pi.category,
            pi.image,
            DATEDIFF(ps.expiry_date, CURRENT_DATE()) as days_until_expiry
        FROM product_stock ps
        JOIN product_info pi ON ps.product_info_id = pi.id
        WHERE ps.branch_id = ? 
        AND ps.quantity > 0
        AND ps.expiry_date >= CURRENT_DATE()
        ORDER BY ps.expiry_date ASC, ps.production_date ASC
        LIMIT 5
    ");
    $fefoStmt->execute([$branchId]);
    $fefoRecommendations = $fefoStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get FIFO recommendations (oldest produced first)
    $fifoStmt = $pdo->prepare("
        SELECT 
            ps.id,
            ps.batch_number,
            ps.quantity,
            ps.production_date,
            ps.expiry_date,
            ps.unit_type,
            pi.name,
            pi.category,
            pi.image,
            DATEDIFF(CURRENT_DATE(), ps.production_date) as days_since_production
        FROM product_stock ps
        JOIN product_info pi ON ps.product_info_id = pi.id
        WHERE ps.branch_id = ? 
        AND ps.quantity > 0
        AND ps.expiry_date >= CURRENT_DATE()
        ORDER BY ps.production_date ASC, ps.expiry_date ASC
        LIMIT 5
    ");
    $fifoStmt->execute([$branchId]);
    $fifoRecommendations = $fifoStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $fefoRecommendations = [];
    $fifoRecommendations = [];
}

// 4. Products with highest waste-to-sales ratio (potential issues)
try {
    $productEfficiencyStmt = $pdo->prepare("
        WITH product_sales AS (
            SELECT 
                product_id,
                SUM(quantity_sold) AS total_sales
            FROM sales
            WHERE branch_id = ?
            AND sales_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY product_id
        ),
        product_waste AS (
            SELECT 
                product_id,
                SUM(waste_quantity) AS total_waste
            FROM product_waste
            WHERE branch_id = ?
            AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY product_id
        )
        SELECT 
            pi.id,
            pi.name,
            pi.category,
            pi.image,
            COALESCE(ps.total_sales, 0) AS sales_quantity,
            COALESCE(pw.total_waste, 0) AS waste_quantity,
            CASE 
                WHEN COALESCE(ps.total_sales, 0) = 0 THEN 100
                ELSE ROUND((COALESCE(pw.total_waste, 0) / (COALESCE(ps.total_sales, 0) + COALESCE(pw.total_waste, 0))) * 100, 1)
            END AS waste_percentage
        FROM 
            product_info pi
            LEFT JOIN product_sales ps ON pi.id = ps.product_id
            LEFT JOIN product_waste pw ON pi.id = pw.product_id
        WHERE 
            (COALESCE(ps.total_sales, 0) > 0 OR COALESCE(pw.total_waste, 0) > 0)
            AND pi.id IN (
                SELECT DISTINCT product_info_id FROM product_stock WHERE branch_id = ?
            )
        ORDER BY 
            waste_percentage DESC, waste_quantity DESC
        LIMIT 5
    ");
    $productEfficiencyStmt->execute([$branchId, $branchId, $branchId]);
    $inefficientProducts = $productEfficiencyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inefficientProducts = [];
}

// 5. Product category performance (sales by category)
try {
    $categoryPerformanceStmt = $pdo->prepare("
        SELECT 
            pi.category,
            SUM(s.quantity_sold) AS total_sold,
            SUM(s.quantity_sold * pi.price_per_unit) AS total_revenue,
            COUNT(DISTINCT pi.id) AS product_count
        FROM 
            sales s
            JOIN product_info pi ON s.product_id = pi.id
        WHERE 
            s.branch_id = ?
            AND s.sales_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        GROUP BY 
            pi.category
        ORDER BY 
            total_revenue DESC
    ");
    $categoryPerformanceStmt->execute([$branchId]);
    $categoryPerformance = $categoryPerformanceStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categoryPerformance = [];
}

// 6. Product waste trend data for chart visualization
try {
    $wasteTrendStmt = $pdo->prepare("
        SELECT 
            DATE(waste_date) AS waste_day,
            SUM(waste_quantity) AS total_waste
        FROM 
            product_waste
        WHERE 
            branch_id = ?
            AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 14 DAY)
        GROUP BY 
            DATE(waste_date)
        ORDER BY 
            waste_day ASC
    ");
    $wasteTrendStmt->execute([$branchId]);
    $wasteTrendData = $wasteTrendStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $wasteTrendData = [];
}

// 7. Smart recommendations based on data analysis
$smartRecommendations = [];

// Check for high waste items
foreach ($inefficientProducts as $product) {
    if ($product['waste_percentage'] > 30 && $product['waste_quantity'] > 5) {
        $smartRecommendations[] = [
            'type' => 'waste_reduction',
            'severity' => 'high',
            'message' => "Consider reducing production of " . htmlspecialchars($product['name']) . 
                         " which has a " . $product['waste_percentage'] . "% waste rate",
            'action_url' => "product_stocks.php?product_id=" . $product['id'],
            'icon' => 'warning'
        ];
    }
}

// Check for expiring inventory
foreach ($expiringProducts as $product) {
    if ($product['days_until_expiry'] <= 2) {
        $smartRecommendations[] = [
            'type' => 'urgent_expiry',
            'severity' => 'critical',
            'message' => htmlspecialchars($product['quantity']) . " units of " . 
                         htmlspecialchars($product['name']) . " will expire in " . 
                         $product['days_until_expiry'] . " day(s)",
            'action_url' => "view_stock.php?id=" . $product['id'],
            'icon' => 'alert'
        ];
    }
}

// Check for stock at risk
foreach ($riskInventory as $product) {
    if ($product['estimated_days_left'] !== null && $product['estimated_days_left'] <= 3) {
        $smartRecommendations[] = [
            'type' => 'low_stock',
            'severity' => 'medium',
            'message' => "Only " . $product['current_stock'] . " units of " . 
                         htmlspecialchars($product['name']) . " left (approx. " . 
                         $product['estimated_days_left'] . " days)",
            'action_url' => "add_stock.php?product_id=" . $product['id'],
            'icon' => 'inventory'
        ];
    }
}

// Sort recommendations by severity
$severityOrder = ['critical' => 1, 'high' => 2, 'medium' => 3];
usort($smartRecommendations, function($a, $b) use ($severityOrder) {
    return $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']];
});

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
$inventoryStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get expiring soon count
$expiringStmt = $pdo->prepare("
    SELECT COUNT(*) as expiring_soon
    FROM product_stock ps
    WHERE ps.branch_id = ? AND ps.expiry_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
");
$expiringStmt->execute([$branchId]);
$expiringStats = $expiringStmt->fetch(PDO::FETCH_ASSOC);
$inventoryStats['expiring_soon'] = $expiringStats['expiring_soon'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Dashboard - Bea Bakes</title>
  <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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

   $(document).ready(function() {
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('-translate-x-full');
    });

     $('#closeSidebar').on('click', function() {
        $('#sidebar').addClass('-translate-x-full');
    });
});
 </script>
</head>

<body class="flex h-auto bg-gray-50">

<?php include ('../layout/staff_nav.php' ) ?> 

<div class="w-full p-4 overflow-y-auto">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-primarycol">Hi, <?= htmlspecialchars($userName) ?>!</h1>
    <div class="text-sm text-gray-500"><?= date('l, F j, Y') ?></div>
  </div>

  <!-- Smart Recommendations Section -->
  <?php if (!empty($smartRecommendations)): ?>
  <div class="mb-6">
    <h2 class="text-xl font-semibold mb-3">Smart Recommendations</h2>
    <div class="bg-white shadow rounded-lg p-4">
      <ul class="divide-y divide-gray-200">
        <?php foreach($smartRecommendations as $index => $rec): 
            $iconClass = '';
            $bgClass = '';
            
            switch($rec['severity']) {
                case 'critical':
                    $bgClass = 'bg-red-50';
                    $iconClass = 'text-red-600';
                    break;
                case 'high':
                    $bgClass = 'bg-amber-50';
                    $iconClass = 'text-amber-600';
                    break;
                case 'medium':
                    $bgClass = 'bg-blue-50';
                    $iconClass = 'text-blue-600';
                    break;
            }
            
            if($index >= 3) continue; // Show only top 3 recommendations
        ?>
        <li class="py-3 <?= $bgClass ?> px-4 rounded-md mb-2">
          <div class="flex items-center">
            <div class="<?= $iconClass ?> mr-3">
              <?php if($rec['icon'] == 'warning'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              <?php elseif($rec['icon'] == 'alert'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              <?php endif; ?>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium"><?= $rec['message'] ?></p>
            </div>
            <div>
              <a href="<?= $rec['action_url'] ?>" class="text-primarycol hover:text-fourth text-sm font-medium">
                Take Action
              </a>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
        
        <?php if(count($smartRecommendations) > 3): ?>
          <li class="pt-2">
            <button type="button" id="showMoreRecommendations" class="text-primarycol hover:text-fourth text-sm">
              Show <?= count($smartRecommendations) - 3 ?> more recommendations
            </button>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats Overview Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="stats shadow bg-white">
      <div class="stat">
        <div class="stat-figure text-primarycol">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
          </svg>
        </div>
        <div class="stat-title">Products Excess Today</div>
        <div class="stat-value text-primarycol"><?= number_format($totalProductWaste) ?></div>
        <div class="stat-desc">Finished products</div>
      </div>
    </div>

    <div class="stats shadow bg-white">
      <div class="stat">
        <div class="stat-figure text-primarycol">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
        </div>
        <div class="stat-title">Sales This Week</div>
        <div class="stat-value text-primarycol">₱<?= number_format($totalWeekSales, 0) ?></div>
        <div class="stat-desc"><?= date('M d', strtotime($weekStartDate)) ?> - <?= date('M d', strtotime($weekEndDate)) ?></div>
      </div>
    </div>

    <div class="stats shadow bg-white">
      <div class="stat">
        <div class="stat-figure text-primarycol">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
        </div>
        <div class="stat-title">Items Expiring Soon</div>
        <div class="stat-value text-amber-500"><?= count($expiringProducts) ?></div>
        <div class="stat-desc">Within next 7 days</div>
      </div>
    </div>
  </div>

  <!-- Inventory Stats Cards -->
  <div class="mb-6">
    <div class="flex justify-between items-center mb-3">
      <h2 class="text-xl font-semibold">Inventory Overview</h2>
      <a href="product_stocks.php" class="text-sm text-primarycol hover:text-fourth">View Full Inventory</a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="flex items-center">
          <div class="rounded-full bg-blue-100 p-2 mr-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <div>
            <h3 class="text-gray-500 text-sm uppercase">Total Batches</h3>
            <p class="text-2xl font-bold"><?= number_format($inventoryStats['total_batches'] ?? 0) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="flex items-center">
          <div class="rounded-full bg-green-100 p-2 mr-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
          </div>
          <div>
            <h3 class="text-gray-500 text-sm uppercase">Total Items</h3>
            <p class="text-2xl font-bold"><?= number_format($inventoryStats['total_items'] ?? 0) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-amber-500">
        <div class="flex items-center">
          <div class="rounded-full bg-amber-100 p-2 mr-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h3 class="text-gray-500 text-sm uppercase">Expiring Soon</h3>
            <p class="text-2xl font-bold"><?= number_format($inventoryStats['expiring_soon'] ?? 0) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <div class="flex items-center">
          <div class="rounded-full bg-red-100 p-2 mr-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div>
            <h3 class="text-gray-500 text-sm uppercase">Expired Batches</h3>
            <p class="text-2xl font-bold"><?= number_format($inventoryStats['expired_batches'] ?? 0) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Sales and Performance Column -->
    <div class="lg:col-span-2 space-y-6">
      <!-- Sales Trends Chart -->
      <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Sales Trends</h2>
          <a href="record_sales.php" class="text-sm text-primarycol hover:text-fourth">View All Sales</a>
        </div>
        <div id="sales-chart" class="h-64"></div>
      </div>
      
      <!-- Category Performance -->
      <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Category Performance</h2>
        </div>
        
        <?php if (!empty($categoryPerformance)): ?>
          <div class="space-y-4">
            <?php foreach ($categoryPerformance as $category): ?>
              <div class="relative">
                <div class="flex justify-between mb-1">
                  <span class="text-sm font-medium"><?= htmlspecialchars($category['category']) ?></span>
                  <span class="text-sm text-gray-500">₱<?= number_format($category['total_revenue'], 0) ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <?php 
                  // Calculate percentage based on highest revenue
                  $highestRevenue = $categoryPerformance[0]['total_revenue'];
                  $percentage = ($highestRevenue > 0) ? ($category['total_revenue'] / $highestRevenue) * 100 : 0;
                  ?>
                  <div class="bg-primarycol h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                  <span><?= number_format($category['total_sold']) ?> units</span>
                  <span><?= $category['product_count'] ?> products</span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-gray-500 text-center py-4">No sales data available for categories</p>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Inventory Priority Column -->
    <div class="space-y-6">
      <!-- Action Required Section -->
      <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Inventory Priority</h2>
        
        <div class="tabs mb-4">
          <a class="tab tab-bordered tab-active" id="expiring-tab">Expiring Soon</a>
          <a class="tab tab-bordered" id="risk-tab">Stock at Risk</a>
        </div>
        
        <div id="expiring-content">
          <?php if (empty($expiringProducts)): ?>
            <div class="text-center py-6">
              <p class="text-gray-500">No products expiring soon</p>
            </div>
          <?php else: ?>
            <ul class="divide-y divide-gray-200">
              <?php foreach ($expiringProducts as $product): ?>
                <li class="py-2">
                  <a href="view_stock.php?id=<?= $product['id'] ?>" class="block hover:bg-gray-50 rounded-md -mx-2 px-2 py-1">
                    <div class="flex items-start space-x-3">
                      <div class="h-10 w-10 bg-gray-200 rounded-md overflow-hidden">
                        <?php $imagePath = !empty($product['image']) ? $product['image'] : '../../assets/images/Company Logo.jpg'; ?>
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="" class="h-full w-full object-cover">
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($product['name']) ?></p>
                        <p class="text-xs text-gray-500">Batch #<?= htmlspecialchars($product['batch_number']) ?> - <?= $product['quantity'] ?> units</p>
                      </div>
                      <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $product['days_until_expiry'] <= 2 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' ?>">
                          <?= $product['days_until_expiry'] ?> day<?= $product['days_until_expiry'] != 1 ? 's' : '' ?>
                        </span>
                        <p class="text-xs text-gray-500 mt-1"><?= date('M j', strtotime($product['expiry_date'])) ?></p>
                      </div>
                    </div>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        
        <div id="risk-content" class="hidden">
          <?php if (empty($riskInventory)): ?>
            <div class="text-center py-6">
              <p class="text-gray-500">No products at stock risk</p>
            </div>
          <?php else: ?>
            <ul class="divide-y divide-gray-200">
              <?php foreach ($riskInventory as $product): ?>
                <li class="py-2">
                  <a href="add_stock.php?product_id=<?= $product['id'] ?>" class="block hover:bg-gray-50 rounded-md -mx-2 px-2 py-1">
                    <div class="flex items-start space-x-3">
                      <div class="h-10 w-10 bg-gray-200 rounded-md overflow-hidden">
                        <?php $imagePath = !empty($product['image']) ? $product['image'] : '../../assets/images/Company Logo.jpg'; ?>
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="" class="h-full w-full object-cover">
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($product['name']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($product['category']) ?></p>
                      </div>
                      <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                          <?= $product['current_stock'] ?> left
                        </span>
                        <?php if ($product['estimated_days_left'] !== null): ?>
                          <p class="text-xs text-gray-500 mt-1">~<?= $product['estimated_days_left'] ?> day<?= $product['estimated_days_left'] != 1 ? 's' : '' ?> supply</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Products to Use First -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">Products to Use First</h2>
        
        <div class="tabs mb-4">
          <a class="tab tab-bordered tab-active" id="fefo-tab">FEFO</a>
          <a class="tab tab-bordered" id="fifo-tab">FIFO</a>
        </div>
        
        <div id="fefo-content">
          <?php if (empty($fefoRecommendations)): ?>
            <div class="text-center py-6">
              <p class="text-gray-500">No products available</p>
            </div>
          <?php else: ?>
            <ul class="divide-y divide-gray-200">
              <?php foreach ($fefoRecommendations as $index => $item): ?>
                <li class="py-2">
                  <a href="view_stock.php?id=<?= $item['id'] ?>" class="block hover:bg-gray-50 rounded-md -mx-2 px-2 py-1">
                    <div class="flex items-center">
                      <div class="h-8 w-8 rounded-full bg-primarycol text-white flex items-center justify-center text-sm font-bold">
                        <?= $index + 1 ?>
                      </div>
                      <div class="ml-3 flex-1">
                        <p class="text-sm font-medium"><?= htmlspecialchars($item['name']) ?></p>
                        <p class="text-xs text-gray-500">Exp: <?= date('M j', strtotime($item['expiry_date'])) ?> (<?= $item['days_until_expiry'] ?> days)</p>
                      </div>
                      <div class="text-xs font-medium text-gray-900"><?= $item['quantity'] ?> <?= $item['unit_type'] ?><?= $item['quantity'] > 1 ? 's' : '' ?></div>
                    </div>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        
        <div id="fifo-content" class="hidden">
          <?php if (empty($fifoRecommendations)): ?>
            <div class="text-center py-6">
              <p class="text-gray-500">No products available</p>
            </div>
          <?php else: ?>
            <ul class="divide-y divide-gray-200">
              <?php foreach ($fifoRecommendations as $index => $item): ?>
                <li class="py-2">
                  <a href="view_stock.php?id=<?= $item['id'] ?>" class="block hover:bg-gray-50 rounded-md -mx-2 px-2 py-1">
                    <div class="flex items-center">
                      <div class="h-8 w-8 rounded-full bg-blue-500 text-white flex items-center justify-center text-sm font-bold">
                        <?= $index + 1 ?>
                      </div>
                      <div class="ml-3 flex-1">
                        <p class="text-sm font-medium"><?= htmlspecialchars($item['name']) ?></p>
                        <p class="text-xs text-gray-500">Prod: <?= date('M j', strtotime($item['production_date'])) ?> (<?= $item['days_since_production'] ?> days old)</p>
                      </div>
                      <div class="text-xs font-medium text-gray-900"><?= $item['quantity'] ?> <?= $item['unit_type'] ?><?= $item['quantity'] > 1 ? 's' : '' ?></div>
                    </div>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        
        <div class="mt-4 pt-3 border-t">
          <a href="product_stocks.php" class="text-primarycol hover:underline text-sm flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            View All Inventory
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Top Products Section -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Top Selling Products</h2>
      </div>
      
      <?php if (empty($topSellingProducts)): ?>
        <div class="text-center py-10">
          <p class="text-gray-500">No sales data available for this week</p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($topSellingProducts as $index => $product): ?>
                <tr class="<?= $index === 0 ? 'bg-green-50' : '' ?>">
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center">
                      <?php if ($index === 0): ?>
                        <span class="flex-shrink-0 h-8 w-8 rounded-full bg-green-500 text-white flex items-center justify-center mr-3 text-sm font-bold">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7" />
                          </svg>
                        </span>
                      <?php else: ?>
                        <span class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center mr-3 text-sm font-bold">
                          <?= $index + 1 ?>
                        </span>
                      <?php endif; ?>
                      <div>
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($product['category']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                    <?= number_format($product['total_sold']) ?>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-right">
                    <div class="text-sm font-semibold text-gray-900">₱<?= number_format($product['total_revenue']) ?></div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Waste Analysis Section -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Waste Analysis</h2>
      </div>
      
      <?php if (empty($wasteTrendData)): ?>
        <div class="text-center py-10">
          <p class="text-gray-500">No waste data available</p>
        </div>
      <?php else: ?>
        <div id="waste-trend-chart" class="h-48 mb-6"></div>
      <?php endif; ?>
      
      <h3 class="text-lg font-medium mb-3">Products with High Waste %</h3>
      
      <?php if (empty($inefficientProducts)): ?>
        <p class="text-gray-500 text-center py-2">No waste data available</p>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach (array_slice($inefficientProducts, 0, 3) as $product): ?>
            <div>
              <div class="flex justify-between items-center mb-1">
                <span class="text-sm font-medium"><?= htmlspecialchars($product['name']) ?></span>
                <span class="text-sm font-medium <?= $product['waste_percentage'] > 30 ? 'text-red-600' : 'text-amber-600' ?>">
                  <?= $product['waste_percentage'] ?>% waste
                </span>
              </div>
              <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full <?= $product['waste_percentage'] > 30 ? 'bg-red-500' : 'bg-amber-500' ?>" 
                     style="width: <?= min($product['waste_percentage'], 100) ?>%"></div>
              </div>
              <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span><?= $product['waste_quantity'] ?> wasted</span>
                <span><?= $product['sales_quantity'] ?> sold</span>
              </div>
            </div>
          <?php endforeach; ?>
          
          <?php if (count($inefficientProducts) > 3): ?>
            <div class="text-center pt-2">
              <a href="waste_product_input.php" class="text-primarycol hover:underline text-sm">
                View all waste data
              </a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Tab switching functionality for inventory priority
    document.addEventListener('DOMContentLoaded', function() {
      // Expiring vs Risk tabs
      const expiringTab = document.getElementById('expiring-tab');
      const riskTab = document.getElementById('risk-tab');
      const expiringContent = document.getElementById('expiring-content');
      const riskContent = document.getElementById('risk-content');
      
      expiringTab.addEventListener('click', function() {
        expiringTab.classList.add('tab-active');
        riskTab.classList.remove('tab-active');
        expiringContent.classList.remove('hidden');
        riskContent.classList.add('hidden');
      });
      
      riskTab.addEventListener('click', function() {
        riskTab.classList.add('tab-active');
        expiringTab.classList.remove('tab-active');
        riskContent.classList.remove('hidden');
        expiringContent.classList.add('hidden');
      });
      
      // FEFO vs FIFO tabs
      const fefoTab = document.getElementById('fefo-tab');
      const fifoTab = document.getElementById('fifo-tab');
      const fefoContent = document.getElementById('fefo-content');
      const fifoContent = document.getElementById('fifo-content');
      
      fefoTab.addEventListener('click', function() {
        fefoTab.classList.add('tab-active');
        fifoTab.classList.remove('tab-active');
        fefoContent.classList.remove('hidden');
        fifoContent.classList.add('hidden');
      });
      
      fifoTab.addEventListener('click', function() {
        fifoTab.classList.add('tab-active');
        fefoTab.classList.remove('tab-active');
        fifoContent.classList.remove('hidden');
        fefoContent.classList.add('hidden');
      });
      
      // Sales chart
      const salesChartOptions = {
        series: [{
          name: 'Sales Revenue',
          data: [
            <?php 
              $days = [];
              $salesValues = [];
              
              foreach ($salesByDay as $day) {
                $salesValues[] = round($day['day_revenue'], 2);
              }
              
              // If no data, add placeholder
              if (empty($salesValues)) {
                $salesValues = [0, 0, 0, 0, 0, 0, 0];
              }
              echo implode(',', $salesValues);
            ?>
          ]
        }],
        chart: {
          type: 'area',
          height: 250,
          toolbar: {
            show: false
          },
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'smooth',
          width: 2
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
          categories: [
            <?php 
              foreach ($salesByDay as $day) {
                echo "'" . date('D', strtotime($day['sale_day'])) . "',";
              }
              
              // If no data, add placeholder
              if (empty($salesByDay)) {
                echo "'Mon','Tue','Wed','Thu','Fri','Sat','Sun'";
              }
            ?>
          ],
          labels: {
            style: {
              fontSize: '12px'
            }
          }
        },
        yaxis: {
          labels: {
            formatter: function (val) {
              return '₱' + val.toFixed(0);
            },
            style: {
              fontSize: '12px'
            }
          }
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return '₱' + val.toFixed(2);
            }
          }
        }
      };
      
      if (document.getElementById('sales-chart')) {
        const salesChart = new ApexCharts(document.getElementById('sales-chart'), salesChartOptions);
        salesChart.render();
      }
      
      // Waste trend chart
      <?php if (!empty($wasteTrendData)): ?>
      const wasteTrendOptions = {
        series: [{
          name: 'Waste Quantity',
          data: [
            <?php 
              foreach ($wasteTrendData as $day) {
                echo $day['total_waste'] . ',';
              }
            ?>
          ]
        }],
        chart: {
          type: 'bar',
          height: 190,
          toolbar: {
            show: false
          }
        },
        plotOptions: {
          bar: {
            borderRadius: 2,
            columnWidth: '70%',
          }
        },
        dataLabels: {
          enabled: false
        },
        colors: ['#f97316'],
        xaxis: {
          categories: [
            <?php 
              foreach ($wasteTrendData as $day) {
                echo "'" . date('d', strtotime($day['waste_day'])) . "',";
              }
            ?>
          ],
          labels: {
            style: {
              fontSize: '10px'
            }
          },
          axisBorder: {
            show: false
          },
          axisTicks: {
            show: false
          }
        },
        yaxis: {
          labels: {
            formatter: function (val) {
              return val.toFixed(0);
            },
            style: {
              fontSize: '10px'
            }
          }
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val.toFixed(0) + ' units';
            }
          },
          x: {
            formatter: function (val) {
              // Find the full date from the data
              <?php 
                echo "const dates = [";
                foreach ($wasteTrendData as $day) {
                  echo "'" . date('M j', strtotime($day['waste_day'])) . "',";
                }
                echo "];";
              ?>
              return 'Waste on ' + dates[val-1];
            }
          }
        }
      };
      
      if (document.getElementById('waste-trend-chart')) {
        const wasteChart = new ApexCharts(document.getElementById('waste-trend-chart'), wasteTrendOptions);
        wasteChart.render();
      }
      <?php endif; ?>
    });
  </script>
</div>
</body>
</html>