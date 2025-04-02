<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

$errors = [];
$successMessage = '';

// Get all products from this branch for dropdown
$productsStmt = $pdo->prepare("
    SELECT id, name, category
    FROM products 
    WHERE branch_id = ?
    GROUP BY name, category
    ORDER BY name ASC
");
$productsStmt->execute([$branchId]);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique products with total stock counts
$productsQuery = $pdo->prepare("
    SELECT 
        MIN(p.id) as id, 
        p.name, 
        p.category, 
        p.price_per_unit, 
        p.image,
        (SELECT SUM(stock_quantity) FROM products 
         WHERE name = p.name AND branch_id = ? 
         AND (is_archived = 0 OR is_archived IS NULL)) as total_stock
    FROM products p
    WHERE p.branch_id = ?
    AND (p.is_archived = 0 OR p.is_archived IS NULL)
    GROUP BY p.name, p.category, p.price_per_unit, p.image
    ORDER BY p.name ASC
    LIMIT 12
");

$productsQuery->execute([$branchId, $branchId]);
$allProducts = $productsQuery->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for filter dropdown
$categoriesQuery = $pdo->prepare("SELECT DISTINCT category FROM products WHERE branch_id = ? ORDER BY category");
$categoriesQuery->execute([$branchId]);
$categories = $categoriesQuery->fetchAll(PDO::FETCH_COLUMN);

// Function to generate batch number
function generateBatchNumber() {
    $today = date('Ymd');
    $random = mt_rand(1000, 9999);
    return "BB-{$today}-{$random}";
}

// Handle Add Stock Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_stock'])) {
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $stockQuantity = intval($_POST['stock_quantity']);
    $productionDate = $_POST['production_date'] ?? date('Y-m-d');
    
    // Use posted batch number or generate a new one
    $batchNumber = !empty($_POST['batch_number']) ? $_POST['batch_number'] : generateBatchNumber();
    
    // Get expiry date from form
    $expiryDate = $_POST['expiry_date'] ?? '';
    
    // If no expiry date is set from JavaScript, calculate one using PHP (as fallback)
    if (empty($expiryDate) && !empty($productionDate)) {
        // Get product details including shelf_life_days
        $productDetailsStmt = $pdo->prepare("SELECT shelf_life_days FROM products WHERE id = ? LIMIT 1");
        $productDetailsStmt->execute([$productId]);
        $productDetails = $productDetailsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Use shelf life from product or default to 30 days
        $shelfLifeDays = $productDetails['shelf_life_days'] ?? 30;
        
        // Calculate expiry date
        $expiryDate = date('Y-m-d', strtotime($productionDate . ' + ' . $shelfLifeDays . ' days'));
        
        // Log this fallback for debugging
        error_log("Using PHP fallback for expiry calculation: Production date {$productionDate} + {$shelfLifeDays} days = {$expiryDate}");
    }
    
    // Validate inputs
    if ($productId <= 0) {
        $errors[] = "Please select a valid product.";
    }
    
    if ($stockQuantity <= 0) {
        $errors[] = "Stock quantity must be greater than zero.";
    }
    
    if ($_POST['unit_type'] === 'box' && (!isset($_POST['pieces_per_box']) || intval($_POST['pieces_per_box']) <= 0)) {
        $errors[] = "Please specify how many pieces are in each box.";
    }

    // Validate that an expiry date was calculated
    if (empty($_POST['expiry_date'])) {
        $errors[] = "Error: Missing expiry date. The selected product may not have shelf life information. Please contact an administrator.";
    }

    if (!empty($_POST['expiry_date']) && !empty($_POST['production_date'])) {
        if (strtotime($_POST['expiry_date']) <= strtotime($_POST['production_date'])) {
            $errors[] = "Error: Expiry date must be after production date.";
        }
    }

    // If no errors, proceed
    if (empty($errors)) {
        try {
            // Get original product details
            $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND branch_id = ?");
            $productStmt->execute([$productId, $branchId]);
            $productData = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$productData) {
                $errors[] = "Product not found.";
            } else {
                // Insert new product batch
                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        name, category, expiry_date, unit, price_per_unit, 
                        stock_quantity, image, branch_id, batch_number, production_date,
                        shelf_life_days, unit_type, pieces_per_box
                    ) VALUES (
                        :name, :category, :expiryDate, :unit, :pricePerUnit, 
                        :stockQuantity, :image, :branchId, :batchNumber, :productionDate,
                        :shelfLifeDays, :unitType, :piecesPerBox
                    )
                ");
                
                $stmt->execute([
                    ':name' => $productData['name'],
                    ':category' => $productData['category'],
                    ':expiryDate' => $expiryDate,
                    ':unit' => $productData['unit'],
                    ':pricePerUnit' => $productData['price_per_unit'],
                    ':stockQuantity' => $stockQuantity,
                    ':image' => $productData['image'],
                    ':branchId' => $branchId,
                    ':batchNumber' => $batchNumber,
                    ':productionDate' => $productionDate,
                    ':shelfLifeDays' => $productData['shelf_life_days'],
                    ':unitType' => $_POST['unit_type'],
                    ':piecesPerBox' => intval($_POST['pieces_per_box'])
                ]);
                
                $successMessage = "New stock batch added successfully with batch #" . $batchNumber;
            }
        } catch (PDOException $e) {
            $errors[] = "Error: Could not execute the query. " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Stock - Bea Bakes</title>
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
        // Generate and display batch number
        function generateBatchNumber() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const dateString = `${year}${month}${day}`;
            const random = Math.floor(1000 + Math.random() * 9000);
            
            return `BB-${dateString}-${random}`;
        }

        // Set initial batch number on page load
        const batchNumber = generateBatchNumber();
        $('#batch_number_display').text(batchNumber);
        $('#batch_number').val(batchNumber);

        // Regenerate batch number when button is clicked
        $('#regenerate-batch').on('click', function(e) {
            e.preventDefault();
            const newBatch = generateBatchNumber();
            $('#batch_number_display').text(newBatch);
            $('#batch_number').val(newBatch);
        });
        
        // Calculate expiry date based on production date and shelf life days
        function calculateExpiryDateFromShelfLife() {
            const productionDate = $('#production_date').val();
            const shelfLifeDays = parseInt($('#product_shelf_life').val()) || 30;
            
            console.log("Calculating expiry date:", productionDate, shelfLifeDays);
            
            if (productionDate) {
                // Create a date object from production date
                const prodDate = new Date(productionDate);
                
                // Add shelf life days
                const expiryDate = new Date(prodDate);
                expiryDate.setDate(prodDate.getDate() + shelfLifeDays);
                
                // Format as YYYY-MM-DD
                const formattedDate = expiryDate.toISOString().split('T')[0];
                
                // Update fields
                $('#expiry_preview').val(formattedDate);
                $('#expiry_date').val(formattedDate);
                
                if (shelfLifeDays <= 0) {
                    $('#expiry_message').text('Warning: Using default 30 days shelf life');
                    $('#expiry_message').addClass('text-amber-500');
                } else {
                    $('#expiry_message').text(`Based on ${shelfLifeDays} days shelf life`);
                    $('#expiry_message').removeClass('text-red-500 text-amber-500');
                }
                
                console.log("Calculated expiry date:", formattedDate);
            }
        }
        
        // Production date change handler
        $('#production_date').on('change', calculateExpiryDateFromShelfLife);
        
        // Handle image loading errors
        function handleImageErrors() {
            $('img').on('error', function() {
                $(this).attr('src', '../../assets/images/Company Logo.jpg')
                      .addClass('img-error');
            });
        }
        
        // Call handler for existing images
        handleImageErrors();
        
        // Get product details when selected
        $('#product_id').on('change', function() {
            const productId = $(this).val();
            if (!productId) {
                $('#product_details').addClass('hidden');
                return;
            }
            
            $.ajax({
                url: 'get_product_details.php',
                type: 'GET',
                data: { id: productId },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        console.error("API Error:", data.error);
                        alert("Error loading product details: " + data.error);
                        return;
                    }
                    
                    $('#product_details').removeClass('hidden');
                    $('#product_name').text(data.name);
                    $('#product_category').text(data.category);
                    $('#product_price').text('₱' + parseFloat(data.price_per_unit).toFixed(2));
                    
                    // Store shelf life and calculate expiry
                    $('#product_shelf_life').val(data.shelf_life_days || 30);
                    
                    // Calculate expiry date
                    calculateExpiryDateFromShelfLife();
                    
                    // Improved image handling
                    if (data.image) {
                        let imgPath;
                        
                        if (data.image.startsWith('../../')) {
                            imgPath = data.image;
                        } else if (data.image.includes('/')) {
                            imgPath = "../../" + data.image;
                        } else {
                            imgPath = "../../assets/uploads/products/" + data.image;
                        }
                        
                        const img = new Image();
                        img.onload = function() {
                            $('#product_image').attr('src', imgPath);
                        };
                        img.onerror = function() {
                            $('#product_image').attr('src', '../../assets/images/Company Logo.jpg');
                            $('#image-error').removeClass('hidden');
                        };
                        img.src = imgPath;
                    } else {
                        $('#product_image').attr('src', '../../assets/images/default-product.jpg');
                    }
                },
                error: function(xhr, status, error) {
                    $('#product_details').addClass('hidden');
                    console.error("AJAX Error:", error);
                    alert('Error fetching product details: ' + error);
                }
            });
        });

        // Initialize with current production date
        if ($('#production_date').val()) {
            const defaultShelfLife = 30;
            $('#product_shelf_life').val(defaultShelfLife);
            calculateExpiryDateFromShelfLife();
        }
        
        // Search and filter functionality
        const $searchInput = $('#search-products');
        const $categoryFilter = $('#category-filter');
        const $stockFilter = $('#stock-filter');
        const $productCards = $('.product-card');
        
        function filterProducts() {
            const searchTerm = $searchInput.val().toLowerCase();
            const categoryFilter = $categoryFilter.val();
            const stockFilter = $stockFilter.val();
            
            $productCards.each(function() {
                const $card = $(this);
                const name = $card.data('name').toLowerCase();
                const category = $card.data('category');
                const stockStatus = $card.data('stock-status');
                
                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = !categoryFilter || category === categoryFilter;
                const matchesStock = !stockFilter || stockStatus === stockFilter;
                
                if (matchesSearch && matchesCategory && matchesStock) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        }
        
        $searchInput.on('input', filterProducts);
        $categoryFilter.on('change', filterProducts);
        $stockFilter.on('change', filterProducts);

        // Optimize image loading
        optimizeImageLoading();
    });

    // Replace your optimizeImageLoading function with this simpler version
    function optimizeImageLoading() {
        // Simple direct assignment - no delays or complex logic
        document.querySelectorAll('img[data-src]').forEach(function(img) {
            var dataSrc = img.getAttribute('data-src');
            if (dataSrc) {
                img.src = dataSrc;
                img.removeAttribute('data-src');
            }
        });
    }
