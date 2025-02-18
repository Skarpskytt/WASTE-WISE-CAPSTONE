<?php
session_start();
// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct

$errors = [];

// Check if 'id' is set and not empty
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: product_data.php");
    exit();
}

$inventory_id = intval($_GET['id']);

// Fetch existing inventory data using PDO
try {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = :id");
    $stmt->execute([':id' => $inventory_id]);
    $inventory = $stmt->fetch();

    if (!$inventory) {
        $_SESSION['error'] = "Inventory item not found.";
        header("Location: inventory_data.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error fetching inventory: " . $e->getMessage());
}

// Handle form submission for editing
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $itemName = htmlspecialchars(trim($_POST['itemname']));
    $category = htmlspecialchars(trim($_POST['category']));
    $stockDate = $_POST['stockdate'];
    $itemQuantity = floatval($_POST['itemquantity']);
    $unit = htmlspecialchars(trim($_POST['unit']));
    $location = htmlspecialchars(trim($_POST['itemlocation']));
    $pricePerUnit = floatval($_POST['price_per_unit']);

    // Handle image upload (optional)
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
        $filename = time() . '_' . $_FILES['item_image']['name'];
        $filetype = $_FILES['item_image']['type'];
        $filesize = $_FILES['item_image']['size'];

        // Verify file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $errors[] = "Error: Please select a valid file format.";
        }

        if (empty($errors)) {
            // Create uploads directory if it doesn't exist
            $uploadDir = "../../assets/uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $targetPath)) {
                // Delete old image if exists
                if (!empty($inventory['image'])) {
                    $oldImage = $inventory['image'];
                    if (file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                }
                $imagePath = $targetPath;
            } else {
                $errors[] = "Error uploading the image.";
            }
        }
    } else {
        // If no new image is uploaded, keep the existing image
        $imagePath = $inventory['image'];
    }

    // Validate Stock Date
    if (!DateTime::createFromFormat('Y-m-d', $stockDate)) {
        $errors[] = "Error: Invalid stock date format.";
    }

    // Validate Unit (example: ensure it's one of allowed units)
    $allowedUnits = ['grams', 'kilograms', 'liters', 'milliliters', 'pieces'];
    if (!in_array($unit, $allowedUnits)) {
        $errors[] = "Error: Invalid unit selected.";
    }

    // After handling inputs and image upload, update the inventory including 'price_per_unit'
    if (empty($errors)) {
        try {
            if (isset($imagePath)) {
                $stmt = $pdo->prepare("UPDATE inventory SET name = ?, category = ?, stock_date = ?, quantity = ?, unit = ?, location = ?, image = ?, price_per_unit = ? WHERE id = ?");
                $stmt->execute([$itemName, $category, $stockDate, $itemQuantity, $unit, $location, $imagePath, $pricePerUnit, $inventory_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE inventory SET name = ?, category = ?, stock_date = ?, quantity = ?, unit = ?, location = ?, price_per_unit = ? WHERE id = ?");
                $stmt->execute([$itemName, $category, $stockDate, $itemQuantity, $unit, $location, $pricePerUnit, $inventory_id]);
            }
            $_SESSION['success'] = "Product updated successfully.";
            header("Location: product_data.php");
            exit(); 
        } catch (PDOException $e) {
            $errors[] = "Error updating inventory: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Product - Bea Bakes</title>
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
   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-screen">

<?php include '../layout/nav.php' ?>

 <div class="p-7">

  <div>
    <h1 class="text-2xl font-semibold">Edit Product Item</h1>
    <p class="text-gray-500 mt-2">Update Product details</p>
  </div>

  <!-- Display Success or Error Messages -->
  <?php
    if (isset($_SESSION['success'])) {
        echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4" role="alert">';
        echo '<span class="block sm:inline">' . htmlspecialchars($_SESSION['success']) . '</span>';
        echo '</div>';
        unset($_SESSION['success']);
    }

    if (!empty($errors)) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4" role="alert">';
        foreach ($errors as $error) {
            echo '<span class="block sm:inline">' . htmlspecialchars($error) . '</span><br>';
        }
        echo '</div>';
    }
  ?>

  <div class="flex flex-col mx-3 mt-6 lg:flex-row gap-4">
    <!-- Edit Inventory Form -->
    <div class="w-full lg:w-1/3 m-1">
        <form class="w-full bg-white shadow-xl p-6 border" 
              action="edit_product.php?id=<?php echo htmlspecialchars($inventory_id); ?>" 
              method="POST" 
              enctype="multipart/form-data">
            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Item Name -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">Item Name</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                    id="itemname" type="text" name="itemname" placeholder="Item Name" value="<?php echo htmlspecialchars($inventory['name']); ?>" required />
                </div>
                <!-- Category -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="category">Category</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                    id="category" type="text" name="category" placeholder="Category" value="<?php echo htmlspecialchars($inventory['category']); ?>" required />
                </div>
                <!-- Stock Date -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stockdate">Stock Date</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                    id="stockdate" type="date" name="stockdate" value="<?php echo htmlspecialchars($inventory['stock_date']); ?>" required />
                </div>
                <!-- Quantity -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemquantity">Quantity</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                    id="itemquantity" type="number" step="0.01" name="itemquantity" placeholder="Quantity" value="<?php echo htmlspecialchars($inventory['quantity']); ?>" required />
                </div>
                <!-- Unit -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unit">Unit</label>
                    <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                    id="unit" name="unit" required>
                        <option value="">Select Unit</option>
                        <option value="grams" <?php if($inventory['unit'] == 'grams') echo 'selected'; ?>>Grams</option>
                        <option value="kilograms" <?php if($inventory['unit'] == 'kilograms') echo 'selected'; ?>>Kilograms</option>
                        <option value="liters" <?php if($inventory['unit'] == 'liters') echo 'selected'; ?>>Liters</option>
                        <option value="milliliters" <?php if($inventory['unit'] == 'milliliters') echo 'selected'; ?>>Milliliters</option>
                        <option value="pieces" <?php if($inventory['unit'] == 'pieces') echo 'selected'; ?>>Pieces</option>
                    </select>
                </div>
                <!-- Price per Unit -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label for="price_per_unit" class="block text-sm font-bold mb-2">Price per Unit</label>
                    <input type="number" step="0.01" id="price_per_unit" name="price_per_unit" value="<?= htmlspecialchars($inventory['price_per_unit']) ?>" required class="...">
                </div>
                <!-- Location -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemlocation">Location</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                    id="itemlocation" type="text" name="itemlocation" placeholder="Location" value="<?php echo htmlspecialchars($inventory['location']); ?>" required />
                </div>
            </div>
            
            <!-- Update Inventory Button -->
            <div class="w-full md:w/full px-3 mb-6">
                <button type="submit" class="appearance-none block w-full bg-yellow-500 text-gray-100 font-bold border border-gray-200 rounded-lg py-3 px-3 leading-tight 
                hover:bg-yellow-600 focus:outline-none focus:bg-white focus:border-gray-500">Update Inventory</button>
            </div>
            
            <!-- Item Image Upload -->
            <div class="w-full px-3 mb-8">
                <label class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center" for="dropzone-file">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>

                <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Item Image</h2>

                <p class="mt-2 text-gray-500 tracking-wide">Upload or drag & drop your file SVG, PNG, JPG or WEBP.</p>

                <input id="dropzone-file" type="file" class="hidden" name="item_image" accept="image/png, image/jpeg, image/webp"/>
                </label>
                
                <!-- Display Current Image -->
                <div class="mt-2 text-center">
                    <?php if (!empty($inventory['image']) && file_exists($inventory['image'])): ?>
                        <img src="<?= htmlspecialchars($inventory['image']) ?>" 
                             alt="Current Image" 
                             class="h-20 w-20 mx-auto object-cover">
                    <?php else: ?>
                        <img src="../../assets/images/default-product.jpg" 
                             alt="Default Image" 
                             class="h-20 w-20 mx-auto object-cover">
                    <?php endif; ?>
                    <p class="text-sm text-gray-600">Current Image</p>
                </div>
            </div>
            
        </form>
    </div>

    <!-- You can include the Inventory Table here if needed -->

  </div>
 </div>

</body>
</html>