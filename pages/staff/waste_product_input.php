<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

$pdo = getPDO();

// Fetch the user's name from the session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$branchId = $_SESSION['branch_id'];

// Initialize message variables
$successMessage = '';
$errorMessage = '';

// Update the SQL query to include batch number, expiry date and days until expiry
try {
    $prodStmt = $pdo->prepare("
        SELECT 
            p.*,
            p.stock_quantity as available_quantity,
            DATEDIFF(p.expiry_date, CURRENT_DATE()) AS days_until_expiry,
            p.created_at AS stock_date
        FROM products p
        WHERE p.branch_id = ? 
        AND p.expiry_date >= CURRENT_DATE()
        AND p.stock_quantity > 0
        AND (p.is_archived = 0 OR p.is_archived IS NULL)  /* Add this line to exclude archived products */
        ORDER BY p.expiry_date ASC, p.name ASC
    ");
    $prodStmt->execute([$branchId]);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Determine earliest expiry date for FEFO indicators
    $earliestExpiryDate = null;
    if (!empty($products)) {
        $earliestExpiryDate = $products[0]['expiry_date'];
    }
    
    // Determine oldest stock date for FIFO indicators
    $oldestStockDate = null;
    if (!empty($products)) {
        $oldestProduct = $products[0]; // Since they're ordered by expiry date
        foreach ($products as $p) {
            if (empty($oldestStockDate) || strtotime($p['stock_date']) < strtotime($oldestStockDate)) {
                $oldestStockDate = $p['stock_date'];
                $oldestProduct = $p;
            }
        }
    }
} catch (PDOException $e) {
    die("Error retrieving data: " . $e->getMessage());
}

if (isset($_POST['submitwaste'])) {
    // Extract form data with better validation
    $productId = isset($_POST['product_id']) && !empty($_POST['product_id']) ? $_POST['product_id'] : null;
    $wasteDate = isset($_POST['waste_date']) && !empty($_POST['waste_date']) ? $_POST['waste_date'] : null;
    $wasteQuantity = isset($_POST['waste_quantity']) && is_numeric($_POST['waste_quantity']) && $_POST['waste_quantity'] > 0 ? (float)$_POST['waste_quantity'] : null;
    $wasteReason = isset($_POST['waste_reason']) && !empty($_POST['waste_reason']) ? $_POST['waste_reason'] : null;
    $disposalMethod = isset($_POST['disposal_method']) && !empty($_POST['disposal_method']) ? $_POST['disposal_method'] : null;
    $productionStage = isset($_POST['production_stage']) && !empty($_POST['production_stage']) ? $_POST['production_stage'] : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $branchId = $_SESSION['branch_id'];
    
    // Product value calculation
    $costPerUnit = isset($_POST['product_value']) && is_numeric($_POST['product_value']) ? (float)$_POST['product_value'] : 0;
    $wasteValue = $wasteQuantity * $costPerUnit;
    
    // Validate required fields
    $errors = [];
    if (!$productId) $errors[] = "Product must be selected";
    if (!$wasteDate) $errors[] = "Excess date is required";
    if (!$wasteQuantity) $errors[] = "Excess quantity is required";
    if (!$wasteReason) $errors[] = "Waste reason is required";
    if (!$disposalMethod) $errors[] = "Disposal method is required";
    if (!$productionStage) $errors[] = "Production stage is required";
    
    // Debug information - add this temporarily
    if (!empty($errors)) {
        $errorMessage = "Please fill in all required fields: " . implode(", ", $errors);
        $errorMessage .= "<br>Debug: productId=" . (isset($_POST['product_id']) ? $_POST['product_id'] : 'not set') . 
                        ", wasteDate=" . (isset($_POST['waste_date']) ? $_POST['waste_date'] : 'not set') .
                        ", wasteQuantity=" . (isset($_POST['waste_quantity']) ? $_POST['waste_quantity'] : 'not set') .
                        ", wasteReason=" . (isset($_POST['waste_reason']) ? $_POST['waste_reason'] : 'not set') .
                        ", disposalMethod=" . (isset($_POST['disposal_method']) ? $_POST['disposal_method'] : 'not set') .
                        ", productionStage=" . (isset($_POST['production_stage']) ? $_POST['production_stage'] : 'not set');
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Insert waste record
            $stmt = $pdo->prepare("
                INSERT INTO product_waste (
                    product_id, staff_id, waste_date, waste_quantity, 
                    waste_value, waste_reason, disposal_method, notes, 
                    branch_id, production_stage
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // When executing the INSERT query, format the date properly
            $formattedDate = date('Y-m-d H:i:s', strtotime($wasteDate));
            // Then use $formattedDate in your SQL insert statement
            $stmt->execute([
                $productId, $userId, $formattedDate, $wasteQuantity, 
                $wasteValue, $wasteReason, $disposalMethod, $notes, 
                $branchId, $productionStage
            ]);
            
            // 2. Update product stock quantity
            $updateStmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ? AND branch_id = ?
            ");
            
            $updateStmt->execute([$wasteQuantity, $productId, $branchId]);
            
            $pdo->commit();
            
            // Redirect with success message
            header("Location: waste_product_input.php?success=1");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMessage = "Error recording waste: " . $e->getMessage();
        }
    }
}

$showSuccessMessage = isset($_GET['success']) && $_GET['success'] == '1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Excess Tracking - WasteWise</title>
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
            
            $('select[name="disposal_method"]').on('change', function() {
                const forms = $(this).closest('form');
                const expiryDateField = forms.find('.donation-expiry-container');
                
                if ($(this).val() === 'donation') {
                    expiryDateField.removeClass('hidden').addClass('block');
                    expiryDateField.find('input').prop('required', true);
                } else {
                    expiryDateField.removeClass('block').addClass('hidden');
                    expiryDateField.find('input').prop('required', false);
                }
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

        /* Badge styling */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Status indicators */
        .expiry-urgent {
            background-color: #fee2e2;
            color: #b91c1c;
            animation: pulse 2s infinite;
        }
        
        .expiry-warning {
            background-color: #ffedd5;
            color: #c2410c;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
</head>

<body class="flex min-h-screen bg-gray-50">

<?php include ('../layout/staff_nav.php'); ?>

    <div class="p-5 w-full">
        <div>
        <nav class="mb-4">
                <ol class="flex items-center gap-2 text-gray-600">
                    <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="record_sales.php" class="hover:text-primarycol">Record Sales</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="product_stocks.php" class="hover:text-primarycol">Product Stocks</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Waste</a></li>
                    <li class="text-gray-400">/</li>
                    
                    <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Waste Records</a></li>
                </ol>
            </nav>
            <h1 class="text-3xl font-bold mb-2 text-primarycol">Bakery Product Excess Tracking</h1>
            <p class="text-gray-500 mb-6">Track product Excess to reduce losses and improve production efficiency</p>
        </div>

        <!-- Notification Messages -->
        <?php if (!empty($errorMessage)): ?>
            <div class="notification notification-error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($showSuccessMessage): ?>
            <div class="notification notification-success">
                Product Excess entry submitted successfully.
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left sidebar - Statistics -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-lg shadow mb-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Product Excess Tracking Tips</h2>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-700">Why track product Excess?</h3>
                        <ul class="list-disc pl-5 mt-2 text-gray-600 text-sm">
                            <li>Identify products with high excess rates</li>
                            <li>Calculate the financial impact of product excess</li>
                            <li>Analyze patterns in overproduction or spoilage</li>
                            <li>Improve production planning to reduce excess</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-700">Best practices:</h3>
                        <ul class="list-disc pl-5 mt-2 text-gray-600 text-sm">
                            <li>Record excess immediately after it occurs</li>
                            <li>Track both product quantity and value lost</li>
                            <li>Document specific reasons for excess</li>
                            <li>Consider sustainable disposal methods</li>
                            <li>Track all excess, even small amounts</li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-lg shadow">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Quick Stats</h2>
                    
                    <?php
                    try {
                        $topWasteStmt = $pdo->prepare("
                            SELECT p.name, SUM(w.waste_quantity) as total_waste,
                            SUM(w.waste_value) as total_value
                            FROM product_waste w
                            JOIN products p ON w.product_id = p.id
                            WHERE w.branch_id = ?
                            GROUP BY w.product_id
                            ORDER BY total_waste DESC
                            LIMIT 1
                        ");
                        $topWasteStmt->execute([$branchId]);
                        $topWaste = $topWasteStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $reasonStmt = $pdo->prepare("
                            SELECT waste_reason, COUNT(*) as count
                            FROM product_waste
                            WHERE branch_id = ?
                            GROUP BY waste_reason
                            ORDER BY count DESC
                            LIMIT 1
                        ");
                        $reasonStmt->execute([$branchId]);
                        $topReason = $reasonStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Add stage stats
                        $stageStmt = $pdo->prepare("
                            SELECT production_stage, COUNT(*) as count
                            FROM product_waste
                            WHERE branch_id = ? AND production_stage IS NOT NULL
                            GROUP BY production_stage
                            ORDER BY count DESC
                            LIMIT 1
                        ");
                        $stageStmt->execute([$branchId]);
                        $topStage = $stageStmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Silently handle error
                    }
                    ?>
                    
                    <?php if (!empty($topWaste)): ?>
                    <div class="stat-box bg-gray-50 p-3 rounded mb-3">
                        <p class="text-sm text-gray-500">Most Excess product:</p>
                        <p class="font-bold"><?= htmlspecialchars($topWaste['name']) ?></p>
                        <p class="text-sm"><?= number_format($topWaste['total_waste'], 2) ?> units excess</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($topReason)): ?>
                    <div class="stat-box bg-gray-50 p-3 rounded mb-3">
                        <p class="text-sm text-gray-500">Most common excess reason:</p>
                        <p class="font-bold"><?= ucfirst(htmlspecialchars($topReason['waste_reason'])) ?></p>
                        <p class="text-sm"><?= $topReason['count'] ?> occurrences</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($topStage)): ?>
                    <div class="stat-box bg-gray-50 p-3 rounded">
                        <p class="text-sm text-gray-500">Most excess production stage:</p>
                        <p class="font-bold"><?= ucfirst(htmlspecialchars($topStage['production_stage'])) ?></p>
                        <p class="text-sm"><?= $topStage['count'] ?> occurrences</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right section - Product cards with waste forms -->
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Record Product Excess</h2>
                
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($products as $product):
                        $productId = $product['id'];
                        $productName = $product['name'] ?? 'N/A';
                        $productCategory = $product['category'] ?? 'N/A';
                        $productPrice = $product['price_per_unit'] ?? 0;
                        $productImage = $product['image'] ?? '';
                        $quantityProduced = $product['quantity_produced'] ?? 0;
                        $totalWaste = $product['total_waste'] ?? 0;
                        $remainingQuantity = $product['available_quantity'] ?? 0;
                    ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="flex flex-col md:flex-row">
                            <!-- Product info -->
                            <div class="md:w-1/3 p-4 bg-gray-50">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="px-2 py-1 rounded text-xs font-semibold bg-green-100 text-green-800">
                                        <?= htmlspecialchars($productCategory) ?>
                                    </span>
                                </div>
                                
                                <?php if(!empty($productImage)): ?>
                                    <?php
                                    $imagePath = $productImage;
                                    
                                    if (strpos($imagePath, 'C:') === 0) {
                                        $filename = basename($imagePath);
                                        $imagePath = './uploads/products/' . $filename;
                                    } else if (strpos($imagePath, './uploads/') === 0) {
                                        $imagePath = $productImage;
                                    } else if (strpos($imagePath, 'uploads/') === 0) {
                                        $imagePath = './' . $imagePath;
                                    } else if (strpos($imagePath, '../../assets/') === 0) {
                                        $imagePath = $productImage;
                                    } else {
                                        $filename = basename($imagePath);
                                        $imagePath = './uploads/products/' . $filename;
                                    }
                                    ?>
                                    <img src="<?= htmlspecialchars($imagePath) ?>"
                                         alt="<?= htmlspecialchars($productName) ?>"
                                         class="h-32 w-full object-cover rounded-md mb-3">
                                <?php else: ?>
                                    <img src="../../assets/images/default-product.jpg"
                                         alt="<?= htmlspecialchars($productName) ?>"
                                         class="h-32 w-full object-cover rounded-md mb-3">
                                <?php endif; ?>

                                <h2 class="text-lg font-bold"><?= htmlspecialchars($productName) ?></h2>

                                <p class="text-gray-600 text-sm mt-2">
                                    Price: ₱<?= htmlspecialchars(number_format($productPrice, 2)) ?> per unit
                                </p>
                                
                                <div class="mt-3 p-2 bg-blue-50 rounded-md">
                                    <h3 class="font-medium text-blue-800 text-sm">Stock Information</h3>
                                    <div class="grid grid-cols-2 gap-1 mt-1 text-xs text-gray-600">
                                        <div class="font-medium text-blue-700">Available Quantity:</div>
                                        <div class="text-right font-medium text-blue-700"><?= htmlspecialchars($product['stock_quantity']) ?> units</div>
                                        
                                        <?php if(!empty($product['batch_number'])): ?>
                                        <div class="font-medium text-blue-700">Batch Number:</div>
                                        <div class="text-right font-mono text-blue-700"><?= htmlspecialchars($product['batch_number']) ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="font-medium text-blue-700">Expiry Date:</div>
                                        <div class="text-right text-blue-700">
                                            <?= date('M j, Y', strtotime($product['expiry_date'])) ?>
                                            <span class="block">
                                                (<?= $product['days_until_expiry'] ?> days left)
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add FEFO/FIFO badges - place this after stock information -->
                                <div class="mt-2">
                                    <?php if($product['expiry_date'] === $earliestExpiryDate): ?>
                                        <span class="px-2 py-1 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                            Use First (FEFO)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($oldestStockDate) && $product['stock_date'] === $oldestStockDate): ?>
                                        <span class="px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800 ml-1">
                                            Use First (FIFO)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Expiry status indicator
                                    if ($product['days_until_expiry'] <= 3): 
                                    ?>
                                        <span class="px-2 py-1 rounded text-xs font-semibold bg-red-100 text-red-800 ml-1">
                                            Critical Expiry
                                        </span>
                                    <?php elseif ($product['days_until_expiry'] <= 7): ?>
                                        <span class="px-2 py-1 rounded text-xs font-semibold bg-amber-100 text-amber-800 ml-1">
                                            Expiring Soon
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Waste form -->
                            <div class="md:w-2/3 p-4">
                                <h3 class="font-bold text-primarycol mb-3">Record Excess</h3>
                                
                                <form method="POST" class="waste-form">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId) ?>">
                                    <input type="hidden" name="product_value" value="<?= htmlspecialchars($productPrice) ?>">
                                    <input type="hidden" name="available_stock" value="<?= htmlspecialchars($product['stock_quantity']) ?>">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <!-- Basic waste info -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Excess Quantity
                                            </label>
                                            <input type="number"
                                                name="waste_quantity"
                                                min="0.01"
                                                max="<?= htmlspecialchars($product['stock_quantity']) ?>"
                                                step="any"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                            <p class="text-xs text-gray-500 mt-1">
                                                Available: <?= htmlspecialchars($product['stock_quantity']) ?> units
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Date of Record
                                            </label>
                                            <input type="date" 
                                                id="waste_date" 
                                                name="waste_date" 
                                                value="<?php echo date('Y-m-d'); ?>" 
                                                class="input input-bordered w-full">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Production Stage
                                            </label>
                                            <select name="production_stage"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                                <option value="">Select Stage</option>
                                                <option value="mixing">Mixing</option>
                                                <option value="baking">Baking</option>
                                                <option value="packaging">Packaging</option>
                                                <option value="storage">Storage</option>
                                                <option value="sales">Sales</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Excess Reason
                                            </label>
                                            <select name="waste_reason"
                                                required
                                                class="waste-reason-select w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                                <option value="">Select Reason</option>
                                                <option value="overproduction">Overproduction</option>
                                                <option value="expired">Expired</option>
                                                <option value="burnt">Burnt</option>
                                                <option value="damaged">Damaged</option>
                                                <option value="quality_issues">Quality Issues</option>
                                                <option value="unsold">Unsold/End of Day</option>
                                                <option value="spoiled">Spoiled</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Disposal Method
                                            </label>
                                            <select name="disposal_method"
                                                required
                                                class="disposal-method-select w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                                <option value="">Select Method</option>
                                                <option value="donation">Donation</option>
                                                <option value="compost">Compost</option>
                                                <option value="trash">Trash</option>
                                                <option value="staff_meals">Staff Meals</option>
                                                <option value="animal_feed">Animal Feed</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <script>
                                            document.querySelectorAll('.waste-reason-select').forEach(function(select) {
                                                select.addEventListener('change', function() {
                                                    const disposalMethod = this.closest('form').querySelector('.disposal-method-select');
                                                    
                                                    // Map waste reasons to disposal methods
                                                    const disposalMap = {
                                                        'burnt': 'trash',
                                                        'damaged': 'trash',
                                                        'expired': 'trash',
                                                        'quality_issues': 'trash',
                                                        'spoiled': 'compost',
                                                        'overproduction': 'donation',
                                                        'unsold': 'donation'
                                                    };
                                                    
                                                    if (disposalMap[this.value]) {
                                                        disposalMethod.value = disposalMap[this.value];
                                                    } else {
                                                        disposalMethod.value = '';
                                                    }
                                                });
                                            });
                                        </script>
                                        
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
                                            Record Excess Entry
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($products)): ?>
                        <div class="text-center py-10 bg-white rounded-lg shadow p-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="text-xl text-gray-500">No products found.</p>
                            <p class="text-gray-400 mt-2">Add products in the Products section first.</p>
                            <a href="product_data.php" class="inline-block mt-4 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700">
                                Add Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
