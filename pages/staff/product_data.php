<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Change access permissions to staff only
checkAuth(['staff']);

$errors = [];
$successMessage = '';

// Check and create uploads directory if needed
$uploadDir = __DIR__ . "/uploads/products/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "<!-- Created directory: $uploadDir -->";
}

// Handle EDIT product submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_product'])) {
    $productId = $_POST['product_id'];
    $itemName = htmlspecialchars(trim($_POST['edit_itemname']));
    $category = htmlspecialchars(trim($_POST['edit_category']));
    $stockDate = $_POST['edit_stockdate'];
    $pricePerUnit = floatval($_POST['edit_price_per_unit']);
    
    // Handle image update if a new one is provided
    $itemImage = $_POST['current_image']; // Keep existing image by default
    
    if (isset($_FILES['edit_item_image']) && $_FILES['edit_item_image']['error'] === 0) {
        $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
        $filename = time() . '_' . $_FILES['edit_item_image']['name'];
        $filetype = $_FILES['edit_item_image']['type'];
        $filesize = $_FILES['edit_item_image']['size'];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $errors[] = "Error: Please select a valid file format (JPG, JPEG, PNG, WEBP).";
        } elseif ($filesize > 2 * 1024 * 1024) {
            $errors[] = "Error: File size exceeds the 2MB limit.";
        } else {
            $uploadDir = __DIR__ . "/uploads/products/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["edit_item_image"]["tmp_name"], $targetPath)) {
                // Store only the relative path in the database
                $itemImage = './uploads/products/' . $filename;
            } else {
                $errors[] = "Error: Failed to move uploaded file.";
            }
        }
    }

    // If no errors, update database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE products SET 
                    name = :name,
                    category = :category,
                    stock_date = :stock_date,
                    price_per_unit = :price_per_unit,
                    image = :image
                WHERE id = :id AND branch_id = :branch_id
            ");
            
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':stock_date' => $stockDate,
                ':price_per_unit' => $pricePerUnit,
                ':image' => $itemImage,
                ':id' => $productId,
                ':branch_id' => $_SESSION['branch_id'] // Security check to ensure users only edit their branch's products
            ]);
            
            $successMessage = "Product updated successfully.";
        } catch (PDOException $e) {
            $errors[] = "Error updating product: " . $e->getMessage();
        }
    }
}

// Handle DELETE product
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product'])) {
    $productId = $_POST['product_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id AND branch_id = :branch_id");
        $stmt->execute([
            ':id' => $productId,
            ':branch_id' => $_SESSION['branch_id'] // Security check
        ]);
        
        $successMessage = "Product deleted successfully.";
    } catch (PDOException $e) {
        $errors[] = "Error deleting product: " . $e->getMessage();
    }
}

// Handle form submission for adding product
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    // Validate and sanitize inputs
    $itemName = htmlspecialchars(trim($_POST['itemname']));
    $category = htmlspecialchars(trim($_POST['category']));
    $stockDate = $_POST['stockdate'];
    $pricePerUnit = floatval($_POST['price_per_unit']);
    $branchId = $_SESSION['branch_id']; // Get branch_id from session
    
    if (!$branchId) {
        $errors[] = "Error: Branch ID not found in session.";
    }

    // Process image upload
    $itemImage = "../../assets/images/default-product.jpg"; // Default image path
    
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
        $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
        $filename = time() . '_' . $_FILES['item_image']['name']; // Add timestamp to prevent duplicates
        $filetype = $_FILES['item_image']['type'];
        $filesize = $_FILES['item_image']['size'];

        // Verify file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $errors[] = "Error: Please select a valid file format (JPG, JPEG, PNG, WEBP).";
        } elseif ($filesize > 2 * 1024 * 1024) {
            $errors[] = "Error: File size exceeds the 2MB limit.";
        } else {
            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . "/uploads/products/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $targetPath)) {
                // Store only the relative path in the database
                $itemImage = './uploads/products/' . $filename;
            } else {
                $errors[] = "Error: Failed to move uploaded file.";
            }
        }
    }

    // Validate Stock Date
    if (!DateTime::createFromFormat('Y-m-d', $stockDate)) {
        $errors[] = "Error: Invalid stock date format.";
    }

    // If no errors, insert into database using PDO
    if (empty($errors)) {
        try {
            // Modified INSERT query to remove quantity and unit fields
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, category, stock_date, price_per_unit, image, branch_id
                ) VALUES (
                    :name, :category, :stock_date, :price_per_unit, :image, :branch_id
                )
            ");
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':stock_date' => $stockDate,
                ':image' => $itemImage,
                ':price_per_unit' => $pricePerUnit,
                ':branch_id' => $branchId
            ]);
            $successMessage = "Product added successfully.";
        } catch (PDOException $e) {
            $errors[] = "Error: Could not execute the query. " . $e->getMessage();
        }
    }
}

