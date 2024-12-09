<?php
session_start();

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php'); // Adjust the path as needed

// Fetch product data
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
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

<body class="flex h-auto">

<?php include ('../layout/sidebaruser.php' ) ?> 

<!-- Main Content -->
<div class="p-6 overflow-y-auto w-full">

    <!-- Notification Container -->
    <div id="notification"></div>

    <h2 class="text-2xl font-semibold mb-5">Sales Data Entry</h2>

    <!-- Product Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
        <?php foreach ($products as $product): ?>
            <div class="bg-white shadow-md rounded-lg p-3 card">
                <!-- Product Details -->
                <img src="'../../pages/admin/uploads/'<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="h-32 w-full object-cover rounded-md">
                <h2 class="text-lg font-bold mt-3"><?php echo htmlspecialchars($product['name']); ?></h2>
                <p class="text-gray-600">Price: ₱<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>

                <!-- Sales Input Form -->
                <form class="sales-form mt-3">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">

                    <!-- Date -->
                    <div class="mb-2">
                        <label for="date_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" name="date" id="date_<?php echo $product['id']; ?>" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none">
                    </div>

                    <!-- Quantity Sold -->
                    <div class="mb-2">
                        <label for="quantity_sold_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Quantity Sold</label>
                        <input type="number" name="quantity_sold" id="quantity_sold_<?php echo $product['id']; ?>" min="0" step="1" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none">
                    </div>

                    <!-- Revenue -->
                    <div class="mb-2">
                        <label for="revenue_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Revenue</label>
                        <input type="number" name="revenue" id="revenue_<?php echo $product['id']; ?>" min="0" step="0.01" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none">
                    </div>

                    <!-- Inventory Level -->
                    <div class="mb-2">
                        <label for="inventory_level_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Inventory Level</label>
                        <input type="number" name="inventory_level" id="inventory_level_<?php echo $product['id']; ?>" min="0" step="1" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none">
                    </div>

                    <!-- Staff Member -->
                    <div class="mb-2">
                        <label for="staff_member_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Staff Member</label>
                        <input type="text" name="staff_member" id="staff_member_<?php echo $product['id']; ?>" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none">
                    </div>

                    <!-- Comments -->
                    <div class="mb-2">
                        <label for="comments_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Comments</label>
                        <textarea name="comments" id="comments_<?php echo $product['id']; ?>" rows="2"
                                  class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-green-600 text-white py-1.5 px-3 rounded hover:bg-green-700">Submit Sales Data</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Include your scripts -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Custom Script -->
<script>
    $(document).ready(function() {
        // Handle AJAX form submission
        $('.sales-form').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            let form = $(this);
            let formData = form.serialize();

            $.ajax({
                type: 'POST',
                url: 'process_sales.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log(response); // For debugging

                    if (response.success) {
                        // Display success notification
                        showNotification(response.message, true);

                        // Optionally, clear the form fields
                        form[0].reset();
                    } else {
                        // Display error notification
                        showNotification(response.message, false);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown);
                    showNotification('An unexpected error occurred.', false);
                }
            });
        });

        // Notification Function
        function showNotification(message, isSuccess) {
            let notification = $('#notification');
            notification.removeClass('bg-green-500 bg-red-500');

            if (isSuccess) {
                notification.addClass('bg-green-500');
            } else {
                notification.addClass('bg-red-500');
            }

            notification.text(message).fadeIn();

            setTimeout(function() {
                notification.fadeOut();
            }, 3000); // Hide after 3 seconds
        }
    });
</script>

<style>
    /* Notification Styles */
    #notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 10px 20px;
        border-radius: 5px;
        color: white;
        display: none;
        z-index: 1000;
    }
</style>

</body> 
</html>
