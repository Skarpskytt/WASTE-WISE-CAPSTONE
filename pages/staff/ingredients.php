<?php
// filepath: /c:/xampp/htdocs/capstone/WASTE-WISE-CAPSTONE/pages/staff/ingredients.php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Define unit categories and their options
$unitCategories = [
    'weight' => ['g', 'kg', 'lb', 'oz'],
    'volume' => ['ml', 'l', 'cup', 'tbsp', 'tsp', 'gal', 'qt'],
    'count' => ['pcs', 'dozen', 'case', 'bag'],
    'custom' => ['custom']
];

// Ensure upload directories exist
$uploadPath = __DIR__ . "/uploads/ingredients";
if (!is_dir($uploadPath)) {
    // Create the directory with full permissions (adjust as needed for security)
    mkdir($uploadPath, 0777, true);
}

// Check for staff access
checkAuth(['staff']);

$errors = [];
$successMessage = '';

// Handle EDIT ingredient submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_ingredient'])) {
    $ingredientId = $_POST['ingredient_id'];
    $ingredientName = htmlspecialchars(trim($_POST['edit_itemname']));
    $category = htmlspecialchars(trim($_POST['edit_category']));
    $supplierName = htmlspecialchars(trim($_POST['edit_supplier']));
    $stockDate = $_POST['edit_stockdate'];
    $expiryDate = !empty($_POST['edit_expirydate']) ? $_POST['edit_expirydate'] : null;
    
    // New fields
    $unitCategory = $_POST['edit_unit_category'] ?? '';
    $unit = $_POST['edit_unit'] ?? '';
    $costPerUnit = floatval($_POST['edit_cost_per_unit']) ?? 0;
    $density = $_POST['edit_density'] ? floatval($_POST['edit_density']) : null;
    
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
            $uploadDir = __DIR__ . "/uploads/ingredients/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["edit_item_image"]["tmp_name"], $targetPath)) {
                // Store only the relative path in the database for consistent access
                $itemImage = 'uploads/ingredients/' . $filename;
            } else {
                $errors[] = "Error: Failed to move uploaded file.";
            }
        }
    }

    // If no errors, update database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE ingredients SET 
                    ingredient_name = :name,
                    category = :category,
                    supplier_name = :supplier,
                    stock_datetime = :stock_date,
                    expiry_date = :expiry_date,
                    unit_category = :unit_category,
                    unit = :unit,
                    cost_per_unit = :cost_per_unit,
                    density = :density,
                    item_image = :image
                WHERE id = :id AND branch_id = :branch_id
            ");
            
            $stmt->execute([
                ':name' => $ingredientName,
                ':category' => $category,
                ':supplier' => $supplierName,
                ':stock_date' => $stockDate,
                ':expiry_date' => $expiryDate,
                ':unit_category' => $unitCategory,
                ':unit' => $unit,
                ':cost_per_unit' => $costPerUnit,
                ':density' => $density,
                ':image' => $itemImage,
                ':id' => $ingredientId,
                ':branch_id' => $_SESSION['branch_id'] // Security check to ensure users only edit their branch's ingredients
            ]);
            
            $successMessage = "Ingredient updated successfully.";
        } catch (PDOException $e) {
            $errors[] = "Error updating ingredient: " . $e->getMessage();
        }
    }
}

// Handle DELETE ingredient
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_ingredient'])) {
    $ingredientId = $_POST['ingredient_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM ingredients WHERE id = :id AND branch_id = :branch_id");
        $stmt->execute([
            ':id' => $ingredientId,
            ':branch_id' => $_SESSION['branch_id'] // Security check
        ]);
        
        $successMessage = "Ingredient deleted successfully.";
    } catch (PDOException $e) {
        $errors[] = "Error deleting ingredient: " . $e->getMessage();
    }
}