// Retrieve products from database for the current branch
try {
    // Pagination setup
    $itemsPerPage = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $itemsPerPage;
    
    // Count total products for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM products 
        WHERE branch_id = :branch_id
    ");
    $countStmt->execute([':branch_id' => $_SESSION['branch_id']]);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $itemsPerPage);
    
    // Modify the existing query to add LIMIT and OFFSET
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE branch_id = :branch_id
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':branch_id', $_SESSION['branch_id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving products: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Product Data - Bea Bakes</title>
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
      
      // Open edit modal
      $('.openEditModal').on('click', function() {
        const productId = $(this).data('id');
        const productName = $(this).data('name');
        const category = $(this).data('category');
        const stockDate = $(this).data('stockdate');
        const pricePerUnit = $(this).data('price');
        const imagePath = $(this).data('image');
        
        // Populate edit form with current values
        $('#product_id').val(productId);
        $('#edit_itemname').val(productName);
        $('#edit_category').val(category);
        $('#edit_stockdate').val(stockDate);
        $('#edit_price_per_unit').val(pricePerUnit);
        $('#current_image').val(imagePath);
        $('#edit_image_preview').attr('src', imagePath);
        
        // Show the modal
        $('#editModal').removeClass('hidden');
      });
      
      // Close edit modal
      $('#closeEditModal').on('click', function() {
        $('#editModal').addClass('hidden');
      });
      
      // Open delete confirmation
      $('.openDeleteModal').on('click', function() {
        const productId = $(this).data('id');
        const productName = $(this).data('name');
        
        $('#delete_product_id').val(productId);
        $('#delete_product_name').text(productName);
        
        $('#deleteModal').removeClass('hidden');
      });
      
      // Close delete modal
      $('#closeDeleteModal').on('click', function() {
        $('#deleteModal').addClass('hidden');
      });
      
      // Preview edit image
      $('#edit_item_image').on('change', function() {
        const file = this.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            $('#edit_image_preview').attr('src', e.target.result);
          }
          reader.readAsDataURL(file);
        }
      });
      
      // Preview add image
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
    });
  </script>
</head>

<body class="flex h-screen">

<?php include (__DIR__ . '/../layout/staff_nav.php'); ?>

