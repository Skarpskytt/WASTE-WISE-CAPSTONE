<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

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
                            <th>Qty Produced</th>
                            <th>Actions</th>
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
                                <td><?= htmlspecialchars($product['quantity_produced']) ?></td>
                                <td class="flex gap-2">
                                    <a href="edit_product.php?id=<?= $product['id'] ?>" 
                                       class="btn btn-sm bg-blue-500 text-white hover:bg-blue-700">
                                        Edit
                                    </a>
                                    <button onclick="confirmDelete(<?= $product['id'] ?>)" 
                                            class="btn btn-sm bg-red-500 text-white hover:bg-red-700">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8">
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

    <script>
        function confirmDelete(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                window.location.href = `delete_product.php?id=${productId}`;
            }
        }
    </script>
</body>
</html>