// Modify the add ingredient handler to include expiry date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ingredient'])) {
    // Map form fields to variables
    $ingredient_name = $_POST['itemname'] ?? '';
    $category = $_POST['category'] ?? '';
    $supplier_name = $_POST['supplier'] ?? null; // Optional
    $stock_datetime = $_POST['stockdate'] ?? date('Y-m-d H:i:s');
    $expiry_date = !empty($_POST['expirydate']) ? $_POST['expirydate'] : null;
    $branchId = $_SESSION['branch_id'];
    
    // New fields
    $unit_category = $_POST['unit_category'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $cost_per_unit = $_POST['cost_per_unit'] ?? 0;
    $density = $_POST['density'] ?? null; // Optional, can be null
    
    // Process image upload if exists
    $item_image = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        if (in_array($_FILES['item_image']['type'], $allowed)) {
            $uploadDir = __DIR__ . "/uploads/ingredients/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_ext;
            $target_file = $uploadDir . $file_name;
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file)) {
                // Save relative file path
                $item_image = "uploads/ingredients/" . $file_name;
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid file type. Only PNG, JPG, and WEBP allowed.";
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO ingredients 
                (ingredient_name, category, supplier_name, stock_datetime, expiry_date,
                 unit_category, unit, cost_per_unit, density, item_image, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ingredient_name,
            $category,
            $supplier_name,
            $stock_datetime,
            $expiry_date,
            $unit_category,
            $unit,
            $cost_per_unit,
            $density,
            $item_image,
            $branchId
        ]);
        $successMessage = "Ingredient added successfully!";
    } catch (PDOException $e) {
        $errors[] = "Error adding ingredient: " . $e->getMessage();
    }
}

// Pagination setup
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Count total ingredients for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ingredients WHERE branch_id = ?");
$countStmt->execute([$_SESSION['branch_id']]);
$totalIngredients = $countStmt->fetchColumn();
$totalPages = ceil($totalIngredients / $itemsPerPage);

