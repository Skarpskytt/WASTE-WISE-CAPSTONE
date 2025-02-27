<?php
// filepath: /c:/xampp/htdocs/capstone/WASTE-WISE-CAPSTONE/pages/staff/waste_input.php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Fetch the user's name from the session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$branchId = $_SESSION['branch_id'];

try {
    // Fetch products from the products table (instead of data_management)
    $prodStmt = $pdo->prepare("SELECT *, 'product' as type FROM products WHERE branch_id = ? ORDER BY created_at DESC");
    $prodStmt->execute([$branchId]);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ingredients from ingredients table
    $ingStmt = $pdo->prepare("SELECT *, 'ingredient' as type FROM ingredients WHERE branch_id = ? ORDER BY stock_datetime DESC");
    $ingStmt->execute([$branchId]);
    $ingredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine both arrays
    // $products = array_merge($products, $ingredients);
} catch (PDOException $e) {
    die("Error retrieving data: " . $e->getMessage());
}

if (isset($_POST['submitwaste'])) {
    // Extract form data
    $userId = $_SESSION['user_id'];
    $wasteDate = $_POST['waste_date'] ?? null;
    $wasteQuantity = $_POST['waste_quantity'] ?? null;
    // waste value
    $wasteValue = $wasteQuantity * $_POST['product_value'];
    $wasteReason = $_POST['waste_reason'] ?? null;
    $responsiblePerson = $_POST['responsible_person'] ?? null;
    $branchId = $_SESSION['branch_id'];

    // Validate form data
    if (!$userId || !$wasteDate || !$wasteQuantity || !$wasteValue || !$wasteReason || !$responsiblePerson || !$branchId) {
        $response = [
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ];
    } else {
        // Insert waste entry into the database
        try {
            $stmt = $pdo->prepare("INSERT INTO waste (user_id, item_type, waste_date, waste_quantity, waste_value, waste_reason, responsible_person, created_at, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, 'product', date('Y-m-d H:i:s'), $wasteQuantity, $wasteValue, $wasteReason, $responsiblePerson, date('Y-m-d H:i:s'), $branchId]);

            $response = [
                'success' => true,
                'message' => 'Waste entry submitted successfully.'
            ];
        } catch (PDOException $e) {
            $response = [
                'success' => false,
                'message' => 'An error occurred while submitting the waste entry.'
            ];
        }
    }

    // Return the response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
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
            // $('.waste-form').on('submit', function(e) {
            //     e.preventDefault(); // Prevent default form submission

            //     let form = $(this);
            //     let formData = form.serialize();

            //     $.ajax({
            //         type: 'POST',
            //         url: 'waste_input.php',
            //         data: formData,
            //         dataType: 'json',
            //         success: function(response) {
            //             console.log(response); // For debugging

            //             if (response.success) {
            //                 // Remove the card with a fade-out effect
            //                 form.closest('.card').fadeOut(300, function() {
            //                     $(this).remove();
            //                 });

            //                 // Display success notification
            //                 showNotification(response.message, true);
            //             } else {
            //                 // Display error notification
            //                 showNotification(response.message, false);
            //             }
            //         },
            //         error: function(jqXHR, textStatus, errorThrown) {
            //             console.error('AJAX Error:', textStatus, errorThrown);
            //             showNotification('An unexpected error occurred.', false);
            //         }
            //     });
            // });

         // Fix the notification function name
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

    <?php include(__DIR__ . '/../layout/staff_nav.php'); ?>

    <div class="p-5">
        <div>
            <h1 class="text-3xl font-bold mb-6 text-primarycol">Waste Input</h1>
            <p class="text-gray-500 mt-2">Manage waste entries for products and ingredients</p>
        </div>

        <!-- Notification Container -->
        <div id="notification"></div>

        <!-- products Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
            <?php foreach ($products as $product):

                // Set variables based on product type

                $productId = $product['id'];
                $productName = $product['name'] ?? 'N/A';
                $productCategory = $product['category'] ?? 'N/A';
                $productPrice = $product['price_per_unit'] ?? $product['price'] ?? 0;
                $productImage = $product['product_image'] ?? '';
                $productDate = date('Y-m-d', strtotime($product['stock_date']));

            ?>
                <div class="bg-white shadow-md rounded-lg p-3 card">
                    <!-- Type Badge -->
                    <div class="flex justify-between products-center mb-2">

                        <span class="px-2 py-1 rounded text-xs font-semibold bg-gray-100 text-gray-800">
                            <?= htmlspecialchars($productCategory) ?>
                        </span>
                    </div>

                    <img src="<?= htmlspecialchars($productImage) ?>"
                        alt="<?= htmlspecialchars($productName) ?>"
                        class="h-32 w-full object-cover rounded-md">

                    <h2 class="text-lg font-bold mt-3"><?= htmlspecialchars($productName) ?></h2>

                    <p class="text-gray-600">
                        Added on: <?= htmlspecialchars($productDate) ?>
                    </p>
                    <p class="text-gray-600">
                        Price per Unit: ₱<?= htmlspecialchars(number_format($productPrice, 2)) ?>
                    </p>

                    <!-- Waste Input Form -->
                    <form class="waste-form mt-3" method="POST" action="">
                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId) ?>">
                        <input type="hidden" name="product_type" value="<?= htmlspecialchars($product['type']) ?>">
                        <input type="hidden" name="product_value" value="<?= htmlspecialchars($productPrice) ?>">


                        <!-- Product-specific fields -->
                        <div class="mb-2">
                            <label for="quantity_produced_<?= $productId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Produced
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="quantity_produced"
                                    id="quantity_produced_<?= $productId ?>"
                                    min="1"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="quantity_sold_<?= $productId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Sold
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="quantity_sold"
                                    id="quantity_sold_<?= $productId ?>"
                                    min="0"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>

                        <!-- Common field: Quantity Wasted -->
                        <div class="mb-2">
                            <label for="waste_quantity_<?= $productId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Wasted
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="waste_quantity"
                                    id="waste_quantity_<?= $productId ?>"
                                    min="1"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>

                        <!-- Waste Reason -->
                        <div class="mb-2">
                            <label for="waste_reason_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Waste Reason</label>
                            <select name="waste_reason"
                                id="waste_reason_<?= $productId ?>"
                                required
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                                <option value="">Select Reason</option>
                                <?php if ($isIngredient): ?>
                                    <!-- Options for ingredients -->
                                    <option value="expired">Expired</option>
                                    <option value="spoiled">Spoiled</option>
                                    <option value="over-measured">Over-measured</option>
                                    <option value="contaminated">Contaminated</option>
                                    <option value="other">Other</option>
                                <?php else: ?>
                                    <!-- Options for products -->
                                    <option value="overproduction">Overproduction</option>
                                    <option value="expired">Expired</option>
                                    <option value="burnt">Burnt</option>
                                    <option value="spoiled">Spoiled</option>
                                    <option value="other">Other</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Waste Disposal Method -->
                        <div class="mb-2">
                            <label for="disposal_method_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Waste Disposal Method</label>
                            <select name="disposal_method"
                                id="disposal_method_<?= $productId ?>"
                                required
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                                <option value="">Select Disposal Method</option>
                                <?php if ($isIngredient): ?>
                                    <!-- Options for ingredients -->
                                    <option value="compost">Compost</option>
                                    <option value="trash">Trash</option>
                                    <option value="reused">Reused</option>
                                    <option value="other">Other</option>
                                <?php else: ?>
                                    <!-- Options for products -->
                                    <option value="donation">Donation</option>
                                    <option value="compost">Compost</option>
                                    <option value="trash">Trash</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Waste Date -->
                        <div class="mb-2">
                            <label for="waste_date_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Date of Waste Entry</label>
                            <input type="date"
                                name="waste_date"
                                id="waste_date_<?= $productId ?>"
                                required
                                value="<?= date('Y-m-d') ?>"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                        </div>

                        <!-- Responsible Person (Auto-filled) -->
                        <div class="mb-2">
                            <label for="responsible_person_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Responsible Person</label>
                            <input type="text"
                                name="responsible_person"
                                id="responsible_person_<?= $productId ?>"
                                value="<?= htmlspecialchars($userName) ?>"
                                readonly
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 bg-gray-50 focus:outline-none focus:ring-[#98c01d]">
                        </div>

                        <!-- Submit Button -->
                        <div class="mb-2">
                            <button type="submit" name="submitwaste" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">
                                Submit Waste
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>

            <?php if (empty($products)): ?>
                <div class="col-span-3 text-center py-10">
                    <p class="text-xl text-gray-500">No products or ingredients found.</p>
                    <p class="text-gray-400 mt-2">Add some products or ingredients first.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ingredients cards -->

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
            <?php foreach ($ingredients as $ingredient):

                // Set variables based on product type
                $ingredientId = $ingredient['id'];
                $ingredientName = $ingredient['ingredient_name'] ?? 'N/A';
                $ingredientCategory = $ingredient['category'] ?? 'N/A';
                $ingredientPrice = $ingredient['price_per_unit'] ?? 0;
                $ingredientImage = $ingredient['item_image'] ?? '';
                $ingredientDate = date('Y-m-d', strtotime($ingredient['stock_datetime']));

            ?>
                <div class="bg-white shadow-md rounded-lg p-3 card">
                    <!-- Type Badge -->
                    <div class="flex justify-between products-center mb-2">

                        <span class="px-2 py-1 rounded text-xs font-semibold <?= $isIngredient ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                            <?= ucfirst($ingredient['type']) ?>
                        </span>

                    </div>

                    <img src="<?= htmlspecialchars($ingredientImage) ?>"
                        alt="<?= htmlspecialchars($ingredientName) ?>"
                        class="h-32 w-full object-cover rounded-md">

                    <h2 class="text-lg font-bold mt-3"><?= htmlspecialchars($ingredientName) ?></h2>

                    <p class="text-gray-600">
                        Added on: <?= htmlspecialchars($ingredientDate) ?>
                    </p>
                    <p class="text-gray-600">
                        Price per Unit: ₱<?= htmlspecialchars(number_format($ingredientPrice, 2)) ?>
                    </p>

                    <!-- Waste Input Form -->
                    <form class=" mt-3" method="POST" action="">
                        <input type="hidden" name="ingredient_id" value="<?= htmlspecialchars($ingredientId) ?>">
                        <input type="hidden" name="ingredient_type" value="<?= htmlspecialchars($ingredient['type']) ?>">
                        <input type="hidden" name="ingredient_value" value="<?= htmlspecialchars($ingredientPrice) ?>">

                        <!-- Ingredient-specific fields -->
                        <div class="mb-2">
                            <label for="quantitypurchased<?= $itemId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Purchased
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="quantity_purchased"
                                    id="quantitypurchased<?= $itemId ?>"
                                    min="1"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="quantityused<?= $itemId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Used
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="quantity_used"
                                    id="quantityused<?= $itemId ?>"
                                    min="0"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>


                        <!-- ingredient-specific fields -->
                        <div class="mb-2">
                            <label for="quantity_produced_<?= $productId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Produced
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="quantity_produced"
                                    id="quantity_produced_<?= $productId ?>"
                                    min="1"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="quantity_sold_<?= $productId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Sold
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="quantity_sold"
                                    id="quantity_sold_<?= $productId ?>"
                                    min="0"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>

                        <!-- Common field: Quantity Wasted -->
                        <div class="mb-2">
                            <label for="waste_quantity_<?= $productId ?>" class="block text-sm font-medium text-gray-700">
                                Quantity Wasted
                            </label>
                            <div class="flex gap-2">
                                <input type="number"
                                    name="waste_quantity"
                                    id="waste_quantity_<?= $productId ?>"
                                    min="1"
                                    step="any"
                                    required
                                    class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                            </div>
                        </div>

                        <!-- Waste Reason -->
                        <div class="mb-2">
                            <label for="waste_reason_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Waste Reason</label>
                            <select name="waste_reason"
                                id="waste_reason_<?= $productId ?>"
                                required
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                                <option value="">Select Reason</option>
                                <?php if ($isIngredient): ?>
                                    <!-- Options for ingredients -->
                                    <option value="expired">Expired</option>
                                    <option value="spoiled">Spoiled</option>
                                    <option value="over-measured">Over-measured</option>
                                    <option value="contaminated">Contaminated</option>
                                    <option value="other">Other</option>
                                <?php else: ?>
                                    <!-- Options for products -->
                                    <option value="overproduction">Overproduction</option>
                                    <option value="expired">Expired</option>
                                    <option value="burnt">Burnt</option>
                                    <option value="spoiled">Spoiled</option>
                                    <option value="other">Other</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Waste Disposal Method -->
                        <div class="mb-2">
                            <label for="disposal_method_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Waste Disposal Method</label>
                            <select name="disposal_method"
                                id="disposal_method_<?= $productId ?>"
                                required
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                                <option value="">Select Disposal Method</option>
                                <?php if ($isIngredient): ?>
                                    <!-- Options for ingredients -->
                                    <option value="compost">Compost</option>
                                    <option value="trash">Trash</option>
                                    <option value="reused">Reused</option>
                                    <option value="other">Other</option>
                                <?php else: ?>
                                    <!-- Options for products -->
                                    <option value="donation">Donation</option>
                                    <option value="compost">Compost</option>
                                    <option value="trash">Trash</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Waste Date -->
                        <div class="mb-2">
                            <label for="waste_date_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Date of Waste Entry</label>
                            <input type="date"
                                name="waste_date"
                                id="waste_date_<?= $productId ?>"
                                required
                                value="<?= date('Y-m-d') ?>"
                                class="mt-1 block w-full border border-gray-300 rounded-md p-1.5 focus:outline-none focus:ring-[#98c01d]">
                        </div>

                        <!-- Responsible Person (Auto-filled) -->
                        <div class="mb-2">
                            <label for="responsible_person_<?= $productId ?>" class="block text-sm font-medium text-gray-700">Responsible Person</label>
                            <input type="text"
                                name="responsible_person"
                                id="responsible_person_<?= $productId ?>"
                                value="<?= htmlspecialchars($userName) ?>"
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

            <?php if (empty($products)): ?>
                <div class="col-span-3 text-center py-10">
                    <p class="text-xl text-gray-500">No products or ingredients found.</p>
                    <p class="text-gray-400 mt-2">Add some products or ingredients first.</p>
                </div>
            <?php endif; ?>
        </div>








    </div>
</body>

</html>
