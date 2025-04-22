<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

checkAuth(['staff', 'company']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Initialize alert variables
$alertType = '';
$alertMessage = '';

// Get stock ID from URL parameter
$stockId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$stockId) {
    // No stock ID provided, redirect back to stock list
    header('Location: product_stocks.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Only validate and sanitize quantity
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

        // Validate required fields
        $errors = [];
        if ($quantity <= 0) {
            $errors[] = "Quantity must be greater than zero";
        }

        if (empty($errors)) {
            // Update only quantity in database
            $updateSql = "
                UPDATE product_stock
                SET quantity = :quantity
                WHERE id = :id AND branch_id = :branch_id
            ";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':quantity' => $quantity,
                ':id' => $stockId,
                ':branch_id' => $branchId
            ]);
            
            // Check if update was successful
            if ($updateStmt->rowCount() > 0) {
                $pdo->commit();
                $alertType = 'success';
                $alertMessage = 'Stock quantity updated successfully!';
            } else {
                throw new Exception('No changes were made or stock not found');
            }
        } else {
            // Form has validation errors
            $alertType = 'error';
            $alertMessage = 'Please fix the following errors: ' . implode(', ', $errors);
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $alertType = 'error';
        $alertMessage = 'An error occurred: ' . $e->getMessage();
    }
}

// Get stock details with product info for display
$sql = "
    SELECT 
        ps.*,
        pi.name,
        pi.category,
        pi.price_per_unit,
        pi.image,
        b.name as branch_name
    FROM product_stock ps
    JOIN product_info pi ON ps.product_info_id = pi.id
    JOIN branches b ON ps.branch_id = b.id
    WHERE ps.id = :stockId AND ps.branch_id = :branchId
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':stockId' => $stockId,
    ':branchId' => $branchId
]);

$stock = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stock) {
    // Stock not found or doesn't belong to user's branch
    header('Location: product_stocks.php');
    exit;
}

// Calculate expiry status and days remaining
$today = strtotime(date('Y-m-d'));
$expiryDate = strtotime($stock['expiry_date']);
$daysUntilExpiry = round(($expiryDate - $today) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Stock Quantity - Bea Bakes</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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
    </script>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include ('../layout/staff_nav.php'); ?>

    <div class="p-7 w-full overflow-y-auto">
        <nav class="mb-4">
            <ol class="flex items-center gap-2 text-gray-600">
                <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="product_stocks.php" class="hover:text-primarycol">Product Stocks</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="view_stock.php?id=<?= $stockId ?>" class="hover:text-primarycol">View Stock</a></li>
                <li class="text-gray-400">/</li>
                <li class="text-primarycol font-medium">Edit Stock Quantity</li>
            </ol>
        </nav>
        
        <!-- Alert Messages -->
        <?php if (!empty($alertMessage)): ?>
        <div class="mb-4 px-4 py-3 rounded-lg <?= $alertType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
            <?= $alertMessage ?>
        </div>
        <?php endif; ?>
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-primarycol">Edit Stock Quantity</h1>
            <div class="flex gap-2">
                <a href="view_stock.php?id=<?= $stockId ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Back to Stock Details
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Product Info (Left) -->
            <div class="col-span-1 bg-white rounded-lg shadow p-6">
                <div class="mb-4">
                    <?php 
                    $imagePath = !empty($stock['image']) ? $stock['image'] : '../../assets/images/Company Logo.jpg';
                    ?>
                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($stock['name']) ?>" 
                         class="w-full h-52 object-cover rounded-lg">
                </div>
                
                <h2 class="text-xl font-bold mb-1"><?= htmlspecialchars($stock['name']) ?></h2>
                <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($stock['category']) ?></p>
                
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Batch Number:</span>
                    <span class="font-mono font-medium"><?= htmlspecialchars($stock['batch_number']) ?></span>
                </div>
                
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Price per Unit:</span>
                    <span class="font-medium">₱<?= number_format($stock['price_per_unit'], 2) ?></span>
                </div>

                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Unit Type:</span>
                    <span class="font-medium"><?= ucfirst($stock['unit_type']) ?></span>
                </div>
                
                <?php if (!empty($stock['pieces_per_box']) && $stock['unit_type'] === 'box'): ?>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Pieces per Box:</span>
                    <span class="font-medium"><?= $stock['pieces_per_box'] ?></span>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Branch:</span>
                    <span class="font-medium"><?= htmlspecialchars($stock['branch_name']) ?></span>
                </div>
                
                <div class="mt-6">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Note:</h3>
                        <p class="text-xs text-blue-800">
                            Only the quantity can be edited. Other product attributes cannot be modified.
                            If you need to change other details, please contact your administrator.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form (Right) -->
            <div class="col-span-2 bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold mb-6">Update Stock Quantity</h2>
                
                <form method="POST" class="space-y-6">
                    <!-- Quantity (only editable field) -->
                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">
                            Quantity <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="quantity" name="quantity" value="<?= $stock['quantity'] ?>" min="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primarycol focus:border-primarycol">
                        <p class="mt-1 text-sm text-gray-500">Enter the new stock quantity</p>
                    </div>
                    
                    <!-- Display-only fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Production Date</label>
                            <input type="date" value="<?= $stock['production_date'] ?>" disabled
                                   class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-500 cursor-not-allowed">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Production Time</label>
                            <input type="time" value="<?= $stock['production_time'] ?? '' ?>" disabled
                                   class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-500 cursor-not-allowed">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Best Before</label>
                            <input type="date" value="<?= $stock['best_before'] ?? '' ?>" disabled
                                   class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-500 cursor-not-allowed">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                            <input type="date" value="<?= $stock['expiry_date'] ?>" disabled
                                   class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-500 cursor-not-allowed">
                            <?php if ($daysUntilExpiry <= 7): ?>
                            <p class="mt-1 text-xs <?= $daysUntilExpiry < 0 ? 'text-red-600' : 'text-amber-600' ?>">
                                <?= $daysUntilExpiry < 0 ? 'Expired ' . abs($daysUntilExpiry) . ' days ago' : $daysUntilExpiry . ' days until expiry' ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="view_stock.php?id=<?= $stockId ?>" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-primarycol text-white rounded-md hover:bg-fourth">
                            Update Quantity
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quick Tips -->
        <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Inventory Management Tips</h3>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <ul class="list-disc list-inside space-y-1 text-sm text-blue-800">
                    <li>Update stock quantity when performing physical inventory counts</li>
                    <li>For depleted stock, set quantity to 0 rather than deleting the record</li>
                    <li>Ensure accurate counts to maintain proper inventory tracking</li>
                    <li>Regular stock audits help minimize inventory discrepancies</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>