// Fetch ingredients with pagination
$stmt = $pdo->prepare("SELECT * FROM ingredients WHERE branch_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $_SESSION['branch_id'], PDO::PARAM_INT);
$stmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingredients Data - WasteWise</title>
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
        const ingredientId = $(this).data('id');
        const ingredientName = $(this).data('name');
        const category = $(this).data('category');
        const supplier = $(this).data('supplier');
        const stockDate = $(this).data('stockdate');
        const pricePerUnit = $(this).data('price');
        const imagePath = $(this).data('image');
        
        // Populate edit form with current values
        $('#ingredient_id').val(ingredientId);
        $('#edit_itemname').val(ingredientName);
        $('#edit_category').val(category);
        $('#edit_supplier').val(supplier);
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
        const ingredientId = $(this).data('id');
        const ingredientName = $(this).data('name');
        
        $('#delete_ingredient_id').val(ingredientId);
        $('#delete_ingredient_name').text(ingredientName);
        
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

    // Add this JavaScript inside your existing <script> tag
    function updateUnitOptions() {
      const selectedCategory = document.getElementById('unit_category').value;
      const unitSelect = document.getElementById('unit');
      const densityContainer = document.getElementById('density_container');
      
      // Hide all options first
      Array.from(unitSelect.options).forEach(option => {
        option.style.display = 'none';
      });
      
      // Show only relevant options
      Array.from(unitSelect.options).forEach(option => {
        if (option.dataset.category === selectedCategory || option.value === '') {
          option.style.display = 'block';
        }
      });
      
      // Reset selection
      unitSelect.value = '';
      
      // Show density field only for volume ingredients
      if (selectedCategory === 'volume') {
        densityContainer.style.display = 'block';
      } else {
        densityContainer.style.display = 'none';
      }
    }

    function updateEditUnitOptions() {
      const selectedCategory = document.getElementById('edit_unit_category').value;
      const unitSelect = document.getElementById('edit_unit');
      const densityContainer = document.getElementById('edit_density_container');
      
      // Hide all options first
      Array.from(unitSelect.options).forEach(option => {
        option.style.display = 'none';
      });
      
      // Show only relevant options
      Array.from(unitSelect.options).forEach(option => {
        if (option.dataset.category === selectedCategory || option.value === '') {
          option.style.display = 'block';
        }
      });
      
      // Reset selection
      unitSelect.value = '';
      
      // Show density field only for volume ingredients
      if (selectedCategory === 'volume') {
        densityContainer.style.display = 'block';
      } else {
        densityContainer.style.display = 'none';
      }
    }

    // Add data loading for edit modal
    $(document).ready(function() {
      $('.openEditModal').on('click', function() {
        // Existing code...
        const unitCategory = $(this).data('unit-category');
        const unit = $(this).data('unit');
        const costPerUnit = $(this).data('cost-per-unit');
        const density = $(this).data('density');
        
        $('#edit_unit_category').val(unitCategory);
        
        // First update unit options based on category
        const editUnitSelect = document.getElementById('edit_unit');
        const editDensityContainer = document.getElementById('edit_density_container');
        
        // Hide all options first
        Array.from(editUnitSelect.options).forEach(option => {
          option.style.display = 'none';
        });
        
        // Show only relevant options
        Array.from(editUnitSelect.options).forEach(option => {
          if (option.dataset.category === unitCategory || option.value === '') {
            option.style.display = 'block';
          }
        });
        
        // Then set the selected unit
        $('#edit_unit').val(unit);
        $('#edit_cost_per_unit').val(costPerUnit);
        $('#edit_density').val(density);
        
        // Show density field if needed
        if (unitCategory === 'volume') {
          $('#edit_density_container').show();
        } else {
          $('#edit_density_container').hide();
        }
      });
    });

    // Modify the image preview logic in your jQuery script (around line 302-303):

    // Preview edit image
    $('.openEditModal').on('click', function() {
      // Existing data attributes...
      const imagePath = $(this).data('image');
      
      $('#ingredient_id').val(ingredientId);
      $('#edit_itemname').val(ingredientName);
      $('#edit_category').val(category);
      $('#edit_supplier').val(supplier);
      $('#edit_stockdate').val(stockDate);
      $('#edit_cost_per_unit').val(costPerUnit);
      $('#current_image').val(imagePath);
      
      // Fix image path for display
      if (imagePath.includes('default-ingredient.jpg')) {
        $('#edit_image_preview').attr('src', imagePath);
      } else {
        $('#edit_image_preview').attr('src', './' + imagePath);
      }
      
      // Rest of your code...
    });

    // Add this to your existing JavaScript
    $('.openEditModal').on('click', function() {
      const ingredientId = $(this).data('id');
      const ingredientName = $(this).data('name');
      const category = $(this).data('category');
      const supplier = $(this).data('supplier');
      const stockDate = $(this).data('stockdate');
      const expiryDate = $(this).data('expirydate'); // Add this line
      const unitCategory = $(this).data('unit-category');
      const unit = $(this).data('unit');
      const costPerUnit = $(this).data('cost-per-unit');
      const density = $(this).data('density');
      const imagePath = $(this).data('image');
      
      // Populate edit form with current values
      $('#ingredient_id').val(ingredientId);
      $('#edit_itemname').val(ingredientName);
      $('#edit_category').val(category);
      $('#edit_supplier').val(supplier);
      $('#edit_stockdate').val(stockDate);
      $('#edit_expirydate').val(expiryDate); // Add this line
      $('#edit_unit_category').val(unitCategory);
      // ... rest of your code
    });
  </script>
</head>

<body class="flex h-screen">

<?php include (__DIR__ . '/../layout/staff_nav.php'); ?>

<div class="p-7">
  <div>
  <nav class="mb-4">
      <ol class="flex items-center gap-2 text-gray-600">
        <li><a href="ingredients.php" class="hover:text-primarycol">Ingredients</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_input.php" class="hover:text-primarycol">Record Waste</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_record.php" class="hover:text-primarycol">View Ingredients Waste Records</a></li>
      </ol>
    </nav>
    <h1 class="text-3xl font-bold mb-6 text-primarycol">Ingredients Data</h1>
    <p class="text-gray-500 mt-2">Manage your ingredients</p>
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
    <!-- Add Ingredient Form -->
    <div class="w-full lg:w-1/3 m-1">
      <form class="w-full bg-white shadow-xl p-6 border" action="ingredients.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="add_ingredient" value="1" />
      <div class="flex flex-wrap -mx-3 mb-6">
        <!-- Ingredient Name -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">
          Ingredient Name
        </label>
        <input
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="itemname"
          type="text"
          name="itemname"
          placeholder="Item Name"
          required
        />
        </div>

        <!-- Category -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="category">
          Category
        </label>
        <input
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="category"
          type="text"
          name="category"
          placeholder="Category (e.g., Dairy, Dry Goods)"
          required
        />
        </div>

        <!-- Supplier Name - Optional -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="supplier">
          Supplier Name (Optional)
        </label>
        <input
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="supplier"
          type="text"
          name="supplier"
          placeholder="Supplier Name"
        />
        </div>

        <!-- Stock Date -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stockdate">
          Stock Date
        </label>
        <input
          type="date"
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="stockdate"
          name="stockdate"
          required
        />
        </div>

        <!-- Expiry Date -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expirydate">
          Expiry Date
        </label>
        <input
          type="date"
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="expirydate"
          name="expirydate"
        />
        </div>

        <!-- Unit Category -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unit_category">
          Unit Category
        </label>
        <select
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="unit_category"
          name="unit_category"
          required
          onchange="updateUnitOptions()"
        >
          <option value="">Select Unit Category</option>
          <option value="weight">Weight (g, kg, etc.)</option>
          <option value="volume">Volume (ml, l, etc.)</option>
          <option value="count">Count (pcs, dozen, etc.)</option>
          <option value="custom">Custom</option>
        </select>
        </div>

        <!-- Unit of Measure -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unit">
          Unit of Measure
        </label>
        <select
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="unit"
          name="unit"
          required
        >
          <option value="">Select Unit</option>
          <?php foreach ($unitCategories as $category => $units): ?>
          <?php foreach ($units as $unit): ?>
            <option value="<?= $unit ?>" data-category="<?= $category ?>" style="display:none;">
            <?= $unit ?>
            </option>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </select>
        </div>

        <!-- Cost per Unit -->
        <div class="w-full md:w-1/2 px-3 mb-6">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="cost_per_unit">
          Cost per Unit
        </label>
        <input
          type="number"
          step="0.01"
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="cost_per_unit"
          name="cost_per_unit"
          placeholder="Cost per unit"
          required
        />
        </div>

        <!-- Density (conditionally shown) -->
        <div class="w-full md:w-1/2 px-3 mb-6" id="density_container" style="display:none;">
        <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="density">
          Density (g/ml)
        </label>
        <input
          type="number"
          step="0.001"
          class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
          id="density"
          name="density"
          placeholder="For volume ingredients (e.g., water = 1.0)"
        />
        <p class="text-xs text-gray-500 mt-1">e.g.: Water = 1.0, Flour ≈ 0.6, Sugar ≈ 0.85</p>
        </div>
      </div>

      <!-- Add Ingredient Button -->
      <div class="w-full md:w-full px-3 mb-6">
        <button
        type="submit"
        class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors"
        >
        Add Ingredient
        </button>
      </div>

      <!-- Item Image Upload -->
      <div class="w-full px-3 mb-8">
        <label
        class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center"
        for="dropzone-file"
        >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-10 w-10 text-green-800"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
          />
        </svg>
        <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Item Image</h2>
        <p class="mt-2 text-gray-500 tracking-wide">Upload or drag & drop your file PNG, JPG, or WEBP.</p>
        <img id="add_image_preview" class="mt-4 max-h-40 hidden" alt="Image preview" />
        <input
          id="dropzone-file"
          type="file"
          class="hidden"
          name="item_image"
          accept="image/png, image/jpeg, image/webp"
        />
        </label>
      </div>
      </form>
    </div>

    <!-- Updated Ingredients Table with removed Price/Unit and Waste Status columns -->
    <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200">
      <div class="overflow-x-auto p-4">
        <table class="table table-zebra w-full">
          <!-- Table Head -->
          <thead>
            <tr class="bg-sec">
              <th>#</th>
              <th class="flex justify-center">Image</th>
              <th>Ingredient Name</th>
              <th>Category</th>
              <th>Supplier</th>
              <th>Stock Date</th>
              <th>Expiry Date</th>
              <th>Unit</th>
              <th>Cost/Unit</th>
              <th>Density</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ingredients as $index => $ingredient): 
  // Properly format image path for display
  $imgPath = '';
  if (!empty($ingredient['item_image'])) {
    if (strpos($ingredient['item_image'], 'C:') === 0) {
      // Handle absolute Windows paths
      $filename = basename($ingredient['item_image']);
      $imgPath = 'uploads/ingredients/' . $filename;
    } else if (strpos($ingredient['item_image'], 'uploads/') === 0) {
      // Path already relative, use as-is
      $imgPath = $ingredient['item_image'];
    } else {
      // For any other format, try to use the base filename
      $filename = basename($ingredient['item_image']);
      $imgPath = 'uploads/ingredients/' . $filename;
    }
  } else {
    // Default image
    $imgPath = '../../assets/images/default-ingredient.jpg';
  }
  
  $stockDate = date('Y-m-d', strtotime($ingredient['stock_datetime']));
  
  // Format unit display
  $unitDisplay = '';
  // ...rest of your code
                $stockDate = date('Y-m-d', strtotime($ingredient['stock_datetime']));
                
                // Format unit display
                $unitDisplay = '';
                if (!empty($ingredient['unit'])) {
                    $unitDisplay = htmlspecialchars($ingredient['unit']);
                    if (!empty($ingredient['unit_category'])) {
                        $unitDisplay = '<span class="font-semibold text-xs text-gray-500">' . 
                            htmlspecialchars(ucfirst($ingredient['unit_category'])) . '</span>: ' . $unitDisplay;
                    }
                } else {
                    $unitDisplay = 'N/A';
                }
            ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td class="flex justify-center">
                  <img src="<?= htmlspecialchars($imgPath) ?>" alt="Ingredient Image" class="h-8 w-8 object-cover rounded" />
                </td>
                <td><?= htmlspecialchars($ingredient['ingredient_name']) ?></td>
                <td><?= htmlspecialchars($ingredient['category'] ?? 'N/A') ?></td>
                <td><?= !empty($ingredient['supplier_name']) ? htmlspecialchars($ingredient['supplier_name']) : 'N/A' ?></td>
                <td><?= htmlspecialchars($stockDate) ?></td>
                <td><?= !empty($ingredient['expiry_date']) ? htmlspecialchars(date('Y-m-d', strtotime($ingredient['expiry_date']))) : 'N/A' ?></td>
                <td><?= $unitDisplay ?></td>
                <td>₱<?= number_format($ingredient['cost_per_unit'] ?? 0, 2) ?></td>
                <td><?= !empty($ingredient['density']) ? number_format($ingredient['density'], 3) . ' g/ml' : 'N/A' ?></td>
                <td class="p-2">
                  <div class="flex justify-center space-x-2">
                    <button 
                      data-id="<?= htmlspecialchars($ingredient['id']) ?>" 
                      data-name="<?= htmlspecialchars($ingredient['ingredient_name']) ?>" 
                      data-category="<?= htmlspecialchars($ingredient['category'] ?? '') ?>" 
                      data-supplier="<?= htmlspecialchars($ingredient['supplier_name'] ?? '') ?>" 
                      data-stockdate="<?= htmlspecialchars($stockDate) ?>" 
                      data-expirydate="<?= !empty($ingredient['expiry_date']) ? htmlspecialchars(date('Y-m-d', strtotime($ingredient['expiry_date']))) : '' ?>"
                      data-unit-category="<?= htmlspecialchars($ingredient['unit_category'] ?? '') ?>"
                      data-unit="<?= htmlspecialchars($ingredient['unit'] ?? '') ?>"
                      data-cost-per-unit="<?= htmlspecialchars($ingredient['cost_per_unit'] ?? 0) ?>"
                      data-density="<?= htmlspecialchars($ingredient['density'] ?? '') ?>"
                      data-image="<?= htmlspecialchars($imgPath) ?>"
                      class="openEditModal rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13" />
                      </svg>
                      Edit
                    </button>
                    <button 
                      data-id="<?= htmlspecialchars($ingredient['id']) ?>" 
                      data-name="<?= htmlspecialchars($ingredient['ingredient_name']) ?>" 
                      class="openDeleteModal rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($ingredients)): ?>
              <tr>
                <td colspan="10" class="text-center">No ingredients found.</td>
              </tr>
            <?php endif; ?>
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

