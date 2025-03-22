<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

// Branch ID for Branch 2 (assuming it's 2)
$branchId = 2;

// Pagination setup
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Count total active products for pagination (not expired only)
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM products 
    WHERE branch_id = ? 
    AND expiry_date > CURRENT_DATE
    AND stock_quantity > 0
");
$countStmt->execute([$branchId]);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $itemsPerPage);

// Fetch active products with pagination (only with stock > 0)
$stmt = $pdo->prepare("
    SELECT * 
    FROM products 
    WHERE branch_id = ? 
    AND expiry_date > CURRENT_DATE
    AND stock_quantity > 0
    ORDER BY id DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $branchId, PDO::PARAM_INT);
$stmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch branch name
$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: 'Branch 2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Stocks Branch 2</title>
  <link rel="icon" type="image/x-icon" href="../../assets/images/Logo.png">
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

    $('.request-donation-btn').on('click', function() {
        const productId = $(this).data('id');
        const productName = $(this).data('name');
        const stockQuantity = $(this).data('stock');
        const expiryDate = $(this).data('expiry');
        
        $('#product_id').val(productId);
        $('#product_name_display').text(productName);
        $('#stock_quantity_display').text(stockQuantity);
        $('#donation_quantity').attr('max', stockQuantity);
        
        document.getElementById('donation_modal').showModal();
    });
   });
  </script>
</head>

<body class="flex min-h-screen bg-gray-50">

<?php include '../layout/nav.php' ?>

<div class="p-7 w-full">
    <div>
        <nav class="mb-4">
            <ol class="flex items-center gap-2 text-gray-600">
                <li><a href="dashboard.php" class="hover:text-primarycol">Home</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="branches.php" class="hover:text-primarycol">Branches</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="product_stocks_branch2.php" class="hover:text-primarycol">Branch 2 Stock</a></li>
            </ol>
        </nav>
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-primarycol">Product Stocks - <?= htmlspecialchars($branchName) ?></h1>
                <p class="text-gray-500 mt-2">Active products in branch inventory</p>
            </div>
            <div class="flex space-x-2">
                <a href="product_stocks_branch1.php" class="btn btn-outline bg-sec hover:bg-third">
                    View Branch 1
                </a>
                <a href="product_stocks_branch3.php" class="btn btn-outline bg-sec hover:bg-third">
                    View Branch 3
                </a>
            </div>
        </div>
    </div>
    
    <!-- Product Stock Table -->
    <div class="w-full bg-white shadow-xl rounded-sm border border-gray-200 mt-4">
        <div class="overflow-x-auto p-4">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="bg-sec">
                        <th>#</th>
                        <th class="flex justify-center">Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Stock Date</th>
                        <th>Expiry Date</th>
                        <th>Price/Unit</th>
                        <th>Stock Qty</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $index => $product): 
                        $imgPath = !empty($product['image']) ? $product['image'] : '../../assets/images/default-product.jpg';
                    ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td class="flex justify-center">
                                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Product Image" class="h-8 w-8 object-cover rounded" />
                            </td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['category']) ?></td>
                            <td><?= htmlspecialchars($product['stock_date']) ?></td>
                            <td><?= htmlspecialchars($product['expiry_date']) ?></td>
                            <td>₱<?= number_format($product['price_per_unit'], 2) ?></td>
                            <td><?= htmlspecialchars($product['stock_quantity']) ?></td>
                            <td>
                                <button 
                                    class="request-donation-btn btn btn-sm bg-green-600 text-white hover:bg-green-700"
                                    data-id="<?= $product['id'] ?>"
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-stock="<?= htmlspecialchars($product['stock_quantity']) ?>"
                                    data-expiry="<?= htmlspecialchars($product['expiry_date']) ?>">
                                    Request Donation
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-8">
                                <div class="flex flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="font-medium text-gray-500">No active products found for this branch.</p>
                                    <p class="text-sm text-gray-400 mt-1">Branch staff need to add products or check expired items.</p>
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
    
    <!-- Stock Summary -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <h2 class="text-xl font-semibold text-primarycol mb-4">Stock Value Summary</h2>
            
            <?php
            // Calculate stock value
            $valueStmt = $pdo->prepare("
                SELECT 
                    SUM(stock_quantity * price_per_unit) as total_value,
                    COUNT(*) as product_count
                FROM products 
                WHERE branch_id = ? AND expiry_date > CURRENT_DATE
            ");
            $valueStmt->execute([$branchId]);
            $valueData = $valueStmt->fetch(PDO::FETCH_ASSOC);
            
            $totalValue = $valueData['total_value'] ?? 0;
            $productCount = $valueData['product_count'] ?? 0;
            ?>
            
            <div class="flex flex-col gap-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Total Products:</span>
                    <span class="font-semibold"><?= number_format($productCount) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Total Stock Value:</span>
                    <span class="font-semibold text-green-600">₱<?= number_format($totalValue, 2) ?></span>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <h2 class="text-xl font-semibold text-primarycol mb-4">Expiry Alert</h2>
            
            <?php
            // Get products expiring in the next 7 days
            $expiryStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as count,
                    SUM(stock_quantity * price_per_unit) as value
                FROM products 
                WHERE branch_id = ? 
                AND expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
            ");
            $expiryStmt->execute([$branchId]);
            $expiryData = $expiryStmt->fetch(PDO::FETCH_ASSOC);
            
            $expiringCount = $expiryData['count'] ?? 0;
            $expiringValue = $expiryData['value'] ?? 0;
            ?>
            
            <div class="flex flex-col gap-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Products Expiring Soon (7 days):</span>
                    <span class="font-semibold <?= $expiringCount > 0 ? 'text-amber-600' : 'text-green-600' ?>">
                        <?= number_format($expiringCount) ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Value at Risk:</span>
                    <span class="font-semibold <?= $expiringValue > 0 ? 'text-amber-600' : 'text-green-600' ?>">
                        ₱<?= number_format($expiringValue, 2) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Donation Request Modal -->
<dialog id="donation_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg text-primarycol">Request Product Donation</h3>
        <p class="py-2">Request donation for <span id="product_name_display" class="font-semibold"></span></p>
        
        <form method="POST" action="process_donation_request.php">
            <input type="hidden" id="product_id" name="product_id">
            <input type="hidden" name="branch_id" value="<?= $branchId ?>">
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Quantity to Donate</span>
                </label>
                <input type="number" name="quantity" id="donation_quantity" class="input input-bordered" min="1" required>
                <span class="text-xs text-gray-500">Available: <span id="stock_quantity_display"></span></span>
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Notes (Optional)</span>
                </label>
                <textarea name="notes" class="textarea textarea-bordered h-24"></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="document.getElementById('donation_modal').close();" class="btn">Cancel</button>
                <button type="submit" class="btn bg-primarycol text-white">Request Donation</button>
            </div>
        </form>
    </div>
</dialog>

</body>
</html>