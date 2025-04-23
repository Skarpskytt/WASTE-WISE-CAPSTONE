<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check if user is NGO
checkAuth(['ngo']);

$pdo = getPDO();
$ngoId = $_SESSION['user_id'];

// Set daily limit to 200 directly without depending on system_settings table
$dailyLimit = 200; // Fixed value instead of reading from missing table

// Check how many items this NGO has already requested today
$todayRequests = $pdo->prepare("
    SELECT SUM(quantity_requested) as total_requested 
    FROM ngo_donation_requests 
    WHERE ngo_id = ? AND DATE(request_date) = CURDATE()
");
$todayRequests->execute([$ngoId]);
$requestedToday = $todayRequests->fetchColumn() ?: 0;

// Calculate remaining requests for today
$remainingLimit = $dailyLimit - $requestedToday;
if ($remainingLimit < 0) {
    $remainingLimit = 0;
}

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$expiryFilter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

// Base query
$sql = "
    SELECT 
        dp.id AS donation_id,
        pw.id AS waste_id, 
        pi.id AS product_id,
        b.id AS branch_id,
        pi.name AS product_name, 
        pi.category,
        pi.image,
        pi.price_per_unit,
        b.name AS branch_name,
        b.address AS branch_address,
        dp.quantity_available,
        dp.expiry_date,
        pw.waste_date,
        pw.auto_approval,
        pw.pickup_instructions
    FROM donation_products dp
    JOIN product_waste pw ON dp.waste_id = pw.id
    JOIN product_info pi ON dp.product_id = pi.id
    JOIN branches b ON dp.branch_id = b.id
    WHERE dp.status = 'available' 
    AND dp.quantity_available > 0
    AND dp.expiry_date >= CURDATE()
";

$params = [];

// Apply search filter
if (!empty($search)) {
    $sql .= " AND (pi.name LIKE ? OR pi.category LIKE ? OR b.name LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Apply category filter
if (!empty($category)) {
    $sql .= " AND pi.category = ?";
    $params[] = $category;
}

// Apply expiry filter
if (!empty($expiryFilter)) {
    switch ($expiryFilter) {
        case 'today':
            $sql .= " AND dp.expiry_date = CURDATE()";
            break;
        case 'tomorrow':
            $sql .= " AND dp.expiry_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $sql .= " AND dp.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
    }
}

// Apply sorting
switch ($sort) {
    case 'expiry':
        $sql .= " ORDER BY dp.expiry_date ASC";
        break;
    case 'quantity':
        $sql .= " ORDER BY dp.quantity_available DESC";
        break;
    case 'name':
        $sql .= " ORDER BY pi.name ASC";
        break;
    case 'branch':
        $sql .= " ORDER BY b.name ASC, dp.creation_date DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY dp.creation_date DESC";
        break;
}

// Get all categories for filter dropdown
$categoryStmt = $pdo->query("SELECT DISTINCT category FROM product_info WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle donation requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['items'])) {
    if ($remainingLimit <= 0) {
        $errorMessage = "You've reached your daily request limit. Please try again tomorrow.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Generate a unique request group ID to link all items in this request together
            $requestGroupId = 'req-' . uniqid('', true);
            $pickupDate = $_POST['pickup_date'];
            $pickupTime = $_POST['pickup_time'];
            $ngoNotes = $_POST['ngo_notes'] ?? '';
            
            // Parse the JSON string into array
            $requestItems = json_decode($_POST['items'], true);
            $totalRequested = 0;
            $requestedItemsList = array(); // Store details of requested items
            
            foreach ($requestItems as $item) {
                // Validate quantity is available
                $checkStmt = $pdo->prepare("SELECT quantity_available FROM donation_products WHERE id = ?");
                $checkStmt->execute([$item['donation_id']]);
                $availableQty = $checkStmt->fetchColumn();
                
                if ($availableQty < $item['quantity']) {
                    throw new Exception("Requested quantity for " . htmlspecialchars($item['product_name']) . " exceeds available amount.");
                }
                
                // Create request
                $requestStmt = $pdo->prepare("
                    INSERT INTO ngo_donation_requests (
                        ngo_id, product_id, branch_id, waste_id, request_date, 
                        pickup_date, pickup_time, status, quantity_requested, 
                        ngo_notes, request_group_id
                    ) VALUES (
                        ?, ?, ?, ?, NOW(), 
                        ?, ?, ?, ?, 
                        ?, ?
                    )
                ");
                
                // Determine initial status based on auto-approval
                $initialStatus = $item['auto_approval'] ? 'approved' : 'pending';
                
                $requestStmt->execute([
                    $ngoId,
                    $item['product_id'],
                    $item['branch_id'],
                    $item['waste_id'],
                    $pickupDate,
                    $pickupTime,
                    $initialStatus,
                    $item['quantity'],
                    $ngoNotes,
                    $requestGroupId
                ]);

                // Store request details for confirmation
                $requestedItemsList[] = array(
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'branch_name' => $item['branch_name'],
                    'status' => $initialStatus
                );
                
                // Update available quantity
                $updateStmt = $pdo->prepare("
                    UPDATE donation_products 
                    SET quantity_available = quantity_available - ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$item['quantity'], $item['donation_id']]);
                
                // Update status to fully_allocated if no quantity left
                $checkUpdatedStmt = $pdo->prepare("SELECT quantity_available FROM donation_products WHERE id = ?");
                $checkUpdatedStmt->execute([$item['donation_id']]);
                $updatedQty = $checkUpdatedStmt->fetchColumn();
                
                if ($updatedQty <= 0) {
                    $statusStmt = $pdo->prepare("UPDATE donation_products SET status = 'fully_allocated' WHERE id = ?");
                    $statusStmt->execute([$item['donation_id']]);
                }
                
                $totalRequested += $item['quantity'];
            }
            
            // Create notification for branch owner or admin for pending approval requests
            $pendingRequests = array_filter($requestItems, function($item) {
                return !$item['auto_approval'];
            });
            
            if (!empty($pendingRequests)) {
                $branchIds = array_unique(array_map(function($item) {
                    return $item['branch_id'];
                }, $pendingRequests));
                
                foreach ($branchIds as $branchId) {
                    $branchItems = array_filter($pendingRequests, function($item) use ($branchId) {
                        return $item['branch_id'] == $branchId;
                    });
                    
                    $itemCount = count($branchItems);
                    
                    $notifyStmt = $pdo->prepare("
                        INSERT INTO notifications (
                            target_role, branch_id, message, notification_type, link, is_read, created_at
                        ) VALUES (
                            'admin', ?, ?, 'donation_request', ?, 0, NOW()
                        )
                    ");
                    
                    $ngoNameStmt = $pdo->prepare("SELECT organization_name FROM users WHERE id = ?");
                    $ngoNameStmt->execute([$ngoId]);
                    $ngoName = $ngoNameStmt->fetchColumn();
                    
                    $message = "New donation request from $ngoName: $itemCount item(s) need approval";
                    $link = "../admin/donation_requests.php?request_id=" . $requestGroupId;
                    $notifyStmt->execute([$branchId, $message, $link]);
                }
            }
            
            $pdo->commit();
            $successMessage = "Your donation request has been submitted successfully!";
            
            // Store request details in session for confirmation page
            $_SESSION['request_confirmation'] = [
                'request_id' => $requestGroupId,
                'pickup_date' => $pickupDate,
                'pickup_time' => $pickupTime,
                'total_requested' => $totalRequested,
                'requested_items' => $requestedItemsList
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error processing your request: " . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donationItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for confirmation data from a previous request
$showConfirmation = false;
$confirmationData = null;
if (isset($_SESSION['request_confirmation'])) {
    $showConfirmation = true;
    $confirmationData = $_SESSION['request_confirmation'];
    // Clear it so it only shows once
    unset($_SESSION['request_confirmation']);
}
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

        <?php if ($showConfirmation): ?>
        <!-- Request Confirmation Banner -->
        <div class="bg-green-50 border-l-4 border-green-500 p-6 m-4 shadow-md rounded-md">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-green-800">Donation Request Confirmed!</h3>
                    <p class="text-sm text-green-700 mt-1">
                        Your request ID: <span class="font-mono font-bold"><?= htmlspecialchars($confirmationData['request_id']) ?></span>
                    </p>
                    <div class="mt-3 flex flex-col sm:flex-row sm:items-center">
                        <div class="bg-white px-4 py-2 rounded-md mr-4 mb-2 sm:mb-0 text-sm">
                            <span class="font-semibold">Pickup Date:</span> 
                            <?= date('F j, Y', strtotime($confirmationData['pickup_date'])) ?>
                        </div>
                        <div class="bg-white px-4 py-2 rounded-md text-sm">
                            <span class="font-semibold">Pickup Time:</span> 
                            <?= date('g:i A', strtotime($confirmationData['pickup_time'])) ?>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h4 class="font-medium text-green-800">Requested Items:</h4>
                        <div class="bg-white rounded-md mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($confirmationData['requested_items'] as $item): ?>
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item['quantity']) ?> units
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item['branch_name']) ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <?php if ($item['status'] == 'approved'): ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    Approved
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="donation_history.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primarycol hover:bg-green-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            View All Requests
                        </a>
                        <button type="button" class="ml-2 text-primarycol hover:text-green-700 font-medium" onclick="document.getElementById('requestConfirmationBanner').classList.add('hidden')">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($successMessage) && !$showConfirmation): ?>
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
                                <li><label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="sort" class="radio radio-sm" 
                                           value="branch" <?= ($sort === 'branch') ? 'checked' : '' ?>>
                                    <span>Branch (A-Z)</span>
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
                <?php 
                // Group items by branch if sort by branch is selected
                if ($sort === 'branch' || true) { // Always group by branch
                    $groupedItems = [];
                    foreach ($donationItems as $item) {
                        $branchId = $item['branch_id'];
                        if (!isset($groupedItems[$branchId])) {
                            $groupedItems[$branchId] = [
                                'branch_name' => $item['branch_name'],
                                'branch_address' => $item['branch_address'],
                                'items' => []
                            ];
                        }
                        
                        $groupedItems[$branchId]['items'][] = $item;
                    }
                    
                    foreach ($groupedItems as $branchId => $branchData): 
                    ?>
                        <div class="mb-8">
                            <div class="flex items-center mb-4">
                                <h3 class="text-xl font-semibold text-primarycol"><?= htmlspecialchars($branchData['branch_name']) ?></h3>
                                <div class="ml-3 px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full">
                                    <?= count($branchData['items']) ?> items
                                </div>
                            </div>
                            <p class="text-gray-600 mb-4"><?= htmlspecialchars($branchData['branch_address']) ?></p>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <?php foreach ($branchData['items'] as $item): ?>
                                    <div class="card bg-base-100 shadow-md hover:shadow-lg transition-shadow">
                                        <figure class="h-48 bg-gray-100 relative">
                                            <?php 
                                            if (!empty($item['image'])) {
                                                $imagePath = $item['image'];
                                                if (strpos($imagePath, '../../') === 0) {
                                                    $imagePath = substr($imagePath, 6);
                                                    echo '<img src="../../' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['product_name']) . '" class="object-cover h-full w-full">';
                                                } else {
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
                                                    <span class="font-medium"><?= htmlspecialchars($item['quantity_available']) ?> units</span>
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
                                            <div class="card-actions justify-between items-center mt-4">
                                                <div class="flex items-center">
                                                    <input type="number" class="item-quantity w-16 input input-bordered input-sm mr-1" 
                                                        value="1" min="1" max="<?= $item['quantity_available'] ?>" />
                                                    <span class="text-xs text-gray-500">of <?= $item['quantity_available'] ?></span>
                                                </div>
                                                <button class="btn btn-sm bg-primarycol text-white hover:bg-fourth" 
                                                        onclick="addToBasket(<?= htmlspecialchars(json_encode([
                                                            'donation_id' => $item['donation_id'],
                                                            'waste_id' => $item['waste_id'],
                                                            'product_id' => $item['product_id'],
                                                            'branch_id' => $item['branch_id'],
                                                            'product_name' => $item['product_name'],
                                                            'branch_name' => $item['branch_name'],
                                                            'quantity_available' => $item['quantity_available'],
                                                            'expiry_date' => $expiryDate,
                                                            'auto_approval' => $item['auto_approval']
                                                        ])) ?>, this)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                    </svg>
                                                    Add to Basket
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php } else { ?>
                    <!-- Default display (not grouped by branch) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($donationItems as $item): ?>
                            <!-- Item card (same as above) -->
                        <?php endforeach; ?>
                    </div>
                <?php } ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Donation Basket Drawer -->
    <div id="basketDrawer" class="fixed top-0 right-0 z-50 h-screen p-4 overflow-y-auto bg-white w-80 dark:bg-gray-800 transform translate-x-full transition-transform duration-300 ease-in-out shadow-lg">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Your Donation Basket</h3>
        <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 absolute top-2.5 right-2.5 flex items-center justify-center" onclick="toggleBasket()">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
            <span class="sr-only">Close menu</span>
        </button>

        <div class="mt-8">
            <div class="flex justify-between items-center py-2 border-y border-gray-200 mb-4">
                <span class="font-medium">Selected Items</span>
                <span class="text-sm text-gray-500">Daily Limit: <span id="basketCount">0</span>/<?= $dailyLimit ?></span>
            </div>
            
            <form method="POST" action="" id="donationRequestForm">
                <input type="hidden" name="pickup_date" id="pickup_date_input">
                <input type="hidden" name="pickup_time" id="pickup_time_input">
                <input type="hidden" name="ngo_notes" id="ngo_notes_input">
                <input type="hidden" name="items" id="basket_items_input">
                
                <div id="basketItems" class="divide-y divide-gray-100 mb-4">
                    <!-- Basket items will be added here via JavaScript -->
                    <div class="py-3 flex justify-between items-center text-gray-500">
                        Your basket is empty
                    </div>
                </div>

                <div class="py-2 border-t border-gray-200">
                    <button type="button" id="clearBasketBtn" onclick="clearBasket()" class="w-full btn btn-sm btn-outline mt-2 mb-2" disabled>
                        Clear Basket
                    </button>
                    <button type="button" id="requestItemsBtn" class="w-full btn btn-sm bg-primarycol text-white hover:bg-fourth" 
                            onclick="showRequestConfirmation()" disabled>
                        Request Selected Items
                    </button>
                    <p class="text-xs text-gray-500 mt-2 text-center">
                        Items will be reserved for pickup at their respective branches
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Confirmation Modal -->
    <dialog id="requestConfirmationModal" class="modal">
        <div class="modal-box max-w-4xl">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-bold text-lg text-primarycol mb-4">Confirm Donation Request</h3>
            
            <div class="bg-sec p-4 rounded-lg mb-4">
                <h4 class="font-medium text-gray-700 mb-2">Pickup Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-medium">Pickup Date</span>
                        </label>
                        <input type="date" id="confirmPickupDate" class="input input-bordered" 
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                               value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                    </div>
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-medium">Pickup Time</span>
                        </label>
                        <input type="time" id="confirmPickupTime" class="input input-bordered" 
                               value="09:00" min="09:00" max="17:00" required>
                        <label class="label">
                            <span class="label-text-alt">Business hours: 9:00 AM - 5:00 PM</span>
                        </label>
                    </div>
                </div>
                <div class="form-control mt-2">
                    <label class="label">
                        <span class="label-text font-medium">Special Instructions (optional)</span>
                    </label>
                    <textarea id="confirmNotes" class="textarea textarea-bordered" 
                              placeholder="Add any notes or special instructions for this request"></textarea>
                </div>
            </div>
            
            <div class="mb-4">
                <h4 class="font-medium text-gray-700 mb-2">Requested Items</h4>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Branch</th>
                                <th>Expires</th>
                            </tr>
                        </thead>
                        <tbody id="confirmItemsList">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="alert bg-yellow-100 text-yellow-800 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <h3 class="font-bold">Please Note</h3>
                    <div class="text-sm">Once submitted, you will be able to track your request in the donation history. Items will be reserved pending approval.</div>
                </div>
            </div>
            
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Cancel</button>
                </form>
                <button id="finalSubmitBtn" class="btn bg-primarycol text-white hover:bg-fourth" onclick="submitDonationRequest()">
                    Submit Donation Request
                </button>
            </div>
        </div>
    </dialog>

    <script>
    // Donation basket functionality
    let basket = [];
    const dailyLimit = <?= $dailyLimit ?>;
    const remainingLimit = <?= $remainingLimit ?>;

    // Toggle basket drawer
    function toggleBasket() {
        const drawer = document.getElementById('basketDrawer');
        drawer.classList.toggle('translate-x-full');
    }

    // Add item to basket
    function addToBasket(item, button) {
        const quantityInput = button.closest('.card-actions').querySelector('.item-quantity');
        const quantity = parseInt(quantityInput.value);
        
        if (isNaN(quantity) || quantity <= 0) {
            alert('Please enter a valid quantity');
            return;
        }
        
        if (quantity > item.quantity_available) {
            alert('Requested quantity exceeds available amount');
            return;
        }
        
        // Check if adding this item would exceed daily limit
        const currentBasketTotal = basket.reduce((total, item) => total + item.quantity, 0);
        if (currentBasketTotal + quantity > remainingLimit) {
            alert(`You can only request ${remainingLimit} more items today. Please adjust the quantity.`);
            return;
        }
        
        // Check if item already in basket
        const existingItemIndex = basket.findIndex(i => i.donation_id === item.donation_id);
        
        if (existingItemIndex > -1) {
            const newQuantity = basket[existingItemIndex].quantity + quantity;
            if (newQuantity > item.quantity_available) {
                alert('Total requested quantity exceeds available amount');
                return;
            }
            basket[existingItemIndex].quantity = newQuantity;
            
            // Show success feedback
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg> Updated`;
        } else {
            basket.push({
                ...item,
                quantity: quantity
            });
            
            // Show success feedback
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg> Added`;
        }
        
        // Reset button after delay
        setTimeout(() => {
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg> Add to Basket`;
        }, 2000);
        
        updateBasketUI();
        
        // Briefly flash the basket icon to draw attention
        const basketIcon = document.getElementById('basketToggle');
        if (basketIcon) {
            basketIcon.classList.add('animate-pulse', 'text-primarycol');
            setTimeout(() => {
                basketIcon.classList.remove('animate-pulse', 'text-primarycol');
            }, 1000);
        }
        
        // Open the basket drawer if it's the first item
        if (basket.length === 1) {
            toggleBasket();
        }
    }

    // Remove item from basket
    function removeFromBasket(index) {
        basket.splice(index, 1);
        updateBasketUI();
    }

    // Clear entire basket
    function clearBasket() {
        basket = [];
        updateBasketUI();
    }

    // Add this function to update the basket count in the navbar
    function updateNavBasketCount() {
        const navBasketCount = document.getElementById('nav-basket-count');
        if (navBasketCount) {
            const totalItems = basket.reduce((total, item) => total + item.quantity, 0);
            navBasketCount.textContent = totalItems;
            navBasketCount.style.display = totalItems > 0 ? 'flex' : 'none';
        }
    }

    // Update the updateBasketUI function to also call updateNavBasketCount
    const originalUpdateBasketUI = updateBasketUI;
    updateBasketUI = function() {
        originalUpdateBasketUI();
        updateNavBasketCount();
    };

    // Update basket UI
    function updateBasketUI() {
        const basketContainer = document.getElementById('basketItems');
        const basketCountElement = document.getElementById('basketCount');
        const clearBasketBtn = document.getElementById('clearBasketBtn');
        const requestItemsBtn = document.getElementById('requestItemsBtn');
        
        // Update basket items count
        const totalItems = basket.reduce((total, item) => total + item.quantity, 0);
        basketCountElement.textContent = totalItems;
        
        // Enable/disable buttons
        clearBasketBtn.disabled = basket.length === 0;
        requestItemsBtn.disabled = basket.length === 0;
        
        // Clear basket container
        basketContainer.innerHTML = '';
        
        if (basket.length === 0) {
            basketContainer.innerHTML = `
                <div class="py-3 flex justify-between items-center text-gray-500">
                    Your basket is empty
                </div>
            `;
            return;
        }
        
        // Add items to basket
        basket.forEach((item, index) => {
            const itemElement = document.createElement('div');
            itemElement.className = 'py-3';
            itemElement.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <h5 class="text-sm font-medium">${item.product_name}</h5>
                        <p class="text-xs text-gray-600 mt-1">
                            ${item.quantity} units · ${item.branch_name}
                        </p>
                        <p class="text-xs text-gray-500">
                            Expires: ${new Date(item.expiry_date).toLocaleDateString()}
                        </p>
                    </div>
                    <button onclick="removeFromBasket(${index})" class="text-gray-400 hover:text-red-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            `;
            basketContainer.appendChild(itemElement);
        });
        
        // Update hidden input with basket items
        document.getElementById('basket_items_input').value = JSON.stringify(basket);
    }

    // Submit donation request
    function submitDonationRequest() {
        // Get form values
        const pickupDate = document.getElementById('confirmPickupDate').value;
        const pickupTime = document.getElementById('confirmPickupTime').value;
        const ngoNotes = document.getElementById('confirmNotes').value;
        
        // Validate pickup date/time
        if (!pickupDate || !pickupTime) {
            alert('Please select pickup date and time');
            return;
        }
        
        // Validate pickup date is not in the past
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(pickupDate);
        if (selectedDate < today) {
            alert('Please select a pickup date in the future');
            return;
        }
        
        // Set form input values
        document.getElementById('pickup_date_input').value = pickupDate;
        document.getElementById('pickup_time_input').value = pickupTime;
        document.getElementById('ngo_notes_input').value = ngoNotes;
        document.getElementById('basket_items_input').value = JSON.stringify(basket);
        
        // Show loading state on button
        const submitBtn = document.getElementById('finalSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Processing...
        `;
        
        // Submit the form
        document.getElementById('donationRequestForm').submit();
    }

    // Initialize navigation elements to work with basket
    document.addEventListener('DOMContentLoaded', function() {
        // Set up basket toggle button in nav
        const basketToggle = document.getElementById('basketToggle');
        if (basketToggle) {
            basketToggle.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                toggleBasket();
            });
        }

        // Close confirmation banner if it exists
        const dismissBtn = document.getElementById('dismissConfirmation');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function() {
                document.getElementById('requestConfirmationBanner').classList.add('hidden');
            });
        }
        
        // Initialize the basket count in the navbar
        updateNavBasketCount();
    });

    // Show request confirmation dialog
    function showRequestConfirmation() {
        // Populate confirmation dialog with basket items
        const confirmItemsList = document.getElementById('confirmItemsList');
        confirmItemsList.innerHTML = '';
        
        basket.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.product_name}</td>
                <td>${item.quantity}</td>
                <td>${item.branch_name}</td>
                <td>${new Date(item.expiry_date).toLocaleDateString()}</td>
            `;
            confirmItemsList.appendChild(row);
        });
        
        // Show the modal
        const modal = document.getElementById('requestConfirmationModal');
        modal.showModal();
    }
</script>
</body>
</html>