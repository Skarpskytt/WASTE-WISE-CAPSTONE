<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Fetch the user's name from the session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$branchId = $_SESSION['branch_id'];

// Ensure upload directories exist
$uploadPath = __DIR__ . "/uploads/ingredients";
if (!is_dir($uploadPath)) {
    // Create the directory with full permissions (adjust as needed for security)
    mkdir($uploadPath, 0777, true);
}

// Initialize message variables
$successMessage = '';
$errorMessage = '';

// Update the query to use the correct column names
try {
    // Fetch only active ingredients (not expired and with stock > 0)
    $currentDate = date('Y-m-d');
    $ingStmt = $pdo->prepare("
        SELECT 
            i.*,
            COALESCE(SUM(w.waste_quantity), 0) as total_waste
        FROM ingredients i
        LEFT JOIN ingredients_waste w ON i.id = w.ingredient_id AND w.branch_id = ?
        WHERE i.branch_id = ? 
        AND (i.expiry_date IS NULL OR i.expiry_date > ?) 
        AND i.stock_quantity > 0 
        GROUP BY i.id
        ORDER BY i.id DESC
    ");
    $ingStmt->execute([$branchId, $branchId, $currentDate]);
    $ingredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving data: " . $e->getMessage());
}

// Remove responsible_person from the form processing
if (isset($_POST['submitwaste'])) {
    // Extract form data
    $userId = $_SESSION['user_id'];  // This is the staff_id of the logged-in user
    $ingredientId = $_POST['ingredient_id'] ?? null;
    $wasteDate = $_POST['waste_date'] ?? null;
    $wasteQuantity = $_POST['waste_quantity'] ?? null;
    $costPerUnit = $_POST['ingredient_value'] ?? 0;
    $wasteValue = $wasteQuantity * $costPerUnit;
    $wasteReason = $_POST['waste_reason'] ?? null;
    $branchId = $_SESSION['branch_id'];
    
    // Tracking fields
    $batchNumber = $_POST['batch_number'] ?? null;
    $productionStage = $_POST['production_stage'] ?? null;
    $disposalMethod = $_POST['disposal_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    // Validate form data - remove responsible_person from validation
    if (!$userId || !$ingredientId || !$wasteDate || !$wasteQuantity || !$wasteReason || 
        !$disposalMethod || !$productionStage) {
        $errorMessage = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Modify the INSERT query to use staff_id instead of responsible_person
            $stmt = $pdo->prepare("
                INSERT INTO ingredients_waste (
                    staff_id, ingredient_id, waste_date, waste_quantity, waste_value, 
                    waste_reason, batch_number, production_stage, disposal_method,
                    notes, created_at, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,  // Use staff_id directly
                $ingredientId,  
                date('Y-m-d H:i:s', strtotime($wasteDate)), 
                $wasteQuantity, 
                $wasteValue, 
                $wasteReason, 
                $batchNumber,
                $productionStage, 
                $disposalMethod, 
                $notes,
                date('Y-m-d H:i:s'),
                $branchId
            ]);
            
            // Update ingredient stock quantity
            $updateStock = $pdo->prepare("
                UPDATE ingredients 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ? AND branch_id = ?
            ");
            
            $updateStock->execute([$wasteQuantity, $ingredientId, $branchId]);
            
            // Commit transaction
            $pdo->commit();

            // Redirect to the record page after successful submission
            header('Location: waste_ingredients_input.php?success=1');
            exit;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errorMessage = 'An error occurred while submitting the waste entry: ' . $e->getMessage();
        }
    }

    // If we get here, there was an error (no redirect happened)
}

// Check if redirected back with success message
$showSuccessMessage = isset($_GET['success']) && $_GET['success'] == '1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingredient Waste Tracking - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
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
            
            // Auto-hide notification after 3 seconds
            setTimeout(function() {
                $('.notification').fadeOut();
            }, 3000);

            // Validate waste quantity doesn't exceed stock
            $('.waste-form').on('submit', function(e) {
                const wasteQty = parseFloat($(this).find('[name="waste_quantity"]').val());
                const availableStock = parseFloat($(this).find('[name="available_stock"]').val());
                
                if (wasteQty > availableStock) {
                    e.preventDefault();
                    alert('Error: Waste quantity cannot exceed available stock (' + availableStock + ' units)');
                }
            });
        });

        // Add this inside your existing script tag, after the $(document).ready function
        function generateBatchNumber() {
            const now = new Date();
            const year = now.getFullYear().toString().substring(2); // Get last 2 digits of year
            const month = (now.getMonth() + 1).toString().padStart(2, '0');
            const day = now.getDate().toString().padStart(2, '0');
            
            // Generate random 4-character string for uniqueness
            const randomChars = Math.random().toString(36).substring(2, 6).toUpperCase();
            
            // Format: B-YYMMDD-XXXX
            return `B-${year}${month}${day}-${randomChars}`;
        }

        // Generate a batch number for each form when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Get all batch number inputs
            const batchInputs = document.querySelectorAll('input[name="batch_number"]');
            
            // Set a unique batch number for each
            batchInputs.forEach(input => {
                input.value = generateBatchNumber();
            });
        });
    </script>

    <style>
        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
        }
        
        .notification-success {
            background-color: #47663B;
        }
        
        .notification-error {
            background-color: #ef4444;
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

<?php include ('../layout/staff_nav.php'); ?>

    <div class="p-5 w-full">
        <div>
        <nav class="mb-4">
      <ol class="flex items-center gap-2 text-gray-600">
        <li><a href="ingredients.php" class="hover:text-primarycol">Ingredients</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_input.php" class="hover:text-primarycol">Record Waste</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_record.php" class="hover:text-primarycol">View Ingredients Waste Records</a></li>
      </ol>
    </nav>
            <h1 class="text-3xl font-bold mb-2 text-primarycol">Bakery Ingredient Waste Tracking</h1>
            <p class="text-gray-500 mb-6">Record detailed waste information to identify patterns and reduce losses</p>
        </div>

        <!-- Notification Messages -->
        <?php if (!empty($errorMessage)): ?>
            <div class="notification notification-error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($showSuccessMessage): ?>
            <div class="notification notification-success">
                Ingredient waste entry submitted successfully.
            </div>
        <?php endif; ?>

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
                            SELECT i.ingredient_name, SUM(w.waste_quantity) as total_waste
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
                        <!-- Removed waste value display -->
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
    <?php
    // Fix image path to ensure browser can access it correctly
    $imagePath = $ingredientImage;
    
    if (strpos($imagePath, 'C:') === 0) {
        // For absolute Windows paths, extract just the filename from the path
        $filename = basename($imagePath);
        // Point to the correct web-accessible path
        $imagePath = './uploads/ingredients/' . $filename;
    } else if (strpos($imagePath, './uploads/') === 0) {
        // Path already starts with ./
        $imagePath = $ingredientImage;
    } else if (strpos($imagePath, 'uploads/') === 0) {
        // Path doesn't have ./ prefix, add it
        $imagePath = './' . $imagePath;
    } else {
        // For any other format, try to use the base filename
        $filename = basename($imagePath);
        $imagePath = './uploads/ingredients/' . $filename;
    }
    ?>
    <img src="<?= htmlspecialchars($imagePath) ?>"
        alt="<?= htmlspecialchars($ingredientName) ?>"
        class="h-32 w-full object-cover rounded-md mb-3">
<?php else: ?>
    <!-- Show default image if no ingredient image is available -->
    <img src="../../assets/images/default-ingredient.jpg"
        alt="<?= htmlspecialchars($ingredientName) ?>"
        class="h-32 w-full object-cover rounded-md mb-3">
<?php endif; ?>

                                <h2 class="text-lg font-bold"><?= htmlspecialchars($ingredientName) ?></h2>

                                <p class="text-gray-600 text-sm mt-2">
                                    Cost: ₱<?= htmlspecialchars(number_format($ingredientCost, 2)) ?> per <?= htmlspecialchars($ingredientUnit) ?>
                                </p>
                                
                                <!-- Updated stock information box -->
<div class="mt-3 p-2 bg-blue-50 rounded-md">
    <h3 class="font-medium text-blue-800 text-sm">Stock Information</h3>
    <div class="grid grid-cols-2 gap-1 mt-1 text-xs text-gray-600">
        <?php if (isset($ingredient['quantity_purchased'])): ?>
        <div>Original Stock:</div>
        <div class="text-right font-medium"><?= htmlspecialchars($ingredient['quantity_purchased']) ?> <?= htmlspecialchars($ingredientUnit) ?></div>
        <?php endif; ?>
        
        <?php if (isset($ingredient['total_waste'])): ?>
        <div>Wasted So Far:</div>
        <div class="text-right font-medium"><?= htmlspecialchars($ingredient['total_waste']) ?> <?= htmlspecialchars($ingredientUnit) ?></div>
        <?php endif; ?>
        
        <div class="font-medium text-blue-700">Current Stock:</div>
        <div class="text-right font-medium text-blue-700"><?= htmlspecialchars($ingredient['stock_quantity']) ?> <?= htmlspecialchars($ingredientUnit) ?></div>
    </div>
</div>

                            </div>
                            
                            <!-- Waste form -->
                            <div class="md:w-2/3 p-4">
                                <h3 class="font-bold text-primarycol mb-3">Record Waste</h3>
                                
                                <form method="POST" class="waste-form">
                                    <input type="hidden" name="ingredient_id" value="<?= htmlspecialchars($ingredientId) ?>">
                                    <input type="hidden" name="ingredient_value" value="<?= htmlspecialchars($ingredientCost) ?>">
                                    <input type="hidden" name="available_stock" value="<?= htmlspecialchars($ingredient['stock_quantity']) ?>">
                                    
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
                                                id="batch_number"
                                                readonly
                                                class="w-full border border-gray-300 rounded-md p-2 bg-gray-50 focus:outline-none focus:ring-primary focus:border-primary">
                                        </div>
                                        
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
                            <p class="text-xl text-gray-500">No active ingredients found.</p>
                            <p class="text-gray-400 mt-2">Add ingredients or ensure some have stock available and aren't expired.</p>
                            <a href="ingredients.php" class="inline-block mt-4 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700">
                                Manage Ingredients
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
