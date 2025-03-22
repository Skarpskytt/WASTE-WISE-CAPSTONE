<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

// Handle delete action
if (isset($_POST['delete_product'])) {
    $productId = $_POST['product_id'] ?? 0;
    
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND branch_id = ?");
        $deleteStmt->execute([$productId, $_SESSION['branch_id']]);
        
        $deleteSuccess = "Product deleted successfully!";
    } catch (PDOException $e) {
        $deleteError = "Error deleting product: " . $e->getMessage();
    }
}

// Handle edit/update action
if (isset($_POST['update_product'])) {
    $productId = $_POST['product_id'] ?? 0;
    $productName = $_POST['product_name'] ?? '';
    $category = $_POST['category'] ?? '';
    $stockDate = $_POST['stock_date'] ?? '';
    $expiryDate = $_POST['expiry_date'] ?? '';
    $pricePerUnit = $_POST['price_per_unit'] ?? 0;
    $stockQuantity = $_POST['stock_quantity'] ?? 0;
    
    try {
        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, 
                category = ?,
                stock_date = ?,
                expiry_date = ?,
                price_per_unit = ?,
                stock_quantity = ?
            WHERE id = ? AND branch_id = ?
        ");
        
        $updateStmt->execute([
            $productName,
            $category,
            $stockDate,
            $expiryDate,
            $pricePerUnit,
            $stockQuantity,
            $productId,
            $_SESSION['branch_id']
        ]);
        
        $updateSuccess = "Product updated successfully!";
    } catch (PDOException $e) {
        $updateError = "Error updating product: " . $e->getMessage();
    }
}

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
$countStmt->execute([$_SESSION['branch_id']]);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $itemsPerPage);

// Fetch active products with pagination
$stmt = $pdo->prepare("
    SELECT * 
    FROM products 
    WHERE branch_id = ? 
    AND expiry_date > CURRENT_DATE
    AND stock_quantity > 0
    ORDER BY id DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $_SESSION['branch_id'], PDO::PARAM_INT);
$stmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Stocks - WasteWise</title>
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
            
            // Open edit modal and populate form
            $('.edit-product-btn').on('click', function() {
                const productId = $(this).data('id');
                const productName = $(this).data('name');
                const category = $(this).data('category');
                const stockDate = $(this).data('stock-date');
                const expiryDate = $(this).data('expiry-date');
                const pricePerUnit = $(this).data('price');
                const quantityProduced = $(this).data('quantity-produced');
                const stockQuantity = $(this).data('stock-quantity');
                
                // Set the values in the edit form
                $('#edit_product_id').val(productId);
                $('#edit_product_name').val(productName);
                $('#edit_category').val(category);
                $('#edit_stock_date').val(stockDate);
                $('#edit_expiry_date').val(expiryDate);
                $('#edit_price_per_unit').val(pricePerUnit);
                $('#edit_quantity_produced').val(quantityProduced);
                $('#edit_stock_quantity').val(stockQuantity);
                
                // Open the modal using DaisyUI's modal API
                document.getElementById('edit_modal').showModal();
            });
            
            // Open delete confirmation modal
            $('.delete-product-btn').on('click', function() {
                const productId = $(this).data('id');
                
                // Set the product ID in the delete form
                $('#delete_product_id').val(productId);
                
                // Open the modal using DaisyUI's modal API
                document.getElementById('delete_modal').showModal();
            });
        });
    </script>
</head>

