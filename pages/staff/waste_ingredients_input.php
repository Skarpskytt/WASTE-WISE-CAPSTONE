<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Fetch the user's name from the session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$branchId = $_SESSION['branch_id'];

try {
    // Fetch ingredients from ingredients table
    $ingStmt = $pdo->prepare("SELECT * FROM ingredients WHERE branch_id = ? ORDER BY stock_datetime DESC");
    $ingStmt->execute([$branchId]);
    $ingredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving data: " . $e->getMessage());
}

if (isset($_POST['submitwaste'])) {
    // Extract form data
    $userId = $_SESSION['user_id'];
    $ingredientId = $_POST['ingredient_id'] ?? null;
    $wasteDate = $_POST['waste_date'] ?? null;
    $wasteQuantity = $_POST['waste_quantity'] ?? null;
    $costPerUnit = $_POST['ingredient_value'] ?? 0;
    $wasteValue = $wasteQuantity * $costPerUnit;
    $wasteReason = $_POST['waste_reason'] ?? null;
    $responsiblePerson = $_POST['responsible_person'] ?? null;
    $branchId = $_SESSION['branch_id'];
    
    // Tracking fields
    $batchNumber = $_POST['batch_number'] ?? null;
    $productionStage = $_POST['production_stage'] ?? null;
    $disposalMethod = $_POST['disposal_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    // Validate form data
    if (!$userId || !$ingredientId || !$wasteDate || !$wasteQuantity || !$wasteReason || !$responsiblePerson) {
        $response = [
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ];
    } else {
        // Insert waste entry into the ingredients_waste table
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ingredients_waste (
                    user_id, ingredient_id, waste_date, waste_quantity, waste_value, 
                    waste_reason, responsible_person, created_at, branch_id,
                    production_stage, disposal_method, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId, 
                $ingredientId,  
                date('Y-m-d H:i:s', strtotime($wasteDate)), 
                $wasteQuantity, 
                $wasteValue, 
                $wasteReason, 
                $responsiblePerson, 
                date('Y-m-d H:i:s'), 
                $branchId,
                $productionStage, 
                $disposalMethod, 
                $notes
            ]);

            $response = [
                'success' => true,
                'message' => 'Waste entry submitted successfully.'
            ];
        } catch (PDOException $e) {
            $response = [
                'success' => false,
                'message' => 'An error occurred while submitting the waste entry: ' . $e->getMessage()
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
    <title>Ingredient Waste Tracking - WasteWise</title>
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
            // Sidebar toggling
            $('#toggleSidebar').on('click', function() {
                $('#sidebar').toggleClass('-translate-x-full');
            });

            $('#closeSidebar').on('click', function() {
                $('#sidebar').addClass('-translate-x-full');
            });

            // Notification system
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
                }, 3000);
            }
            
            // Handle form submission with AJAX
            $('.waste-form').on('submit', function(e) {
                e.preventDefault();
                
                let form = $(this);
                let formData = form.serialize();
                
                $.ajax({
                    type: 'POST',
                    url: 'waste_ingredients_input.php',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showNotification(response.message, true);
                            form[0].reset();
                        } else {
                            showNotification(response.message, false);
                        }
                    },
                    error: function() {
                        showNotification('An unexpected error occurred.', false);
                    }
                });
            });
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
        
        /* Form section styling */
        .form-section {
            @apply bg-white p-4 rounded-lg shadow mb-4;
        }
        
        .form-section-title {
            @apply text-lg font-semibold mb-3 text-gray-800 border-b pb-2;
        }
    </style>
</head>

