<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Change the role check to use the 'staff' role which covers both branch staff types
checkAuth(['staff']);

// Get branch ID from session
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard</title>
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
    function markTaskDone(button) {
      button.parentElement.style.textDecoration = 'line-through';
      button.disabled = true;
    }

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
        <div class="stat-title">Products Wasted Today</div>
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
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
          </svg>
        </div>
        <div class="stat-title">Current Waste Goal</div>
        <div class="stat-value text-primarycol">-10%</div>
        <div class="stat-desc">From last month</div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Daily Tasks -->
 
    <!-- Enhanced Task Management Section -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold">Daily Waste Management Tasks</h3>
        <button class="text-xs px-2 py-1 bg-primarycol text-white rounded hover:bg-fourth" 
          onclick="document.getElementById('addTaskModal').classList.remove('hidden')">
          + New Task
        </button>
      </div>
      
      <div class="mb-3">
        <div class="flex gap-2 mb-2">
          <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
          <span class="text-sm font-medium">High Priority</span>
          <span class="inline-block w-3 h-3 rounded-full bg-yellow-500 ml-3"></span>
          <span class="text-sm font-medium">Medium Priority</span>
          <span class="inline-block w-3 h-3 rounded-full bg-blue-500 ml-3"></span>
          <span class="text-sm font-medium">Low Priority</span>
        </div>
      </div>
      
      <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
        <!-- High Priority Task -->
        <div class="task-item border-l-4 border-red-500 bg-gray-50 p-3 rounded-r flex justify-between items-center">
          <div>
            <div class="flex items-center">
              <input type="checkbox" class="task-checkbox mr-2" onchange="toggleTask(this)">
              <span class="font-medium">Record Today's Product Waste</span>
            </div>
            <div class="text-xs text-gray-500 mt-1 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Due today by 5:00 PM
            </div>
          </div>
          <a href="waste_product_input.php" class="text-xs bg-primarycol hover:bg-fourth text-white py-1 px-2 rounded">
            Go to Form
          </a>
        </div>
        
        <!-- Medium Priority Task -->
        <div class="task-item border-l-4 border-yellow-500 bg-gray-50 p-3 rounded-r flex justify-between items-center">
          <div>
            <div class="flex items-center">
              <input type="checkbox" class="task-checkbox mr-2" onchange="toggleTask(this)">
              <span class="font-medium">Track Flour & Sugar Usage</span>
            </div>
            <div class="text-xs text-gray-500 mt-1 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Due today by 3:00 PM
            </div>
          </div>
          <a href="waste_ingredients_input.php" class="text-xs bg-primarycol hover:bg-fourth text-white py-1 px-2 rounded">
            Go to Form
          </a>
        </div>
        
        <!-- Medium Priority Task -->
        <div class="task-item border-l-4 border-yellow-500 bg-gray-50 p-3 rounded-r flex justify-between items-center">
          <div>
            <div class="flex items-center">
              <input type="checkbox" class="task-checkbox mr-2" onchange="toggleTask(this)">
              <span class="font-medium">Check Expiring Ingredients</span>
            </div>
            <div class="text-xs text-gray-500 mt-1 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Due today by 12:00 PM
            </div>
          </div>
          <a href="ingredients.php" class="text-xs bg-primarycol hover:bg-fourth text-white py-1 px-2 rounded">
            Check Stock
          </a>
        </div>
        
        <!-- Low Priority Task -->
        <div class="task-item border-l-4 border-blue-500 bg-gray-50 p-3 rounded-r flex justify-between items-center">
          <div>
            <div class="flex items-center">
              <input type="checkbox" class="task-checkbox mr-2" onchange="toggleTask(this)">
              <span class="font-medium">Review Weekly Waste Reports</span>
            </div>
            <div class="text-xs text-gray-500 mt-1 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Due tomorrow
            </div>
          </div>
          <a href="#" class="text-xs bg-primarycol hover:bg-fourth text-white py-1 px-2 rounded">
            View Reports
          </a>
        </div>
        
        <!-- Completed Task Example -->
        <div class="task-item border-l-4 border-green-500 bg-gray-50 p-3 rounded-r flex justify-between items-center opacity-70">
          <div>
            <div class="flex items-center">
              <input type="checkbox" class="task-checkbox mr-2" checked onchange="toggleTask(this)">
              <span class="font-medium line-through">Morning Inventory Check</span>
            </div>
            <div class="text-xs text-gray-500 mt-1 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              Completed at 9:15 AM
            </div>
          </div>
        </div>
      </div>
      
      <div class="mt-4 pt-3 border-t text-sm text-gray-500 flex justify-between">
        <span>4 tasks remaining</span>
        <a href="#" class="text-primarycol hover:underline">View all tasks</a>
      </div>
    </div>

    <!-- Top Wasted Products -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h3 class="text-xl font-semibold mb-4">Top Wasted Products (This Week)</h3>
      <?php if (empty($topWastedProducts)): ?>
        <p class="text-gray-500">No product waste recorded this week.</p>
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

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Waste by Reason -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h3 class="text-xl font-semibold mb-4">Waste by Reason (Last 30 Days)</h3>
      <?php if (empty($wasteReasons)): ?>
        <p class="text-gray-500">No waste reasons data available.</p>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($wasteReasons as $reason): 
            $percentage = min(100, max(5, ($reason['count'] / array_sum(array_column($wasteReasons, 'count'))) * 100));
          ?>
          <div>
            <div class="flex justify-between mb-1">
              <span class="font-medium"><?= ucfirst(htmlspecialchars($reason['waste_reason'])) ?></span>
              <span><?= htmlspecialchars($reason['count']) ?> records</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
              <div class="bg-primarycol h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Ingredient Waste by Production Stage -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h3 class="text-xl font-semibold mb-4">Ingredient Waste by Production Stage (Last 30 Days)</h3>
      <?php if (empty($productionStages)): ?>
        <p class="text-gray-500">No production stage data available.</p>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($productionStages as $stage): 
            $percentage = min(100, max(5, ($stage['count'] / array_sum(array_column($productionStages, 'count'))) * 100));
          ?>
          <div>
            <div class="flex justify-between mb-1">
              <span class="font-medium"><?= ucfirst(htmlspecialchars($stage['production_stage'])) ?></span>
              <span><?= htmlspecialchars($stage['count']) ?> records</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
              <div class="bg-primarycol h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Product Trend -->
  <div class="bg-white p-6 rounded-lg shadow-md">
    <h2 class="font-extrabold text-2xl text-primarycol mb-6">Product Trend</h2>
    <?php include '../../charts/linechart.php'?>
    <div class="overflow-x-auto mt-4">
      <table class="table">
        <thead>
          <tr class="bg-primarycol text-white">
            <th></th>
            <th>Name</th>
            <th>Daily</th>
            <th>Weeks</th>
            <th>Monthly</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th>1</th>
            <td>Ensaymada</td>
            <td>80</td>
            <td>30</td>
            <td>21</td>
          </tr>
          <tr>
            <th>2</th>
            <td>Pandesal</td>
            <td>100</td>
            <td>300</td>
            <td>800</td>
          </tr> 
          <tr>
            <th>3</th>
            <td>Muffin</td>
            <td>40</td>
            <td>200</td>
            <td>520</td>
          </tr> 
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Task Modal -->
<div id="addTaskModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 max-w-md w-full">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold">Add New Task</h3>
      <button onclick="document.getElementById('addTaskModal').classList.add('hidden')">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    <form>
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="task-title">
          Task Title
        </label>
        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="task-title" type="text" placeholder="Enter task
