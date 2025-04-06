<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

date_default_timezone_set('Asia/Manila');

checkAuth(['staff']);

$pdo = getPDO();

$errors = [];
$successMessage = '';

// Get branch ID directly from session like in staff_dashboard.php
$branchId = $_SESSION['branch_id'] ?? 0;

// If branch_id is not in session, try to get it from user data
if ($branchId <= 0) {
    $userId = $_SESSION['user_id'] ?? 0;
    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData && $userData['branch_id'] > 0) {
                $branchId = $userData['branch_id'];
                // Update session
                $_SESSION['branch_id'] = $branchId;
            }
        } catch (PDOException $e) {
            // Log error but continue
            error_log("Error fetching branch_id: " . $e->getMessage());
        }
    }
}

// Check if user has a valid branch assigned
if ($branchId <= 0) {
    $errors[] = "Error: Your account is not assigned to a branch. Please contact an administrator.";
}

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
    
    $pricePerUnit = floatval($_POST['price_per_unit']);
    $unitType = $_POST['unit_type'] ?? 'piece';
    $piecesPerBox = ($unitType === 'box') ? intval($_POST['pieces_per_box']) : null;
    $shelfLifeDays = !empty($_POST['shelf_life_days']) ? intval($_POST['shelf_life_days']) : null;

    // Use branch_id from session
    if ($branchId <= 0) {
        $errors[] = "Error: You are not assigned to a branch. Please contact an administrator.";
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

    // Insert product if no errors
    if (empty($errors)) {
        try {
            // SQL statement for product_info table with shelf_life_days and branch_id
            $stmt = $pdo->prepare("
                INSERT INTO product_info (
                    name, category, unit, price_per_unit, 
                    image, unit_type, pieces_per_box, shelf_life_days, branch_id
                ) VALUES (
                    :name, :category, 'pcs', :pricePerUnit, 
                    :itemImage, :unitType, :piecesPerBox, :shelfLifeDays, :branchId
                )
            ");
            
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':pricePerUnit' => $pricePerUnit,
                ':itemImage' => $itemImage,
                ':unitType' => $unitType,
                ':piecesPerBox' => $piecesPerBox,
                ':shelfLifeDays' => $shelfLifeDays,
                ':branchId' => $branchId
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
            $('#dropzone-file').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#add_image_preview').attr('src', e.target.result);
                        $('#add_image_preview').removeClass('hidden');
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Show/hide custom category input
            $('#category').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom_category_div').removeClass('hidden');
                    $('#custom_category').prop('required', true);
                } else {
                    $('#custom_category_div').addClass('hidden');
                    $('#custom_category').prop('required', false);
                }
            });

            // Show/hide pieces per box input
            $('#unit_type').on('change', function() {
                if ($(this).val() === 'box') {
                    $('#box_details').removeClass('hidden');
                } else {
                    $('#box_details').addClass('hidden');
                }
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
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Excess</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Excess Records</a></li>
                </ol>
            </nav>
         
        </div>
        <div>
            <h1 class="text-3xl font-bold mb-6 text-primarycol">Add New Product</h1>
            <p class="text-gray-500 mt-2">Add a new product to the product catalog</p>
            
            <?php if ($branchId > 0): ?>
                <p class="text-gray-700 mt-2">
                    Creating product for branch: 
                    <span class="font-bold">
                        <?php 
                        // Get branch name for display
                        try {
                            $stmtBranch = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
                            $stmtBranch->execute([$branchId]);
                            $branch = $stmtBranch->fetch(PDO::FETCH_ASSOC);
                            echo htmlspecialchars($branch['name'] ?? 'Unknown Branch');
                        } catch (PDOException $e) {
                            echo 'Unknown Branch';
                        }
                        ?>
                    </span>
                </p>
            <?php endif; ?>
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
                    <!-- Product Name -->
                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">Product Name</label>
                        <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="itemname" type="text" name="itemname" placeholder="Item Name" required />
                    </div>

                    <!-- Category Selection -->
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
                    
                    <!-- Custom Category Input -->
                    <div class="mb-4 hidden" id="custom_category_div">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="custom_category">Custom Category</label>
                        <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="custom_category" type="text" name="custom_category" placeholder="Enter Custom Category" />
                    </div>

                    <!-- Price Per Unit -->
                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price_per_unit">Price per Unit</label>
                        <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="price_per_unit" name="price_per_unit" placeholder="Price per Unit" required />
                    </div>

                    <!-- Unit Type Selection -->
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

                    <!-- Pieces Per Box (only for box unit type) -->
                    <div id="box_details" class="mb-4 hidden">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="pieces_per_box">
                            Pieces Per Box
                        </label>
                        <input type="number" min="1" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="pieces_per_box" name="pieces_per_box" value="12" placeholder="How many pieces in each box?" />
                        <p class="text-xs text-gray-500 mt-1">Example: 12 cookies per box</p>
                    </div>

                    <!-- Shelf Life Days -->
                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="shelf_life_days">
                            Shelf Life (Days)
                        </label>
                        <input type="number" min="1" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                            id="shelf_life_days" name="shelf_life_days" placeholder="Number of days until expiry" />
                        <p class="text-xs text-gray-500 mt-1">Example: 3 days for fresh bread, 14 days for cookies</p>
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