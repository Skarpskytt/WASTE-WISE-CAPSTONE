<?php
session_start();
include('../../config/db_connect.php');

if (!isset($_GET['id'])) {
    header("Location: ingredients.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = ?");
$stmt->execute([$id]);
$ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ingredient) {
    $_SESSION['errorMessage'] = "Ingredient not found.";
    header("Location: ingredients.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Map form fields to variables.
    $ingredient_name = $_POST['itemname'] ?? '';
    $expiration_date = $_POST['expirationdate'] ?? '';
    $stock_datetime  = $_POST['stockdate'] ?? date('Y-m-d H:i:s');
    $quantity        = $_POST['itemquantity'] ?? '';
    $metric_unit     = $_POST['unit'] ?? '';
    $price           = $_POST['price'] ?? '';
    $location        = $_POST['itemlocation'] ?? '';
    
    // Process image upload if a new image is provided.
    // Retain current image if no new one is uploaded.
    $item_image = $ingredient['item_image'];
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        if (in_array($_FILES['item_image']['type'], $allowed)) {
            $target_dir = 'uploads/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $file_name;
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file)) {
                $item_image = "uploads/" . $file_name;
            } else {
                $errorMessage = "Failed to upload image.";
            }
        } else {
            $errorMessage = "Invalid file type. Only PNG, JPG, and WEBP allowed.";
        }
    }

    $stmt = $pdo->prepare("UPDATE ingredients 
                           SET ingredient_name = ?, expiration_date = ?, stock_datetime = ?, quantity = ?, metric_unit = ?, price = ?, location = ?, item_image = ? 
                           WHERE id = ?");
    if ($stmt->execute([
        $ingredient_name,
        $expiration_date,
        $stock_datetime,
        $quantity,
        $metric_unit,
        $price,
        $location,
        $item_image,
        $id
    ])) {
        $_SESSION['successMessage'] = "Ingredient updated successfully!";
    } else {
        $_SESSION['errorMessage'] = "Error updating ingredient.";
    }
    header("Location: ingredients.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Ingredient - WasteWise</title>
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

    $(document).ready(function(){
        $('#toggleSidebar').on('click', function(){
            $('#sidebar').toggleClass('-translate-x-full');
        });
        $('#closeSidebar').on('click', function(){
            $('#sidebar').addClass('-translate-x-full');
        });
    });
  </script>
</head>
<body class="flex h-screen">
  <?php include '../layout/sidebaruser.php' ?>
  <div class="p-7">
    <div>
      <h1 class="text-2xl font-semibold">Edit Ingredient</h1>
      <p class="text-gray-500 mt-2">Update ingredient details</p>
    </div>

    <!-- Display Success or Error Messages -->
    <?php if (!empty($_SESSION['successMessage'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4">
        <span class="block sm:inline"><?= htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']); ?></span>
      </div>
    <?php elseif (!empty($_SESSION['errorMessage'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4">
        <span class="block sm:inline"><?= htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']); ?></span>
      </div>
    <?php endif; ?>
<div class="flex flex-col mx-3 mt-6 lg:flex-row gap-4">
 <!-- Edit Ingredient Form -->
 <div class="w-full lg:w-1/3 m-1">
 <form action="edit_ingredient.php?id=<?= $ingredient['id'] ?>" method="POST" enctype="multipart/form-data" class="w-full bg-white shadow-xl p-6 border mt-6">
      <div class="flex flex-wrap -mx-3 mb-6">
        <!-- Ingredient Name -->
        <div class="w-full md:w-full px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">Ingredient Name</label>
          <input type="text" name="itemname" id="itemname" value="<?= htmlspecialchars($ingredient['ingredient_name']) ?>" placeholder="Item Name" class="appearance-none block w-full bg-white text-gray-900 border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" required>
        </div>
        <!-- Stock Date -->
        <div class="w-full md:w-full px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stockdate">Stock Date</label>
          <input type="date" name="stockdate" id="stockdate" value="<?= date('Y-m-d', strtotime($ingredient['stock_datetime'])) ?>" class="appearance-none block w-full bg-white text-gray-900 border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" required>
        </div>
        <!-- Quantity -->
        <div class="w-full md:w-1/2 px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemquantity">Quantity</label>
          <input type="number" name="itemquantity" id="itemquantity" value="<?= htmlspecialchars($ingredient['quantity']) ?>" placeholder="Quantity" min="0" step="any" class="appearance-none block w-full bg-white text-gray-900 border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" required>
        </div>
        <!-- Metric Unit -->
        <div class="w-full md:w-1/2 px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unit">Metric Unit</label>
          <select name="unit" id="unit" class="appearance-none block w-full bg-white text-gray-900 border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" required>
            <option value="">Select Unit</option>
            <option value="grams" <?= $ingredient['metric_unit'] === 'grams' ? 'selected' : '' ?>>Grams</option>
            <option value="kilograms" <?= $ingredient['metric_unit'] === 'kilograms' ? 'selected' : '' ?>>Kilograms</option>
            <option value="liters" <?= $ingredient['metric_unit'] === 'liters' ? 'selected' : '' ?>>Liters</option>
            <option value="milliliters" <?= $ingredient['metric_unit'] === 'milliliters' ? 'selected' : '' ?>>Milliliters</option>
            <option value="pieces" <?= $ingredient['metric_unit'] === 'pieces' ? 'selected' : '' ?>>Pieces</option>
          </select>
        </div>
        <!-- Location -->
        <div class="w-full md:w-full px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemlocation">Location</label>
          <input type="text" name="itemlocation" id="itemlocation" value="<?= htmlspecialchars($ingredient['location']) ?>" placeholder="Location" class="appearance-none block w-full bg-white text-gray-900 border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" required>
        </div>
        <!-- Expiration Date -->
        <div class="w-full md:w-full px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expirationdate">Expiration Date</label>
          <input type="date" name="expirationdate" id="expirationdate" value="<?= htmlspecialchars($ingredient['expiration_date']) ?>" class="appearance-none block w-full bg-white text-gray-900 border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" required>
        </div>
        <!-- Price -->
        <div class="w-full md:w-full px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price">Price</label>
          <input type="number" name="price" id="price" value="<?= htmlspecialchars($ingredient['price']) ?>" placeholder="Price" step="0.01" class="appearance-none block w-full bg-white text-gray-900 border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" required>
        </div>
        <!-- Current Image Preview -->
        <div class="w-full md:w-full px-3 mb-6">
          <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2">Current Image</label>
          <?php if (!empty($ingredient['item_image'])): ?>
            <img src="<?= htmlspecialchars($ingredient['item_image']) ?>" alt="Ingredient Image" class="h-20 w-20 mx-auto">
            <p class="text-center text-sm text-gray-600">Current Image</p>
          <?php else: ?>
            <p class="text-center text-sm text-gray-600">N/A</p>
          <?php endif; ?>
        </div>
        <!-- Update Image Upload -->
        <div class="w-full px-3 mb-8">
          <label class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center" for="dropzone-file">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primarycol" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Update Image</h2>
            <p class="mt-2 text-gray-500 tracking-wide">Upload or drag &amp; drop your file PNG, JPG, or WEBP.</p>
            <input id="dropzone-file" type="file" name="item_image" class="hidden" accept="image/png, image/jpeg, image/webp">
          </label>
        </div>
      </div>
      <button type="submit" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">Update Ingredient</button>
    </form>
</div>
   
  </div>
</body>
</html>