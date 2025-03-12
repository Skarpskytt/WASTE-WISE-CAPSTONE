<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Get branch ID from session
$branchId = $_SESSION['branch_id'];

// Handle delete action
if (isset($_POST['delete_waste'])) {
    $wasteId = $_POST['waste_id'] ?? 0;
    
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM ingredients_waste WHERE id = ? AND branch_id = ?");
        $deleteStmt->execute([$wasteId, $branchId]);
        
        $deleteSuccess = "Ingredient waste record deleted successfully!";
    } catch (PDOException $e) {
        $deleteError = "Error deleting waste record: " . $e->getMessage();
    }
}

// Handle edit/update action
if (isset($_POST['update_waste'])) {
    $wasteId = $_POST['waste_id'] ?? 0;
    $wasteQuantity = $_POST['waste_quantity'] ?? null;
    $wasteDate = $_POST['waste_date'] ?? null;
    $wasteReason = $_POST['waste_reason'] ?? null;
    $productionStage = $_POST['production_stage'] ?? null;
    $disposalMethod = $_POST['disposal_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $costPerUnit = $_POST['ingredient_value'] ?? 0;
    $wasteValue = $wasteQuantity * $costPerUnit;
    
    try {
        $updateStmt = $pdo->prepare("
            UPDATE ingredients_waste 
            SET waste_quantity = ?, 
                waste_date = ?,
                waste_reason = ?,
                production_stage = ?,
                disposal_method = ?,
                notes = ?,
                waste_value = ?
            WHERE id = ? AND branch_id = ?
        ");
        
        $updateStmt->execute([
            $wasteQuantity,
            date('Y-m-d H:i:s', strtotime($wasteDate)),
            $wasteReason,
            $productionStage,
            $disposalMethod,
            $notes,
            $wasteValue,
            $wasteId,
            $branchId
        ]);
        
        $updateSuccess = "Waste record updated successfully!";
    } catch (PDOException $e) {
        $updateError = "Error updating waste record: " . $e->getMessage();
    }
}

// Handle filters (search + date range)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Build the SQL query to get waste records
$sql = "
    SELECT w.*, i.ingredient_name, i.category, i.unit,
           i.cost_per_unit, i.item_image,
           CONCAT(u.fname, ' ', u.lname) as staff_name
    FROM ingredients_waste w
    JOIN ingredients i ON w.ingredient_id = i.id
    JOIN users u ON w.user_id = u.id
    WHERE w.branch_id = ?
";

$params = [$branchId];

// Apply search filter
if (!empty($search)) {
    $sql .= " AND (i.ingredient_name LIKE ? OR w.waste_reason LIKE ? OR w.disposal_method LIKE ? OR w.production_stage LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply date filter
if (!empty($start_date)) {
    $sql .= " AND DATE(w.waste_date) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $sql .= " AND DATE(w.waste_date) <= ?";
    $params[] = $end_date;
}

$sql .= " ORDER BY w.waste_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $wasteRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error retrieving waste records: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Ingredient Waste Records - WasteWise</title>
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
            
            // Open edit modal and populate form
            $('.edit-waste-btn').on('click', function() {
                const wasteId = $(this).data('id');
                const wasteQuantity = $(this).data('waste-quantity');
                const wasteDate = $(this).data('waste-date');
                const wasteReason = $(this).data('waste-reason');
                const productionStage = $(this).data('production-stage');
                const disposalMethod = $(this).data('disposal-method');
                const notes = $(this).data('notes');
                const ingredientValue = $(this).data('ingredient-value');
                
                // Set the values in the edit form
                $('#edit_waste_id').val(wasteId);
                $('#edit_waste_quantity').val(wasteQuantity);
                $('#edit_waste_date').val(wasteDate.split(' ')[0]); // Get only the date part
                $('#edit_waste_reason').val(wasteReason);
                $('#edit_production_stage').val(productionStage);
                $('#edit_disposal_method').val(disposalMethod);
                $('#edit_notes').val(notes);
                $('#edit_ingredient_value').val(ingredientValue);
                
                // Open the modal
                $('#edit_modal').show();
            });
            
            $('.close-modal').on('click', function() {
                $('.modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').hide();
                }
            });
        });
    </script>
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            padding: 50px;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: auto;
            width: 100%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body class="flex min-h-screen bg-gray-50">
    <?php include(__DIR__ . '/../layout/staff_nav.php'); ?>

    <div class="p-6 w-full">
        <div class="mb-6">
        <nav class="mb-4">
      <ol class="flex items-center gap-2 text-gray-600">
        <li><a href="ingredients.php" class="hover:text-primarycol">Ingredients</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_input.php" class="hover:text-primarycol">Record Waste</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_ingredients_record.php" class="hover:text-primarycol">View Ingredients Waste Records</a></li>
      </ol>
    </nav>
            <h1 class="text-3xl font-bold mb-2 text-primarycol">Ingredient Waste Records</h1>
            <p class="text-gray-500">View and manage all waste records for bakery ingredients</p>
        </div>

        <!-- Display success or error messages -->
        <?php if (!empty($updateSuccess)): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
                <?= htmlspecialchars($updateSuccess) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($deleteSuccess)): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
                <?= htmlspecialchars($deleteSuccess) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($updateError)): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
                <?= htmlspecialchars($updateError) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($deleteError)): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
                <?= htmlspecialchars($deleteError) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Form -->
        <form method="GET" class="flex flex-wrap gap-3 items-end mb-6">
            <div>
                <label for="search" class="block mb-1 text-sm font-medium">Search</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="border w-60 p-2 rounded focus:ring focus:border-primarycol"
                    placeholder="Ingredient name, reason..." />
            </div>

            <div>
                <label for="start_date" class="block mb-1 text-sm font-medium">From</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                    class="border p-2 rounded focus:ring focus:border-primarycol" />
            </div>

            <div>
                <label for="end_date" class="block mb-1 text-sm font-medium">To</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                    class="border p-2 rounded focus:ring focus:border-primarycol" />
            </div>
            
            <div>
                <button type="submit" class="bg-primarycol text-white px-4 py-2 rounded hover:bg-fourth">
                    Search
                </button>
            </div>
            
            <div class="ml-auto">
                <a href="waste_ingredients_input.php" class="inline-block bg-primarycol text-white px-4 py-2 rounded hover:bg-fourth">
                    + Add New Ingredient Waste
                </a>
            </div>
        </form>

        <div class="overflow-x-auto w-full">
            <div class="bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200">
                <div class="overflow-x-auto p-4">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr class="bg-sec">
                                <th>#</th>
                                <th>Ingredient</th>
                                <th>Category</th>
                                <th>Waste Date</th>
                                <th>Quantity (Unit)</th>
                                <!-- Removed Waste Value column - admin only -->
                                <th>Production Stage</th>
                                <th>Reason</th>
                                <th>Disposal Method</th>
                                <th>Staff</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($wasteRecords)) {
                                $count = 1;
                                foreach ($wasteRecords as $record) {
                                    // Format the waste date
                                    $formattedDate = date('M d, Y', strtotime($record['waste_date']));
                                    
                                    echo "<tr>";
                                    echo "<td>" . $count++ . "</td>";
                                    echo "<td>" . htmlspecialchars($record['ingredient_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($record['category']) . "</td>";
                                    echo "<td>" . htmlspecialchars($formattedDate) . "</td>";
                                    echo "<td>" . htmlspecialchars($record['waste_quantity']) . " " . htmlspecialchars($record['unit']) . "</td>";
                                    // Removed waste value display - admin only
                                    echo "<td>" . htmlspecialchars(ucfirst($record['production_stage'])) . "</td>";
                                    echo "<td>" . htmlspecialchars(ucfirst($record['waste_reason'])) . "</td>";
                                    echo "<td>" . htmlspecialchars(ucfirst($record['disposal_method'])) . "</td>";
                                    echo "<td>" . htmlspecialchars($record['staff_name']) . "</td>";
                                    echo "<td class='p-2'>
                                            <div class='flex justify-center space-x-2'>
                                                <button 
                                                    class='edit-waste-btn rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center'
                                                    data-id='" . $record['id'] . "'
                                                    data-waste-quantity='" . $record['waste_quantity'] . "'
                                                    data-waste-date='" . $record['waste_date'] . "'
                                                    data-waste-reason='" . $record['waste_reason'] . "'
                                                    data-production-stage='" . $record['production_stage'] . "'
                                                    data-disposal-method='" . $record['disposal_method'] . "'
                                                    data-notes='" . htmlspecialchars($record['notes']) . "'
                                                    data-ingredient-value='" . ($record['waste_value'] / $record['waste_quantity']) . "'>
                                                    <!-- Edit Icon -->
                                                    <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13' />
                                                    </svg>
                                                    Edit
                                                </button>
                                                
                                                <form method='POST' onsubmit='return confirm(\"Are you sure you want to delete this waste record?\");' class='inline'>
                                                    <input type='hidden' name='waste_id' value='" . $record['id'] . "'>
                                                    <button type='submit' name='delete_waste' class='rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center'>
                                                        <!-- Delete Icon -->
                                                        <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12' />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='11' class='text-center py-4'>No ingredient waste records found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="edit_modal" class="modal">
        <div class="modal-content p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Edit Ingredient Waste Record</h3>
                <button class="close-modal text-gray-600 hover:text-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" id="edit_waste_id" name="waste_id">
                <input type="hidden" id="edit_ingredient_value" name="ingredient_value">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Quantity Wasted
                        </label>
                        <input type="number"
                            id="edit_waste_quantity"
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
                            id="edit_waste_date"
                            name="waste_date"
                            required
                            class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Production Stage
                        </label>
                        <select 
                            id="edit_production_stage"
                            name="production_stage"
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
                        <select 
                            id="edit_waste_reason"
                            name="waste_reason"
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
                        <select 
                            id="edit_disposal_method"
                            name="disposal_method"
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
                            id="edit_notes"
                            name="notes"
                            placeholder="Additional details about this waste incident"
                            class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary"
                            rows="2"
                        ></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" class="close-modal px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="submit" name="update_waste" class="px-4 py-2 bg-primarycol text-white rounded-md hover:bg-fourth">
                        Update Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>