<body class="flex h-screen">
        <?php include ('../layout/staff_nav.php'); ?>

    <div class="p-7 w-full">
        <div>
            <nav class="mb-4">
                <ol class="flex items-center gap-2 text-gray-600">
                    <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="record_sales.php" class="hover:text-primarycol">Record Sales</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="product_stocks.php" class="hover:text-primarycol">Product Stocks</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Waste</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Waste Records</a></li>
                </ol>
            </nav>
            <h1 class="text-3xl font-bold mb-6 text-primarycol">Product Stocks</h1>
            <p class="text-gray-500 mt-2">Active products in your inventory</p>
        </div>
        
        <!-- Display success or error messages -->
        <?php if (!empty($deleteSuccess)): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
                <?= htmlspecialchars($deleteSuccess) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($deleteError)): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
                <?= htmlspecialchars($deleteError) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($updateSuccess)): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
                <?= htmlspecialchars($updateSuccess) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($updateError)): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
                <?= htmlspecialchars($updateError) ?>
            </div>
        <?php endif; ?>

        <!-- Product Stock Table -->
        <div class="w-full bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200 mt-4">
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
                            <th>Available Qty</th>
                            <th class="text-center">Actions</th>
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
                                <td class="p-2">
                                    <div class="flex justify-center space-x-2">
                                        <button 
                                            class="edit-product-btn btn btn-sm btn-outline btn-success"
                                            data-id="<?= $product['id'] ?>"
                                            data-name="<?= htmlspecialchars($product['name']) ?>"
                                            data-category="<?= htmlspecialchars($product['category']) ?>"
                                            data-stock-date="<?= htmlspecialchars($product['stock_date']) ?>"
                                            data-expiry-date="<?= htmlspecialchars($product['expiry_date']) ?>"
                                            data-price="<?= htmlspecialchars($product['price_per_unit']) ?>"
                                            data-quantity-produced="<?= htmlspecialchars($product['quantity_produced']) ?>"
                                            data-stock-quantity="<?= htmlspecialchars($product['stock_quantity']) ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13" />
                                            </svg>
                                            Edit
                                        </button>
                                        
                                        <button 
                                            class="delete-product-btn btn btn-sm btn-outline btn-error"
                                            data-id="<?= $product['id'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Delete
                                        </button>
                                    </div>
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
                                        <p class="font-medium text-gray-500">No active products found.</p>
                                        <p class="text-sm text-gray-400 mt-1">Add products or check expired items.</p>
                                        <a href="product_data.php" class="mt-3 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700 text-sm">
                                            Add New Product
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

    <!-- Edit Modal -->
    <dialog id="edit_modal" class="modal">
        <div class="modal-box w-11/12 max-w-3xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-lg text-primarycol">Edit Product</h3>
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                </form>
            </div>
            
            <form method="POST">
                <input type="hidden" id="edit_product_id" name="product_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Product Name
                        </label>
                        <input type="text"
                            id="edit_product_name"
                            name="product_name"
                            required
                            class="input input-bordered w-full">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Category
                        </label>
                        <input type="text"
                            id="edit_category"
                            name="category"
                            required
                            class="input input-bordered w-full">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Stock Date
                        </label>
                        <input type="date"
                            id="edit_stock_date"
                            name="stock_date"
                            required
                            class="input input-bordered w-full">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Expiry Date
                        </label>
                        <input type="date"
                            id="edit_expiry_date"
                            name="expiry_date"
                            required
                            class="input input-bordered w-full">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Price Per Unit
                        </label>
                        <input type="number"
                            id="edit_price_per_unit"
                            name="price_per_unit"
                            min="0.01"
                            step="0.01"
                            required
                            class="input input-bordered w-full">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Quantity Produced
                        </label>
                        <input type="number"
                            id="edit_quantity_produced"
                            name="quantity_produced"
                            min="1"
                            step="1"
                            required
                            class="input input-bordered w-full">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Stock Quantity
                        </label>
                        <input type="number"
                            id="edit_stock_quantity"
                            name="stock_quantity"
                            min="0"
                            step="1"
                            required
                            class="input input-bordered w-full">
                    </div>
                </div>
                
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('edit_modal').close();" class="btn">Cancel</button>
                    <button type="submit" name="update_product" class="btn bg-primarycol text-white hover:bg-fourth">
                        Update Product
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Delete Confirmation Modal -->
    <dialog id="delete_modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg text-red-600">Delete Product</h3>
            <p class="py-4">Are you sure you want to delete this product? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" id="delete_product_id" name="product_id">
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('delete_modal').close();" class="btn">Cancel</button>
                    <button type="submit" name="delete_product" class="btn btn-error text-white">
                        Delete Product
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</body>
</html>