<div class="p-7">
  <div>
    <nav class="mb-4">
      <ol class="flex items-center gap-2 text-gray-600">
        <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Waste</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_product_recWord.php" class="hover:text-primarycol">View Product Waste Records</a></li>
      </ol>
    </nav>
    <h1 class="text-3xl font-bold mb-6 text-primarycol">Product Data</h1>
    <p class="text-gray-500 mt-2">Manage your Products</p>
  </div>

  <!-- Display Success or Error Messages -->
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

  <div class="flex flex-col mx-3 mt-6 lg:flex-row gap-4">
    <!-- Add Product Form -->
    <div class="w-full lg:w-1/3 m-1">
        <form class="w-full bg-white shadow-xl p-6 border" action="product_data.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_product" value="1" />
            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Item Name -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">Product Name</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="itemname" type="text" name="itemname" placeholder="Item Name" required />
                </div>
                <div class="flex w-full">
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="category">Category </label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="category" type="text" name="category" placeholder="Category" required />
                </div>
                <div class="w-full md:w/full px-3 mb-6">
                     <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stockdate">Stock Date</label>
                      <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                       id="stockdate" name="stockdate" required />
                </div>
                </div>
                
                <!-- Price per Unit -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price_per_unit">Price per Unit</label>
                    <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="price_per_unit" name="price_per_unit" placeholder="Price per Unit" required />
                </div>
            </div>
            
            <!-- Add Product Button -->
            <div class="w-full md:w/full px-3 mb-6">
                <button type="submit" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">Add Product</button>
            </div>
            
            <!-- Item Image Upload -->
            <div class="w-full px-3 mb-8">
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
        </form>
    </div>

    <!-- Products Table -->
    <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200">
      <div class="overflow-x-auto p-4">
        <table class="table table-zebra w-full">
          <!-- Table Head -->
          <thead>
            <tr class="bg-sec">
              <th>#</th>
              <th class="flex justify-center">Image</th>
              <th>Item Name</th>
              <th>Category</th>
              <th>Stock Date</th>
              <th>Price per Unit</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (!empty($inventory)) {
                $count = 1;
                foreach ($inventory as $item) {
                    $imagePath = !empty($item['image']) ? $item['image'] : '../../assets/images/default-product.jpg';
                    
                    // Fix image path to ensure browser can access it correctly
                    if (strpos($imagePath, 'C:') === 0) {
                        // For absolute Windows paths, extract just the filename from the path
                        $filename = basename($imagePath);
                        // Point to the correct web-accessible path
                        $imagePath = './uploads/products/' . $filename;
                    } else if (strpos($imagePath, 'uploads/') === 0) {
                        // Path is already relative, but make sure it starts with ./
                        $imagePath = './' . $imagePath;
                    }
                    
                    echo "<tr>";
                    echo "<th>" . $count++ . "</th>";
                    echo "<td class='flex justify-center'>";
                    echo "<img src='" . htmlspecialchars($imagePath) . "' class='h-8 w-8 object-cover rounded' alt='" . htmlspecialchars($item['name']) . "'>";
                    echo "</td>";
                    echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['stock_date']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['price_per_unit']) . "</td>";
                    echo "<td class='p-2'>
                            <div class='flex justify-center space-x-2'>
                                <button 
                                    data-id='" . htmlspecialchars($item['id']) . "' 
                                    data-name='" . htmlspecialchars($item['name']) . "' 
                                    data-category='" . htmlspecialchars($item['category']) . "' 
                                    data-stockdate='" . htmlspecialchars($item['stock_date']) . "' 
                                    data-price='" . htmlspecialchars($item['price_per_unit']) . "' 
                                    data-image='" . htmlspecialchars($imagePath) . "' 
                                    class='openEditModal rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center'>
                                    <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                      <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13' />
                                    </svg>
                                    Edit
                                </button>
                                <button 
                                    data-id='" . htmlspecialchars($item['id']) . "' 
                                    data-name='" . htmlspecialchars($item['name']) . "' 
                                    class='openDeleteModal rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center'>
                                    <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                      <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12' />
                                    </svg>
                                    Delete
                                </button>
                            </div>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7' class='text-center'>No products found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
        <!-- Add this right before closing the </div> that contains the table -->
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
</div>

<!-- Edit Product Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white p-6 rounded-lg w-full max-w-lg">
    <div class="flex justify-between items-center border-b pb-3">
      <h3 class="text-xl font-semibold text-gray-900">Edit Product</h3>
      <button id="closeEditModal" class="text-gray-500 hover:text-gray-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
    
    <form action="product_data.php" method="POST" enctype="multipart/form-data" class="mt-4">
      <input type="hidden" name="edit_product" value="1">
      <input type="hidden" name="product_id" id="product_id">
      <input type="hidden" name="current_image" id="current_image">
      
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_itemname">
          Item Name
        </label>
        <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
          id="edit_itemname" name="edit_itemname" type="text" required>
      </div>
      
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_category">
          Category
        </label>
        <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
          id="edit_category" name="edit_category" type="text" required>
      </div>
      
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_stockdate">
          Stock Date
        </label>
        <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
          id="edit_stockdate" name="edit_stockdate" type="date" required>
      </div>
      
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_price_per_unit">
          Price per Unit
        </label>
        <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
          id="edit_price_per_unit" name="edit_price_per_unit" type="number" step="0.01" required>
      </div>
      
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2">
          Current Image
        </label>
        <img id="edit_image_preview" src="" alt="Product Image" class="h-32 object-contain mb-2">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_item_image">
          Change Image (optional)
        </label>
        <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
          id="edit_item_image" name="edit_item_image" type="file" accept="image/png, image/jpeg, image/webp">
      </div>
      
      <div class="flex items-center justify-end pt-4 border-t">
        <button type="button" id="cancelEdit" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2"
          onclick="document.getElementById('editModal').classList.add('hidden')">
          Cancel
        </button>
        <button type="submit" class="bg-primarycol hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Product Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white p-6 rounded-lg w-full max-w-md">
    <div class="flex justify-between items-center border-b pb-3">
      <h3 class="text-xl font-semibold text-gray-900">Confirm Deletion</h3>
      <button id="closeDeleteModal" class="text-gray-500 hover:text-gray-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
    
    <div class="mt-4">
      <p class="text-gray-700">Are you sure you want to delete <span id="delete_product_name" class="font-semibold"></span>?</p>
      <p class="text-gray-500 text-sm mt-2">This action cannot be undone.</p>
    </div>
    
    <form action="product_data.php" method="POST" class="mt-6">
      <input type="hidden" name="delete_product" value="1">
      <input type="hidden" name="product_id" id="delete_product_id">
      
      <div class="flex items-center justify-end border-t pt-4">
        <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2"
          onclick="document.getElementById('deleteModal').classList.add('hidden')">
          Cancel
        </button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
          Delete
        </button>
      </div>
    </form>
  </div>
</div>

</body>
</html>