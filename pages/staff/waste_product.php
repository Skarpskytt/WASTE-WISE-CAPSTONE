<?php
session_start();

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php');

// Fetch product data along with inventory level from the sales table
try {
    $stmt = $pdo->query("
        SELECT products.*, sales.inventory_level
        FROM products
        LEFT JOIN sales ON products.id = sales.product_id
        ORDER BY products.created_at DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving products: " . $e->getMessage());
}

// Fetch waste data for products
try {
    $stmt = $pdo->query("
        SELECT waste.*, products.name AS product_name, products.image 
        FROM waste 
        LEFT JOIN products ON waste.inventory_id = products.id 
        WHERE waste.classification = 'product'
        ORDER BY waste.waste_date DESC
    ");
    $wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching waste data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Product - WasteWise</title>
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

            // Handle AJAX form submission
            $('.waste-form').on('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                let form = $(this);
                let formData = form.serialize();

                $.ajax({
                    type: 'POST',
                    url: 'process_waste.php',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        console.log(response); // For debugging

                        if (response.success) {
                            // Remove the card with a fade-out effect
                            form.closest('.card').fadeOut(300, function() {
                                $(this).remove();
                            });

                            // Display success notification
                            showNotification(response.message, true);
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
</head>

<body class="flex min-h-screen">

<?php include '../layout/sidebaruser.php' ?>

<div class="p-5 flex-grow">
    <div>
        <h1 class="text-2xl font-semibold">Waste Product</h1>
        <p class="text-gray-500 mt-2">Manage waste entries for products</p>
    </div>

    <!-- Notification Container -->
    <div id="notification"></div>

    <!-- Product Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
        <?php foreach ($products as $product): ?>
            <div class="bg-white shadow-md rounded-lg p-3 card">
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="h-32 w-full object-cover rounded-md">
                <h2 class="text-lg font-bold mt-3"><?php echo htmlspecialchars($product['name']); ?></h2>
                <p class="text-gray-600">Price: ₱<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
                <p class="text-gray-600">Inventory Level: <?php echo htmlspecialchars($product['inventory_level']); ?></p>
                
                <!-- Waste Input Form -->
                <form class="waste-form mt-3">
                    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($product['id']); ?>">

                    <div class="mb-2">
                        <label for="waste_quantity_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Quantity</label>
                        <input type="number" name="waste_quantity" id="waste_quantity_<?php echo $product['id']; ?>" min="0" step="any" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <div class="mb-2">
                        <label for="waste_value_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Value</label>
                        <input type="text" name="waste_value" id="waste_value_<?php echo $product['id']; ?>" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <div class="mb-2">
                        <label for="waste_reason_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Reason</label>
                        <select name="waste_reason" id="waste_reason_<?php echo $product['id']; ?>" required
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            <option value="">Select Reason</option>
                            <option value="overproduction">Overproduction</option>
                            <option value="expired">Expired</option>
                            <option value="compost">Compost</option>
                            <option value="donation">Donation</option>
                            <option value="dumpster">Dumpster</option>
                        </select>
                    </div>

                    <!-- Classification -->
                    <input type="hidden" name="classification" value="product">

                    <div class="mb-2">
                        <label for="waste_date_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Date</label>
                        <input type="date" name="waste_date" id="waste_date_<?php echo $product['id']; ?>" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <div class="mb-2">
                        <label for="responsible_person_<?php echo $product['id']; ?>" class="block text-sm font-medium text-gray-700">Responsible Person</label>
                        <input type="text" name="responsible_person" id="responsible_person_<?php echo $product['id']; ?>" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <button type="submit" class="w-full bg-[#98c01d] text-white py-1.5 px-3 rounded hover:bg-[#85a814]">Submit Waste</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>