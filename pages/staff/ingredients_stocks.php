<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Pagination setup
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Count total active ingredients for pagination (not expired only and with stock)
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM ingredients 
    WHERE branch_id = ? 
    AND (expiry_date > CURRENT_DATE OR expiry_date IS NULL)
    AND stock_quantity > 0
");
$countStmt->execute([$_SESSION['branch_id']]);
$totalIngredients = $countStmt->fetchColumn();
$totalPages = ceil($totalIngredients / $itemsPerPage);

// Fetch active ingredients with pagination
$stmt = $pdo->prepare("
    SELECT * 
    FROM ingredients 
    WHERE branch_id = ? 
    AND (expiry_date > CURRENT_DATE OR expiry_date IS NULL)
    AND stock_quantity > 0
    ORDER BY id DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $_SESSION['branch_id'], PDO::PARAM_INT);
$stmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingredients Stock - WasteWise</title>
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
</head>

<body class="flex h-screen">

<?php include (__DIR__ . '/../layout/staff_nav.php'); ?>

<!-- Add this alert message block -->
<?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
  <div id="alertMessage" class="alert <?= isset($_GET['success']) ? 'alert-success' : 'alert-error' ?> fixed top-4 right-4 w-auto max-w-md z-50 shadow-lg">
    <div class="flex items-center">
      <?php if (isset($_GET['success'])): ?>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      <?php else: ?>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      <?php endif; ?>
      <span><?= htmlspecialchars($_GET['message'] ?? '') ?></span>
      <button onclick="document.getElementById('alertMessage').style.display='none';" class="ml-auto">×</button>
    </div>
  </div>
  
  <script>
    // Auto-hide the alert after 5 seconds
    setTimeout(function() {
      const alert = document.getElementById('alertMessage');
      if (alert) {
        alert.style.display = 'none';
      }
    }, 5000);
  </script>
<?php endif; ?>

<div class="p-7">
  <div>
    <nav class="mb-4">
      <ol class="flex items-center gap-2 text-gray-600">
        <li><a href="ingredients.php" class="hover:text-primarycol">Ingredients</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="ingredients_stocks.php" class="hover:text-primarycol">Ingredients Stock</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_input.php" class="hover:text-primarycol">Record Waste</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_record.php" class="hover:text-primarycol">View Ingredients Waste Records</a></li>
      </ol>
    </nav>
    <h1 class="text-3xl font-bold mb-6 text-primarycol">Ingredients Stock</h1>
    <p class="text-gray-500 mt-2">Active ingredients in your inventory</p>
  </div>

  <!-- Ingredients Stock Table -->
  <div class="w-full bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200 mt-4">
    <div class="overflow-x-auto p-4">
      <table class="table table-zebra w-full">
        <!-- Table Head -->
        <thead>
          <tr class="bg-sec">
            <th>#</th>
            <th class="flex justify-center">Image</th>
            <th>Ingredient Name</th>
            <th>Category</th>
            <th>Supplier</th>
            <th>Stock Qty</th>
            <th>Unit</th>
            <th>Cost/Unit</th>
            <th>Expiry Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ingredients as $index => $ingredient): 
            // Properly format image path for display
            $imgPath = '';
            if (!empty($ingredient['item_image'])) {
              if (strpos($ingredient['item_image'], 'C:') === 0) {
                // Handle absolute Windows paths
                $filename = basename($ingredient['item_image']);
                $imgPath = 'uploads/ingredients/' . $filename;
              } else if (strpos($ingredient['item_image'], 'uploads/') === 0) {
                // Path already relative, use as-is
                $imgPath = $ingredient['item_image'];
              } else {
                // For any other format, try to use the base filename
                $filename = basename($ingredient['item_image']);
                $imgPath = 'uploads/ingredients/' . $filename;
              }
            } else {
              // Default image
              $imgPath = '../../assets/images/default-ingredient.jpg';
            }
          ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td class="flex justify-center">
                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Ingredient Image" class="h-8 w-8 object-cover rounded" />
              </td>
              <td><?= htmlspecialchars($ingredient['ingredient_name']) ?></td>
              <td><?= htmlspecialchars($ingredient['category'] ?? 'N/A') ?></td>
              <td><?= !empty($ingredient['supplier_name']) ? htmlspecialchars($ingredient['supplier_name']) : 'N/A' ?></td>
              <td><?= htmlspecialchars($ingredient['stock_quantity'] ?? 0) ?></td>
              <td><?= htmlspecialchars($ingredient['unit'] ?? '') ?></td>
              <td>₱<?= number_format($ingredient['cost_per_unit'] ?? 0, 2) ?></td>
              <td><?= !empty($ingredient['expiry_date']) ? htmlspecialchars(date('Y-m-d', strtotime($ingredient['expiry_date']))) : 'N/A' ?></td>
              <td>
                <a href="use_stock.php?id=<?= $ingredient['id'] ?>" 
                  class="btn btn-sm bg-primarycol text-white hover:bg-green-700">
                  Use Stock
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($ingredients)): ?>
            <tr>
              <td colspan="10" class="text-center py-8">
                <div class="flex flex-col items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                  </svg>
                  <p class="font-medium text-gray-500">No active ingredients found.</p>
                  <p class="text-sm text-gray-400 mt-1">Add ingredients with stock or check expired items.</p>
                  <a href="ingredients.php" class="mt-3 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700 text-sm">
                    Add New Ingredients
                  </a>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
      
      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="flex justify-center mt-4">
        <div class="join">
          <?php if ($page > 1): ?>
            <a href="?page=<?= ($page - 1) ?>" class="join-item btn bg-sec hover:bg-third">«</a>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="join-item btn <?= ($i == $page) ? 'bg-primarycol text-white' : 'bg-sec hover:bg-third' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= ($page + 1) ?>" class="join-item btn bg-sec hover:bg-third">»</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<

<!-- No content needed since all modal-related code was removed -->

</body>
</html>