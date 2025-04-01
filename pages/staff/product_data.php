<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

$pdo = getPDO();

$errors = [];
$successMessage = '';

// Create upload directory if it doesn't exist using relative path
$uploadDir = '../../assets/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle Add Product Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $itemName = htmlspecialchars(trim($_POST['itemname']));
    
    // Handle custom category
    if (isset($_POST['category']) && $_POST['category'] === 'custom') {
        if (empty($_POST['custom_category'])) {
            $errors[] = "Error: Custom category cannot be empty.";
            $category = "";
        } else {
            $category = htmlspecialchars(trim($_POST['custom_category']));
        }
    } else {
        $category = htmlspecialchars(trim($_POST['category']));
    }
    
    $expiryDate = $_POST['expirydate'];
    $pricePerUnit = floatval($_POST['price_per_unit']);
    $stockQuantity = intval($_POST['stock_quantity']);
    $batchNumber = isset($_POST['batch_number']) ? trim($_POST['batch_number']) : null;
    $productionDate = isset($_POST['production_date']) ? trim($_POST['production_date']) : date('Y-m-d');
    $branchId = $_SESSION['branch_id'];

    if (!$branchId) {
        $errors[] = "Error: Branch ID not found in session.";
    }

    $itemImage = "../../assets/images/default-product.jpg";

    // Handle image upload
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
        $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
        $filename = time() . '_' . $_FILES['item_image']['name'];
        $filetype = $_FILES['item_image']['type'];
        $filesize = $_FILES['item_image']['size'];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $errors[] = "Error: Please select a valid file format (JPG, JPEG, PNG, WEBP).";
        } elseif ($filesize > 2 * 1024 * 1024) {
            $errors[] = "Error: File size exceeds the 2MB limit.";
        } else {
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $targetPath)) {
                // Store path relative to the site root for better portability
                $itemImage = '../../assets/uploads/products/' . $filename;
            } else {
                $errors[] = "Error: Failed to move uploaded file.";
            }
        }
    }

    // Validate dates
    // Keep this validation to ensure expiry date is after production date
    if (strtotime($expiryDate) <= strtotime($productionDate)) {
        $errors[] = "Error: Expiry date must be after production date.";
    }

    // Get shelf life days
    $shelfLifeDays = null;
    if ($_POST['expiry_days'] === 'custom') {
        $shelfLifeDays = intval($_POST['custom_days']);
    } else {
        $shelfLifeDays = intval($_POST['expiry_days']);
    }

    $unitType = $_POST['unit_type'] ?? 'piece';
    $piecesPerBox = ($unitType === 'box') ? intval($_POST['pieces_per_box']) : 1;

    // Insert product if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, category, expiry_date, unit, price_per_unit, 
                    stock_quantity, image, branch_id, batch_number, production_date,
                    shelf_life_days, unit_type, pieces_per_box
                ) VALUES (
                    :name, :category, :expiryDate, 'pcs', :pricePerUnit, 
                    :stockQuantity, :itemImage, :branchId, :batchNumber, :productionDate,
                    :shelfLifeDays, :unitType, :piecesPerBox
                )
            ");
            
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':expiryDate' => $expiryDate,
                ':pricePerUnit' => $pricePerUnit,
                ':stockQuantity' => $stockQuantity,
                ':itemImage' => $itemImage,
                ':branchId' => $branchId,
                ':batchNumber' => $batchNumber,
                ':productionDate' => $productionDate,
                ':shelfLifeDays' => $shelfLifeDays,
                ':unitType' => $unitType,
                ':piecesPerBox' => $piecesPerBox
            ]);
            
            $successMessage = "Product added successfully.";
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
    <title>Add Product - Bea Bakes</title>
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
            // Auto-generate batch number when page loads
            $('#batch_number').val(generateBatchNumber());
            
            // Re-generate batch number when button is clicked
            $('#regenerate-batch').click(function(e) {
                e.preventDefault();
                $('#batch_number').val(generateBatchNumber());
            });
            
            $('#dropzone-file').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#add_image_preview').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Show/hide custom days input
            $('#expiry_days').change(function() {
                if ($(this).val() === 'custom') {
                    $('#custom_days_container').removeClass('hidden');
                } else {
                    $('#custom_days_container').addClass('hidden');
                }
                calculateExpiryDate();
            });

            // Calculate expiry date
            $('#production_date, #expiry_days, #custom_days').change(function() {
                calculateExpiryDate();
            });

            function calculateExpiryDate() {
                const productionDate = new Date($('#production_date').val());
                let daysUntilExpiry = parseInt($('#expiry_days').val());

                if ($('#expiry_days').val() === 'custom') {
                    daysUntilExpiry = parseInt($('#custom_days').val());
                }

                if (!isNaN(productionDate.getTime()) && !isNaN(daysUntilExpiry)) {
                    const expiryDate = new Date(productionDate);
                    expiryDate.setDate(expiryDate.getDate() + daysUntilExpiry);
                    $('#expiry_preview').val(expiryDate.toISOString().split('T')[0]);
                    $('#expirydate').val(expiryDate.toISOString().split('T')[0]);
                }
            }

            // Calculate expiry date based on production date and shelf life days
            function calculateExpiryDate() {
                const productionDate = $('#production_date').val();
                let days = $('#expiry_days').val();
                
                // Handle custom days
                if (days === 'custom') {
                    days = $('#custom_days').val();
                }
                
                if (productionDate && days && days !== 'custom') {
                    // Create a new date from production date
                    const expiryDate = new Date(productionDate);
                    // Add the specified days
                    expiryDate.setDate(expiryDate.getDate() + parseInt(days));
                    
                    // Format the date for the input field (YYYY-MM-DD)
                    const year = expiryDate.getFullYear();
                    const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
                    const day = String(expiryDate.getDate()).padStart(2, '0');
                    const formattedDate = `${year}-${month}-${day}`;
                    
                    // Update the fields
                    $('#expiry_preview').val(formattedDate);
                    $('#expirydate').val(formattedDate);
                } else {
                    $('#expiry_preview').val('');
                    $('#expirydate').val('');
                }
            }

            // Attach event listeners
            $('#production_date, #expiry_days, #custom_days').on('change', calculateExpiryDate);

            // Show/hide custom days input
            $('#expiry_days').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom_days_container').removeClass('hidden');
                } else {
                    $('#custom_days_container').addClass('hidden');
                }
                calculateExpiryDate();
            });

            // Add this to your existing $(document).ready function

            $('#unit_type').on('change', function() {
                if ($(this).val() === 'box') {
                    $('#box_details').removeClass('hidden');
                    $('#stock_quantity_label').text('Number of Boxes');
                } else {
                    $('#box_details').addClass('hidden');
                    $('#stock_quantity_label').text('Stock Quantity');
                }
            });
        });
        
        function generateBatchNumber() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const random = Math.floor(1000 + Math.random() * 9000);
            
            return `BB-${year}${month}${day}-${random}`;
        }
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
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Excess</a></li>
                    <li class="text-gray-400">/</li>
                    
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Excess Records</a></li>
                </ol>
            </nav>
         
        </div>
        <div>
            <h1 class="text-3xl font-bold mb-6 text-primarycol">Add New Product</h1>
            <p class="text-gray-500 mt-2">Add a new product to stocks</p>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4" role="alert">
                <?php foreach ($errors as $error): ?>
                    <span class="block sm:inline"><?= htmlspecialchars($error) ?></span><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <div class="w-full bg-white shadow-xl p-6 border mb-8 mt-4">
            <form class="w-full" action="product_data.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_product" value="1" />
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Form fields remain the same -->
                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">Product Name</label>
                        <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="itemname" type="text" name="itemname" placeholder="Item Name" required />
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="category">Category</label>
                        <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="category" name="category" required>
                            <option value="" disabled selected>Select Category</option>
                            <option value="Cookies">Cookies</option>
                            <option value="Donuts">Donuts</option>
                            <option value="Cakes">Cakes</option>
                            <option value="Brownies">Brownies</option>
                            <option value="Cheesecakes">Cheesecakes</option>
                            <option value="Muffins">Muffins</option>
                            <option value="Cupcakes">Cupcakes</option>
                            <option value="custom">Custom...</option>
                        </select>
                    </div>
                    
                    <div class="mb-4 hidden" id="custom_category_div">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="custom_category">Custom Category</label>
                        <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="custom_category" type="text" name="custom_category" placeholder="Enter Custom Category" />
                    </div>

                    <div class="mb-4 col-span-1">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expiry_days">
                            Days Until Expiry
                        </label>
                        <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="expiry_days" name="expiry_days" required>
                            <option value="">Select shelf life</option>
                            <option value="1">1 day</option>
                            <option value="2">2 days</option>
                            <option value="3">3 days</option>
                            <option value="5">5 days</option>
                            <option value="7">1 week</option>
                            <option value="14">2 weeks</option>
                            <option value="30">1 month</option>
                            <option value="60">2 months</option>
                            <option value="90">3 months</option>
                            <option value="180">6 months</option>
                            <option value="365">1 year</option>
                            <option value="custom">Custom...</option>
                        </select>
                    </div>

                    <div id="custom_days_container" class="mb-4 col-span-1 hidden">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="custom_days">
                            Custom Days
                        </label>
                        <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="custom_days" name="custom_days" min="1" placeholder="Enter number of days" />
                    </div>

                    <div class="mb-4 col-span-1">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expiry_preview">
                            Calculated Expiry Date
                        </label>
                        <input type="date" class="appearance-none block w-full bg-gray-100 text-gray-700 font-medium border border-gray-400 rounded-lg py-3 px-3" 
                            id="expiry_preview" readonly />
                        <input type="hidden" id="expirydate" name="expirydate" />
                        <p class="text-xs text-gray-500 mt-1">This date is calculated based on production date + shelf life</p>
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price_per_unit">Price per Unit</label>
                        <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="price_per_unit" name="price_per_unit" placeholder="Price per Unit" required />
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stock_quantity" id="stock_quantity_label">
                            Stock Quantity
                        </label>
                        <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="stock_quantity" name="stock_quantity" placeholder="Available Quantity" required />
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unit_type">
                            Unit Type
                        </label>
                        <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="unit_type" name="unit_type">
                            <option value="piece">Individual Pieces</option>
                            <option value="box">Box/Package</option>
                        </select>
                    </div>

                    <div id="box_details" class="mb-4 hidden">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="pieces_per_box">
                            Pieces Per Box
                        </label>
                        <input type="number" min="1" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="pieces_per_box" name="pieces_per_box" value="12" placeholder="How many pieces in each box?" />
                        <p class="text-xs text-gray-500 mt-1">Example: 12 cookies per box</p>
                    </div>

                    <div class="mb-4 relative">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="batch_number">
                            Batch Number
                        </label>
                        <div class="flex">
                            <input type="text" 
                                class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                                id="batch_number" 
                                name="batch_number" 
                                placeholder="Auto-generated batch identifier" 
                                readonly />
                            <button id="regenerate-batch" class="ml-2 bg-sec hover:bg-third text-primarycol font-medium py-2 px-3 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Automatically generated for batch tracking</p>
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="production_date">Production Date</label>
                        <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="production_date" name="production_date" value="<?= date('Y-m-d') ?>" />
                        <p class="text-xs text-gray-500 mt-1">When was this batch produced</p>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="mt-4 mb-6">
                    <label class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center" for="dropzone-file">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Item Image</h2>
                        <p class="mt-2 text-gray-500 tracking-wide">Upload or drag & drop your file PNG, JPG, or WEBP.</p>
                        <img id="add_image_preview" class="mt-4 max-h-40 hidden" alt="Image preview" />
                        <input id="dropzone-file" type="file" class="hidden" name="item_image" accept="image/png, image/jpeg, image/webp"/>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="bg-primarycol text-white font-bold py-2 px-6 rounded hover:bg-green-600 transition-colors">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>