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
            // Show the auto-generated batch number
            $('#batch_number_display').text(generateBatchNumber());
            
            // Update batch number when button is clicked
            $('#regenerate-batch').on('click', function(e) {
                e.preventDefault();
                $('#batch_number_display').text(generateBatchNumber());
            });
            
            // Get product details when selected
            $('#product_id').on('change', function() {
                const productId = $(this).val();
                if (productId) {
                    $.ajax({
                        url: 'get_product_details.php',
                        type: 'GET',
                        data: { id: productId },
                        dataType: 'json',
                        success: function(data) {
                            $('#product_details').removeClass('hidden');
                            $('#product_name').text(data.name);
                            $('#product_category').text(data.category);
                            $('#product_price').text('₱' + parseFloat(data.price_per_unit).toFixed(2));
                            
                            // Debug code - show shelf life value
                            const shelfLifeDays = data.shelf_life_days || 0;
                            console.log("Product shelf life days:", shelfLifeDays);
                            if (shelfLifeDays <= 0) {
                                console.warn("Warning: This product has no shelf life information (days: " + shelfLifeDays + ")");
                            }
                            
                            // ADD THIS LINE - you're not storing the shelf life value anywhere!
                            $('#product_shelf_life').val(shelfLifeDays);
                            
                            // Calculate expiry date based on production date and shelf life
                            calculateExpiryDateFromShelfLife();
                            
                            // Replace your complex image path handling with this simpler version:

                            if (data.image) {
                                console.log("Image from server:", data.image);
                                
                                // Get just the filename without path
                                const filename = data.image.split('/').pop().split('\\').pop();
                                
                                // Try a simpler approach with just one path
                                const imagePath = `../../uploads/${filename}`;
                                $('#product_image').attr('src', imagePath);
                                
                                // Log what we're doing
                                console.log("Setting image source to:", imagePath);
                            } else {
                                $('#product_image').attr('src', '../../assets/images/default-product.jpg');
                            }
                            
                            // Handle unit type display
                            if (data.unit_type === 'box') {
                                $('#unit_type').val('box');
                                $('#pieces_per_box').val(data.pieces_per_box);
                                $('#unit_type_text').text('Boxes (packages)');
                                $('#box_size').text(data.pieces_per_box);
                                $('#box_info').removeClass('hidden');
                                
                                // Update the label for stock quantity
                                $('label[for="stock_quantity"]').text('Number of Boxes');
                                
                                // Calculate total pieces when box quantity changes
                                $('#stock_quantity').on('input', function() {
                                    const boxes = parseInt($(this).val()) || 0;
                                    const piecesPerBox = parseInt(data.pieces_per_box);
                                    const totalPieces = boxes * piecesPerBox;
                                    $('#total_pieces_calc').text(`You're adding ${totalPieces} total pieces.`);
                                });
                            } else {
                                $('#unit_type').val('piece');
                                $('#unit_type_text').text('Individual pieces');
                                $('#box_info').addClass('hidden');
                                $('label[for="stock_quantity"]').text('Stock Quantity');
                            }

                            // Inside the AJAX success function, add:
                            if (data.debug_image_info) {
                                console.table(data.debug_image_info);
                            }
                        },
                        error: function() {
                            $('#product_details').addClass('hidden');
                            alert('Error fetching product details');
                        }
                    });
                } else {
                    $('#product_details').addClass('hidden');
                }
            });

            // Show/hide custom days input based on selection
            $('#expiry_days').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom_days_container').removeClass('hidden');
                } else {
                    $('#custom_days_container').addClass('hidden');
                    $('#custom_days').val('');
                }
                calculateExpiryDate();
            });

            // Calculate expiry date based on production date and shelf life days
            function calculateExpiryDateFromShelfLife() {
                const productionDate = $('#production_date').val();
                const shelfLifeDays = parseInt($('#product_shelf_life').val());
                
                console.log("CALCULATION TRIGGERED with:", {
                    productionDate: productionDate,
                    shelfLifeDays: shelfLifeDays,
                    isDateValid: !!productionDate,
                    isShelfLifeValid: !isNaN(shelfLifeDays) && shelfLifeDays > 0
                });
                
                if (productionDate && !isNaN(shelfLifeDays) && shelfLifeDays > 0) {
                    // Create a date object from production date
                    const prodDate = new Date(productionDate);
                    console.log("Production date as Date object:", prodDate);
                    
                    // Add shelf life days
                    const expiryDate = new Date(prodDate);
                    expiryDate.setDate(prodDate.getDate() + shelfLifeDays);
                    console.log("Calculated expiry date as Date object:", expiryDate);
                    
                    // Format as YYYY-MM-DD
                    const year = expiryDate.getFullYear();
                    const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
                    const day = String(expiryDate.getDate()).padStart(2, '0');
                    const formattedDate = `${year}-${month}-${day}`;
                    
                    // Update fields
                    $('#expiry_preview').val(formattedDate);
                    $('#expiry_date').val(formattedDate);
                    $('#expiry_message').text(`Based on ${shelfLifeDays} days shelf life`);
                    console.log("Setting expiry date to:", formattedDate);
                    
                } else {
                    $('#expiry_preview').val('');
                    $('#expiry_date').val('');
                    $('#expiry_message').text('Missing shelf life information for this product');
                    console.log("Could not calculate - missing data");
                    
                    if (!productionDate) {
                        console.error("Missing production date");
                    }
                    
                    if (isNaN(shelfLifeDays) || shelfLifeDays <= 0) {
                        console.error("Invalid shelf life:", shelfLifeDays);
                    }
                }
            }
            
            // Production date change handler
            $('#production_date').on('change', function() {
                console.log("Production date changed to:", $(this).val());
                calculateExpiryDateFromShelfLife();
            });

            // Generate and display batch number
            function generateBatchNumber() {
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                const dateString = `${year}${month}${day}`;
                const random = Math.floor(1000 + Math.random() * 9000);
                
                // Match the exact format used in PHP
                return `BB-${dateString}-${random}`;
            }

            // On page load, generate and set batch number
            const batchNumber = generateBatchNumber();
            $('#batch_number_display').text(batchNumber);
            $('#batch_number').val(batchNumber); // Make sure to update the hidden field!

            // Regenerate batch number when button is clicked
            $('#regenerate-batch').on('click', function(e) {
                e.preventDefault();
                const newBatch = generateBatchNumber();
                $('#batch_number_display').text(newBatch);
                $('#batch_number').val(newBatch);
            });

            // Update batch number when button is clicked
            $('#regenerate-batch').on('click', function(e) {
                e.preventDefault();
                const newBatch = generateBatchNumber();
                $('#batch_number_display').text(newBatch);
                $('#batch_number').val(newBatch);  // Make sure to update the hidden field
            });
        });
    </script>
</head>

<body class="flex h-screen">
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
                        <input type="hidden" id="expiry_date" name="expiry_date" />
                        <p class="text-xs text-gray-500 mt-1" id="expiry_message">Automatically calculated based on the product's shelf life</p>
                    </div>

                    <input type="hidden" id="product_shelf_life" name="product_shelf_life" value="0">
                </div>

                <div id="product_details" class="hidden bg-gray-50 p-4 rounded-lg mb-6 flex gap-4 items-center">
                    <div class="w-24 h-24 bg-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                        <img id="product_image" 
                             src="../../assets/images/default-product.jpg" 
                             alt="Product" 
                             class="w-full h-full object-cover"
                             onerror="this.onerror=null; this.src='../../assets/images/default-product.jpg'; console.error('Image failed to load');">
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
    </div>
</body>
</html>