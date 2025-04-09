<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access
checkAuth(['admin']);

$pdo = getPDO();

// Auto-handle expired products
// Logic for handling expired products could be added here

// Get categories for filter
$categoriesQuery = $pdo->query("SELECT DISTINCT category FROM product_info ORDER BY category");
$categories = $categoriesQuery->fetchAll(PDO::FETCH_COLUMN);

// Build query based on filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$expiryFilter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
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
        ps.batch_number,
        pw.donation_status,
        pw.created_at,
        (SELECT COUNT(*) FROM ngo_donation_requests ndr WHERE ndr.waste_id = pw.id) as request_count
    FROM product_waste pw
    JOIN product_info p ON pw.product_id = p.id
    JOIN branches b ON pw.branch_id = b.id
    LEFT JOIN product_stock ps ON pw.stock_id = ps.id
    WHERE pw.disposal_method = 'donation' 
    AND pw.archived = 0
";

// Filter by donation status
if ($status != 'all') {
    $sql .= " AND pw.donation_status = ?";
    $params[] = $status;
}

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
    case 'requests':
        $sql .= " ORDER BY request_count DESC";
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

// Get overall statistics
$statsQuery = $pdo->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN donation_status = 'pending' THEN 1 ELSE 0 END) as pending_items,
        SUM(CASE WHEN donation_status = 'requested' THEN 1 ELSE 0 END) as requested_items,
        SUM(CASE WHEN donation_status = 'approved' THEN 1 ELSE 0 END) as approved_items
    FROM product_waste
    WHERE disposal_method = 'donation' AND archived = 0
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Donations - WasteWise</title>
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
    </script>
</head>

