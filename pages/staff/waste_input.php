<?php
// Fix incorrect paths - remove one level of "../"
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for Branch 1 staff access only
checkAuth(['staff']);

// Fetch the user's name from the session or database
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];

try {
    // Fetch inventory items - remove the waste_processed filter
    $invStmt = $pdo->prepare("SELECT *, 'product' as type FROM data_management ORDER BY created_at DESC");
    $invStmt->execute();
    $inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ingredients - remove the waste_processed filter
    $ingStmt = $pdo->prepare("SELECT *, 'ingredient' as type FROM ingredients ORDER BY stock_datetime DESC");
    $ingStmt->execute();
    $ingredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine both arrays
    $items = array_merge($inventory, $ingredients);
} catch (PDOException $e) {
    die("Error retrieving data: " . $e->getMessage());
}

// Handle form submission for processing waste
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inventoryId = $_POST['inventory_id'];
    $wasteDate = $_POST['waste_date'];
    $wasteQuantity = $_POST['waste_quantity'];
    $wasteValue = $_POST['waste_value'];
    $wasteReason = $_POST['waste_reason'];

    try {
        // Insert waste data into the waste table
        $stmt = $pdo->prepare("INSERT INTO waste (user_id, inventory_id, waste_date, waste_quantity, waste_value, waste_reason, responsible_person) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $inventoryId, $wasteDate, $wasteQuantity, $wasteValue, $wasteReason, $userName]);

        // Mark the inventory item as processed for waste
        $updateStmt = $pdo->prepare("UPDATE data_management SET waste_processed = TRUE WHERE id = ?");
        $updateStmt->execute([$inventoryId]);

        echo "Waste processed successfully.";
    } catch (PDOException $e) {
        die("Failed to process waste: " . $e->getMessage());
    }
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
            notification.removeClass('bg-green-500 bg-red-500 text-white');

            if (isSuccess) {
                notification.addClass('bg-green-500 text-white');
            } else {
                notification.addClass('bg-red-500 text-white');
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

<?php include (__DIR__ . '/../layout/staff_nav.php'); ?>

<div class="p-5 flex-grow">
    <div>
        <h1 class="text-3xl font-bold mb-6 text-primarycol">Waste Input</h1>
        <p class="text-gray-500 mt-2">Manage waste entries for products and ingredients</p>
    </div>

    <!-- Notification Container -->
    <div id="notification"></div>

    <!-- Items Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
        <?php foreach ($items as $item): 
            $isIngredient = $item['type'] === 'ingredient';
            $itemName = $isIngredient ? $item['ingredient_name'] : $item['name'];
            $itemQuantity = $isIngredient ? $item['quantity'] : $item['quantity'];
            $itemUnit = $isIngredient ? $item['metric_unit'] : $item['unit'];
            $itemPrice = $isIngredient ? $item['price'] : $item['price_per_unit'];
            $itemImage = $isIngredient ? $item['item_image'] : $item['image'];
        ?>
            <div class="bg-white shadow-md rounded-lg p-3 card">
                <!-- Type Badge -->
                <div class="flex justify-between items-center mb-2">
                    <span class="px-2 py-1 rounded text-xs font-semibold <?= $isIngredient ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                        <?= ucfirst($item['type']) ?>
                    </span>
                </div>

                <img src="<?php echo $isIngredient ? $itemImage : '../admin/' . $itemImage; ?>" 
                     alt="<?php echo htmlspecialchars($itemName); ?>" 
                     class="h-32 w-full object-cover rounded-md">
                <h2 class="text-lg font-bold mt-3"><?php echo htmlspecialchars($itemName); ?></h2>
                <p class="text-gray-600">
                    Quantity: <?php echo htmlspecialchars($itemQuantity); ?> <?php echo htmlspecialchars($itemUnit); ?>
                </p>
                <p class="text-gray-600">
                    Price per Unit: ₱<?php echo htmlspecialchars(number_format($itemPrice, 2)); ?>
                </p>
                
                <!-- Waste Input Form -->
                <form class="waste-form mt-3" method="POST" action="process_waste.php">
                    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                    <input type="hidden" name="item_type" value="<?php echo htmlspecialchars($item['type']); ?>">

                    <div class="mb-2">
                        <label for="waste_quantity_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">
                            Waste Quantity <?php echo $isIngredient ? "(" . htmlspecialchars($itemUnit) . ")" : ""; ?>
                        </label>
                        <div class="flex gap-2">
                            <input type="number" 
                                   name="waste_quantity" 
                                   id="waste_quantity_<?php echo $item['id']; ?>" 
                                   min="0" 
                                   step="any" 
                                   required
                                   class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            <?php if ($isIngredient): ?>
                                <span class="mt-2 text-sm text-gray-600"><?php echo htmlspecialchars($itemUnit); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-gray-500">Available: <?php echo htmlspecialchars($itemQuantity); ?> <?php echo htmlspecialchars($itemUnit); ?></span>
                    </div>

                    <!-- Waste Reason -->
                    <div class="mb-2">
                        <label for="waste_reason_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Reason</label>
                        <select name="waste_reason" 
                                id="waste_reason_<?php echo $item['id']; ?>" 
                                required
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            <option value="">Select Reason</option>
                            <?php if ($isIngredient): ?>
                                <!-- Options for ingredients -->
                                <option value="overproduction">Overproduction</option>
                                <option value="expired">Expired</option>
                            <?php else: ?>
                                <!-- Options for products -->
                                <option value="overproduction">Overproduction</option>
                                <option value="expired">Expired</option>
                                <option value="donation">Donation</option>
                                <option value="compost">Compost</option>
                                <option value="spoilage">Spoilage</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Waste Date -->
                    <div class="mb-2">
                        <label for="waste_date_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">Waste Date</label>
                        <input type="date" 
                               name="waste_date" 
                               id="waste_date_<?php echo $item['id']; ?>" 
                               required
                               value="<?php echo date('Y-m-d'); ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <!-- Responsible Person (Auto-filled) -->
                    <div class="mb-2">
                        <label for="responsible_person_<?php echo $item['id']; ?>" class="block text-sm font-medium text-gray-700">Responsible Person</label>
                        <input type="text" 
                               name="responsible_person" 
                               id="responsible_person_<?php echo $item['id']; ?>" 
                               value="<?php echo htmlspecialchars($userName); ?>" 
                               readonly
                               class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 bg-gray-50 focus:outline-none focus:ring-[#98c01d]">
                    </div>

                    <!-- Submit Button -->
                    <div class="mb-2">
                        <button type="submit" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">
                            Submit Waste
                        </button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>