<?php
// Start the session
session_start();

// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct

// Initialize variables
$productName = $unitPrice = $productType = $productDescription = "";
$productImage = "";
$errors = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $productName = htmlspecialchars($_POST['productname']);
    $unitPrice = floatval($_POST['unitprice']);
    $productType = htmlspecialchars($_POST['producttype']);
    $productDescription = htmlspecialchars($_POST['description']);

    // Handle image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
        $filename = $_FILES['product_image']['name'];
        $filetype = $_FILES['product_image']['type'];
        $filesize = $_FILES['product_image']['size'];

        // Verify file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $errors[] = "Error: Please select a valid file format (JPG, JPEG, PNG, WEBP).";
        }

        // Verify file type
        if (in_array($filetype, $allowed)) {
            // Check file size - 2MB maximum
            if ($filesize > 2 * 1024 * 1024) {
                $errors[] = "Error: File size is larger than the allowed limit of 2MB.";
            }

            // Check whether file exists before uploading
            if (file_exists("uploads/" . $filename)) {
                $errors[] = "Error: " . htmlspecialchars($filename) . " already exists.";
            } else {
                // Create the uploads directory if it doesn't exist
                if (!is_dir("uploads")) {
                    mkdir("uploads", 0777, true);
                }

                move_uploaded_file($_FILES["product_image"]["tmp_name"], "uploads/" . $filename);
                $productImage = "uploads/" . $filename;
            }
        } else {
            $errors[] = "Error: There was a problem uploading your file. Please try again.";
        }
    } else {
        $errors[] = "Error: Please upload a product image.";
    }

    // If no errors, insert into database using PDO
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, price, type, description, image) VALUES (:name, :price, :type, :description, :image)");
            $stmt->execute([
                ':name' => $productName,
                ':price' => $unitPrice,
                ':type' => $productType,
                ':description' => $productDescription,
                ':image' => $productImage
            ]);
            $_SESSION['success'] = "Product added successfully.";
            header("Location: productdata.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error: Could not execute the query. " . $e->getMessage();
        }
    }
}

// Retrieve products from database using PDO
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error retrieving products: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Product Data</title>
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
    <h1 class="text-2xl font-semibold">Product Data</h1>
    <p class="text-gray-500 mt-2">Add new product to sell</p>
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
    <!-- Add Product Form -->
    <div class="w-full lg:w-1/3 m-1">
        <form class="w-full bg-white shadow-xl p-6 border" action="productdata.php" method="POST" enctype="multipart/form-data">
            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Product Name -->
                <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="productname">Product Name</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="productname" type="text" name="productname" placeholder="Product Name" required />
                </div>
                <!-- Product Price -->
                <div class="flex flex-1">
                  <div class="w-full md:w/full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="unitprice">Unit Price</label>
                    <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
                    id="unitprice" name="unitprice" placeholder="Product Price" required />
                </div>
                <!-- Product Type -->
                <div class="w-full md:w/full px-3 mb-6">
                  <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="producttype">Product Type</label>
                  <input type="text" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                   id="producttype" name="producttype" placeholder="Product Type" required />
              </div>
                </div>
                
                <!-- Product Description -->
                <div class="w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="description">Product Description</label>
                    <textarea rows="4" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                   id="description" name="description" required></textarea>
                </div>                        
                
                <!-- Add Product Button -->
                <div class="w-full md:w/full px-3 mb-6">
                    <button type="submit" class="appearance-none block w-full bg-green-700 text-gray-100 font-bold border border-gray-200 rounded-lg py-3 px-3 leading-tight 
                    hover:bg-green-600 focus:outline-none focus:bg-white focus:border-gray-500">Add Product</button>
                </div>
                
                <!-- Product Image Upload -->
                <div class="w-full px-3 mb-8">
                    <label class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center" for="dropzone-file">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>

                    <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Product Image</h2>

                    <p class="mt-2 text-gray-500 tracking-wide">Upload or drag & drop your file SVG, PNG, JPG or WEBP.</p>

                    <input id="dropzone-file" type="file" class="hidden" name="product_image" accept="image/png, image/jpeg, image/webp"/>
                    </label>
                </div>
                
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200 ">
      <div class="overflow-x-auto p-4">
        <table class="table table-zebra w-full">
          <!-- Table Head -->
          <thead>
            <tr class="bg-sec">
              <th>#</th>
              <th class="flex justify-center">Image</th>
              <th>Product Name</th>
              <th>Description</th>
              <th>Price</th>
              <th>Type</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
              if (!empty($products)) {
                  $count = 1;
                  foreach ($products as $row) {
                      echo "<tr>";
                      echo "<th>" . $count++ . "</th>";
                      echo "<td><img src='" . htmlspecialchars($row['image']) . "' class='h-8 w-8 mx-auto' alt='" . htmlspecialchars($row['name']) . "'></td>";
                      echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                      echo "<td>₱" . number_format($row['price'], 2) . "</td>";
                      echo "<td>" . htmlspecialchars($row['type']) . "</td>";
                      echo "<td class='p-2'>
                              <div class='flex justify-center space-x-2'>
                                <a href='edit_product.php?id=" . urlencode($row['id']) . "' class='rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center'>
                                    <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                      <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13' />
                                    </svg>
                                    Edit
                                </a>
                                <a href='delete_product.php?id=" . urlencode($row['id']) . "' onclick=\"return confirm('Are you sure you want to delete this product?');\" class='rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center'>
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
                  echo "<tr><td colspan='7' class='text-center'>No products found.</td></tr>";
              }
            ?>
          </tbody>
        </table>
      </div>
  </div>
    
  </div>
 </div>

</body>
</html>
