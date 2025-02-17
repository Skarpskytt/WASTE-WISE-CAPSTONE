<?php
session_start();
include('../../config/db_connect.php');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ingredient'])) {
    // Map form fields to variables.
    $ingredient_name = $_POST['itemname']       ?? '';
    $expiration_date = $_POST['expirationdate'] ?? '';
    $stock_datetime  = $_POST['stockdate']       ?? date('Y-m-d H:i:s');
    $quantity        = $_POST['itemquantity']    ?? '';
    $metric_unit     = $_POST['unit']            ?? '';
    $price           = $_POST['price']           ?? '';
    $location        = $_POST['itemlocation']    ?? '';

    // Process image upload if exists
    $item_image = null;
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
                // Save relative file path
                $item_image = "uploads/" . $file_name;
            } else {
                $errorMessage = "Failed to upload image.";
            }
        } else {
            $errorMessage = "Invalid file type. Only PNG, JPG, and WEBP allowed.";
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO ingredients 
                (ingredient_name, expiration_date, stock_datetime, quantity, metric_unit, price, location, item_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ingredient_name,
            $expiration_date,
            $stock_datetime,
            $quantity,
            $metric_unit,
            $price,
            $location,
            $item_image
        ]);
        $successMessage = "Ingredient added successfully!";
    } catch (PDOException $e) {
        $errorMessage = "Error adding ingredient: " . $e->getMessage();
    }
}

// Fetch ingredients data from the database
$stmt = $pdo->query("SELECT * FROM ingredients ORDER BY id DESC");
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventory Data - WasteWise</title>
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
    });
  </script>
</head>

<body class="flex h-screen">

<?php include '../layout/sidebaruser.php' ?>

<div class="p-7">
  <div>
    <h1 class="text-2xl font-semibold">Inventory Data</h1>
    <p class="text-gray-500 mt-2">Manage your inventory</p>
  </div>

  <!-- Display Success or Error Messages -->
  <?php if (!empty($successMessage)): ?>
    <div class="bg-green-100 text-green-800 p-3 rounded my-4">
      <?= htmlspecialchars($successMessage) ?>
    </div>
  <?php elseif (!empty($errorMessage)): ?>
    <div class="bg-red-100 text-red-800 p-3 rounded my-4">
      <?= htmlspecialchars($errorMessage) ?>
    </div>
  <?php endif; ?>

  <div class="flex flex-col mx-3 mt-6 lg:flex-row gap-4">
    <!-- Add Inventory Ingredient Form -->
    <div class="w-full lg:w-1/3 m-1">
      <form class="w-full bg-white shadow-xl p-6 border" action="ingredients.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="add_ingredient" value="1" />
        <div class="flex flex-wrap -mx-3 mb-6">
          <!-- Ingredient Name -->
          <div class="w-full md:w-full px-3 mb-6">
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

          <!-- Stock Date -->
          <div class="w-full md:w-full px-3 mb-6">
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

          <div class="flex flex-1">
            <!-- Quantity -->
            <div class="w-full md:w-full px-3 mb-6">
              <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemquantity">
                Quantity
              </label>
              <input
                type="number"
                min="0"
                step="any"
                class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
                id="itemquantity"
                name="itemquantity"
                placeholder="Quantity"
                required
              />
            </div>
            <!-- Metric Unit -->
            <div class="w-full md:w-full px-3 mb-6">
              <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unit">
                Metric Unit
              </label>
              <select
                class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
                id="unit"
                name="unit"
                required
              >
                <option value="">Select Unit</option>
                <option value="grams">Grams</option>
                <option value="kilograms">Kilograms</option>
                <option value="liters">Liters</option>
                <option value="milliliters">Milliliters</option>
                <option value="pieces">Pieces</option>
              </select>
            </div>
          </div>

          <!-- Location -->
          <div class="w-full md:w-full px-3 mb-6">
            <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemlocation">
              Location
            </label>
            <input
              type="text"
              class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
              id="itemlocation"
              name="itemlocation"
              placeholder="Location"
              required
            />
          </div>

          <!-- Expiration Date -->
          <div class="w-full md:w-full px-3 mb-6">
            <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="expirationdate">
              Expiration Date
            </label>
            <input
              type="date"
              class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
              id="expirationdate"
              name="expirationdate"
              required
            />
          </div>

          <!-- Price -->
          <div class="w-full md:w-full px-3 mb-6">
            <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price">
              Price
            </label>
            <input
              type="number"
              step="0.01"
              class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
              id="price"
              name="price"
              placeholder="Price"
              required
            />
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

    <!-- Updated Ingredients Table -->
    <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200">
      <div class="overflow-x-auto p-4">
        <table class="table table-zebra w-full">
          <!-- Table Head -->
          <thead>
            <tr class="bg-sec">
              <th>#</th>
              <th class="flex justify-center">Image</th>
              <th>Ingredient Name</th>
              <th>Quantity</th>
              <th>Metric Unit</th>
              <th>Location</th>
              <th>Stock Date</th>
              <th>Expiration Date</th>
              <th>Price</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ingredients as $index => $ingredient): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td class="flex justify-center">
                  <!-- Assuming you store the image path in a column, otherwise display N/A -->
                  <?php if (!empty($ingredient['item_image'])): ?>
                    <img src="<?= htmlspecialchars($ingredient['item_image']) ?>" alt="Ingredient Image" class="w-10 h-10 object-cover" />
                  <?php else: ?>
                    N/A
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($ingredient['ingredient_name']) ?></td>
                <td><?= htmlspecialchars($ingredient['quantity']) ?></td>
                <td><?= htmlspecialchars($ingredient['metric_unit']) ?></td>
                <td><?= htmlspecialchars($ingredient['location']) ?></td>
                <td><?= htmlspecialchars(date('Y-m-d', strtotime($ingredient['stock_datetime']))) ?></td>
                <td><?= htmlspecialchars($ingredient['expiration_date']) ?></td>
                <td><?= htmlspecialchars($ingredient['price']) ?></td>
                <td class="text-center">
                  <a href="edit_ingredient.php?id=<?= $ingredient['id'] ?>" 
                     class="bg-blue-500 text-white py-1 px-2 rounded mr-2">Edit</a>
                  <a href="delete_ingredient.php?id=<?= $ingredient['id'] ?>" 
                     class="bg-red-500 text-white py-1 px-2 rounded"
                     onclick="return confirm('Are you sure you want to delete this ingredient?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<div></div>

</body>
</html>
