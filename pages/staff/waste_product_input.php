<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Fetch the user's name from the session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$branchId = $_SESSION['branch_id'];

// Initialize message variables
$successMessage = '';
$errorMessage = '';

try {
    // Fetch products from the products table
    $prodStmt = $pdo->prepare("SELECT * FROM products WHERE branch_id = ? ORDER BY created_at DESC");
    $prodStmt->execute([$branchId]);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving data: " . $e->getMessage());
}

if (isset($_POST['submitwaste'])) {
    // Extract form data
    $userId = $_SESSION['user_id'];
    $productId = $_POST['product_id'] ?? null;
    $wasteDate = $_POST['waste_date'] ?? null;
    $wasteQuantity = $_POST['waste_quantity'] ?? null;
    $quantityProduced = $_POST['quantity_produced'] ?? null;
    $quantitySold = $_POST['quantity_sold'] ?? null;
    $costPerUnit = $_POST['product_value'] ?? 0;
    $wasteValue = $wasteQuantity * $costPerUnit;
    $wasteReason = $_POST['waste_reason'] ?? null;
    $disposalMethod = $_POST['disposal_method'] ?? null;
    $responsiblePerson = $_POST['responsible_person'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $branchId = $_SESSION['branch_id'];
    
    // Get donation expiry date if applicable
    $donationExpiryDate = null;
    if ($disposalMethod === 'donation') {
        $donationExpiryDate = $_POST['donation_expiry_date'] ?? null;
        if (empty($donationExpiryDate)) {
            $errorMessage = 'Please specify an expiration date for donated products.';
            // Don't proceed with form submission
            goto display_page;
        }
    }

    // Validate form data
    if (!$userId || !$productId || !$wasteDate || !$wasteQuantity || !$wasteReason || 
        !$disposalMethod || !$responsiblePerson || !$quantityProduced || !$quantitySold) {
        $errorMessage = 'Please fill in all required fields.';
    } else {
        // Insert waste entry into the product_waste table
        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_waste (
                    user_id, product_id, waste_date, waste_quantity, quantity_produced, quantity_sold,
                    waste_value, waste_reason, disposal_method, responsible_person, notes, created_at, branch_id,
                    donation_expiry_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId, 
                $productId,
                date('Y-m-d H:i:s', strtotime($wasteDate)),
                $wasteQuantity,
                $quantityProduced,
                $quantitySold,
                $wasteValue,
                $wasteReason,
                $disposalMethod,
                $responsiblePerson,
                $notes,
                date('Y-m-d H:i:s'),
                $branchId,
                $donationExpiryDate ? date('Y-m-d', strtotime($donationExpiryDate)) : null
            ]);

            // Redirect to the record page after successful submission
            header('Location: waste_product_input.php?success=1');
            exit;
        } catch (PDOException $e) {
            $errorMessage = 'An error occurred while submitting the waste entry: ' . $e->getMessage();
        }
    }

    display_page: // Skip point for validation errors
}

