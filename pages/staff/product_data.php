<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

$pdo = getPDO();

$errors = [];
$successMessage = '';

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . "/uploads/products/";
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
    
    $stockDate = $_POST['stockdate'];
    $expiryDate = $_POST['expirydate'];
    $pricePerUnit = floatval($_POST['price_per_unit']);
    $stockQuantity = intval($_POST['stock_quantity']);
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
                // Use a full server path for database storage
                $itemImage = 'uploads/products/' . $filename;  // Remove the leading './'
            } else {
                $errors[] = "Error: Failed to move uploaded file.";
            }
        }
    }

    // Validate dates
    if (!DateTime::createFromFormat('Y-m-d', $stockDate)) {
        $errors[] = "Error: Invalid stock date format.";
    }

    if (!DateTime::createFromFormat('Y-m-d', $expiryDate)) {
        $errors[] = "Error: Invalid expiry date format.";
    }

    if (strtotime($expiryDate) <= strtotime($stockDate)) {
        $errors[] = "Error: Expiry date must be after stock date.";
    }

    // Insert product if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, category, stock_date, expiry_date, price_per_unit, 
                    stock_quantity, image, branch_id
                ) VALUES (
                    :name, :category, :stock_date, :expiry_date, :price_per_unit, 
                    :stock_quantity, :image, :branch_id
                )
            ");
            
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':stock_date' => $stockDate,
                ':expiry_date' => $expiryDate,
                ':price_per_unit' => $pricePerUnit,
                ':stock_quantity' => $stockQuantity,
                ':image' => $itemImage,
                ':branch_id' => $branchId
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
            
            // Handle category selection
            $('#category').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom_category_div').removeClass('hidden');
                    $('#custom_category').prop('required', true);
                } else {
                    $('#custom_category_div').addClass('hidden');
                    $('#custom_category').prop('required', false);
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
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Waste</a></li>
                    <li class="text-gray-400">/</li>
                    
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Waste Records</a></li>
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

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stockdate">Stock Date</label>
                        <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="stockdate" name="stockdate" required />
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expirydate">Expiry Date</label>
                        <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="expirydate" name="expirydate" required />
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price_per_unit">Price per Unit</label>
                        <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="price_per_unit" name="price_per_unit" placeholder="Price per Unit" required />
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stock_quantity">Stock Quantity</label>
                        <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                        id="stock_quantity" name="stock_quantity" placeholder="Available Quantity" required />
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