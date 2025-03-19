<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Define simplified unit options
$unitOptions = ['kg', 'g', 'L', 'ml', 'pcs'];

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
    // ...existing code...
}

// Handle DELETE ingredient
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_ingredient'])) {
    // ...existing code...
}

// Handle ADD ingredient submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ingredient'])) {
    // Map form fields to variables
    $ingredient_name = $_POST['itemname'] ?? '';
    $category = $_POST['category'] ?? '';
    $supplier_name = $_POST['supplier'] ?? null; // Optional
    $stock_quantity = floatval($_POST['stock_quantity'] ?? 0); // New field
    $expiry_date = !empty($_POST['expirydate']) ? $_POST['expirydate'] : null;
    $branchId = $_SESSION['branch_id'];
    $unit = $_POST['unit'] ?? '';
    $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
    
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
                (ingredient_name, category, supplier_name, stock_quantity, expiry_date,
                 unit, cost_per_unit, item_image, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ingredient_name,
            $category,
            $supplier_name,
            $stock_quantity,
            $expiry_date,
            $unit,
            $cost_per_unit,
            $item_image,
            $branchId
        ]);
        $successMessage = "Ingredient added successfully!";
    } catch (PDOException $e) {
        $errors[] = "Error adding ingredient: " . $e->getMessage();
    }
}
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

  <!-- Add Ingredient Form using product_data.php styling -->
  <div class="w-full bg-white shadow-xl p-6 border mb-8 mt-4">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Add New Ingredient</h2>
    <form class="w-full" action="ingredients.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="add_ingredient" value="1" />
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Ingredient Name -->
        <div class="mb-4">
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
        <div class="mb-4">
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
        <div class="mb-4">
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

        <!-- Expiry Date -->
        <div class="mb-4">
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

        <!-- Unit of Measure -->
        <div class="mb-4">
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
            <?php foreach ($unitOptions as $unit): ?>
              <option value="<?= $unit ?>">
                <?= $unit === 'kg' ? 'Kilogram (kg)' : 
                   ($unit === 'g' ? 'Gram (g)' : 
                   ($unit === 'L' ? 'Liter (L)' : 
                   ($unit === 'ml' ? 'Milliliter (ml)' : 
                   ($unit === 'pcs' ? 'Pieces (pcs)' : $unit)))) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Cost per Unit -->
        <div class="mb-4">
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

        <!-- Stock Quantity -->
        <div class="mb-4">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stock_quantity">
            Stock Quantity
          </label>
          <input
            type="number"
            step="0.01"
            class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
            id="stock_quantity"
            name="stock_quantity"
            placeholder="Current stock amount"
            required
          />
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
        <button type="submit" class="bg-primarycol text-white font-bold py-2 px-6 rounded hover:bg-green-600 transition-colors">Add Ingredient</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>