<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';


checkAuth(['staff']);

$errors = [];
$successMessage = '';


$uploadDir = __DIR__ . "/uploads/products/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "<!-- Created directory: $uploadDir -->";
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_product'])) {
    $productId = $_POST['product_id'];
    $itemName = htmlspecialchars(trim($_POST['edit_itemname']));
    $category = htmlspecialchars(trim($_POST['edit_category']));
    $stockDate = $_POST['edit_stockdate'];
    $expiryDate = $_POST['edit_expirydate']; 
    $pricePerUnit = floatval($_POST['edit_price_per_unit']);
    $quantityProduced = intval($_POST['edit_quantity_produced']); 
    

    $itemImage = $_POST['current_image'];
    
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

                $itemImage = './uploads/products/' . $filename;
            } else {
                $errors[] = "Error: Failed to move uploaded file.";
            }
        }
    }


    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE products SET 
                    name = :name,
                    category = :category,
                    stock_date = :stock_date,
                    expiry_date = :expiry_date,
                    price_per_unit = :price_per_unit,
                    quantity_produced = :quantity_produced,
                    image = :image
                WHERE id = :id AND branch_id = :branch_id
            ");
            
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':stock_date' => $stockDate,
                ':expiry_date' => $expiryDate,
                ':price_per_unit' => $pricePerUnit,
                ':quantity_produced' => $quantityProduced,
                ':image' => $itemImage,
                ':id' => $productId,
                ':branch_id' => $_SESSION['branch_id'] 
            ]);
            
            $successMessage = "Product updated successfully.";
        } catch (PDOException $e) {
            $errors[] = "Error updating product: " . $e->getMessage();
        }
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_product'])) {
    $productId = $_POST['product_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id AND branch_id = :branch_id");
        $stmt->execute([
            ':id' => $productId,
            ':branch_id' => $_SESSION['branch_id'] 
        ]);
        
        $successMessage = "Product deleted successfully.";
    } catch (PDOException $e) {
        $errors[] = "Error deleting product: " . $e->getMessage();
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {

    $itemName = htmlspecialchars(trim($_POST['itemname']));
    $category = htmlspecialchars(trim($_POST['category']));
    $stockDate = $_POST['stockdate'];
    $expiryDate = $_POST['expirydate'];
    $pricePerUnit = floatval($_POST['price_per_unit']);
    $quantityProduced = intval($_POST['quantity_produced']);
    $branchId = $_SESSION['branch_id']; 
    
    if (!$branchId) {
        $errors[] = "Error: Branch ID not found in session.";
    }


    $itemImage = "../../assets/images/default-product.jpg"; 
    
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

            $uploadDir = __DIR__ . "/uploads/products/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $targetPath)) {
    
                $itemImage = './uploads/products/' . $filename;
            } else {
                $errors[] = "Error: Failed to move uploaded file.";
            }
        }
    }


    if (!DateTime::createFromFormat('Y-m-d', $stockDate)) {
        $errors[] = "Error: Invalid stock date format.";
    }
    

    if (!DateTime::createFromFormat('Y-m-d', $expiryDate)) {
        $errors[] = "Error: Invalid expiry date format.";
    }
    
  
    if (strtotime($expiryDate) <= strtotime($stockDate)) {
        $errors[] = "Error: Expiry date must be after stock date.";
    }


    if (empty($errors)) {
        try {

            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, category, stock_date, expiry_date, price_per_unit, quantity_produced, image, branch_id, status
                ) VALUES (
                    :name, :category, :stock_date, :expiry_date, :price_per_unit, :quantity_produced, :image, :branch_id, 'active'
                )
            ");
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':stock_date' => $stockDate,
                ':expiry_date' => $expiryDate,
                ':price_per_unit' => $pricePerUnit,
                ':quantity_produced' => $quantityProduced,
                ':image' => $itemImage,
                ':branch_id' => $branchId
            ]);
            $successMessage = "Product added successfully.";
        } catch (PDOException $e) {
            $errors[] = "Error: Could not execute the query. " . $e->getMessage();
        }
    }
}


// Add this code before the existing query section (around line 147)
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['active', 'waste_processed']) 
    ? $_GET['status'] 
    : 'all';

try {

    $itemsPerPage = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $itemsPerPage;
    

    // Modify the count query with the filter
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM products 
        WHERE branch_id = :branch_id
        " . ($statusFilter !== 'all' ? " AND status = :status" : "") . "
    ");
    $countStmt->bindValue(':branch_id', $_SESSION['branch_id'], PDO::PARAM_INT);
    if ($statusFilter !== 'all') {
        $countStmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $itemsPerPage);
    

    // Modify the main query with the filter
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE branch_id = :branch_id
        " . ($statusFilter !== 'all' ? " AND status = :status" : "") . "
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':branch_id', $_SESSION['branch_id'], PDO::PARAM_INT);
    if ($statusFilter !== 'all') {
        $stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving products: " . $e->getMessage();
}

