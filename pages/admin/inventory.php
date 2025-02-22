<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

// -----------------------
// INVENTORY PAGINATION
// -----------------------
$inv_page = isset($_GET['inv_page']) && is_numeric($_GET['inv_page']) ? (int) $_GET['inv_page'] : 1;
$inv_per_page = 10;

// Get total inventory count
$inv_total = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$inv_total_pages = ceil($inv_total / $inv_per_page);
$inv_offset = ($inv_page - 1) * $inv_per_page;

// Fetch Inventory Data with LIMIT/OFFSET
$invStmt = $pdo->prepare("SELECT * FROM inventory ORDER BY id DESC LIMIT :limit OFFSET :offset");
$invStmt->bindValue(':limit', $inv_per_page, PDO::PARAM_INT);
$invStmt->bindValue(':offset', $inv_offset, PDO::PARAM_INT);
$invStmt->execute();
$inventoryData = $invStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------
// INGREDIENTS PAGINATION
// -----------------------
$ing_page = isset($_GET['ing_page']) && is_numeric($_GET['ing_page']) ? (int) $_GET['ing_page'] : 1;
$ing_per_page = 10;

// Get total ingredients count
$ing_total = $pdo->query("SELECT COUNT(*) FROM ingredients")->fetchColumn();
$ing_total_pages = ceil($ing_total / $ing_per_page);
$ing_offset = ($ing_page - 1) * $ing_per_page;

// Fetch Ingredients Data with LIMIT/OFFSET
$ingStmt = $pdo->prepare("SELECT * FROM ingredients ORDER BY id DESC LIMIT :limit OFFSET :offset");
$ingStmt->bindValue(':limit', $ing_per_page, PDO::PARAM_INT);
$ingStmt->bindValue(':offset', $ing_offset, PDO::PARAM_INT);
$ingStmt->execute();
$ingredientsData = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Inventory & Ingredients Data</title>
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

        // Action buttons for Edit and Delete (Waste)
        $('.edit-waste-btn').on('click', function() {
            let id = $(this).data('id');
            window.location.href = `edit_waste.php?id=${id}`;
        });

        $('.delete-waste-btn').on('click', function() {
            if (confirm('Are you sure you want to delete this waste record?')) {
                let id = $(this).data('id');
                window.location.href = `delete_waste.php?id=${id}`;
            }
        });
    });
     </script>
       <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-screen">
<?php include '../layout/nav.php'?>

  <div class="flex-1 p-6 overflow-auto space-y-12">
    <!-- Inventory Data Table -->
    <div>
      <h1 class="text-3xl font-bold mb-6 text-primarycol">Inventory Data</h1>
      <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">ID</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Item Name</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Category</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Stock Date</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Quantity</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Unit</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Location</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Price/Unit</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Image</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($inventoryData): ?>
              <?php foreach ($inventoryData as $item): ?>
                <tr class="hover:bg-gray-100">
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['id']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['name']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['category']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['stock_date']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['quantity']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['unit']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['location']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">₱<?= htmlspecialchars(number_format($item['price_per_unit'], 2)) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                    <?php if (!empty($item['image'])): ?>
                      <img src="<?= htmlspecialchars($item['image']) ?>" class="w-8 h-8 object-cover rounded" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                      <img src="../../assets/default-product.jpg" class="w-8 h-8 object-cover rounded" alt="No Image">
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="py-4 px-6 text-center text-gray-500">No inventory records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Inventory Pagination -->
      <?php if ($inv_total_pages > 1): ?>
      <div class="flex justify-center mt-4">
        <nav class="inline-flex shadow-sm">
          <?php if ($inv_page > 1): ?>
            <a href="?inv_page=<?= $inv_page - 1 ?>&ing_page=<?= $ing_page ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Prev</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $inv_total_pages; $i++): ?>
            <a href="?inv_page=<?= $i ?>&ing_page=<?= $ing_page ?>" class="px-3 py-2 <?= $i == $inv_page ? 'bg-primarycol text-white' : 'bg-white text-gray-700' ?> border border-gray-300"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($inv_page < $inv_total_pages): ?>
            <a href="?inv_page=<?= $inv_page + 1 ?>&ing_page=<?= $ing_page ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Next</a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    </div>

    <!-- Ingredients Data Table -->
    <div>
      <h1 class="text-3xl font-bold mb-6 text-primarycol">Ingredients Data</h1>
      <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">ID</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Ingredient Name</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Expiration Date</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Stock Datetime</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Quantity</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Unit</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Price</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Location</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Image</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($ingredientsData): ?>
              <?php foreach ($ingredientsData as $ing): ?>
                <tr class="hover:bg-gray-100">
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['id']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['ingredient_name']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['expiration_date']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['stock_datetime']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['quantity']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['metric_unit']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">₱<?= htmlspecialchars(number_format($ing['price'], 2)) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['location']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                    <?php if (!empty($ing['item_image'])): ?>
                      <img src="<?= htmlspecialchars($ing['item_image']) ?>" class="w-8 h-8 object-cover rounded" alt="<?= htmlspecialchars($ing['ingredient_name']) ?>">
                    <?php else: ?>
                      <img src="../../assets/default-product.jpg" class="w-8 h-8 object-cover rounded" alt="No Image">
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="py-4 px-6 text-center text-gray-500">No ingredients records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Ingredients Pagination -->
      <?php if ($ing_total_pages > 1): ?>
      <div class="flex justify-center mt-4">
        <nav class="inline-flex shadow-sm">
          <?php if ($ing_page > 1): ?>
            <a href="?inv_page=<?= $inv_page ?>&ing_page=<?= $ing_page - 1 ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Prev</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $ing_total_pages; $i++): ?>
            <a href="?inv_page=<?= $inv_page ?>&ing_page=<?= $i ?>" class="px-3 py-2 <?= $i == $ing_page ? 'bg-primarycol text-white' : 'bg-white text-gray-700' ?> border border-gray-300"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($ing_page < $ing_total_pages): ?>
            <a href="?inv_page=<?= $inv_page ?>&ing_page=<?= $ing_page + 1 ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Next</a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>