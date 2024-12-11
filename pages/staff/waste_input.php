<?php
// waste_input.php
session_start();

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php');

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Input - WasteWise</title>
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
        <h1 class="text-2xl font-semibold">Waste Input</h1>
        <p class="text-gray-500 mt-2">Manage waste entries</p>
    </div>

    <!-- Notification Container -->
    <div id="notification"></div>

    <!-- Inventory Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
        <?php foreach ($inventory as $item): ?>
            <div class="bg-white shadow-md rounded-lg p-3 card">
                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="h-32 w-full object-cover rounded-md">
                <h2 class="text-lg font-bold mt-3"><?php echo htmlspecialchars($item['name']); ?></h2>
                <p class="text-gray-600">
                    Quantity: <?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?>
                </p>
                <p class="text-gray-600">
                    Price per Unit: ₱<?php echo htmlspecialchars(number_format($item['price_per_unit'], 2)); ?>
                </p>
                
                <!-- Waste Input Form -->
                <form class="waste-form mt-3" method="POST" action="process_waste.php">
                    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['id']); ?>">

                    <div class="mb-2">
                        <label for="waste_quantity_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Quantity</label>
                        <input type="number" name="waste_quantity" id="waste_quantity_<?php echo $item['id']; ?>" min="0" step="any" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <div class="mb-2">
                        <label for="waste_reason_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Reason</label>
                        <select name="waste_reason" id="waste_reason_<?php echo $item['id']; ?>" required
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            <option value="">Select Reason</option>
                            <option value="overproduction">Overproduction</option>
                            <option value="expired">Expired</option>
                            <option value="compost">Compost</option>
                            <option value="donation">Donation</option>
                            <option value="dumpster">Dumpster</option>
                        </select>
                    </div>

                    <!-- Waste Date -->
                    <div class="mb-2">
                        <label for="waste_date_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Date</label>
                        <input type="date" name="waste_date" id="waste_date_<?php echo $item['id']; ?>" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <!-- Responsible Person -->
                    <div class="mb-2">
                        <label for="responsible_person_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">Responsible Person</label>
                        <input type="text" name="responsible_person" id="responsible_person_<?php echo $item['id']; ?>" required
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <!-- Submit Button -->
                    <div class="mb-2">
                        <button type="submit" class="w-full bg-green-700 text-white py-2 rounded-md">Submit Waste</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>