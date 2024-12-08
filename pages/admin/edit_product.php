<?php
session_start();
// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct

$errors = [];

// Check if 'id' is set and not empty
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: productdata.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch existing product data using PDO
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();

    if (!$product) {
        $_SESSION['error'] = "Product not found.";
        header("Location: productdata.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error fetching product: " . $e->getMessage());
}

// Handle form submission for editing
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $productName = htmlspecialchars($_POST['productname']);
    $productPrice = floatval($_POST['productprice']);
    $productType = htmlspecialchars($_POST['producttype']);
    $productDescription = htmlspecialchars($_POST['description']);

    // Handle image upload (optional)
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
        // If no new image is uploaded, keep the existing image
        $productImage = $product['image'];
    }

    // If no errors, update the product in the database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = :name, price = :price, type = :type, description = :description, image = :image WHERE id = :id");
            $stmt->execute([
                ':name' => $productName,
                ':price' => $productPrice,
                ':type' => $productType,
                ':description' => $productDescription,
                ':image' => $productImage,
                ':id' => $id
            ]);
            $_SESSION['success'] = "Product updated successfully.";
            header("Location: productdata.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error: Could not execute the update. " . $e->getMessage();
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
    <h1 class="text-2xl font-semibold">Edit Product</h1>
    <p class="text-gray-500 mt-2">Update product details</p>
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
    <!-- Edit Product Form -->
    <div class="w-full lg:w-1/3 m-1">
        <form class="w-full bg-white shadow-xl p-6 border" action="edit_product.php?id=<?php echo htmlspecialchars($id); ?>" method="POST" enctype="multipart/form-data">
            <div class="flex flex-wrap -mx-3 mb-6">
                <!-- Product Name -->
                <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="productname">Product Name</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="productname" type="text" name="productname" placeholder="Product Name" value="<?php echo htmlspecialchars($product['name']); ?>" required />
                </div>
                <!-- Product Price -->
                <div class="flex flex-1">
                  <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="productprice">Product Price</label>
                    <input type="number" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
                    id="productprice" name="productprice" placeholder="Product Price" value="<?php echo htmlspecialchars($product['price']); ?>" required />
                </div>
                <!-- Product Type -->
                <div class="w-full md:w-full px-3 mb-6">
                  <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="producttype">Product Type</label>
                  <input type="text" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                   id="producttype" name="producttype" placeholder="Product Type" value="<?php echo htmlspecialchars($product['type']); ?>" required />
              </div>
                </div>
                
                <!-- Product Description -->
                <div class="w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="description">Product Description</label>
                    <textarea rows="4" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                   id="description" name="description" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>                        
                
                <!-- Update Product Button -->
                <div class="w-full md:w-full px-3 mb-6">
                    <button type="submit" class="appearance-none block w-full bg-green-700 text-gray-100 font-bold border border-gray-200 rounded-lg py-3 px-3 leading-tight 
                    hover:bg-green-600 focus:outline-none focus:bg-white focus:border-gray-500">Update Product</button>
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
                    
                    <!-- Display Current Image -->
                    <div class="mt-2 text-center">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Current Image" class="h-20 w-20 mx-auto">
                        <p class="text-sm text-gray-600">Current Image</p>
                    </div>
                </div>
                
            </div>
        </form>
    </div>

    <!-- You can include the Products Table here if needed -->

  </div>
 </div>

</body>
</html>