<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

checkAuth(['staff']);

$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$branchId = $_SESSION['branch_id'];

$successMessage = '';
$errorMessage = '';

try {
    $prodStmt = $pdo->prepare("
        SELECT 
            p.*,
            COALESCE(SUM(w.waste_quantity), 0) as total_waste,
            p.quantity_produced as original_quantity,
            (p.quantity_produced - COALESCE(SUM(w.waste_quantity), 0)) as remaining_quantity
        FROM products p
        LEFT JOIN product_waste w ON p.id = w.product_id AND w.branch_id = ?
        WHERE p.branch_id = ? 
        AND p.status = 'active' 
        AND p.expiry_date >= CURRENT_DATE()
        GROUP BY p.id
        HAVING remaining_quantity > 0
        ORDER BY p.created_at DESC
    ");
    $prodStmt->execute([$branchId, $branchId]);
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
    $quantitySold = $_POST['quantity_sold'] ?? null;
    $costPerUnit = $_POST['product_value'] ?? 0;
    $wasteValue = $wasteQuantity * $costPerUnit;
    $wasteReason = $_POST['waste_reason'] ?? null;
    $disposalMethod = $_POST['disposal_method'] ?? null;
    $responsiblePerson = $_POST['responsible_person'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $branchId = $_SESSION['branch_id'];
    $donationExpiryDate = null;
    if ($disposalMethod === 'donation') {
        $donationExpiryDate = $_POST['donation_expiry_date'] ?? null;
        if (empty($donationExpiryDate)) {
            $errorMessage = 'Please specify an expiration date for donated products.';
           
            goto display_page;
        }
    }
    if (!$userId || !$productId || !$wasteDate || !$wasteQuantity || !$wasteReason || 
        !$disposalMethod || !$responsiblePerson || !$quantitySold) {
        $errorMessage = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
           
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

            
            $updateStmt = $pdo->prepare("
                UPDATE products 
                SET status = CASE 
                    WHEN (SELECT SUM(waste_quantity + quantity_sold) 
                          FROM product_waste 
                          WHERE product_id = products.id) >= quantity_produced 
                    THEN 'waste_processed'
                    ELSE status 
                    END
                WHERE id = ? AND branch_id = ?
            ");
            
            $updateStmt->execute([
                $productId, 
                $branchId
            ]);
            
           
            $pdo->commit();

            
            header('Location: waste_product_input.php?success=1');
            exit;
        } catch (PDOException $e) {
           
            $pdo->rollBack();
            $errorMessage = 'An error occurred while submitting the waste entry: ' . $e->getMessage();
        }
    }

    display_page: 
}


if (isset($_POST['custom_waste'])) {
    $productId = $_POST['product_id'] ?? null;
    $wasteQuantity = $_POST['waste_quantity'] ?? null;
    $wasteDate = $_POST['waste_date'] ?? null;
    $wasteReason = $_POST['waste_reason'] ?? null;
    $disposalMethod = $_POST['disposal_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $productValue = $_POST['product_value'] ?? 0;
    $quantityProduced = $_POST['quantity_produced'] ?? 0;
    
    if (!$productId || !$wasteQuantity || !$wasteDate || !$wasteReason || !$disposalMethod) {
        $errorMessage = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            
            $checkStmt = $pdo->prepare("
                SELECT p.quantity_produced,
                       COALESCE(SUM(w.waste_quantity), 0) as total_waste
                FROM products p
                LEFT JOIN product_waste w ON p.id = w.product_id
                WHERE p.id = ? AND p.branch_id = ?
                GROUP BY p.id, p.quantity_produced
            ");
            $checkStmt->execute([$productId, $branchId]);
            $currentData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            $availableQuantity = $currentData['quantity_produced'] - $currentData['total_waste'];
            
            if ($wasteQuantity > $availableQuantity) {
                throw new Exception("Cannot record waste greater than available quantity ($availableQuantity units)");
            }
            
          
            $stmt = $pdo->prepare("
                INSERT INTO product_waste (
                    user_id, product_id, waste_date, waste_quantity,
                    waste_value, waste_reason, disposal_method,
                    notes, created_at, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $productId,
                $wasteDate,
                $wasteQuantity,
                $wasteQuantity * $productValue,
                $wasteReason,
                $disposalMethod,
                $notes,
                date('Y-m-d H:i:s'),
                $branchId
            ]);
            
            
            $updateStmt = $pdo->prepare("
                UPDATE products 
                SET status = CASE 
                    WHEN (SELECT COALESCE(SUM(waste_quantity), 0) 
                          FROM product_waste 
                          WHERE product_id = ?) >= quantity_produced 
                    THEN 'waste_processed'
                    ELSE status 
                    END
                WHERE id = ? AND branch_id = ?
            ");
            $updateStmt->execute([$productId, $productId, $branchId]);
            
            $pdo->commit();
            header('Location: waste_product_record.php?success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = 'Error recording waste: ' . $e->getMessage();
        }
    }
}


if (isset($_POST['custom_waste'])) {
    
    
    try {
        $pdo->beginTransaction();
        
     
        $checkStmt = $pdo->prepare("
            SELECT 
                p.quantity_produced,
                COALESCE(SUM(w.waste_quantity), 0) as total_waste,
                (p.quantity_produced - COALESCE(SUM(w.waste_quantity), 0)) as remaining_quantity
            FROM products p
            LEFT JOIN product_waste w ON p.id = w.product_id AND w.branch_id = ?
            WHERE p.id = ? AND p.branch_id = ?
            GROUP BY p.id
        ");
        $checkStmt->execute([$branchId, $productId, $branchId]);
        $currentData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentData) {
            throw new Exception("Product not found or not active");
        }
        
        if ($wasteQuantity > $currentData['remaining_quantity']) {
            throw new Exception("Cannot record waste greater than available quantity ({$currentData['remaining_quantity']} units)");
        }
        
       
        
        $pdo->commit();
        header('Location: waste_product_record.php?success=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = 'Error recording waste: ' . $e->getMessage();
    }
}


if (isset($_POST['custom_waste'])) {
    $productId = $_POST['product_id'] ?? null;
    $wasteQuantity = $_POST['waste_quantity'] ?? null;
    $wasteDate = $_POST['waste_date'] ?? null;
    $wasteReason = $_POST['waste_reason'] ?? null;
    $disposalMethod = $_POST['disposal_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    if (!$productId || !$wasteQuantity || !$wasteDate || !$wasteReason || !$disposalMethod) {
        $errorMessage = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
           
            $checkStmt = $pdo->prepare("
                SELECT 
                    (p.quantity_produced - COALESCE(SUM(w.waste_quantity), 0)) as available_quantity
                FROM products p
                LEFT JOIN product_waste w ON p.id = w.product_id
                WHERE p.id = ? AND p.branch_id = ?
                GROUP BY p.id, p.quantity_produced
            ");
            $checkStmt->execute([$productId, $branchId]);
            $currentData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($wasteQuantity > $currentData['available_quantity']) {
                throw new Exception("Cannot record waste greater than available quantity ({$currentData['available_quantity']} units)");
            }
            
           
            $stmt = $pdo->prepare("
                INSERT INTO product_waste (
                    user_id, product_id, waste_date, waste_quantity,
                    waste_reason, disposal_method, notes, created_at, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $userId,
                $productId,
                $wasteDate,
                $wasteQuantity,
                $wasteReason,
                $disposalMethod,
                $notes,
                $branchId
            ]);
            
            $pdo->commit();
            header('Location: waste_product_record.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = 'Error recording waste: ' . $e->getMessage();
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
           
            $('#toggleSidebar').on('click', function() {
                $('#sidebar').toggleClass('-translate-x-full');
            });

            $('#closeSidebar').on('click', function() {
                $('#sidebar').addClass('-translate-x-full');
            });
            
            
            setTimeout(function() {
                $('.notification').fadeOut();
            }, 3000);

            
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
            
            
            $('form').on('submit', function(e) {
                const disposalMethod = $(this).find('select[name="disposal_method"]').val();
                const expiryDate = $(this).find('input[name="donation_expiry_date"]').val();
                
                if (disposalMethod === 'donation' && !expiryDate) {
                    e.preventDefault();
                    alert('Please specify an expiration date for the donated product.');
                    return false;
                }
            });

           
            $('form').on('submit', function(e) {
                const form = $(this);
                const quantitySold = parseFloat(form.find('input[name="quantity_sold"]').val()) || 0;
                const quantityWasted = parseFloat(form.find('input[name="waste_quantity"]').val()) || 0;
                const maxInventory = parseFloat(form.find('input[name="quantity_sold"]').attr('max')) || 0;
                
                if (quantitySold + quantityWasted > maxInventory) {
                    e.preventDefault();
                    alert('Error: The combined quantity of sold and wasted items cannot exceed the current inventory (' + maxInventory + ' units).');
                    return false;
                }
            });

            
            $('#openCustomWasteModal').on('click', function() {
                $('#customWasteModal').removeClass('hidden');
            });

           
            $('#closeCustomWasteModal').on('click', function() {
                $('#customWasteModal').addClass('hidden');
            });
        });

        
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('customWasteModal');
            const openButton = document.getElementById('openCustomWasteModal');
            const closeButton = document.getElementById('closeCustomWasteModal');

            openButton.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });

            closeButton.addEventListener('click', () => {
                modal.classList.add('hidden');
            });

           
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });

        
        $(document).ready(function() {
            
            $('.product-item').on('click', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productPrice = $(this).data('product-price');
                const quantityProduced = $(this).data('quantity-produced');
                
                
                $('#selected_product_id').val(productId);
                $('#selected_product_price').val(productPrice);
                $('#selected_quantity_produced').val(quantityProduced);
                $('#selectedProductName').text(productName);
                
               
                $('#productSelectionView').addClass('hidden');
                $('#wasteFormView').removeClass('hidden');
            });
            
            
            $('.back-to-products').on('click', function() {
                $('#wasteFormView').addClass('hidden');
                $('#productSelectionView').removeClass('hidden');
            });
            
           
            $('#closeCustomWasteModal').on('click', function() {
                $('#customWasteModal').addClass('hidden');
                $('#wasteFormView').addClass('hidden');
                $('#productSelectionView').removeClass('hidden');
            });
        });

        
        $(document).ready(function() {
            
            $('#customWasteForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const productId = formData.get('product_id');
                const wasteQuantity = parseFloat(formData.get('waste_quantity'));
                
                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        
                        const mainFormInput = $(`input[data-product-id="${productId}"]`);
                        const currentMax = parseFloat(mainFormInput.attr('max'));
                        const newMax = currentMax - wasteQuantity;
                        
                        mainFormInput.attr('max', newMax);
                        mainFormInput.siblings('p').text(`Available: ${newMax} units`);
                        
                        
                        $('#customWasteModal').addClass('hidden');
                        location.reload(); 
                    },
                    error: function(xhr) {
                        alert('Error processing waste: ' + xhr.responseText);
                    }
                });
            });
        });

        
        $(document).ready(function() {
            $('input[name="quantity_sold"]').on('change', function() {
                const form = $(this).closest('form');
                const quantitySold = parseFloat($(this).val()) || 0;
                const maxQuantity = parseFloat($(this).attr('max')) || 0;
                const wasteQuantity = maxQuantity - quantitySold;
                
                if (wasteQuantity < 0) {
                    alert('Quantity sold cannot exceed available quantity');
                    $(this).val(maxQuantity);
                    form.find('input[name="waste_quantity"]').val(0);
                } else {
                    form.find('input[name="waste_quantity"]').val(wasteQuantity);
                }
            });

            
            $('form').on('submit', function(e) {
                const quantitySold = parseFloat($(this).find('input[name="quantity_sold"]').val()) || 0;
                const wasteQuantity = parseFloat($(this).find('input[name="waste_quantity"]').val()) || 0;
                const maxQuantity = parseFloat($(this).find('input[name="quantity_sold"]').attr('max')) || 0;
                
                if (quantitySold + wasteQuantity > maxQuantity) {
                    e.preventDefault();
                    alert(`Total quantity (${quantitySold + wasteQuantity}) cannot exceed available quantity (${maxQuantity})`);
                    return false;
                }
            });
        });

        
        $(document).ready(function() {
           
            $('form').on('submit', function(e) {
                const quantitySold = parseFloat($(this).find('input[name="quantity_sold"]').val()) || 0;
                const wasteQuantity = parseFloat($(this).find('input[name="waste_quantity"]').val()) || 0;
                const originalQuantity = parseFloat($(this).find('#quantity_produced').val()) || 0;
                
                
                if (quantitySold + wasteQuantity > originalQuantity) {
                    e.preventDefault();
                    alert(`Total quantity (${quantitySold + wasteQuantity}) cannot exceed original quantity (${originalQuantity})`);
                    return false;
                }
            });
        });

        function calculateWaste(input) {
            const form = input.closest('form');
            const quantityProduced = parseFloat(form.querySelector('#quantity_produced').value) || 0;
            const quantitySold = parseFloat(input.value) || 0;
            
            if (quantitySold > quantityProduced) {
                alert('Quantity sold cannot exceed production quantity');
                input.value = quantityProduced;
                form.querySelector('[name="waste_quantity"]').value = 0;
                return;
            }
            
            const wasteQuantity = quantityProduced - quantitySold;
            form.querySelector('[name="waste_quantity"]').value = wasteQuantity;
        }
    </script>

    <style>
     
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
        <nav class="mb-4">
      <ol class="flex items-center gap-2 text-gray-600">
        <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Waste</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="waste_product_record.php" class="hover:text-primarycol">View Product Waste Records</a></li>
      </ol>
    </nav>
            <h1 class="text-3xl font-bold mb-2 text-primarycol">Bakery Product Waste Tracking</h1>
            <p class="text-gray-500 mb-6">Track product waste to reduce losses and improve production efficiency</p>
        </div>

        
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
                    } catch (PDOException $e) {
                      
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
                <div class="mt-4">
                    <button id="openCustomWasteModal" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-700 transition-colors">
                        Custom Waste Entry
                    </button>
                </div>
            </div>
            
         
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Record Product Waste</h2>
                
                <div class="grid grid-cols-1 gap-6">
                  
                    <?php foreach ($products as $product):
                        $productId = $product['id'];
                        $productName = $product['name'] ?? 'N/A';
                        $productCategory = $product['category'] ?? 'N/A';
                        $productPrice = $product['price_per_unit'] ?? 0;
                        $productImage = $product['image'] ?? '';
                        $quantityProduced = $product['quantity_produced'];
                    ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="flex flex-col md:flex-row">
                       
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
                            
                            </div>
                            
                       
                            <div class="md:w-2/3 p-4">
                                <h3 class="font-bold text-primarycol mb-3">Record Waste</h3>
                                
                                <form method="POST">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId) ?>">
                                    <input type="hidden" name="product_value" value="<?= htmlspecialchars($productPrice) ?>">
                                    <input type="hidden" id="quantity_produced" value="<?= htmlspecialchars($product['quantity_produced']) ?>">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                     
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Quantity Sold
                                            </label>
                                            <input type="number"
                                                name="quantity_sold"
                                                min="0"
                                                max="<?= htmlspecialchars($product['quantity_produced']) ?>"
                                                data-product-id="<?= htmlspecialchars($product['id']) ?>"
                                                required
                                                onchange="calculateWaste(this)"
                                                class="w-full border border-gray-300 rounded-md p-2">
                                            <p class="text-xs text-gray-500 mt-1">
                                                Available: <?= htmlspecialchars($product['quantity_produced']) ?> units
                                                (Used: <?= htmlspecialchars($product['total_waste']) ?> units wasted)
                                            </p>
                                        </div>
                                        
                                      
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Quantity Wasted
                                            </label>
                                            <input type="number"
                                                id="waste_quantity"
                                                name="waste_quantity"
                                                readonly
                                                class="w-full border border-gray-300 rounded-md p-2 bg-gray-50 focus:outline-none focus:ring-primary focus:border-primary">
                                            <p class="text-xs text-gray-500 mt-1">
                                                Automatically calculated
                                            </p>
                                        </div>
                                    </div>
                                    
                                
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                                        
                                        <div class="donation-expiry-container hidden">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <span class="text-red-500">*</span> Expiration Date for Donation
                                            </label>
                                            <input type="date" 
                                                name="donation_expiry_date"
                                                class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary"
                                                min="<?= date('Y-m-d') ?>">
                                            <p class="text-xs text-gray-500 mt-1">Required for donations</p>
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
                            <a href="product_data.php" class="inline-block mt-4 px-4 py-2 bg-primarycol text-white rounded hover:bg-green-700">
                                Add Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <div id="customWasteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
           
                <div id="productSelectionView" class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Select Product</h3>
                        <button id="closeCustomWasteModal" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($products as $product): ?>
                        <div class="product-item border rounded p-3 hover:bg-gray-50 cursor-pointer"
                             data-product-id="<?= htmlspecialchars($product['id']) ?>"
                             data-product-name="<?= htmlspecialchars($product['name']) ?>"
                             data-product-price="<?= htmlspecialchars($product['price_per_unit']) ?>"
                             data-quantity-produced="<?= htmlspecialchars($product['quantity_produced']) ?>">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-12">
                                    <?php if(!empty($product['image'])): ?>
                                        <img src="<?= htmlspecialchars($product['image']) ?>" class="h-12 w-12 object-cover rounded">
                                    <?php else: ?>
                                        <div class="h-12 w-12 bg-gray-200 rounded"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></h4>
                                    <p class="text-sm text-gray-500">
                                        Quantity Produced: <?= htmlspecialchars($product['quantity_produced']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

         
                <div id="wasteFormView" class="p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Record Waste for <span id="selectedProductName"></span></h3>
                        <button class="back-to-products text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" id="customWasteForm">
                        <input type="hidden" name="custom_waste" value="1">
                        <input type="hidden" name="product_id" id="selected_product_id">
                        <input type="hidden" name="product_value" id="selected_product_price">
                        <input type="hidden" name="quantity_produced" id="selected_quantity_produced">
                        
                        <div class="space-y-4">
                        
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Quantity Wasted
                                </label>
                                <input type="number"
                                    name="waste_quantity"
                                    required
                                    min="0.01"
                                    step="any"
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

                     
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Notes (optional)
                                </label>
                                <textarea 
                                    name="notes"
                                    placeholder="Additional details about this waste"
                                    class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-primary focus:border-primary"
                                    rows="2"
                                ></textarea>
                            </div>

                        
                            <div class="mt-6 flex justify-end space-x-3">
                                <button type="button" 
                                    class="back-to-products px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    Back
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 bg-primarycol text-white rounded-md hover:bg-green-700">
                                    Submit Waste
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

   
    <script>
    function calculateWaste() {
        const quantityProduced = parseFloat(document.getElementById('quantity_produced').value) || 0;
        const quantitySold = parseFloat(document.getElementById('quantity_sold').value) || 0;
        
        if (quantitySold > quantityProduced) {
            alert('Quantity sold cannot be greater than quantity produced');
            document.getElementById('quantity_sold').value = quantityProduced;
            document.getElementById('waste_quantity').value = 0;
            return;
        }
        
        const wasteQuantity = quantityProduced - quantitySold;
        document.getElementById('waste_quantity').value = wasteQuantity;
    }

    document.addEventListener('DOMContentLoaded', calculateWaste);
    </script>
</body>
</html>