// Check if redirected back with success message
$showSuccessMessage = isset($_GET['success']) && $_GET['success'] == '1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Waste Tracking - WasteWise</title>
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

            // Handle donation expiration date field visibility
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
            
            // Validate donation form before submission
            $('form').on('submit', function(e) {
                const disposalMethod = $(this).find('select[name="disposal_method"]').val();
                const expiryDate = $(this).find('input[name="donation_expiry_date"]').val();
                
                if (disposalMethod === 'donation' && !expiryDate) {
                    e.preventDefault();
                    alert('Please specify an expiration date for the donated product.');
                    return false;
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
    </style>
</head>

<body class="flex min-h-screen bg-gray-50">

    <?php include(__DIR__ . '/../layout/staff_nav.php'); ?>

    <div class="p-5 w-full">
        <div>
            <h1 class="text-3xl font-bold mb-2 text-primarycol">Bakery Product Waste Tracking</h1>
            <p class="text-gray-500 mb-6">Track product waste to reduce losses and improve production efficiency</p>
        </div>

        <!-- Notification Messages -->
        <?php if (!empty($errorMessage)): ?>
            <div class="notification notification-error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($showSuccessMessage): ?>
            <div class="notification notification-success">
                Product waste entry submitted successfully.
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left sidebar - Statistics -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-lg shadow mb-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Product Waste Tracking Tips</h2>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-700">Why track product waste?</h3>
                        <ul class="list-disc pl-5 mt-2 text-gray-600 text-sm">
                            <li>Identify products with high waste rates</li>
                            <li>Calculate the financial impact of product waste</li>
                            <li>Analyze patterns in overproduction or spoilage</li>
                            <li>Improve production planning to reduce waste</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold text-gray-700">Best practices:</h3>
                        <ul class="list-disc pl-5 mt-2 text-gray-600 text-sm">
                            <li>Record accurate production quantities</li>
                            <li>Track both product quantity and value lost</li>
                            <li>Document specific reasons for waste</li>
                            <li>Consider sustainable disposal methods</li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-lg shadow">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Quick Stats</h2>
                    
                    <?php
                    // Most commonly wasted product
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
                        
                        // Most common waste reason
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
                    } catch (PDOException $e) {
                        // Silently fail, stats are not critical
                    }
                    ?>
                    
                    <?php if (!empty($topWaste)): ?>
                    <div class="stat-box bg-gray-50 p-3 rounded mb-3">
                        <p class="text-sm text-gray-500">Most wasted product:</p>
                        <p class="font-bold"><?= htmlspecialchars($topWaste['name']) ?></p>
                        <p class="text-sm"><?= number_format($topWaste['total_waste'], 2) ?> units wasted</p>
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
            
            <!-- Right section - Product cards with waste forms -->
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Record Product Waste</h2>
                
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($products as $product):
                        $productId = $product['id'];
                        $productName = $product['name'] ?? 'N/A';
                        $productCategory = $product['category'] ?? 'N/A';
                        $productPrice = $product['price_per_unit'] ?? 0;
                        $productImage = $product['image'] ?? '';
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
                                <img src="../../uploads/products/<?= basename(htmlspecialchars($productImage)) ?>"
                                    alt="<?= htmlspecialchars($productName) ?>"
                                    class="h-32 w-full object-cover rounded-md mb-3">
                                <?php endif; ?>

                                <h2 class="text-lg font-bold"><?= htmlspecialchars($productName) ?></h2>

                                <p class="text-gray-600 text-sm mt-2">
                                    Price: ₱<?= htmlspecialchars(number_format($productPrice, 2)) ?> per unit
                                </p>
                            </div>
                            
                            <!-- Waste form - Changed to standard submission -->
                            <div class="md:w-2/3 p-4">
                                <h3 class="font-bold text-primarycol mb-3">Record Waste</h3>
                                
                                <form method="POST">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId) ?>">
                                    <input type="hidden" name="product_value" value="<?= htmlspecialchars($productPrice) ?>">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <!-- Production tracking fields -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Quantity Produced
                                            </label>
                                            <input type="number"
                                                name="quantity_produced"
                                                min="0.01"
                                                step="any"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Quantity Sold
                                            </label>
                                            <input type="number"
                                                name="quantity_sold"
                                                min="0"
                                                step="any"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                        </div>
                                        
                                        <!-- Waste info -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Quantity Wasted
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
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Waste Reason
                                            </label>
                                            <select name="waste_reason"
                                                required
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
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
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
                                                <option value="">Select Method</option>
                                                <option value="donation">Donation</option>
                                                <option value="compost">Compost</option>
                                                <option value="trash">Trash</option>
                                                <option value="staff_meals">Staff Meals</option>
                                                <option value="animal_feed">Animal Feed</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="donation-expiry-container hidden col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <span class="text-red-500">*</span> Expiration Date for Donation
                                            </label>
                                            <input type="date" 
                                                name="donation_expiry_date"
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary"
                                                min="<?= date('Y-m-d') ?>">
                                            <p class="text-xs text-gray-500 mt-1">Required for donations - helps NGOs know when the product must be used by</p>
                                        </div>
                                        
                                        <div class="col-span-2 donation-expiry-container hidden">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Donation Expiry Date
                                            </label>
                                            <input type="date"
                                                name="donation_expiry_date"
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary">
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

                    <?php if (empty($products)): ?>
                        <div class="text-center py-10 bg-white rounded-lg shadow p-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="text-xl text-gray-500">No products found.</p>
                            <p class="text-gray-400 mt-2">Add products in the Products section first.</p>
                            <a href="products.php" class="inline-block mt-4 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700">
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