<body class="flex min-h-screen bg-gray-50">
    <?php include('../layout/nav.php'); ?>

    <div class="flex flex-col w-full overflow-y-auto">
        <!-- Page Header -->
        <div class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-primarycol">Food Donations Management</h1>
                        <p class="text-gray-500 text-sm mt-1">Manage all food items available for donation</p>
                    </div>
                </div>
            </div>
        </div>

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
                                <li class="menu-title">Status</li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="status" class="radio radio-sm" 
                                               value="all" <?= ($status === 'all' || empty($status)) ? 'checked' : '' ?>>
                                        <span>All</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="status" class="radio radio-sm" 
                                               value="pending" <?= ($status === 'pending') ? 'checked' : '' ?>>
                                        <span>Available</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="status" class="radio radio-sm" 
                                               value="requested" <?= ($status === 'requested') ? 'checked' : '' ?>>
                                        <span>Requested</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="status" class="radio radio-sm" 
                                               value="approved" <?= ($status === 'approved') ? 'checked' : '' ?>>
                                        <span>Approved</span>
                                    </label>
                                </li>
                                
                                <div class="divider my-1"></div>
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
                                           value="requests" <?= ($sort === 'requests') ? 'checked' : '' ?>>
                                    <span>Most Requested</span>
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
                        <button type="submit" class="absolute end-2.5 top-1/2 transform -translate-y-1/2 px-3 py-1 bg-primarycol text-white rounded-md hover:bg-fourth">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Food Items Grid -->
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <?php
    // Filter only pending/available items for display
    $availableItems = array_filter($donationItems, function($item) {
        return $item['donation_status'] === 'pending';
    });
    
    if (empty($availableItems)): ?>
        <div class="text-center py-12">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01" />
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">No available donation items found</h3>
            <p class="mt-1 text-sm text-gray-500">There are currently no items available for donation.</p>
        </div>
    <?php else: ?>
        <?php
        // Group items by branch
        $itemsByBranch = [];
        foreach ($availableItems as $item) {
            $branchId = $item['branch_id'];
            if (!isset($itemsByBranch[$branchId])) {
                $itemsByBranch[$branchId] = [
                    'name' => $item['branch_name'],
                    'address' => $item['branch_address'],
                    'items' => []
                ];
            }
            $itemsByBranch[$branchId]['items'][] = $item;
        }
        
        // Sort branches by name
        uasort($itemsByBranch, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        // Display count of available items
        $totalAvailableItems = count($availableItems);
        ?>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-primarycol">Available Food Donations (<?= $totalAvailableItems ?>)</h3>
        </div>
        
        <?php foreach ($itemsByBranch as $branchId => $branch): ?>
            <div class="mb-8">
                <div class="bg-sec p-4 rounded-lg mb-4">
                    <h3 class="text-xl font-bold text-primarycol"><?= htmlspecialchars($branch['name']) ?></h3>
                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($branch['address']) ?></p>
                </div>
                
                <div class="overflow-x-auto bg-white shadow-md rounded-lg">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Expiry</th>
                                <th class="text-center">NGO Requests</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branch['items'] as $item): ?>
                                <?php
                                $expiryDate = !empty($item['expiry_date']) ? $item['expiry_date'] : $item['waste_date'];
                                $daysUntilExpiry = ceil((strtotime($expiryDate) - time()) / (60 * 60 * 24));
                                $expiryClass = $daysUntilExpiry <= 1 ? 'text-red-600 font-bold' : ($daysUntilExpiry <= 3 ? 'text-amber-600 font-bold' : '');
                                ?>
                                <tr>
                                    <td class="min-w-[200px]">
                                        <div class="flex items-center space-x-3">
                                            <div class="avatar">
                                                <div class="w-12 h-12 rounded">
                                                    <?php
                                                    if (!empty($item['image'])) {
                                                        $imagePath = $item['image'];
                                                        if (strpos($imagePath, '../../') === 0) {
                                                            $imagePath = substr($imagePath, 6);
                                                            echo '<img src="../../' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['product_name']) . '">';
                                                        } else {
                                                            echo '<img src="../../uploads/products/' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['product_name']) . '">';
                                                        }
                                                    } else {
                                                        echo '<img src="../../assets/images/placeholder-food.jpg" alt="' . htmlspecialchars($item['product_name']) . '">';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                                <?php if (!empty($item['batch_number'])): ?>
                                                    <div class="text-xs text-gray-500">Batch: <?= htmlspecialchars($item['batch_number']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['category']) ?></td>
                                    <td><?= htmlspecialchars($item['available_quantity']) ?> units</td>
                                    <td>
                                        <div class="<?= $expiryClass ?>"><?= date('M d, Y', strtotime($expiryDate)) ?></div>
                                        <div class="text-xs"><?= $daysUntilExpiry ?> day<?= $daysUntilExpiry !== 1 ? 's' : '' ?> left</div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item['request_count'] > 0): ?>
                                            <span class="badge badge-accent"><?= $item['request_count'] ?> request<?= $item['request_count'] > 1 ? 's' : '' ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">No requests</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-xs btn-outline" 
                                                onclick="viewProductDetails(<?= htmlspecialchars(json_encode($item)) ?>)">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
                        <div>
                            <div class="text-sm text-gray-600">Status:</div>
                            <div id="modalProductStatus" class="font-medium"></div>
                        </div>
                        <div id="modalBatchInfo" class="hidden">
                            <div class="text-sm text-gray-600">Batch Number:</div>
                            <div id="modalBatchNumber" class="font-medium"></div>
                        </div>
                    </div>
                </div>
                <div class="md:w-2/3">
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900">Branch Information</h4>
                        <div class="mt-1 space-y-1">
                            <p id="modalBranchName" class="font-medium"></p>
                            <p id="modalBranchAddress" class="text-gray-600"></p>
                            <p id="modalBranchLocation" class="text-gray-600"></p>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4" id="requestsSection">
                        <h4 class="font-medium text-gray-900">Item Details</h4>
                        <div id="requestsInfo" class="mt-2">
                            <p class="text-gray-600">This item is available for donation and currently has no requests.</p>
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
    let currentProductDetails = null;
    
    // View product details
    function viewProductDetails(item) {
        currentProductDetails = item;
        
        // Set modal content
        document.getElementById('modalProductTitle').textContent = item.product_name;
        
        // Set image with improved path handling
        const modalImage = document.getElementById('modalProductImage');
        if (item.image && item.image.length > 0) {
            if (item.image.startsWith('../../')) {
                // The path already has ../../, so don't add it again
                modalImage.src = item.image;
            } else if (item.image.startsWith('uploads/')) {
                // Path starts with uploads/
                modalImage.src = '../../' + item.image;
            } else {
                // Default case, assume it needs the full path prefix
                modalImage.src = '../../uploads/products/' + item.image;
            }
        } else {
            modalImage.src = '../../assets/images/placeholder-food.jpg';
        }
        
        // Set other details
        document.getElementById('modalProductCategory').textContent = item.category;
        document.getElementById('modalProductQuantity').textContent = item.available_quantity + ' units';
        
        // Set expiry date
        const expiryDate = item.expiry_date || item.waste_date;
        document.getElementById('modalProductExpiry').textContent = formatDate(expiryDate);
        
        // Set status
        const statusText = {
            'pending': 'Available for donation',
            'requested': 'Requested by NGO(s)',
            'approved': 'Approved for donation'
        };
        document.getElementById('modalProductStatus').textContent = statusText[item.donation_status] || item.donation_status;
        
        // Set batch info if available
        if (item.batch_number) {
            document.getElementById('modalBatchNumber').textContent = item.batch_number;
            document.getElementById('modalBatchInfo').classList.remove('hidden');
        } else {
            document.getElementById('modalBatchInfo').classList.add('hidden');
        }
        
        // Set location details
        document.getElementById('modalBranchName').textContent = item.branch_name;
        document.getElementById('modalBranchAddress').textContent = item.branch_address;
        document.getElementById('modalBranchLocation').textContent = item.branch_location || '';
        
        // Update requests section title based on donation status
        const requestsSection = document.getElementById('requestsSection');
        const requestsInfo = document.getElementById('requestsInfo');
        
        if (item.donation_status === 'pending') {
            requestsSection.querySelector('h4').textContent = "Item Details";
            requestsInfo.innerHTML = '<p class="text-gray-600">This item is available for donation and currently has no requests.</p>';
        } else if (item.request_count > 0) {
            requestsSection.querySelector('h4').textContent = "Donation Requests";
            requestsInfo.innerHTML = '<div class="flex items-center gap-2"><span class="loading loading-spinner loading-xs"></span> Loading requests...</div>';
            loadDonationRequests(item.waste_id);
        } else {
            requestsSection.querySelector('h4').textContent = "Donation Status";
            requestsInfo.innerHTML = '<p class="text-gray-600">This item has been ' + item.donation_status + ' but has no active requests.</p>';
        }
        
        // Open modal
        document.getElementById('productDetailsModal').showModal();
    }
    
    // Load donation requests for an item
    function loadDonationRequests(wasteId) {
        const requestsContainer = document.getElementById('requestsInfo');
        
        // Use fetch API to get requests data
        fetch(`get_donation_requests.php?waste_id=${wasteId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.requests && data.requests.length > 0) {
                    let html = '<div class="overflow-x-auto">';
                    html += '<table class="table table-xs">';
                    html += '<thead><tr><th>NGO</th><th>Requested</th><th>Quantity</th><th>Status</th></tr></thead>';
                    html += '<tbody>';
                    
                    data.requests.forEach(req => {
                        html += `<tr>
                            <td>${req.organization_name}</td>
                            <td>${formatDateTime(req.request_date)}</td>
                            <td>${req.quantity_requested} units</td>
                            <td><span class="badge ${getStatusBadgeClass(req.status)}">${req.status}</span></td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    requestsContainer.innerHTML = html;
                } else {
                    requestsContainer.innerHTML = '<p class="text-gray-500">No donation requests found for this item.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching requests:', error);
                requestsContainer.innerHTML = '<div class="text-gray-500">No requests data available.</div>';
            });
    }
    
    // Helper functions
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            weekday: 'long',
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        });
    }
    
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function getStatusBadgeClass(status) {
        switch (status) {
            case 'approved': return 'bg-green-100 text-green-800';
            case 'pending': return 'bg-amber-100 text-amber-800';
            case 'rejected': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }
</script>
</body>
</html>