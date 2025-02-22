<?php
require_once '../../../config/auth_middleware.php';
require_once '../../../config/db_connect.php';

checkAuth(['branch2_staff']);

// Handle filters (search + date range)
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$sql    = "SELECT * FROM inventory WHERE 1=1";
$params = [];

// Search filter
if (!empty($search)) {
  $sql .= " AND name LIKE ?";
  $params[] = "%$search%";
}

// Date filter
if (!empty($start_date)) {
  $sql .= " AND stock_date >= ?";
  $params[] = $start_date;
}
if (!empty($end_date)) {
  $sql .= " AND stock_date <= ?";
  $params[] = $end_date;
}

$sql .= " ORDER BY created_at DESC";

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $inventory = $stmt->fetchAll();
} catch (PDOException $e) {
  echo "Error retrieving inventory: " . $e->getMessage();
}

// Handle new ingredient form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ingredient'])) {
    $ingredient_name  = $_POST['ingredient_name']  ?? '';
    $ingredient_type  = $_POST['ingredient_type']  ?? '';
    $expiration_date  = $_POST['expiration_date']  ?? '';
    $stock_datetime   = $_POST['stock_datetime']   ?? '';
    $quantity         = $_POST['quantity']         ?? '';
    $metric_unit      = $_POST['metric_unit']      ?? '';
    $price            = $_POST['price']            ?? '';
    $location         = $_POST['location']         ?? '';

    try {
        // Insert into `ingredients`
        $insertSql = "INSERT INTO ingredients 
            (ingredient_name, ingredient_type, expiration_date, stock_datetime, quantity, metric_unit, price, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            $ingredient_name, 
            $ingredient_type, 
            $expiration_date, 
            $stock_datetime, 
            $quantity, 
            $metric_unit, 
            $price, 
            $location
        ]);
        $ingredientSuccess = "Ingredient added successfully!";
    } catch (PDOException $e) {
        $ingredientError = "Error adding ingredient: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Table</title>
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

<body class="flex h-screen">
<?php include (__DIR__ . '/../../layout/nav_branch2.php'); ?>

<div class="p-6 w-full">
  <h2 class="text-2xl font-semibold mb-6">Inventory Data</h2>

  <!-- Display success or error messages (if any) -->
  <?php if (!empty($ingredientSuccess)): ?>
    <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
      <?= htmlspecialchars($ingredientSuccess) ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($ingredientError)): ?>
    <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
      <?= htmlspecialchars($ingredientError) ?>
    </div>
  <?php endif; ?>

  <!-- Search & Filter Form -->
  <form method="GET" class="flex flex-wrap gap-3 items-end mb-6">
     <!-- Add Ingredient Button (opens a modal, for example) -->

  <div class="ml-auto">
      <label for="search" class="block mb-1 text-sm font-medium">Search Item</label>
      <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>"
             class="border w-60 p-2 rounded focus:ring focus:border-primarycol"
             placeholder="Enter item name..." />
    </div>
   

    <div>
      <label for="start_date" class="block mb-1 text-sm font-medium">From</label>
      <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
             class="border p-2 rounded focus:ring focus:border-primarycol" />
    </div>

    <div>
      <label for="end_date" class="block mb-1 text-sm font-medium">To</label>
      <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
             class="border p-2 rounded focus:ring focus:border-primarycol" />
    </div>
    <div>
      <button type="submit" class="bg-primarycol text-white px-4 py-2 rounded hover:bg-fourth">
        Search
      </button>
    </div>
   

   
  </form>

  <div class="overflow-x-auto w-full">
    <div class="bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200">
      <div class="overflow-x-auto p-4">
        <table class="table table-zebra w-full">
          <thead>
            <tr class="bg-sec">
              <th>#</th>
              <th class="flex justify-center">Image</th>
              <th>Item Name</th>
              <th>Category</th>
              <th>Quantity Sold</th>
              <th>Unit</th>
              <th>Location</th>
              <th>Stock Date</th>
              <th>Price per Unit</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
              if (!empty($inventory)) {
                $count = 1;
                foreach ($inventory as $item) {
                  echo "<tr>";
                  echo "<td>" . $count++ . "</td>";
                    echo "<td class='flex justify-center'><img src='../../admin/" . htmlspecialchars($item['image']) . "' class='h-8 w-8' alt='" . htmlspecialchars($item['name']) . "'></td>";
                  echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['location']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['stock_date']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['price_per_unit']) . "</td>";
                  echo "<td class='p-2'>
                          <div class='flex justify-center space-x-2'>
                            <a href='#' class='rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center'>
                              <!-- Edit Icon -->
                              <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13' />
                              </svg>
                              Edit
                            </a>
                            <a href='#' class='rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center' onclick='return confirm(\"Delete this item?\");'>
                              <!-- Delete Icon -->
                              <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12' />
                              </svg>
                              Delete
                            </a>
                          </div>
                        </td>";
                  echo "</tr>";
                }
              } else {
                echo "<tr><td colspan='10' class='text-center'>No inventory items found.</td></tr>";
              }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


</body>
</html>



