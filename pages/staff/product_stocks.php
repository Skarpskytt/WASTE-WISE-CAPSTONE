<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Get show_expiring parameter from URL
$show_expiring = isset($_GET['show_expiring']) ? (int)$_GET['show_expiring'] : 0;

// Add this near the top of the file, after your existing GET parameters
$sort_method = isset($_GET['sort']) ? $_GET['sort'] : 'fefo'; // Default to FEFO

// Handle archive action (replace the delete action)
if (isset($_POST['archive_product'])) {
    $productId = $_POST['product_id'] ?? 0;
    
    try {
        $archiveStmt = $pdo->prepare("
            UPDATE products 
            SET is_archived = 1, 
                archived_at = NOW() 
            WHERE id = ? AND branch_id = ?
        ");
        $archiveStmt->execute([$productId, $_SESSION['branch_id']]);
        
        $archiveSuccess = "Product archived successfully!";
    } catch (PDOException $e) {
        $archiveError = "Error archiving product: " . $e->getMessage();
    }
}

// Handle edit/update action
if (isset($_POST['update_product'])) {
    $productId = $_POST['product_id'] ?? 0;
    $productName = $_POST['product_name'] ?? '';
    $category = $_POST['category'] ?? '';
    $expiryDate = $_POST['expiry_date'] ?? '';
    $pricePerUnit = $_POST['price_per_unit'] ?? 0;
    $stockQuantity = $_POST['stock_quantity'] ?? 0;
    $productionDate = $_POST['production_date'] ?? null;
    $shelfLifeDays = $_POST['shelf_life_days'] ?? 30;
    
    try {
        // If production date is provided, calculate new expiry date
        if (!empty($productionDate)) {
            $prodDate = new DateTime($productionDate);
            $expiryDate = clone $prodDate;
            $expiryDate->add(new DateInterval('P' . $shelfLifeDays . 'D'));
            $formattedExpiryDate = $expiryDate->format('Y-m-d');
        } else {
            // If no production date, use provided expiry date
            $formattedExpiryDate = date('Y-m-d', strtotime($expiryDate));
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, 
                category = ?,
                expiry_date = ?,
                price_per_unit = ?,
                stock_quantity = ?,
                production_date = ?
            WHERE id = ? AND branch_id = ?
        ");
        
        $updateStmt->execute([
            $productName,
            $category,
            $formattedExpiryDate, // Ensure properly formatted date
            $pricePerUnit,
            $stockQuantity,
            $productionDate ? date('Y-m-d', strtotime($productionDate)) : null,
            $productId,
            $_SESSION['branch_id']
        ]);
        
        $updateSuccess = "Product updated successfully!";
    } catch (PDOException $e) {
        $updateError = "Error updating product: " . $e->getMessage();
    }
}

// Build the SQL query
$countSql = "
    SELECT COUNT(*) 
    FROM products 
    WHERE branch_id = ? 
";

// Apply expiring filter (7 days)
if ($show_expiring) {
    $countSql .= " AND expiry_date > CURRENT_DATE AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND stock_quantity > 0";
} else {
    $countSql .= " AND expiry_date > CURRENT_DATE AND stock_quantity > 0";
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute([$branchId]);
$totalProducts = $countStmt->fetchColumn();

// Pagination setup
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;
$totalPages = ceil($totalProducts / $itemsPerPage);

// Build the SQL query to get products
$sql = "
    SELECT *, 
    DATEDIFF(expiry_date, CURRENT_DATE()) AS days_until_expiry,
    created_at AS stock_date
    FROM products 
    WHERE branch_id = ? 
    AND (is_archived = 0 OR is_archived IS NULL)
";

// Apply expiring filter
if ($show_expiring) {
    $sql .= " AND expiry_date > CURRENT_DATE AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND stock_quantity > 0";
} else {
    $sql .= " AND expiry_date > CURRENT_DATE AND stock_quantity > 0";
}

// Apply sorting based on method
if ($sort_method == 'fifo') {
    $sql .= " ORDER BY created_at ASC"; // FIFO - oldest items first
} else if ($sort_method == 'fefo') {
    $sql .= " ORDER BY expiry_date ASC"; // FEFO - expires soonest first
} else {
    $sql .= " ORDER BY id DESC"; // Default sort
}

$params = [$branchId]; // Initialize params array

// Add this to your SQL WHERE clause
if (!empty($_GET['batch'])) {
    $batchSearch = trim($_GET['batch']);
    $sql .= " AND batch_number LIKE ?";
    $params[] = "%{$batchSearch}%";
}

$sql .= " LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
// Bind all parameters in order
for ($i = 0; $i < count($params); $stmt->bindValue($i + 1, $params[$i]), $i++);
$stmt->bindValue(count($params) + 1, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add this code right after fetching products and before displaying the table
// This will determine the earliest expiry date among all products
$earliestExpiryDate = null;
if (!empty($products) && $sort_method == 'fefo') {
    $earliestExpiryDate = $products[0]['expiry_date'];
}
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
                const expiryDate = $(this).data('expiry-date');
                const pricePerUnit = $(this).data('price');
                const stockQuantity = $(this).data('stock-quantity');
                const batchNumber = $(this).data('batch-number');
                const productionDate = $(this).data('production-date');

                // Set the values in the edit form
                $('#edit_product_id').val(productId);
                $('#edit_product_name').val(productName);
                $('#edit_category').val(category);
                $('#edit_expiry_date').val(expiryDate);
                $('#edit_price_per_unit').val(pricePerUnit);
                $('#edit_stock_quantity').val(stockQuantity);
                $('#edit_batch_number').val(batchNumber);
                
                // Set production date if available
                if (productionDate) {
                    $('#edit_production_date').val(productionDate);
                }
                
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

            // Add this to your existing $(document).ready function
            $('.archive-product-btn').on('click', function() {
                const productId = $(this).data('id');
                
                // Set the product ID in the archive form
                $('#archive_product_id').val(productId);
                
                // Open the modal using DaisyUI's modal API
                document.getElementById('archive_modal').showModal();
            });

            // Auto-calculate expiry date based on production date and shelf life
            $('#edit_production_date').on('change', function() {
                const productionDate = new Date($(this).val());
                const shelfLife = parseInt($('#edit_shelf_life').val()) || 30; // Default to 30 days
                
                if (!isNaN(productionDate.getTime())) {
                    // Calculate expiry date by adding shelf life days
                    const expiryDate = new Date(productionDate);
                    expiryDate.setDate(expiryDate.getDate() + shelfLife);
                    
                    // Format date as YYYY-MM-DD for input field
                    const formattedDate = expiryDate.toISOString().split('T')[0];
                    $('#edit_expiry_date').val(formattedDate);
                }
            });
            
            // Update shelf life value when edit modal is opened
            $('.edit-product-btn').on('click', function() {
                // Previous code...
                const shelfLife = $(this).data('shelf-life') || 30;
                $('#edit_shelf_life').val(shelfLife);
            });

            // Update JavaScript to use the correct field name
            // Auto-calculate expiry date based on production date and shelf life days
            $('#edit_production_date').on('change', function() {
                const productionDate = new Date($(this).val());
                const shelfLifeDays = parseInt($('#edit_shelf_life_days').val()) || 30; // Default to 30 days
                
                if (!isNaN(productionDate.getTime())) {
                    // Calculate expiry date by adding shelf life days
                    const expiryDate = new Date(productionDate);
                    expiryDate.setDate(expiryDate.getDate() + shelfLifeDays);
                    
                    // Format date as YYYY-MM-DD for input field
                    const formattedDate = expiryDate.toISOString().split('T')[0];
                    $('#edit_expiry_date').val(formattedDate);
                }
            });

            // Update shelf life days value when edit modal is opened
            $('.edit-product-btn').on('click', function() {
                // Previous code...
                const shelfLifeDays = $(this).data('shelf-life-days') || 30;
                $('#edit_shelf_life_days').val(shelfLifeDays);
            });
        });
    </script>
    <!-- Replace all existing style tags with this single, consolidated version -->

<style>
    /* ===== TABLE COLUMN SIZING ===== */
    .table {
        width: 100%;
        table-layout: fixed;
    }
    
    /* Column widths */
    th:nth-child(1), td:nth-child(1) { width: 4rem; text-align: center; } /* Image */
    th:nth-child(2), td:nth-child(2) { width: 16%; } /* Product Name */
    th:nth-child(3), td:nth-child(3) { width: 10%; } /* Category */
    th:nth-child(4), td:nth-child(4) { width: 10%; } /* Added Date */
    th:nth-child(5), td:nth-child(5) { width: 10%; } /* Expiry Date */
    th:nth-child(6), td:nth-child(6) { width: 8%; } /* Price/Unit */
    th:nth-child(7), td:nth-child(7) { width: 8%; } /* Available Qty */
    th:nth-child(8), td:nth-child(8) { width: 6%; } /* Status */
    th:nth-child(9), td:nth-child(9) { width: 8%; text-align: center; } /* Use Order */
    th:nth-child(10), td:nth-child(10) { width: 14%; } /* Batch Information */
    th:nth-child(11), td:nth-child(11) { width: 10%; text-align: center; } /* Actions */
    
    /* ===== EXPIRY STATUS STYLING ===== */
    .expiry-urgent {
        background-color: #fee2e2;
        color: #b91c1c;
        animation: pulse 2s infinite;
    }
    
    .expiry-warning {
        background-color: #ffedd5;
        color: #c2410c;
    }
    
    .expiry-normal {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    /* ===== ACTION BUTTONS ===== */
    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
    }
    
    /* ===== FIFO/FEFO HIGHLIGHTING ===== */
    .fifo-oldest {
        background-color: rgba(59, 130, 246, 0.1);
        border-left: 4px solid #3b82f6;
        position: relative;
    }
    
    .fefo-expiring {
        background-color: rgba(245, 158, 11, 0.1);
        border-left: 4px solid #f59e0b;
        position: relative;
    }
    
    .inventory-highlight:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .use-first-badge {
        animation: pulse-strong 1.5s infinite;
    }
    
    /* ===== ANIMATIONS ===== */
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
    
    @keyframes pulse-strong {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    /* ===== CARD & SEARCH STYLING ===== */
    .product-card {
        transition: all 0.3s ease;
    }
    
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    .search-container {
        position: relative;
    }
    
    .search-icon {
        position: absolute;
        top: 50%;
        left: 1rem;
        transform: translateY(-50%);
        color: #9ca3af;
    }
    
    .search-input {
        padding-left: 2.5rem;
    }
</style>
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
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Excess</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Excess Records</a></li>
                </ol>
            </nav>
            <h1 class="text-3xl font-bold mb-6 text-primarycol">Product Stocks</h1>
            <p class="text-gray-500 mt-2"><?= $show_expiring ? 'Products expiring within 7 days' : 'Active products in your inventory' ?></p>
        </div>
        
        <!-- Add this new statistics card section after your title and before the filters -->

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <!-- Total Active Products Card - Renamed to be more accurate -->
    <?php
    // Count all active products (not expired and with stock)
    $totalActiveStmt = $pdo->prepare("
        SELECT COUNT(*) FROM products 
        WHERE branch_id = ? AND expiry_date > CURRENT_DATE AND stock_quantity > 0
    ");
    $totalActiveStmt->execute([$branchId]);
    $totalActiveProducts = $totalActiveStmt->fetchColumn();
    ?>
    <div class="product-card bg-white p-4 rounded-lg shadow border-l-4 border-primarycol">
        <div class="flex items-center">
            <div class="rounded-full bg-primarycol bg-opacity-10 p-3 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primarycol" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Active Products</p>
                <p class="text-xl font-bold text-gray-800"><?= $totalActiveProducts ?></p>
            </div>
        </div>
    </div>
    
    <!-- Critical Expiry Card (3 days) -->
    <?php
    $criticalCountStmt = $pdo->prepare("
        SELECT COUNT(*) FROM products 
        WHERE branch_id = ? AND expiry_date > CURRENT_DATE 
        AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY)
        AND stock_quantity > 0
    ");
    $criticalCountStmt->execute([$branchId]);
    $criticalCount = $criticalCountStmt->fetchColumn();
    ?>
    <div class="product-card bg-white p-4 rounded-lg shadow border-l-4 border-red-500">
        <div class="flex items-center">
            <div class="rounded-full bg-red-500 bg-opacity-10 p-3 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Critical (3 days)</p>
                <p class="text-xl font-bold text-gray-800"><?= $criticalCount ?></p>
            </div>
        </div>
    </div>
    
    <!-- Expiring Soon Card (7 days) -->
    <?php
    $expiringCountStmt = $pdo->prepare("
        SELECT COUNT(*) FROM products 
        WHERE branch_id = ? AND expiry_date > CURRENT_DATE
        AND expiry_date > DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY) 
        AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
        AND stock_quantity > 0
    ");
    $expiringCountStmt->execute([$branchId]);
    $expiringCount = $expiringCountStmt->fetchColumn();
    ?>
    <div class="product-card bg-white p-4 rounded-lg shadow border-l-4 border-amber-500">
        <div class="flex items-center">
            <div class="rounded-full bg-amber-500 bg-opacity-10 p-3 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Warning (4-7 days)</p>
                <p class="text-xl font-bold text-gray-800"><?= $expiringCount ?></p>
            </div>
        </div>
    </div>
    
    <!-- Today's Date Card -->
    <div class="product-card bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
        <div class="flex items-center">
            <div class="rounded-full bg-blue-500 bg-opacity-10 p-3 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Today's Date</p>
                <p class="text-xl font-bold text-gray-800">
                    <?php 
                    date_default_timezone_set('Asia/Manila'); 
                    echo date('M j, Y'); 
                    ?>
                </p>
                <p class="text-xs text-gray-500"><?= date('h:i A') ?> (PHT)</p>
            </div>
        </div>
    </div>
</div>

        <!-- Replace the existing search box with this enhanced version -->

<div class="flex flex-col md:flex-row gap-4 mb-6">
    <!-- Batch search input -->
    <div class="w-full md:w-1/3">
        <form method="GET" class="flex items-center">
            <!-- Preserve existing sort & show_expiring parameters -->
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_method) ?>">
            <input type="hidden" name="show_expiring" value="<?= htmlspecialchars($show_expiring) ?>">
            
            <div class="search-container relative w-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="search-icon h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                    type="text" 
                    name="batch" 
                    placeholder="Search by batch number..."
                    value="<?= isset($_GET['batch']) ? htmlspecialchars($_GET['batch']) : '' ?>"
                    class="search-input input input-bordered w-full pr-10"
                >
                <button type="submit" class="absolute right-0 top-0 h-full px-3 bg-primarycol text-white rounded-r-lg">
                    Search
                </button>
            </div>
        </form>
    </div>
    
    <div class="flex-grow flex justify-end">
        <a href="add_stock.php" class="btn bg-primarycol text-white hover:bg-fourth">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Stock
        </a>
    </div>
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

        <?php if (!empty($archiveSuccess)): ?>
            <div class="bg-amber-100 text-amber-800 p-3 rounded mb-4">
                <?= htmlspecialchars($archiveSuccess) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($archiveError)): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
                <?= htmlspecialchars($archiveError) ?>
            </div>
        <?php endif; ?>

        <!-- Replace the existing filter buttons with this enhanced version -->

<div class="flex flex-col sm:flex-row justify-between mb-4 gap-3">
    <!-- Sort method tabs -->
    <div class="tabs tabs-boxed bg-gray-100 p-1">
        <a href="?sort=fefo&show_expiring=<?= $show_expiring ?>&batch=<?= isset($_GET['batch']) ? htmlspecialchars($_GET['batch']) : '' ?>" 
           class="tab <?= $sort_method == 'fefo' ? 'tab-active bg-primarycol text-white' : '' ?>">
           <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
           </svg>
           FEFO (Expiry)
        </a>
        <a href="?sort=fifo&show_expiring=<?= $show_expiring ?>&batch=<?= isset($_GET['batch']) ? htmlspecialchars($_GET['batch']) : '' ?>" 
           class="tab <?= $sort_method == 'fifo' ? 'tab-active bg-primarycol text-white' : '' ?>">
           <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
           </svg>
           FIFO (Age)
        </a>
    </div>
    
    <!-- Filter buttons -->
    <div class="tabs tabs-boxed bg-gray-100 p-1">
        <a href="?show_expiring=0&sort=<?= $sort_method ?>&batch=<?= isset($_GET['batch']) ? htmlspecialchars($_GET['batch']) : '' ?>" 
           class="tab <?= !$show_expiring ? 'tab-active bg-primarycol text-white' : '' ?>">
           All Products
        </a>
        <a href="?show_expiring=1&sort=<?= $sort_method ?>&batch=<?= isset($_GET['batch']) ? htmlspecialchars($_GET['batch']) : '' ?>" 
           class="tab <?= $show_expiring ? 'tab-active bg-primarycol text-white' : '' ?> relative">
            Expiring Soon
            <?php if ($expiringCount > 0): ?>
                <span class="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center text-xs bg-yellow-500 text-white rounded-full"><?= $expiringCount ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

        <!-- Replace the table container with this enhanced version -->

<div class="w-full bg-white shadow-xl rounded-lg border border-gray-200 mt-4 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table w-full">
            <thead>
                <tr class="bg-sec text-gray-700">
                    <th class="text-center">Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Added Date</th>
                    <th>Expiry Date</th>
                    <th>Price/Unit</th>
                    <th>Available Qty</th>
                    <th>Status</th>
                    <th class="text-center">Use Order</th>
                    <th>Batch Information</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $index => $product): 
                    // Better image path handling
                    if (!empty($product['image'])) {
                        if (strpos($product['image'], '/') !== false) {
                            $imgPath = '../../' . $product['image'];
                        } else {
                            $imgPath = $product['image']; // Use the path directly as stored in DB
                        }
                    } else {
                        $imgPath = '../../assets/images/default-product.jpg';
                    }
                    
                    // Determine expiry status
                    $expiryStatus = '';
                    $expiryClass = '';
                    $daysLeft = $product['days_until_expiry'];
                    
                    if ($daysLeft <= 2) {
                        $expiryStatus = 'Critical';
                        $expiryClass = 'expiry-urgent';
                    } elseif ($daysLeft <= 4) {
                        $expiryStatus = 'Warning';
                        $expiryClass = 'expiry-warning';
                    } else {
                        $expiryStatus = 'Good';
                        $expiryClass = 'expiry-normal';
                    }
                ?>
                    <tr class="<?php 
if ($index === 0 && $sort_method == 'fifo') echo 'fifo-oldest inventory-highlight';
else if ($sort_method == 'fefo' && $product['expiry_date'] === $earliestExpiryDate) echo 'fefo-expiring inventory-highlight';
?>">
<td class="flex justify-center">
    <?php 
    // Correct image path handling
    $imagePath = "../../assets/images/default-product.jpg";
    if (!empty($product['image'])) {
        if (substr($product['image'], 0, 6) === '../../') {
            // Path already has the correct prefix, use as is
            $imagePath = $product['image'];
        } else if (strpos($product['image'], '/') !== false) {
            // Path contains directory structure but not the prefix
            $imagePath = "../../" . $product['image'];
        } else {
            // Just a filename, add full path
            $imagePath = "../../assets/uploads/products/" . $product['image'];
        }
    }
    ?>
    <img src="<?= htmlspecialchars($imagePath) ?>" 
         alt="<?= htmlspecialchars($product['name']) ?>"
         class="w-full h-full object-cover rounded"
         onerror="this.src='../../assets/images/default-product.jpg'">
</td>
<td><?= htmlspecialchars($product['name']) ?></td>
<td><?= htmlspecialchars($product['category']) ?></td>
<td><?= date('F j, Y', strtotime($product['stock_date'])) ?></td>
<td>
    <?= date('F j, Y', strtotime($product['expiry_date'])) ?>
    <span class="block text-xs text-gray-500">
        <?= $daysLeft ?> day<?= $daysLeft != 1 ? 's' : '' ?> left
    </span>
</td>
<td>₱<?= number_format($product['price_per_unit'], 2) ?></td>
<td>
<?php if ($product['unit_type'] === 'box'): ?>
    <div>
        <span class="font-medium"><?= htmlspecialchars($product['stock_quantity']) ?> boxes</span>
        <span class="block text-xs text-gray-500">
            (<?= htmlspecialchars($product['stock_quantity'] * $product['pieces_per_box']) ?> total pieces)
        </span>
    </div>
<?php else: ?>
    <?= htmlspecialchars($product['stock_quantity']) ?> pieces
<?php endif; ?>
</td>
<td>
    <span class="px-2 py-1 rounded text-xs <?= $expiryClass ?>">
        <?= $expiryStatus ?>
    </span>
</td>
<!-- Then replace your Use Order table cell with this code -->
<td>
    <?php if ($index === 0 && $sort_method == 'fifo'): ?>
        <span class="badge bg-blue-100 text-blue-800 font-bold mt-1 use-first-badge">
            Use First (FIFO)
        </span>
    <?php elseif($sort_method == 'fefo' && $product['expiry_date'] === $earliestExpiryDate): ?>
        <span class="badge bg-amber-100 text-amber-800 font-bold mt-1 use-first-badge">
            Use First (FEFO)
        </span>
    <?php elseif($index < 3 && $sort_method != 'fefo' || 
               ($sort_method == 'fefo' && $index < 5 && $product['expiry_date'] !== $earliestExpiryDate)): ?>
        <span class="badge bg-gray-100 text-gray-800 mt-1">
            Use Soon
        </span>
    <?php endif; ?>
</td>
<td>
<?php if (!empty($product['batch_number'])): ?>
    <div class="flex flex-col">
        <span class="text-xs font-mono bg-blue-50 text-blue-700 px-2 py-1 rounded">
            <?= htmlspecialchars($product['batch_number']) ?>
        </span>
        
        <?php if (!empty($product['production_date'])): ?>
            <div class="text-xs text-gray-500 mt-1">
                Produced: <?= date('M j, Y', strtotime($product['production_date'])) ?>
            </div>
        <?php endif; ?>
        
        <div class="text-xs text-gray-500 mt-1">
            Added: <?= date('M j, Y', strtotime($product['created_at'])) ?>
        </div>
    </div>
<?php else: ?>
    <span class="text-xs text-gray-400">No batch info</span>
<?php endif; ?>
</td>
<!-- Replace the action buttons cell with this updated version -->
<td class="text-center">
<div class="action-buttons">
    <button 
        class="edit-product-btn btn btn-sm btn-outline btn-success"
        data-id="<?= $product['id'] ?>"
        data-name="<?= htmlspecialchars($product['name']) ?>"
        data-category="<?= htmlspecialchars($product['category']) ?>"
        data-expiry-date="<?= htmlspecialchars($product['expiry_date']) ?>"
        data-price="<?= htmlspecialchars($product['price_per_unit']) ?>"
        data-stock-quantity="<?= htmlspecialchars($product['stock_quantity']) ?>"
        data-batch-number="<?= isset($product['batch_number']) ? htmlspecialchars($product['batch_number']) : '' ?>"
        data-production-date="<?= isset($product['production_date']) ? htmlspecialchars($product['production_date']) : '' ?>"
        data-shelf-life-days="<?= isset($product['shelf_life_days']) ? htmlspecialchars($product['shelf_life_days']) : '30' ?>">
        Edit
    </button>
    
    <button 
        class="archive-product-btn btn btn-sm btn-outline btn-warning"
        data-id="<?= $product['id'] ?>">
        Archive
    </button>
</div>
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
                                <p class="font-medium text-gray-500">
                                    <?= $show_expiring ? 'No products expiring within the next 7 days.' : 'No active products found.' ?>
                                </p>
                                <?php if (!$show_expiring): ?>
                                    <p class="text-sm text-gray-400 mt-1">Add products or check expired items.</p>
                                    <a href="product_data.php" class="mt-3 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700 text-sm">
                                        Add New Product
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center mt-4">
                        <div class="join">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= ($page - 1) ?>&show_expiring=<?= $show_expiring ?>" class="join-item btn bg-sec hover:bg-third">«</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>&show_expiring=<?= $show_expiring ?>" class="join-item btn <?= ($i == $page) ? 'bg-primarycol text-white' : 'bg-sec hover:bg-third' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= ($page + 1) ?>&show_expiring=<?= $show_expiring ?>" class="join-item btn bg-sec hover:bg-third">»</a>
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
                <input type="hidden" id="edit_shelf_life" name="shelf_life" value="30">
                <input type="hidden" id="edit_shelf_life_days" name="shelf_life_days" value="30">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Product Information -->
                    <div class="col-span-2 bg-gray-50 p-3 rounded-lg mb-2">
                        <h4 class="font-medium text-gray-700 mb-2">Product Information</h4>
                    </div>
                    
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
                            Price Per Unit (₱)
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
                    
                    <!-- Dates Section -->
                    <div class="col-span-2 bg-gray-50 p-3 rounded-lg mb-2 mt-3">
                        <h4 class="font-medium text-gray-700 mb-2">Dates</h4>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Production Date
                        </label>
                        <input type="date"
                            id="edit_production_date"
                            name="production_date"
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
                    
                    <!-- Batch Information -->
                    <div class="col-span-2 bg-gray-50 p-3 rounded-lg mb-2 mt-3">
                        <h4 class="font-medium text-gray-700 mb-2">Batch Information</h4>
                    </div>
                    
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Batch Number
                        </label>
                        <input type="text"
                            id="edit_batch_number"
                            name="batch_number"
                            readonly
                            class="input input-bordered w-full bg-gray-50 font-mono">
                        <p class="text-xs text-gray-400">Batch numbers can't be changed</p>
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
                    <button type="submit" name="archive_product" class="btn btn-error text-white">
                        Delete Product
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Archive Confirmation Modal -->
<dialog id="archive_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg text-amber-600">Archive Product</h3>
        <p class="py-4">Are you sure you want to archive this product? Archived products won't appear in active inventory.</p>
        <form method="POST">
            <input type="hidden" id="archive_product_id" name="product_id">
            <div class="modal-action">
                <button type="button" onclick="document.getElementById('archive_modal').close();" class="btn">Cancel</button>
                <button type="submit" name="archive_product" class="btn bg-amber-500 text-white hover:bg-amber-600">
                    Archive Product
                </button>
            </div>
        </form>
    </div>
</dialog>
</body>
</html>