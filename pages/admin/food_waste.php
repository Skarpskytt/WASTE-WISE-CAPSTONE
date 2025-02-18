<?php
// food_waste.php
session_start();
include('../../config/db_connect.php');
require '../../vendor/autoload.php';
require_once 'notification_handler.php'; // Include notification handler

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Fetch NGOs
$ngoQuery = "SELECT id, name FROM ngos";
$ngoStmt = $pdo->prepare($ngoQuery);
$ngoStmt->execute();
$ngos = $ngoStmt->fetchAll(PDO::FETCH_ASSOC);

// Update the waste query to use item_id and handle both products and ingredients
$wasteQuery = "
    SELECT 
        waste.id,
        COALESCE(inventory.name, ingredients.ingredient_name) AS food_type,
        waste.waste_quantity,
        COALESCE(inventory.image, ingredients.item_image) AS image,
        waste.item_type
    FROM waste
    LEFT JOIN inventory ON waste.item_id = inventory.id AND waste.item_type = 'product'
    LEFT JOIN ingredients ON waste.item_id = ingredients.id AND waste.item_type = 'ingredient'
    WHERE waste.waste_reason = 'overproduction' 
    AND waste.waste_quantity > 0
    AND waste.status = 'pending'
";
$wasteStmt = $pdo->prepare($wasteQuery);
$wasteStmt->execute();
$wastedItems = $wasteStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize variables for notifications
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $selectedItems = $_POST['items'] ?? [];
        $quantities = $_POST['quantity_to_donate'] ?? [];
        $ngo_id = $_POST['ngo_id'] ?? null;
        $preferred_date = $_POST['preferred_date'] ?? null;
        $preferred_time = $_POST['preferred_time'] ?? null;
        $notes = $_POST['notes'] ?? null;
        $expiry_date = $_POST['expiry_date'] ?? null;

        // Validate required fields
        if (empty($ngo_id) || empty($selectedItems)) {
            throw new Exception("Missing required fields");
        }

        foreach ($selectedItems as $item_id) {
            $quantity = floatval($quantities[$item_id] ?? 0);
            if ($quantity <= 0) continue;

            // Check waste quantity
            $wasteCheckStmt = $pdo->prepare("SELECT waste_quantity, item_id, item_type FROM waste WHERE id = ?");
            $wasteCheckStmt->execute([$item_id]);
            $waste = $wasteCheckStmt->fetch(PDO::FETCH_ASSOC);

            // Update the food type check in the POST handling section
            if ($waste && $waste['waste_quantity'] >= $quantity) {
                // Get food type based on item type
                if ($waste['item_type'] === 'product') {
                    $foodTypeStmt = $pdo->prepare("SELECT name FROM inventory WHERE id = ?");
                } else {
                    $foodTypeStmt = $pdo->prepare("SELECT ingredient_name AS name FROM ingredients WHERE id = ?");
                }
                $foodTypeStmt->execute([$waste['item_id']]);
                $foodType = $foodTypeStmt->fetchColumn();

                if (!$foodType) {
                    throw new Exception("Item not found");
                }

                // Insert donation
                $insertStmt = $pdo->prepare("
                    INSERT INTO donations (
                        ngo_id, 
                        waste_id,
                        food_type,
                        quantity, 
                        preferred_date, 
                        preferred_time, 
                        notes, 
                        expiry_date, 
                        status,
                        created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW()
                    )
                ");

                $insertStmt->execute([
                    $ngo_id,
                    $item_id,
                    $foodType,
                    $quantity,
                    $preferred_date,
                    $preferred_time,
                    $notes,
                    $expiry_date
                ]);

                // Get donation ID and send notification
                $donationId = $pdo->lastInsertId();
                $notificationSent = sendDonationNotification($pdo, $donationId);

                // Update waste quantity
                $updateWasteStmt = $pdo->prepare("
                    UPDATE waste 
                    SET waste_quantity = waste_quantity - ? 
                    WHERE id = ?
                ");
                $updateWasteStmt->execute([$quantity, $item_id]);
            } else {
                throw new Exception("Insufficient quantity available");
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Donation created successfully" . 
            ($notificationSent ? " and notification sent" : " but notification failed");
        header('Location: donations.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Waste Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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

            // Enable/disable quantity input based on checkbox selection
            $('.select-item').on('change', function() {
                var waste_id = $(this).data('id');
                var max_quantity = $(this).data('max');
                var quantityInput = $('#quantity_to_donate_' + waste_id);

                if ($(this).is(':checked')) {
                    quantityInput.prop('disabled', false);
                    quantityInput.attr('max', max_quantity);
                    quantityInput.focus();
                } else {
                    quantityInput.prop('disabled', true);
                    quantityInput.val('');
                }
            });
        });
    </script>
</head>

<body class="flex h-screen bg-slate-100">

    <?php include '../layout/nav.php' ?>

    <div class="flex-1 p-6 overflow-auto">
        <h1 class="text-3xl font-bold mb-6 text-primarycol">Create Donation</h1>

        <!-- Notification Section -->
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                <span><?= $error ?></span>
            </div>
        <?php elseif($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-lg rounded-lg p-8 border border-gray-200">
            <form method="POST" class="space-y-8">
                <!-- Donation Request Details -->
                <div>
                    <h2 class="text-2xl font-semibold mb-4 text-primarycol">Donation Request Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="ngo_id" class="block text-sm font-medium text-gray-700">Select NGO</label>
                            <select id="ngo_id" name="ngo_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol">
                                <option value="">-- Select NGO --</option>
                                <?php foreach($ngos as $ngo): ?>
                                    <option value="<?= htmlspecialchars($ngo['id']) ?>"><?= htmlspecialchars($ngo['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="preferred_date" class="block text-sm font-medium text-gray-700">Preferred Date</label>
                            <input type="date" id="preferred_date" name="preferred_date" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol">
                        </div>
                        <div>
                            <label for="preferred_time" class="block text-sm font-medium text-gray-700">Preferred Time</label>
                            <input type="time" id="preferred_time" name="preferred_time" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol">
                        </div>
                        <div>
                            <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol">
                        </div>
                    </div>
                </div>

                <!-- Select Items to Donate -->
                <div>
                    <h2 class="text-2xl font-semibold mb-4 text-primarycol">Select Items to Donate</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($wastedItems as $item): ?>
                            <div class="border border-gray-300 rounded-lg p-4 flex items-center">
                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['food_type']) ?>" class="w-16 h-16 object-cover rounded mr-4">
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium text-gray-800"><?= htmlspecialchars($item['food_type']) ?></h3>
                                    <p class="text-sm text-gray-600">Available: <?= htmlspecialchars($item['waste_quantity']) ?></p>
                                    <input type="number" name="quantity_to_donate[<?= htmlspecialchars($item['id']) ?>]" min="1" max="<?= htmlspecialchars($item['waste_quantity']) ?>" placeholder="Quantity" class="mt-2 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol">
                                </div>
                                <div class="ml-4">
                                    <input 
                                        type="checkbox" 
                                        name="items[]" 
                                        value="<?= htmlspecialchars($item['id']) ?>" 
                                        class="checkbox checkbox-primary"
                                        data-id="<?= htmlspecialchars($item['id']) ?>"
                                        data-max="<?= htmlspecialchars($item['waste_quantity']) ?>"
                                    >
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Additional Notes</label>
                    <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-primarycol focus:border-primarycol" placeholder="Enter any additional information..."></textarea>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-center">
                    <button type="submit" class="w-5/12 bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">
                        Create Donation
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>