</script>
    <style>
    /* Image loading state indicators */
    img {
        transition: opacity 0.3s;
    }
    
    img[src='../../assets/images/Company Logo.jpg'] {
        opacity: 0.8;
    }
    
    .img-error {
        border: 1px dashed #eee;
    }
    
    /* Add smooth fade-in effect for images */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    img:not([src='../../assets/images/Company Logo.jpg']) {
        animation: fadeIn 0.5s;
    }
</style>
</head>

<body class="flex h-screen">
    <!-- Add this loading overlay right after your body tag -->
    <div id="loading-overlay" class="fixed inset-0 bg-white bg-opacity-80 z-50 flex items-center justify-center">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primarycol mx-auto"></div>
            <p class="mt-2 text-primarycol">Loading product data...</p>
        </div>
    </div>

    <script>
        // Hide loading overlay when page is fully loaded or after 3 seconds
        window.addEventListener('load', function() {
            document.getElementById('loading-overlay').style.display = 'none';
        });
        
        // Fallback - hide loading overlay after 3 seconds even if page doesn't fully load
        setTimeout(function() {
            var overlay = document.getElementById('loading-overlay');
            if (overlay) overlay.style.display = 'none';
        }, 1000); // Set to 1 second
    </script>

    <?php include ('../layout/staff_nav.php'); ?>

    <div class="p-7 w-full">
        <nav class="mb-4">
            <ol class="flex items-center gap-2 text-gray-600">
                <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="add_stock.php" class="hover:text-primarycol">Add Stock</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="product_stocks.php" class="hover:text-primarycol">Product Stocks</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Excess</a></li>
            </ol>
        </nav>
        
        <h1 class="text-3xl font-bold mb-6 text-primarycol">Add Stock to Existing Product</h1>
        <p class="text-gray-500 mt-2 mb-6">Add a new batch of an existing product to inventory</p>

        <!-- Display errors -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Display success message -->
        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <div class="w-full bg-white shadow-xl p-6 border mb-8">
            <form class="w-full" method="POST" action="add_stock.php">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="product_id">
                            Select Product
                        </label>
                        <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="product_id" name="product_id" required>
                            <option value="">Choose existing product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>">
                                    <?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($product['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stock_quantity">
                            Stock Quantity
                        </label>
                        <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="stock_quantity" name="stock_quantity" min="1" required />
                    </div>
                    
                    <div class="mb-4" id="unit_type_display">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Unit Type
                        </label>
                        <div id="unit_type_text" class="py-3 px-3 bg-gray-50 rounded-lg">
                            Individual pieces
                        </div>
                        <input type="hidden" id="unit_type" name="unit_type" value="piece">
                        <input type="hidden" id="pieces_per_box" name="pieces_per_box" value="1">
                    </div>

                    <div id="box_info" class="mb-4 hidden bg-blue-50 p-3 rounded-lg">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                            <span>This product is tracked in boxes. Each box contains <span id="box_size" class="font-bold">12</span> pieces.</span>
                        </div>
                        <div class="mt-2 text-sm text-blue-700">
                            <span id="total_pieces_calc">You're adding 0 total pieces.</span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2">
                            Batch Number (Auto-Generated)
                        </label>
                        <div class="flex">
                            <div class="flex-grow appearance-none block w-full bg-gray-100 text-gray-700 font-mono border border-gray-400 rounded-l-lg py-3 px-3">
                                <span id="batch_number_display">BB-YYYYMMDD-XXXX</span>
                                <input type="hidden" name="batch_number" id="batch_number" value="">
                            </div>
                            <button id="regenerate-batch" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 rounded-r-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Auto-generated batch number</p>
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="production_date">
                            Production Date
                        </label>
                        <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="production_date" name="production_date" value="<?= date('Y-m-d') ?>" required />
                        <p class="text-xs text-gray-500 mt-1">When was this batch produced</p>
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expiry_preview">
                            Calculated Expiry Date
                        </label>
                        <input type="date" class="appearance-none block w-full bg-gray-100 text-gray-700 font-medium border border-gray-400 rounded-lg py-3 px-3" 
                            id="expiry_preview" readonly />
                        <p class="text-xs text-gray-500 mt-1" id="expiry_message">Automatically calculated based on the product's shelf life</p>
                    </div>

                    <!-- Keep only ONE hidden field for product_shelf_life and expiry_date -->
                    <input type="hidden" id="product_shelf_life" name="product_shelf_life" value="0">
                    <input type="hidden" id="expiry_date" name="expiry_date" value="">
                </div>

                <div id="product_details" class="hidden bg-gray-50 p-4 rounded-lg mb-6 flex gap-4 items-center">
                    <div class="w-24 h-24 bg-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                        <img id="product_image" 
                             src="../../assets/images/Company Logo.jpg" 
                             alt="Product" 
                             class="w-full h-full object-cover"
                             onerror="this.onerror=null; this.src='../../assets/images/Company Logo.jpg'; console.error('Image failed to load');">
                    </div>
                    <div class="hidden mt-1 text-xs text-red-500" id="image-error">
                        Image could not be loaded
                    </div>
                    <div>
                        <h3 class="font-bold text-lg" id="product_name">Product Name</h3>
                        <p class="text-gray-600" id="product_category">Category</p>
                        <p class="font-medium text-primarycol" id="product_price">₱0.00</p>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-6">
                    <a href="product_stocks.php" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</a>
                    <button type="submit" name="add_stock" class="px-6 py-3 bg-primarycol text-white rounded-lg hover:bg-fourth">
                        Add Stock
                    </button>
                </div>
            </form>
        </div>

        <!-- Product Overview Section -->
        <div class="mt-10">
            <h2 class="text-2xl font-bold text-primarycol mb-4">Products in System</h2>
            
            <!-- Search and filter bar -->
            <div class="flex flex-wrap gap-3 mb-4">
                <div class="relative flex-1 min-w-[200px]">
                    <input type="text" id="search-products" placeholder="Search products..." 
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 pl-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                
                <select id="category-filter" class="px-3 py-2 rounded-lg border border-gray-300">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="stock-filter" class="px-3 py-2 rounded-lg border border-gray-300">
                    <option value="">All Stock Levels</option>
                    <option value="low">Low Stock</option>
                    <option value="out">Out of Stock</option>
                    <option value="in">In Stock</option>
                </select>
            </div>
            
            <!-- Product grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($allProducts as $product): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow product-card" data-name="<?= htmlspecialchars($product['name']) ?>" data-category="<?= htmlspecialchars($product['category']) ?>" data-stock-status="<?= $product['total_stock'] <= 0 ? 'out' : ($product['total_stock'] < 10 ? 'low' : 'in') ?>">
                        <div class="h-48 overflow-hidden relative">
                            <?php 
                            // Fixed image path handling
                            $imagePath = '';
                            if (!empty($product['image'])) {
                                // Check if image is a full path or just a filename
                                if (strpos($product['image'], '/') !== false) {
                                    // Path already has structure like 'assets/uploads/products/image.jpg'
                                    // Just ensure it has the ../../ prefix
                                    $imagePath = strpos($product['image'], '../../') === 0 ? 
                                        $product['image'] : 
                                        "../../" + $product['image'];  // FIXED: Use . for concatenation
                                } else {
                                    // Just a filename, append full path
                                    $imagePath = "../../assets/uploads/products/" + $product['image'];  // FIXED
                                }
                                
                                // Check if file exists
                                $imageFilePath = $_SERVER['DOCUMENT_ROOT'] . '/capstone/WASTE-WISE-CAPSTONE/' . 
                                    str_replace('../../', '', $imagePath);  // FIXED
                                
                                if (!file_exists($imageFilePath)) {
                                    // Use Company Logo.jpg (which exists in your assets folder)
                                    $imagePath = "../../assets/images/Company Logo.jpg";
                                }
                            } else {
                                // Use an existing image from your assets folder
                                $imagePath = "../../assets/images/Company Logo.jpg";
                            }
                            ?>
                            <img src="<?= htmlspecialchars($imagePath) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="w-full h-full object-cover rounded"
                                 onerror="this.onerror=null; this.src='../../assets/images/Company Logo.jpg';">
                            <!-- Stock badge -->
                            <div class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-bold
                                <?php if($product['total_stock'] <= 0): ?>
                                    bg-red-100 text-red-800
                                <?php elseif($product['total_stock'] < 10): ?>
                                    bg-amber-100 text-amber-800
                                <?php else: ?>
                                    bg-green-100 text-green-800
                                <?php endif; ?>">
                                <?= $product['total_stock'] <= 0 ? 'Out of Stock' : ($product['total_stock'] < 10 ? 'Low Stock' : 'In Stock') ?>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800 truncate"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($product['category']) ?></p>
                            <div class="flex justify-between items-center mt-3">
                                <p class="font-medium text-primarycol">₱<?= number_format($product['price_per_unit'], 2) ?></p>
                                <span class="text-sm text-gray-600"><?= $product['total_stock'] ?> in stock</span>
                            </div>
                            
                            <div class="mt-4 flex justify-between">
                                <a href="add_stock.php?prefill=<?= $product['id'] ?>" 
                                   class="px-3 py-1.5 bg-primarycol text-white rounded text-sm hover:bg-opacity-90">
                                    Add Stock
                                </a>
                                <a href="product_details.php?id=<?= $product['id'] ?>" 
                                   class="px-3 py-1.5 border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>