<?php
// inventory_data.php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php');

$errors = [];

// Handle form submission for adding inventory
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $itemName = htmlspecialchars(trim($_POST['itemname']));
    $category = htmlspecialchars(trim($_POST['category']));
    $stockDate = $_POST['stockdate'];
    $itemQuantity = floatval($_POST['itemquantity']);
    $unit = htmlspecialchars(trim($_POST['unit']));
    $location = htmlspecialchars(trim($_POST['itemlocation']));
    $pricePerUnit = floatval($_POST['price_per_unit']);

    // Validate Stock Date
    if (!DateTime::createFromFormat('Y-m-d', $stockDate)) {
        $errors[] = "Error: Invalid stock date format.";
    }

    // Validate Unit
    $allowedUnits = ['grams', 'kilograms', 'liters', 'milliliters', 'pieces'];
    if (!in_array($unit, $allowedUnits)) {
        $errors[] = "Error: Invalid unit selected.";
    }

    // Handle image upload
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
        $filename = $_FILES['item_image']['name'];
        $filetype = $_FILES['item_image']['type'];
        $filesize = $_FILES['item_image']['size'];

        // Verify file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $errors[] = "Error: Please select a valid file format (JPG, JPEG, PNG, WEBP).";
        }

        // Verify file type
        if (in_array($filetype, $allowed)) {
            // Check file size - 2MB maximum
            if ($filesize > 2 * 1024 * 1024) {
                $errors[] = "Error: File size exceeds the 2MB limit.";
            }

            // Check whether file exists before uploading
            if (file_exists("uploads/" . $filename)) {
                $errors[] = "Error: " . htmlspecialchars($filename) . " already exists.";
            } else {
                // Create the uploads directory if it doesn't exist
                if (!is_dir("uploads")) {
                    mkdir("uploads", 0777, true);
                }

                move_uploaded_file($_FILES["item_image"]["tmp_name"], "uploads/" . $filename);
                $itemImage = "uploads/" . $filename;
            }
        } else {
            $errors[] = "Error: There was a problem uploading your file. Please try again.";
        }
    } else {
        $errors[] = "Error: Please upload an item image.";
    }

    // If no errors, insert into database using PDO
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO inventory (name, category, quantity, unit, location, stock_date, image, price_per_unit) VALUES (:name, :category, :quantity, :unit, :location, :stock_date, :image, :price_per_unit)");
            $stmt->execute([
                ':name' => $itemName,
                ':category' => $category,
                ':quantity' => $itemQuantity,
                ':unit' => $unit,
                ':location' => $location,
                ':stock_date' => $stockDate,
                ':image' => $itemImage,
                ':price_per_unit' => $pricePerUnit
            ]);
            $_SESSION['success'] = "Inventory item added successfully.";
            header("Location: inventory_data.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error: Could not execute the query. " . $e->getMessage();
        }
    }
}

// Retrieve inventory items from database using PDO
try {
    $stmt = $pdo->query("SELECT * FROM inventory ORDER BY created_at DESC");
    $inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error retrieving inventory: " . $e->getMessage());
}
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

