<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check if user is NGO
checkAuth(['ngo']);

$pdo = getPDO();
$ngoId = $_SESSION['user_id'];

// Initialize cart in session if not exists
if (!isset($_SESSION['donation_cart'])) {
    $_SESSION['donation_cart'] = [];
}

// Process cart submission if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    try {
        $pdo->beginTransaction();
        
        if (!empty($_SESSION['donation_cart'])) {
            $requestDate = date('Y-m-d H:i:s'); // This will use the Asia/Manila timezone
            $pickupDate = date('Y-m-d', strtotime('+1 day')); // Default to tomorrow
            $pickupTime = '09:00:00'; // Default pickup time
            
            // Generate a UUID for request_group_id to group all items from this request
            $requestGroupId = uniqid('req-', true);
            
            // Create donation requests for each item in cart
            foreach ($_SESSION['donation_cart'] as $item) {
                // Insert into ngo_donation_requests table
                $insertRequest = $pdo->prepare("
                    INSERT INTO ngo_donation_requests 
                    (ngo_id, product_id, branch_id, waste_id, request_date, 
                    pickup_date, pickup_time, status, quantity_requested, ngo_notes, request_group_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                ");
                
                $insertRequest->execute([
                    $ngoId,
                    $item['product_id'],
                    $item['branch_id'],
                    $item['waste_id'],
                    $requestDate,
                    $pickupDate,
                    $pickupTime,
                    $item['quantity'],
                    '',
                    $requestGroupId
                ]);
            }
            
            // Update donation_status in product_waste table
            foreach ($_SESSION['donation_cart'] as $item) {
                // First check the current available quantity
                $checkQty = $pdo->prepare("
                    SELECT waste_quantity FROM product_waste 
                    WHERE id = ? AND disposal_method = 'donation'
                ");
                $checkQty->execute([$item['waste_id']]);
                $currentQty = $checkQty->fetchColumn();
                
                // Calculate remaining quantity after request
                $requestedQty = floatval($item['quantity']);
                $remainingQty = $currentQty - $requestedQty;
                
                if ($remainingQty <= 0) {
                    // If taking all or more than available, mark as fully requested
                    $updateWaste = $pdo->prepare("
                        UPDATE product_waste 
                        SET donation_status = 'requested' 
                        WHERE id = ? AND disposal_method = 'donation'
                    ");
                    $updateWaste->execute([$item['waste_id']]);
                } else {
                    // If taking only a portion, reduce the quantity but keep status as pending
                    $updateWaste = $pdo->prepare("
                        UPDATE product_waste 
                        SET waste_quantity = ? 
                        WHERE id = ? AND disposal_method = 'donation'
                    ");
                    $updateWaste->execute([$remainingQty, $item['waste_id']]);
                }
            }
            
            // Create notification for admin/staff
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, link, created_at)
                SELECT u.id, CONCAT('New donation request from ', np.organization_name), 
                       CONCAT('/admin/ngo.php?group=', ?), NOW()
                FROM users u
                JOIN ngo_profiles np ON np.user_id = ?
                WHERE u.role = 'admin' OR u.role = 'staff'
            ");
            $notifyStmt->execute([$requestGroupId, $ngoId]);

            // Add this after successfully creating donation requests, before the success message

            // Create notification for admin
            $ngoName = $_SESSION['organization_name'] ?: ($_SESSION['fname'] . ' ' . $_SESSION['lname']);
            $notifyAdminStmt = $pdo->prepare("
                INSERT INTO notifications (
                    target_role, 
                    message, 
                    notification_type,
                    link, 
                    is_read,
                    created_at
                ) VALUES (
                    'admin', 
                    ?, 
                    'donation_requested',
                    '../admin/ngo.php?tab=pending', 
                    0,
                    NOW()
                )
            ");

            // If you have the count of requested items
            $itemCount = count($_SESSION['donation_cart']);
            $message = "New donation request from {$ngoName} - {$itemCount} item(s) pending approval";
            $notifyAdminStmt->execute([$message]);
            
            $pdo->commit();
            
            // Clear the cart
            $_SESSION['donation_cart'] = [];
            $successMessage = "Your donation request has been submitted successfully!";
        } else {
            $errorMessage = "Your cart is empty. Please add items before submitting.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errorMessage = "Error submitting donation request: " . $e->getMessage();
    }
}

// Get total items requested today for this NGO
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

$requestedTodayQuery = $pdo->prepare("
    SELECT COALESCE(SUM(quantity_requested), 0) as total_requested
    FROM ngo_donation_requests
    WHERE ngo_id = ? AND request_date BETWEEN ? AND ?
");
$requestedTodayQuery->execute([$ngoId, $todayStart, $todayEnd]);
$totalRequestedToday = $requestedTodayQuery->fetchColumn();

$dailyLimit = 200;
$remainingLimit = $dailyLimit - $totalRequestedToday;

// Get categories for filter
$categoriesQuery = $pdo->query("SELECT DISTINCT category FROM product_info ORDER BY category");
$categories = $categoriesQuery->fetchAll(PDO::FETCH_COLUMN);

// Build query based on filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$expiryFilter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$params = [];
$sql = "
    SELECT 
        pw.id as waste_id, 
        p.id as product_id, 
        p.name as product_name, 
        p.category, 
        pw.waste_quantity as available_quantity,
        ps.expiry_date, 
        p.image, 
        b.id as branch_id, 
        b.name as branch_name, 
        b.address as branch_address,
        b.location as branch_location,
        pw.waste_date, 
        pw.waste_value,
        ps.batch_number
    FROM product_waste pw
    JOIN product_info p ON pw.product_id = p.id
    JOIN branches b ON pw.branch_id = b.id
    LEFT JOIN product_stock ps ON pw.stock_id = ps.id
    WHERE pw.disposal_method = 'donation' 
    AND pw.donation_status = 'pending'
    AND pw.archived = 0
";

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $sql .= " AND p.category = ?";
    $params[] = $category;
}

// Apply expiry date filter
if (!empty($expiryFilter)) {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $weekLater = date('Y-m-d', strtotime('+7 days'));
    
    switch ($expiryFilter) {
        case 'today':
            $sql .= " AND DATE(COALESCE(ps.expiry_date, pw.waste_date)) = ?";
            $params[] = $today;
            break;
        case 'tomorrow':
            $sql .= " AND DATE(COALESCE(ps.expiry_date, pw.waste_date)) = ?";
            $params[] = $tomorrow;
            break;
        case 'this_week':
            $sql .= " AND DATE(COALESCE(ps.expiry_date, pw.waste_date)) BETWEEN ? AND ?";
            $params[] = $today;
            $params[] = $weekLater;
            break;
    }
}

// Apply sorting
switch ($sort) {
    case 'expiry':
        $sql .= " ORDER BY COALESCE(ps.expiry_date, pw.waste_date) ASC";
        break;
    case 'quantity':
        $sql .= " ORDER BY pw.waste_quantity DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY pw.created_at DESC";
        break;
}

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donationItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Available Food - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
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

<body class="flex h-screen bg-slate-100">
    <?php include '../layout/ngo_nav.php' ?>

    <div class="flex flex-col w-full overflow-y-auto">
        <!-- Page Header -->
        <div class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-primarycol">Available Food Donations</h1>
                        <p class="text-gray-500 text-sm mt-1">Browse and request available food donations from local businesses</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="text-sm text-gray-600 bg-sec px-3 py-1.5 rounded-md">
                            <span class="font-semibold"><?= $remainingLimit ?></span> of <span class="font-semibold"><?= $dailyLimit ?></span> items remaining today
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($successMessage)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 m-4" role="alert">
            <p><?= htmlspecialchars($successMessage) ?></p>
        </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 m-4" role="alert">
            <p><?= htmlspecialchars($errorMessage) ?></p>
        </div>
        <?php endif; ?>

        <!-- Filters and Search -->
        <div class="bg-white border-t border-gray-200 shadow-sm">
            <div class="max-w-7xl mx-auto py-3 px-4 sm:px-6 lg:px-8">
                <form method="GET" action="" class="flex flex-col sm:flex-row gap-4 justify-between">
                    <div class="flex gap-2 items-center">
                        <div class="dropdown">
                            <div tabindex="0" role="button" class="btn btn-sm m-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                Filter
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                <li class="menu-title">Categories</li>
                                <?php foreach ($categories as $cat): ?>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="category" class="radio radio-sm" 
                                               value="<?= htmlspecialchars($cat) ?>" 
                                               <?= ($category === $cat) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($cat) ?></span>
                                    </label>
                                </li>
                                <?php endforeach; ?>
                                <div class="divider my-1"></div>
                                <li class="menu-title">Expiry Date</li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="expiry" class="radio radio-sm" 
                                               value="today" <?= ($expiryFilter === 'today') ? 'checked' : '' ?>>
                                        <span>Today</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="expiry" class="radio radio-sm" 
                                               value="tomorrow" <?= ($expiryFilter === 'tomorrow') ? 'checked' : '' ?>>
                                        <span>Tomorrow</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="expiry" class="radio radio-sm" 
                                               value="this_week" <?= ($expiryFilter === 'this_week') ? 'checked' : '' ?>>
                                        <span>This Week</span>
                                    </label>
                                </li>
                                <li class="mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary w-full">Apply</button>
                                </li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <div tabindex="0" role="button" class="btn btn-sm m-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                </svg>
                                Sort
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                <li><label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sort" class="radio radio-sm" 
                                           value="newest" <?= ($sort === 'newest' || empty($sort)) ? 'checked' : '' ?>>
                                    <span>Newest First</span>
                                </label></li>
                                <li><label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sort" class="radio radio-sm" 
                                           value="expiry" <?= ($sort === 'expiry') ? 'checked' : '' ?>>
                                    <span>Expiry Date (Soonest)</span>
                                </label></li>
                                <li><label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sort" class="radio radio-sm" 
                                           value="quantity" <?= ($sort === 'quantity') ? 'checked' : '' ?>>
                                    <span>Quantity (Highest)</span>
                                </label></li>
                                <li><label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sort" class="radio radio-sm" 
                                           value="name" <?= ($sort === 'name') ? 'checked' : '' ?>>
                                    <span>Name (A-Z)</span>
                                </label></li>
                                <li class="mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary w-full">Apply</button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                            </svg>
                        </div>
                        <input type="search" id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="block w-full p-2 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500" placeholder="Search food items...">
                        <button type="submit" class="absolute end-2.5 bottom-1 top-1 px-3 bg-primarycol text-white rounded-md hover:bg-fourth">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Food Items Grid -->
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <?php if (empty($donationItems)): ?>
                <div class="text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01" />
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No donation items available</h3>
                    <p class="mt-1 text-sm text-gray-500">There are currently no items available for donation. Please check back later.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($donationItems as $item): ?>
                        <div class="card bg-base-100 shadow-md hover:shadow-lg transition-shadow">
                            <figure class="h-48 bg-gray-100 relative">
                                <?php 
                                // Fix the image path handling
                                if (!empty($item['image'])) {
                                    // Remove the leading '../../' if it exists in the database path
                                    $imagePath = $item['image'];
                                    if (strpos($imagePath, '../../') === 0) {
                                        // The path already has ../../, so don't add it again
                                        $imagePath = substr($imagePath, 6); // Remove the ../../
                                        echo '<img src="../../' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['product_name']) . '" class="object-cover h-full w-full">';
                                    } else {
                                        // No prefix, add the path as normal
                                        echo '<img src="../../uploads/products/' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['product_name']) . '" class="object-cover h-full w-full">';
                                    }
                                } else {
                                    echo '<img src="../../assets/images/placeholder-food.jpg" alt="' . htmlspecialchars($item['product_name']) . '" class="object-cover h-full w-full">';
                                }
                                ?>
                                
                                <!-- Expiry badge -->
                                <?php
                                $expiryDate = !empty($item['expiry_date']) ? $item['expiry_date'] : $item['waste_date'];
                                $daysUntilExpiry = ceil((strtotime($expiryDate) - time()) / (60 * 60 * 24));
                                $expiryClass = $daysUntilExpiry <= 1 ? 'bg-red-500' : ($daysUntilExpiry <= 3 ? 'bg-amber-500' : 'bg-green-500');
                                ?>
                                <div class="absolute top-2 right-2">
                                    <span class="badge <?= $expiryClass ?> text-white border-none">
                                        Expires in <?= $daysUntilExpiry ?> day<?= $daysUntilExpiry !== 1 ? 's' : '' ?>
                                    </span>
                                </div>
                            </figure>
                            <div class="card-body p-4">
                                <div class="flex justify-between items-start">
                                    <h2 class="card-title text-lg"><?= htmlspecialchars($item['product_name']) ?></h2>
                                    <span class="badge badge-sm bg-sec text-primarycol border-none"><?= htmlspecialchars($item['category']) ?></span>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    <div class="flex justify-between">
                                        <span>Available:</span>
                                        <span class="font-medium"><?= htmlspecialchars($item['available_quantity']) ?> units</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Expiry Date:</span>
                                        <span class="font-medium"><?= date('M d, Y', strtotime($expiryDate)) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Location:</span>
                                        <span class="font-medium"><?= htmlspecialchars($item['branch_name']) ?></span>
                                    </div>
                                </div>
                                <div class="card-actions justify-between mt-4 items-center">
                                    <div class="form-control">
                                        <label class="input-group input-group-xs">
                                            <span>Qty</span>
                                            <input type="number" min="1" max="<?= $item['available_quantity'] ?>" value="1" 
                                                   class="input input-xs input-bordered w-16 item-quantity" 
                                                   data-item-id="<?= $item['waste_id'] ?>">
                                        </label>
                                    </div>
                                    <div class="flex gap-2">
                                        <button class="btn btn-sm btn-outline" 
                                                onclick="viewProductDetails(<?= htmlspecialchars(json_encode($item)) ?>)">
                                            View
                                        </button>
                                        <button class="btn btn-sm bg-primarycol text-white hover:bg-fourth" 
                                               onclick="addToCart({
                                                   waste_id: <?= $item['waste_id'] ?>,
                                                   product_id: <?= $item['product_id'] ?>,
                                                   branch_id: <?= $item['branch_id'] ?>, 
                                                   name: '<?= htmlspecialchars(addslashes($item['product_name'])) ?>', 
                                                   max: <?= $item['available_quantity'] ?>
                                               }, this)">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cart Drawer -->
    <div id="cartDrawer" class="fixed top-0 right-0 z-50 h-screen p-4 overflow-y-auto bg-white w-80 dark:bg-gray-800 transform translate-x-full transition-transform duration-300 ease-in-out shadow-lg">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Your Donation Request</h3>
        <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 absolute top-2.5 right-2.5 flex items-center justify-center" onclick="toggleCart()">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
            <span class="sr-only">Close menu</span>
        </button>

        <div class="mt-8">
            <div class="flex justify-between items-center py-2 border-y border-gray-200 mb-4">
                <span class="font-medium">Selected Items</span>
                <span class="text-sm text-gray-500">Daily Limit: <span id="cartCount">0</span>/<?= $dailyLimit ?></span>
            </div>
            
            <form method="POST" action="">
                <div id="cartItems" class="divide-y divide-gray-100 mb-4">
                    <!-- Cart items will be added here via JavaScript -->
                    <div class="py-3 flex justify-between items-center text-gray-500">
                        Your cart is empty
                    </div>
                </div>

                <div class="py-2 border-t border-gray-200">
                    <button type="button" id="clearCartBtn" onclick="clearCart()" class="w-full btn btn-sm btn-outline mt-2 mb-2" disabled>
                        Clear Cart
                    </button>
                    <button type="submit" name="submit_request" id="requestItemsBtn" class="w-full btn btn-sm bg-primarycol text-white hover:bg-fourth" disabled>
                        Request All Items
                    </button>
                    <p class="text-xs text-gray-500 mt-2 text-center">
                        Items will be reserved for pickup at their respective branches
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Details Modal -->
    <dialog id="productDetailsModal" class="modal">
        <div class="modal-box max-w-3xl">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 id="modalProductTitle" class="text-lg font-bold text-primarycol mb-2"></h3>
            
            <div class="flex flex-col md:flex-row gap-6">
                <div class="md:w-1/3">
                    <div class="bg-gray-100 rounded-lg overflow-hidden h-48 md:h-64">
                        <img id="modalProductImage" src="" alt="Product" class="w-full h-full object-cover">
                    </div>
                    
                    <div class="mt-4 flex flex-col gap-2">
                        <div>
                            <span class="text-sm text-gray-600">Category:</span>
                            <span id="modalProductCategory" class="font-medium"></span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Available Quantity:</span>
                            <span id="modalProductQuantity" class="font-medium"></span>
                        </div>
                        <div>
                            <div class="text-sm text-gray-600">Expires On:</div>
                            <div id="modalProductExpiry" class="font-medium"></div>
                        </div>
                    </div>
                </div>
                <div class="md:w-2/3">
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900">Pickup Location</h4>
                        <div class="mt-1 space-y-1">
                            <p id="modalBranchName" class="font-medium"></p>
                            <p id="modalBranchAddress" class="text-gray-600"></p>
                            <p id="modalBranchLocation" class="text-gray-600"></p>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <h4 class="font-medium text-gray-900">Request This Item</h4>
                        
                        <div class="flex items-center gap-3 mt-3">
                            <div class="form-control">
                                <label class="input-group">
                                    <span>Quantity</span>
                                    <input id="modalQuantityInput" type="number" min="1" class="input input-bordered w-20" value="1">
                                </label>
                            </div>
                            <button id="modalAddToCartBtn" class="btn bg-primarycol text-white hover:bg-fourth">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Close</button>
                </form>
            </div>
        </div>
    </dialog>

    <script>
        let cart = <?= !empty($_SESSION['donation_cart']) ? json_encode($_SESSION['donation_cart']) : '[]' ?>;
        const dailyLimit = <?= $dailyLimit ?>;
        let remainingLimit = <?= $remainingLimit ?>;
        let currentProductDetails = null;
        
        // Toggle cart drawer
        function toggleCart() {
            const drawer = document.getElementById('cartDrawer');
            drawer.classList.toggle('translate-x-full');
        }
        
        document.getElementById('cartToggle').addEventListener('click', function(e) {
            e.preventDefault();
            toggleCart();
        });
        
        // View product details
        function viewProductDetails(item) {
            currentProductDetails = item;
            
            // Set modal content
            document.getElementById('modalProductTitle').textContent = item.product_name;
            
            // Set image
            const modalImage = document.getElementById('modalProductImage');
            if (item.image && item.image.length > 0) {
                modalImage.src = '../../' + item.image;
            } else {
                modalImage.src = '../../assets/images/placeholder-food.jpg';
            }
            
            // Set other details
            document.getElementById('modalProductCategory').textContent = item.category;
            document.getElementById('modalProductQuantity').textContent = item.available_quantity + ' units';
            
            // Set expiry date
            const expiryDate = item.expiry_date || item.waste_date;
            document.getElementById('modalProductExpiry').textContent = formatDate(expiryDate);
            
            // Set location details
            document.getElementById('modalBranchName').textContent = item.branch_name;
            document.getElementById('modalBranchAddress').textContent = item.branch_address;
            document.getElementById('modalBranchLocation').textContent = item.branch_location || '';
            
            // Set quantity input max
            const quantityInput = document.getElementById('modalQuantityInput');
            quantityInput.max = item.available_quantity;
            quantityInput.value = 1;
            
            // Set add to cart button action
            const addToCartBtn = document.getElementById('modalAddToCartBtn');
            addToCartBtn.onclick = function() {
                const qty = parseInt(quantityInput.value);
                addToCartFromModal(item, qty);
            };
            
            // Open modal
            document.getElementById('productDetailsModal').showModal();
        }
        
        // Format date helper
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            });
        }
        
        // Add to cart from modal
        function addToCartFromModal(item, qty) {
            if (qty <= 0 || qty > item.available_quantity) {
                alert('Please select a valid quantity (between 1 and ' + item.available_quantity + ')');
                return;
            }
            
            // Create item object
            const cartItem = {
                waste_id: item.waste_id,
                product_id: item.product_id,
                branch_id: item.branch_id,
                name: item.product_name,
                quantity: qty,
                max: item.available_quantity
            };
            
            // Add to cart using existing function
            const result = addToCartCore(cartItem);
            if (result) {
                document.getElementById('productDetailsModal').close();
            }
        }
        
        // Core add to cart functionality
        function addToCartCore(item) {
            // Check if adding would exceed daily limit
            let currentTotal = cart.reduce((sum, cartItem) => sum + parseInt(cartItem.quantity), 0);
            if (currentTotal + parseInt(item.quantity) > remainingLimit) {
                alert(`You can only request up to ${dailyLimit} items per day. You currently have ${currentTotal} items in your cart and ${remainingLimit} items remaining in your daily limit.`);
                return false;
            }
            
            // Check if item is already in cart
            const existingItemIndex = cart.findIndex(cartItem => cartItem.waste_id === item.waste_id);
            
            if (existingItemIndex !== -1) {
                // Update quantity if not exceeding max
                const newQty = parseInt(cart[existingItemIndex].quantity) + parseInt(item.quantity);
                if (newQty > item.max) {
                    alert(`You cannot request more than ${item.max} units of this item.`);
                    return false;
                }
                cart[existingItemIndex].quantity = newQty;
            } else {
                cart.push({
                    waste_id: item.waste_id,
                    product_id: item.product_id,
                    branch_id: item.branch_id,
                    name: item.name,
                    quantity: parseInt(item.quantity),
                    max: item.max
                });
            }
            
            // Update cart count in navbar and save cart to session via AJAX
            saveCartToSession();
            
            // Update cart UI
            updateCartUI();
            
            // Show cart drawer
            document.getElementById('cartDrawer').classList.remove('translate-x-full');
            
            return true;
        }
        
        // Add to cart function for card buttons
        function addToCart(item, button) {
            const quantityInput = button.closest('.card-actions').querySelector('.item-quantity');
            const qty = parseInt(quantityInput.value);
            
            item.quantity = qty;
            const result = addToCartCore(item);
            
            if (result) {
                // Provide feedback
                button.textContent = 'Added!';
                button.classList.add('btn-success');
                
                setTimeout(() => {
                    button.textContent = 'Add to Cart';
                    button.classList.remove('btn-success');
                }, 1000);
            }
        }
        
        // Save cart to session via AJAX
        function saveCartToSession() {
            fetch('save_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({cart: cart})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count in navbar
                    const cartBadge = document.querySelector('#cartToggle span');
                    if (cartBadge) {
                        cartBadge.textContent = cart.length;
                    }
                }
            })
            .catch(error => {
                console.error('Error saving cart:', error);
            });
        }
        
        // Update cart UI
        function updateCartUI() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartCountElement = document.getElementById('cartCount');
            const clearCartBtn = document.getElementById('clearCartBtn');
            const requestItemsBtn = document.getElementById('requestItemsBtn');
            
            // Update total count
            const totalItems = cart.reduce((sum, item) => sum + parseInt(item.quantity), 0);
            cartCountElement.textContent = totalItems;
            
            // Enable/disable buttons
            clearCartBtn.disabled = cart.length === 0;
            requestItemsBtn.disabled = cart.length === 0;
            
            // Update cart items
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="py-3 flex justify-between items-center text-gray-500">
                        Your cart is empty
                    </div>
                `;
                return;
            }
            
            cartItemsContainer.innerHTML = '';
            cart.forEach((item, index) => {
                const itemElement = document.createElement('div');
                itemElement.className = 'py-3 flex justify-between';
                
                // Add hidden inputs to submit with the form
                const hiddenInputs = `
                    <input type="hidden" name="cart[${index}][waste_id]" value="${item.waste_id}">
                    <input type="hidden" name="cart[${index}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="cart[${index}][branch_id]" value="${item.branch_id}">
                    <input type="hidden" name="cart[${index}][name]" value="${item.name}">
                    <input type="hidden" name="cart[${index}][quantity]" value="${item.quantity}">
                    <input type="hidden" name="cart[${index}][max]" value="${item.max}">
                `;
                
                itemElement.innerHTML = `
                    ${hiddenInputs}
                    <div>
                        <h4 class="font-medium">${item.name}</h4>
                        <div class="flex items-center mt-1">
                            <button type="button" onclick="updateQuantity(${index}, -1)" class="btn btn-xs btn-circle">-</button>
                            <span class="mx-2">${item.quantity}</span>
                            <button type="button" onclick="updateQuantity(${index}, 1)" class="btn btn-xs btn-circle">+</button>
                        </div>
                    </div>
                    <button type="button" onclick="removeItem(${index})" class="btn btn-ghost btn-xs btn-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                `;
                cartItemsContainer.appendChild(itemElement);
            });
        }
        
        // Update item quantity
        function updateQuantity(index, change) {
            const newQty = parseInt(cart[index].quantity) + change;
            
            if (newQty <= 0) {
                removeItem(index);
                return;
            }
            
            if (newQty > cart[index].max) {
                alert(`You cannot request more than ${cart[index].max} units of this item.`);
                return;
            }
            
            // Check if change would exceed daily limit
            const currentTotal = cart.reduce((sum, item) => sum + parseInt(item.quantity), 0);
            if (currentTotal + change > remainingLimit) {
                alert(`You can only request up to ${dailyLimit} items per day. You have ${remainingLimit} items remaining in your daily limit.`);
                return;
            }
            
            cart[index].quantity = newQty;
            saveCartToSession();
            updateCartUI();
        }
        
        // Remove item from cart
        function removeItem(index) {
            cart.splice(index, 1);
            
            saveCartToSession();
            updateCartUI();
        }
        
        // Clear cart
        function clearCart() {
            cart = [];
            
            saveCartToSession();
            updateCartUI();
        }
        
        // Initialize sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        });
        
        document.getElementById('closeSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.add('-translate-x-full');
        });
        
        // Initialize cart UI on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartUI();
        });
    </script>
</body>
</html>