<body class="flex min-h-screen bg-gray-50">

    <?php include(__DIR__ . '/../layout/staff_nav.php'); ?>

    <div class="p-5 w-full">
        <div>
            <h1 class="text-3xl font-bold mb-2 text-primarycol">Bakery Ingredient Waste Tracking</h1>
            <p class="text-gray-500 mb-6">Record detailed waste information to identify patterns and reduce losses</p>
        </div>

        <!-- Notification Container -->
        <div id="notification"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left sidebar - Statistics -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-lg shadow mb-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Waste Tracking Tips</h2>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-700">Why track ingredient waste?</h3>
                        <ul class="list-disc pl-5 mt-2 text-gray-600 text-sm">
                            <li>Identify costly ingredients that are frequently wasted</li>
                            <li>Spot patterns in production processes causing waste</li>
                            <li>Track disposal methods for sustainability reporting</li>
                            <li>Calculate financial impact of ingredient waste</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-700">Best practices:</h3>
                        <ul class="list-disc pl-5 mt-2 text-gray-600 text-sm">
                            <li>Record waste immediately after it occurs</li>
                            <li>Be specific about production stage and batch info</li>
                            <li>Include detailed notes about unusual circumstances</li>
                            <li>Track all waste, even small amounts</li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-lg shadow">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Quick Stats</h2>
                    
                    <?php
                    // Most commonly wasted ingredient
                    try {
                        $topWasteStmt = $pdo->prepare("
                            SELECT i.ingredient_name, SUM(w.waste_quantity) as total_waste,
                            SUM(w.waste_value) as total_value
                            FROM ingredients_waste w
                            JOIN ingredients i ON w.ingredient_id = i.id
                            WHERE w.branch_id = ?
                            GROUP BY w.ingredient_id
                            ORDER BY total_waste DESC
                            LIMIT 1
                        ");
                        $topWasteStmt->execute([$branchId]);
                        $topWaste = $topWasteStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Most common waste reason
                        $reasonStmt = $pdo->prepare("
                            SELECT waste_reason, COUNT(*) as count
                            FROM ingredients_waste
                            WHERE branch_id = ?
                            GROUP BY waste_reason
                            ORDER BY count DESC
                            LIMIT 1
                        ");
                        $reasonStmt->execute([$branchId]);
                        $topReason = $reasonStmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Silently fail, stats are not critical
                    }
                    ?>
                    
                    <?php if (!empty($topWaste)): ?>
                    <div class="stat-box bg-gray-50 p-3 rounded mb-3">
                        <p class="text-sm text-gray-500">Most wasted ingredient:</p>
                        <p class="font-bold"><?= htmlspecialchars($topWaste['ingredient_name']) ?></p>
                        <p class="text-sm"><?= number_format($topWaste['total_waste'], 2) ?> units wasted</p>
                        <p class="text-sm">₱<?= number_format($topWaste['total_value'], 2) ?> value lost</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($topReason)): ?>
                    <div class="stat-box bg-gray-50 p-3 rounded">
                        <p class="text-sm text-gray-500">Most common waste reason:</p>
                        <p class="font-bold"><?= ucfirst(htmlspecialchars($topReason['waste_reason'])) ?></p>
                        <p class="text-sm"><?= $topReason['count'] ?> occurrences</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right section - Ingredient cards with waste forms -->
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Record Ingredient Waste</h2>
                
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($ingredients as $ingredient):
                        $ingredientId = $ingredient['id'];
                        $ingredientName = $ingredient['ingredient_name'] ?? 'N/A';
                        $ingredientCategory = $ingredient['category'] ?? 'N/A';
                        $ingredientCost = $ingredient['cost_per_unit'] ?? 0;
                        $ingredientUnit = $ingredient['unit'] ?? '';
                        $ingredientImage = $ingredient['item_image'] ?? '';
                    ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="flex flex-col md:flex-row">
                            <!-- Ingredient info -->
                            <div class="md:w-1/3 p-4 bg-gray-50">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($ingredientCategory) ?>
                                    </span>
                                </div>
                                
                                <?php if(!empty($ingredientImage)): ?>
                                <img src="../../<?= htmlspecialchars($ingredientImage) ?>"
                                    alt="<?= htmlspecialchars($ingredientName) ?>"
                                    class="h-32 w-full object-cover rounded-md mb-3">
                                <?php endif; ?>

                                <h2 class="text-lg font-bold"><?= htmlspecialchars($ingredientName) ?></h2>

                                <p class="text-gray-600 text-sm mt-2">
                                    Cost: ₱<?= htmlspecialchars(number_format($ingredientCost, 2)) ?> per <?= htmlspecialchars($ingredientUnit) ?>
                                </p>
                            </div>
                            
                            <!-- Waste form -->
                            <div class="md:w-2/3 p-4">
                                <h3 class="font-bold text-primarycol mb-3">Record Waste</h3>
                                
                                <form class="waste-form" method="POST">
                                    <input type="hidden" name="ingredient_id" value="<?= htmlspecialchars($ingredientId) ?>">
                                    <input type="hidden" name="ingredient_value" value="<?= htmlspecialchars($ingredientCost) ?>">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <!-- Basic waste info -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Quantity Wasted (<?= htmlspecialchars($ingredientUnit) ?>)
                                            </label>
                                            <input type="number"
                                                name="waste_quantity"
                                                min="0.01"
                                                step="any"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Date of Waste
                                            </label>
                                            <input type="date"
                                                name="waste_date"
                                                required
                                                value="<?= date('Y-m-d') ?>"
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                        </div>
                                        
                                        <!-- Bakery specific tracking -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Batch Number
                                            </label>
                                            <input type="text"
                                                name="batch_number"
                                                placeholder="e.g. B220301-1"
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                        </div>
                                        
                                        <!-- Remove recipe dropdown completely -->
                                        
                                        <!-- Keep production stage - useful for tracking where waste occurs -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Production Stage
                                            </label>
                                            <select name="production_stage"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                                <option value="">Select Stage</option>
                                                <option value="preparation">Ingredient Preparation</option>
                                                <option value="mixing">Mixing/Dough Making</option>
                                                <option value="proofing">Proofing</option>
                                                <option value="baking">Baking</option>
                                                <option value="finishing">Finishing/Decorating</option>
                                                <option value="storage">Storage</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Waste Reason
                                            </label>
                                            <select name="waste_reason"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                                <option value="">Select Reason</option>
                                                <option value="expired">Expired</option>
                                                <option value="spoiled">Spoiled</option>
                                                <option value="over-measured">Over-measured</option>
                                                <option value="failed_batch">Failed Batch</option>
                                                <option value="spilled">Spilled During Production</option>
                                                <option value="quality_control">Quality Control Rejection</option>
                                                <option value="contaminated">Contaminated</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Disposal Method
                                            </label>
                                            <select name="disposal_method"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                                <option value="">Select Method</option>
                                                <option value="compost">Compost</option>
                                                <option value="trash">Trash</option>
                                                <option value="donation">Food Donation</option>
                                                <option value="animal_feed">Animal Feed</option>
                                                <option value="repurposed">Repurposed</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Notes (optional)
                                            </label>
                                            <textarea 
                                                name="notes"
                                                placeholder="Additional details about this waste incident"
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary"
                                                rows="2"
                                            ></textarea>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="responsible_person" value="<?= htmlspecialchars($userName) ?>">
                                    
                                    <div class="mt-4">
                                        <button type="submit" name="submitwaste" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-700 transition-colors">
                                            Record Waste Entry
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($ingredients)): ?>
                        <div class="text-center py-10 bg-white rounded-lg shadow p-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="text-xl text-gray-500">No ingredients found.</p>
                            <p class="text-gray-400 mt-2">Add ingredients in the Ingredients section first.</p>
                            <a href="ingredients.php" class="inline-block mt-4 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700">
                                Add Ingredients
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