<?php include '../layout/nav.php' ?>

 <div class="p-7">

  <div>
    <h1 class="text-2xl font-semibold">Inventory Data</h1>
    <p class="text-gray-500 mt-2">Manage your inventory</p>
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
    <!-- Add Inventory Ingredient Form -->
    <div class="w-full lg:w-1/3 m-1">
        <form class="w-full bg-white shadow-xl p-6 border" action="inventory_data.php" method="POST" enctype="multipart/form-data">
            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Item Name -->

                <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemname">Item Name</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="itemname" type="text" name="itemname" placeholder="Item Name" required />
                </div>
                <div class="flex w-full">
                <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="category">Category </label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="category" type="text" name="category" placeholder="Category" required />
                </div>
                <div class="w-full md:w-full px-3 mb-6">
                     <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="stockdate">Stock Date</label>
                      <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                       id="stockdate" name="stockdate" required />
                      </div>
                </div>
               

                <div class="flex flex-1">
                 
                 <!-- Item Quantity -->
                 <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemquantity">Quantity</label>
                    <input type="number" min="0" step="any" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
                    id="itemquantity" name="itemquantity" placeholder="Quantity" required />
                </div>
                <div class="w-full md:w-full px-3 mb-6">
                <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unit">Metric Unit</label>
                <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                id="unit" name="unit" required>
                    <option value="">Select Unit</option>
                    <option value="grams">Grams</option>
                    <option value="kilograms">Kilograms</option>
                    <option value="liters">Liters</option>
                    <option value="milliliters">Milliliters</option>
                    <option value="pieces">Pieces</option>
                </select>
            </div>
                 </div>
                
               
                <!-- Item Location -->
                <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="itemlocation">Location</label>
                    <input type="text" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                     id="itemlocation" name="itemlocation" placeholder="Location" required />
                </div>
                <!-- Price per Unit -->
                <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="price_per_unit">Price per Unit</label>
                    <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="price_per_unit" name="price_per_unit" placeholder="Price per Unit" required />
                </div>
            </div>
            
            <!-- Add Inventory Button -->
            <div class="w-full md:w-full px-3 mb-6">
                <button type="submit" class="appearance-none block w-full bg-green-700 text-gray-100 font-bold border border-gray-200 rounded-lg py-3 px-3 leading-tight 
                hover:bg-green-600 focus:outline-none focus:bg-white focus:border-gray-500">Add Inventory Ingredient</button>
            </div>
            
            <!-- Item Image Upload -->
            <div class="w-full px-3 mb-8">
                <label class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center" for="dropzone-file">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>

                <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Item Image</h2>

                <p class="mt-2 text-gray-500 tracking-wide">Upload or drag & drop your file PNG, JPG, or WEBP.</p>

                <input id="dropzone-file" type="file" class="hidden" name="item_image" accept="image/png, image/jpeg, image/webp"/>
                </label>
            </div>
            
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200 ">
      <div class="overflow-x-auto p-4">
        <table class="table table-zebra w-full">
          <!-- Table Head -->
          <thead>
            <tr class="bg-sec">
              <th>#</th>
              <th class="flex justify-center">Image</th>
              <th>Item Name</th>
              <th>Category</th>
              <th>Quantity</th>
              <th>Unit</th>
              <th>Location</th>
              <th>Stock Date</th>
              <th>Price per Unit</th> <!-- New Column Added -->
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
              if (!empty($inventory)) {
                  $count = 1;
                  foreach ($inventory as $item) {
                      echo "<tr>";
                      echo "<th>" . $count++ . "</th>";
                      echo "<td class='flex justify-center'><img src='" . htmlspecialchars($item['image']) . "' class='h-8 w-8' alt='" . htmlspecialchars($item['name']) . "'></td>";
                      echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                      echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                      echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                      echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
                      echo "<td>" . htmlspecialchars($item['location']) . "</td>";
                      echo "<td>" . htmlspecialchars($item['stock_date']) . "</td>";
                      echo "<td>" . htmlspecialchars($item['price_per_unit']) . "</td>"; // New Column Data
                      echo "<td class='p-2'>
                              <div class='flex justify-center space-x-2'>
                                  <a href='edit_inventory.php?id=" . urlencode($item['id']) . "' class='rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center'>
                                      <!-- Edit Icon -->
                                      <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13' />
                                      </svg>
                                      Edit
                                  </a>
                                  <a href='delete_inventory.php?id=" . urlencode($item['id']) . "' onclick=\"return confirm('Are you sure you want to delete this item?');\" class='rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center'>
                                      <!-- Delete Icon -->
                                      <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12' />
                                      </svg>
                                      Delete
                                  </a>
                              </div>
                            </td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='10' class='text-center'>No inventory items found.</td></tr>";
              }
            ?>
          </tbody>
        </table>
      </div>
  </div>
    
  </div>
 </div>
 <div>
  
 </div>
 
</body>
</html>