<!-- Edit Ingredient Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white p-6 rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto">
    <div class="flex justify-between items-center border-b pb-3">
      <h3 class="text-xl font-semibold text-gray-900">Edit Ingredient</h3>
      <button id="closeEditModal" class="text-gray-500 hover:text-gray-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
    
    <form action="ingredients.php" method="POST" enctype="multipart/form-data" class="mt-4">
      <input type="hidden" name="edit_ingredient" value="1">
      <input type="hidden" name="ingredient_id" id="ingredient_id">
      <input type="hidden" name="current_image" id="current_image">
      
      <div class="grid grid-cols-2 gap-4">
        <!-- Name and Category in same row -->
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_itemname">
            Ingredient Name
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
        
        <!-- Supplier and Stock Date in same row -->
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_supplier">
            Supplier (Optional)
          </label>
          <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_supplier" name="edit_supplier" type="text">
        </div>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_stockdate">
            Stock Date
          </label>
          <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_stockdate" name="edit_stockdate" type="date" required>
        </div>

        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_expirydate">
            Expiry Date
          </label>
          <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_expirydate" name="edit_expirydate" type="date">
        </div>
        
        <!-- Unit Category and Unit in same row -->
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_unit_category">
            Unit Category
          </label>
          <select class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_unit_category" name="edit_unit_category" required>
            <?php foreach ($unitCategories as $category => $units): ?>
              <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars(ucfirst($category)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_unit">
            Unit
          </label>
          <select class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_unit" name="edit_unit" required>
            <option value="">Select Unit</option>
            <?php foreach ($unitCategories as $category => $units): ?>
              <?php foreach ($units as $unit): ?>
                <option value="<?= $unit ?>" data-category="<?= $category ?>" style="display:none;">
                  <?= $unit ?>
                </option>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </select>
        </div>
        
        <!-- Cost and Density in same row -->
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_cost_per_unit">
            Cost per Unit
          </label>
          <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_cost_per_unit" name="edit_cost_per_unit" type="number" step="0.01" required>
        </div>
        
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_density">
            Density (Optional)
          </label>
          <input class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:border-primarycol"
            id="edit_density" name="edit_density" type="number" step="0.01">
        </div>
      </div>
      
      <!-- Image section spans full width -->
      <div class="mb-4 col-span-2">
        <label class="block text-gray-700 text-sm font-bold mb-2">
          Current Image
        </label>
        <img id="edit_image_preview" src="" alt="Ingredient Image" class="h-48 w-96 object-cover mb-2">
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

<!-- Delete Ingredient Modal -->
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
      <p class="text-gray-700">Are you sure you want to delete <span id="delete_ingredient_name" class="font-semibold"></span>?</p>
      <p class="text-gray-500 text-sm mt-2">This action cannot be undone.</p>
    </div>
    
    <form action="ingredients.php" method="POST" class="mt-6">
      <input type="hidden" name="delete_ingredient" value="1">
      <input type="hidden" name="ingredient_id" id="delete_ingredient_id">
      
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