// Add this before the HTML part, after the main product query section (around line 145)
// Automatically mark expired products as waste processed
try {
    $today = date('Y-m-d');
    $expiredStmt = $pdo->prepare("
        UPDATE products 
        SET status = 'waste_processed' 
        WHERE expiry_date < :today 
        AND status = 'active' 
        AND branch_id = :branch_id
    ");
    $expiredStmt->execute([
        ':today' => $today,
        ':branch_id' => $_SESSION['branch_id']
    ]);
    
    // If any products were updated, set a message
    $expiredCount = $expiredStmt->rowCount();
    if ($expiredCount > 0) {
        $successMessage = "$expiredCount expired product(s) automatically marked as waste processed.";
    }
} catch (PDOException $e) {
    // Silently fail, this is just an automated process
}

// Add this code to handle expired products
try {
    $today = date('Y-m-d');
    
    // First find expired products
    $expiredStmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE expiry_date < ? 
        AND status = 'active' 
        AND branch_id = ?
    ");
    $expiredStmt->execute([$today, $_SESSION['branch_id']]);
    $expiredProducts = $expiredStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each expired product
    foreach ($expiredProducts as $product) {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Insert waste entry for expired product
            $wasteStmt = $pdo->prepare("
                INSERT INTO product_waste (
                    user_id, product_id, waste_date, waste_quantity,
                    quantity_sold, waste_value, waste_reason,
                    disposal_method, responsible_person, notes,
                    created_at, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $wasteStmt->execute([
                $_SESSION['user_id'],
                $product['id'],
                $today,
                $product['quantity_produced'], // Full quantity as waste
                0, // No sales
                $product['quantity_produced'] * $product['price_per_unit'],
                'expired',
                'waste_processed',
                'System',
                'Automatically processed due to expiration',
                date('Y-m-d H:i:s'),
                $_SESSION['branch_id']
            ]);
            
            // Update product status
            $updateStmt = $pdo->prepare("
                UPDATE products 
                SET status = 'waste_processed' 
                WHERE id = ? AND branch_id = ?
            ");
            $updateStmt->execute([$product['id'], $_SESSION['branch_id']]);
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            // Log error but continue processing other products
            error_log("Error processing expired product {$product['id']}: " . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    // Log error but don't stop execution
    error_log("Error checking for expired products: " . $e->getMessage());
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
      

      $('.openEditModal').on('click', function() {
        const productId = $(this).data('id');
        const productName = $(this).data('name');
        const category = $(this).data('category');
        const stockDate = $(this).data('stockdate');
        const expiryDate = $(this).data('expirydate');
        const pricePerUnit = $(this).data('price');
        const quantityProduced = $(this).data('quantity-produced'); 
        const imagePath = $(this).data('image');
        
        // Populate edit form with current values
        $('#product_id').val(productId);
        $('#edit_itemname').val(productName);
        $('#edit_category').val(category);
        $('#edit_stockdate').val(stockDate);
        $('#edit_expirydate').val(expiryDate);
        $('#edit_price_per_unit').val(pricePerUnit);
        $('#edit_quantity_produced').val(quantityProduced); 
        $('#current_image').val(imagePath);
        $('#edit_image_preview').attr('src', imagePath);
        

        $('#editModal').removeClass('hidden');
      });

      $('#closeEditModal').on('click', function() {
        $('#editModal').addClass('hidden');
      });
      

      $('.openDeleteModal').on('click', function() {
        const productId = $(this).data('id');
        const productName = $(this).data('name');
        
        $('#delete_product_id').val(productId);
        $('#delete_product_name').text(productName);
        
        $('#deleteModal').removeClass('hidden');
      });
      

      $('#closeDeleteModal').on('click', function() {
        $('#deleteModal').addClass('hidden');
      });
      
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
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Add New Product</h2>
    <form class="w-full" action="product_data.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="add_product" value="1" />
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Product Name -->
        <div class="mb-4">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">Product Name</label>
          <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
          id="itemname" type="text" name="itemname" placeholder="Item Name" required />
        </div>

        <!-- Category -->
        <div class="mb-4">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="category">Category</label>
          <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
          id="category" type="text" name="category" placeholder="Category" required />
        </div>

        <!-- Stock Date -->
        <div class="mb-4">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stockdate">Stock Date</label>
          <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
          id="stockdate" name="stockdate" required />
        </div>

        <!-- Expiry Date -->
        <div class="mb-4">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expirydate">Expiry Date</label>
          <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
          id="expirydate" name="expirydate" required />
        </div>

        <!-- Price per Unit -->
        <div class="mb-4">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price_per_unit">Price per Unit</label>
          <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
          id="price_per_unit" name="price_per_unit" placeholder="Price per Unit" required />
        </div>

        <!-- Quantity Produced -->
        <div class="mb-4">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="quantity_produced">Quantity Produced</label>
          <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
          id="quantity_produced" name="quantity_produced" placeholder="Quantity Produced" required />
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

  <!-- Product List Table -->
  <div class="w-full bg-white shadow-xl text-lg rounded-lg border border-gray-200">
    <div class="overflow-x-auto p-6">
      <!-- Filter Section -->
      <div class="flex flex-col sm:flex-row items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-700 mb-4 sm:mb-0">Product Status Filter</h2>
        <div class="flex flex-wrap gap-3">
          <a href="?status=all<?= (isset($_GET['page']) ? '&page=' . $_GET['page'] : '') ?>" 
             class="inline-flex items-center px-4 py-2 rounded-lg transition-all duration-200 <?= ($statusFilter === 'all' ? 'bg-primarycol text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
            All Products
          </a>
          <a href="?status=active<?= (isset($_GET['page']) ? '&page=' . $_GET['page'] : '') ?>" 
             class="inline-flex items-center px-4 py-2 rounded-lg transition-all duration-200 <?= ($statusFilter === 'active' ? 'bg-primarycol text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Active
          </a>
          <a href="?status=waste_processed<?= (isset($_GET['page']) ? '&page=' . $_GET['page'] : '') ?>" 
             class="inline-flex items-center px-4 py-2 rounded-lg transition-all duration-200 <?= ($statusFilter === 'waste_processed' ? 'bg-primarycol text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Waste Processed
          </a>
        </div>
      </div>

      <!-- Status Summary Section -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
          <div class="text-sm text-gray-500">Total Products</div>
          <div class="text-2xl font-bold text-gray-700"><?= $totalProducts ?></div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
          <div class="text-sm text-green-600">Active Products</div>
          <div class="text-2xl font-bold text-green-700">
            <?= array_reduce($inventory, function($count, $item) {
              return $count + ($item['status'] === 'active' ? 1 : 0);
            }, 0) ?>
          </div>
        </div>
        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
          <div class="text-sm text-red-600">Waste Processed</div>
          <div class="text-2xl font-bold text-red-700">
            <?= array_reduce($inventory, function($count, $item) {
              return $count + ($item['status'] === 'waste_processed' ? 1 : 0);
            }, 0) ?>
          </div>
        </div>
      </div>

      <!-- Products Table -->
      <table class="table table-zebra w-full">
        <thead>
          <tr class="bg-sec">
            <th>#</th>
            <th class="flex justify-center">Image</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>Stock Date</th>
            <th>Expiry Date</th>
            <th>Price per Unit</th>
            <th>Quantity Produced</th>
            <th>Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if (!empty($inventory)) {
              $count = 1;
              foreach ($inventory as $item) {
                  $imagePath = !empty($item['image']) ? $item['image'] : '../../assets/images/default-product.jpg';
                  
                  if (strpos($imagePath, 'C:') === 0) {
                      $filename = basename($imagePath);
                      $imagePath = './uploads/products/' . $filename;
                  } else if (strpos($imagePath, 'uploads/') === 0) {
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
                  echo "<td>" . htmlspecialchars($item['expiry_date']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['price_per_unit']) . "</td>";
                  echo "<td>" . htmlspecialchars($item['quantity_produced']) . "</td>";
                  echo "<td>
                          <span class='px-2 py-1 rounded-full text-xs font-semibold " . 
                          ($item['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . 
                          "'>" . ($item['status'] === 'active' ? 'Active' : 'Waste Processed') . "</span>
                        </td>";
                  echo "<td class='p-2'>
                          <div class='flex justify-center space-x-2'>
                              <button 
                                  data-id='" . htmlspecialchars($item['id']) . "' 
                                  data-name='" . htmlspecialchars($item['name']) . "' 
                                  data-category='" . htmlspecialchars($item['category']) . "' 
                                  data-stockdate='" . htmlspecialchars($item['stock_date']) . "' 
                                  data-expirydate='" . htmlspecialchars($item['expiry_date']) . "' 
                                  data-price='" . htmlspecialchars($item['price_per_unit']) . "' 
                                  data-quantity-produced='" . htmlspecialchars($item['quantity_produced']) . "' 
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
              echo "<tr><td colspan='9' class='text-center'>No products found.</td></tr>";
          }
          ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
      <div class="flex justify-center mt-4">
        <div class="join">
          <?php if ($page > 1): ?>
            <a href="?status=<?= $statusFilter ?>&page=<?= ($page - 1) ?>" class="join-item btn bg-sec hover:bg-third">«</a>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?status=<?= $statusFilter ?>&page=<?= $i ?>" class="join-item btn <?= ($i == $page) ? 'bg-primarycol text-white' : 'bg-sec hover:bg-third' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?status=<?= $statusFilter ?>&page=<?= ($page + 1) ?>" class="join-item btn bg-sec hover:bg-third">»</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>


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
      

      <div class="flex gap-4 mb-4">
        <div class="w-1/2">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_expirydate">
            Expiry Date
          </label>
          <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_expirydate" name="edit_expirydate" type="date" required>
        </div>
        
        <div class="w-1/2">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_price_per_unit">
            Price per Unit
          </label>
          <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_price_per_unit" name="edit_price_per_unit" type="number" step="0.01" required>
        </div>
      </div>
      
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_quantity_produced">
          Quantity Produced
        </label>
        <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
          id="edit_quantity_produced" name="edit_quantity_produced" type="number" min="0" required>
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