<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

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
        SELECT SUM(s.quantity_sold * p.price_per_unit) as total_sales
        FROM sales s
        JOIN products p ON s.product_id = p.id
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
        SELECT p.name, p.category, SUM(s.quantity_sold) as total_sold,
               SUM(s.quantity_sold * p.price_per_unit) as total_revenue
        FROM sales s
        JOIN products p ON s.product_id = p.id
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
    $topProductsStmt = $pdo->prepare("
        SELECT p.name, p.category, SUM(w.waste_quantity) as total_waste
        FROM product_waste w
        JOIN products p ON w.product_id = p.id
        WHERE w.branch_id = ? 
        AND w.waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        GROUP BY w.product_id
        ORDER BY total_waste DESC
        LIMIT 3
    ");
    $topProductsStmt->execute([$branchId]);
    $topWastedProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topWastedProducts = [];
}

// Get top 3 wasted ingredients this week
try {
    $topIngredientsStmt = $pdo->prepare("
        SELECT i.ingredient_name, i.category, i.unit, SUM(w.waste_quantity) as total_waste
        FROM ingredients_waste w
        JOIN ingredients i ON w.ingredient_id = i.id
        WHERE w.branch_id = ? 
        AND w.waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        GROUP BY w.ingredient_id
        ORDER BY total_waste DESC
        LIMIT 3
    ");
    $topIngredientsStmt->execute([$branchId]);
    $topWastedIngredients = $topIngredientsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topWastedIngredients = [];
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

// Get ingredient waste by production stage
try {
    $stagesStmt = $pdo->prepare("
        SELECT production_stage, COUNT(*) as count
        FROM ingredients_waste
        WHERE branch_id = ?
        AND waste_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        GROUP BY production_stage
        ORDER BY count DESC
    ");
    $stagesStmt->execute([$branchId]);
    $productionStages = $stagesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $productionStages = [];
}

// Get sales data by day for the last 7 days
try {
    $salesByDayStmt = $pdo->prepare("
        SELECT DATE(s.sales_date) as sale_day, 
               SUM(s.quantity_sold) as items_sold,
               SUM(s.quantity_sold * p.price_per_unit) as day_revenue
        FROM sales s
        JOIN products p ON s.product_id = p.id
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

// Get products expiring soon (within 7 days)
try {
    $expiringStmt = $pdo->prepare("
        SELECT 
            id, 
            name, 
            category, 
            expiry_date, 
            stock_quantity,
            DATEDIFF(expiry_date, CURRENT_DATE()) AS days_until_expiry
        FROM 
            products
        WHERE 
            branch_id = ?
            AND expiry_date > CURRENT_DATE() 
            AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
            AND stock_quantity > 0
        ORDER BY
            expiry_date ASC
        LIMIT 5
    ");
    
    $expiringStmt->execute([$branchId]);
    $expiringProducts = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expiringProducts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard</title>
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

   $(document).ready(function() {
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('-translate-x-full');
    });

     $('#closeSidebar').on('click', function() {
        $('#sidebar').addClass('-translate-x-full');
    });
});
 </script>
   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-auto">

<?php include ('../layout/staff_nav.php' ) ?> 

<div class="w-full p-4">
  <h1 class="text-3xl font-bold text-primarycol mb-6">Hi, <?= htmlspecialchars($userName) ?>!</h1>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="stats shadow">
      <div class="stat">
        <div class="stat-figure text-black">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
          </svg>
        </div>
        <div class="stat-title">Products Excess Today</div>
        <div class="stat-value text-primarycol"><?= number_format($totalProductWaste) ?></div>
        <div class="stat-desc">Finished products</div>
      </div>
    </div>

    <div class="stats shadow">
      <div class="stat">
        <div class="stat-figure text-black">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
          </svg>
        </div>
        <div class="stat-title">Ingredients Wasted Today</div>
        <div class="stat-value text-primarycol"><?= number_format($totalIngredientWaste, 1) ?></div>
        <div class="stat-desc">Various units</div>
      </div>
    </div>

    <div class="stats shadow">
      <div class="stat">
        <div class="stat-figure text-black">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
        </div>
        <div class="stat-title">Total Sales This Week</div>
        <div class="stat-value text-primarycol">₱<?= number_format($totalWeekSales, 2) ?></div>
        <div class="stat-desc"><?= date('M d', strtotime($weekStartDate)) ?> - <?= date('M d', strtotime($weekEndDate)) ?></div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Product Trends Section -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold">Product Sales Trends</h3>
        <a href="record_sales.php" class="text-xs px-2 py-1 bg-primarycol text-white rounded hover:bg-fourth">
          View All Sales
        </a>
      </div>
      
      <div class="space-y-4">
        <div id="daily-sales-chart" class="h-40 mb-4"></div>
        
        <h4 class="font-semibold text-sm text-gray-700 mt-4 border-b pb-1">Top Selling Products</h4>
        <ul class="divide-y">
          <?php foreach ($topSellingProducts as $index => $product): ?>
            <li class="py-2 flex justify-between items-center">
              <div class="flex items-center">
                <span class="text-sm font-medium w-6 text-center"><?= $index + 1 ?></span>
                <span class="ml-2 <?= $index === 0 ? 'font-bold' : '' ?>"><?= htmlspecialchars($product['name']) ?></span>
                <span class="ml-2 text-xs px-2 py-0.5 bg-green-100 text-green-800 rounded-full">
                  <?= htmlspecialchars($product['category']) ?>
                </span>
              </div>
              <div class="text-right">
                <span class="block font-semibold"><?= number_format($product['total_sold']) ?> units</span>
                <span class="text-xs text-gray-500">₱<?= number_format($product['total_revenue'], 2) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
          
          <?php if (empty($topSellingProducts)): ?>
            <li class="py-3 text-center text-gray-500">No sales recorded in the past week</li>
          <?php endif; ?>
        </ul>
        
        <div class="pt-3 border-t text-sm text-gray-500 flex justify-center">
          <a href="record_sales.php" class="text-primarycol hover:underline">Add new sales record</a>
        </div>
      </div>
    </div>

    <!-- Top Wasted Products -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h3 class="text-xl font-semibold mb-4">Top Excessed Products (This Week)</h3>
      <?php if (empty($topWastedProducts)): ?>
        <p class="text-gray-500">No product excess recorded this week.</p>
      <?php else: ?>
        <ul class="divide-y">
          <?php foreach ($topWastedProducts as $product): ?>
          <li class="py-2">
            <div class="flex justify-between items-center">
              <div>
                <span class="font-medium"><?= htmlspecialchars($product['name']) ?></span>
                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded ml-2">
                  <?= htmlspecialchars($product['category']) ?>
                </span>
              </div>
              <div class="text-right">
                <span class="text-lg font-semibold"><?= number_format($product['total_waste']) ?></span>
                <span class="text-xs text-gray-500 block">items</span>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Top Wasted Ingredients -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h3 class="text-xl font-semibold mb-4">Top Wasted Ingredients (This Week)</h3>
      <?php if (empty($topWastedIngredients)): ?>
        <p class="text-gray-500">No ingredient waste recorded this week.</p>
      <?php else: ?>
        <ul class="divide-y">
          <?php foreach ($topWastedIngredients as $ingredient): ?>
          <li class="py-2">
            <div class="flex justify-between items-center">
              <div>
                <span class="font-medium"><?= htmlspecialchars($ingredient['ingredient_name']) ?></span>
                <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded ml-2">
                  <?= htmlspecialchars($ingredient['category']) ?>
                </span>
              </div>
              <div class="text-right">
                <span class="text-lg font-semibold"><?= number_format($ingredient['total_waste'], 1) ?></span>
                <span class="text-xs text-gray-500 block"><?= htmlspecialchars($ingredient['unit']) ?></span>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- Expiring Products -->
  <div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold">Expiring Soon</h3>
      <a href="product_stocks.php?show_expiring=1" class="text-xs px-2 py-1 bg-primarycol text-white rounded hover:bg-fourth">
        View All
      </a>
    </div>
    
    <?php if (empty($expiringProducts)): ?>
      <p class="text-gray-500">No products expiring within the next 7 days.</p>
    <?php else: ?>
      <ul class="divide-y">
        <?php foreach ($expiringProducts as $product): 
          // Determine urgency level based on days until expiry
          $urgencyClass = $product['days_until_expiry'] <= 2 
            ? 'bg-red-100 text-red-800' 
            : 'bg-amber-100 text-amber-800';
        ?>
        <li class="py-2">
          <div class="flex justify-between items-center">
            <div>
              <span class="font-medium"><?= htmlspecialchars($product['name']) ?></span>
              <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded ml-2">
                <?= htmlspecialchars($product['category']) ?>
              </span>
            </div>
            <div class="text-right">
              <span class="text-sm font-semibold <?= $urgencyClass ?> px-2 py-1 rounded">
                <?= $product['days_until_expiry'] ?> day<?= $product['days_until_expiry'] != 1 ? 's' : '' ?> left
              </span>
              <span class="text-xs text-gray-500 block"><?= date('M d, Y', strtotime($product['expiry_date'])) ?></span>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      
      <div class="mt-4 pt-3 border-t">
        <a href="product_stocks.php?show_expiring=1" class="text-primarycol hover:underline text-sm font-medium flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
          Manage expiring products
        </a>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Daily sales chart
    document.addEventListener('DOMContentLoaded', function() {
      // Format the sales by day data for the chart
      const salesData = [
        <?php 
        $days = [];
        $salesValues = [];
        
        foreach ($salesByDay as $day) {
          $dayName = date('D', strtotime($day['sale_day']));
          $days[] = "'$dayName'";
          $salesValues[] = round($day['day_revenue'], 2);
        }
        
        // If no data, add placeholder
        if (empty($days)) {
          $days = ["'Mon'", "'Tue'", "'Wed'", "'Thu'", "'Fri'", "'Sat'", "'Sun'"];
          $salesValues = [0, 0, 0, 0, 0, 0, 0];
        }
        ?>
        
        {
          x: [<?= implode(',', $days) ?>],
          y: [<?= implode(',', $salesValues) ?>],
          type: 'bar',
          marker: { color: '#47663B' }
        }
      ];
      
      const chartLayout = {
        height: 160,
        margin: { t: 0, r: 10, l: 40, b: 20 },
        xaxis: {
          title: { text: 'Day' }
        },
        yaxis: {
          title: { text: 'Sales (₱)' }
        }
      };
      
      const options = {
        series: [{
          name: 'Daily Sales',
          data: [<?= implode(',', $salesValues) ?>]
        }],
        chart: {
          height: 160,
          type: 'bar',
          toolbar: {
            show: false
          }
        },
        plotOptions: {
          bar: {
            borderRadius: 3,
            dataLabels: {
              position: 'top',
            },
          }
        },
        dataLabels: {
          enabled: false
        },
        colors: ['#47663B'],
        xaxis: {
          categories: [<?= implode(',', $days) ?>],
          position: 'bottom',
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
              return '₱' + val.toFixed(0);
            }
          }
        }
      };

      const chart = new ApexCharts(document.querySelector("#daily-sales-chart"), options);
      chart.render();
    });
  </script>
</div>
</body>
</html>
