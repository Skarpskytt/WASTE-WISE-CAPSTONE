<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access
checkAuth(['admin']);

$pdo = getPDO();

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

// UPDATED QUERY: Remove batch_number field from the query since it doesn't exist
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
        b.location AS branch_location,
        dp.quantity_available,
        dp.expiry_date,
        pw.waste_date,
        pw.auto_approval,
        dp.status AS donation_status,
        dp.creation_date,
        dp.donation_priority,
        (SELECT COUNT(*) FROM ngo_donation_requests ndr WHERE ndr.waste_id = pw.id) AS request_count
    FROM donation_products dp
    JOIN product_waste pw ON dp.waste_id = pw.id
    JOIN product_info pi ON dp.product_id = pi.id
    JOIN branches b ON dp.branch_id = b.id
    WHERE dp.quantity_available > 0
";

// Filter by donation status (available, fully_allocated, expired)
if ($status != 'all') {
    $sql .= " AND dp.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $sql .= " AND (pi.name LIKE ? OR pi.category LIKE ? OR b.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($category)) {
    $sql .= " AND pi.category = ?";
    $params[] = $category;
}

// Apply expiry date filter
if (!empty($expiryFilter)) {
    switch ($expiryFilter) {
        case 'today':
            $sql .= " AND DATE(dp.expiry_date) = CURDATE()";
            break;
        case 'tomorrow':
            $sql .= " AND DATE(dp.expiry_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $sql .= " AND DATE(dp.expiry_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
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
    case 'requests':
        $sql .= " ORDER BY request_count DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY dp.creation_date DESC";
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
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_items,
        SUM(CASE WHEN status = 'fully_allocated' THEN 1 ELSE 0 END) as allocated_items,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_items
    FROM donation_products
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
                        <p class="text-gray-500 text-sm mt-1">Manage food items available for donation to NGOs</p>
                    </div>
                    <div class="flex gap-2">
                        <div class="stats shadow">
                            <div class="stat py-2 px-4">
                                <div class="stat-title text-xs">Available Items</div>
                                <div class="stat-value text-lg text-primarycol"><?= $stats['available_items'] ?? 0 ?></div>
                            </div>
                        </div>
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
                                               value="available" <?= ($status === 'available') ? 'checked' : '' ?>>
                                        <span>Available</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="status" class="radio radio-sm" 
                                               value="fully_allocated" <?= ($status === 'fully_allocated') ? 'checked' : '' ?>>
                                        <span>Fully Allocated</span>
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
            // Filter only available items by default if no status is selected
            $displayItems = $donationItems;
            if ($status === 'all' || empty($status)) {
                // Show all items regardless of status
            } else {
                $displayItems = array_filter($donationItems, function($item) use ($status) {
                    return $item['donation_status'] === $status;
                });
            }
            
            if (empty($displayItems)): ?>
                <div class="text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01" />
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No donation items found</h3>
                    <p class="mt-1 text-sm text-gray-500">There are currently no items available for donation matching your criteria.</p>
                </div>
            <?php else: ?>
                <?php
                // Group items by branch
                $itemsByBranch = [];
                foreach ($displayItems as $item) {
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
                $totalDisplayItems = count($displayItems);
                $statusLabel = ($status === 'all' || empty($status)) ? 'All' : ucfirst(str_replace('_', ' ', $status));
                ?>
                
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-primarycol"><?= $statusLabel ?> Food Donations (<?= $totalDisplayItems ?>)</h3>
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
                                        <th class="text-center">Auto-Approve</th>
                                        <th class="text-center">Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branch['items'] as $item): ?>
                                        <?php
                                        $expiryDate = $item['expiry_date'] ?? $item['waste_date'];
                                        $daysUntilExpiry = ceil((strtotime($expiryDate) - time()) / (60 * 60 * 24));
                                        $expiryClass = $daysUntilExpiry <= 1 ? 'text-red-600 font-bold' : ($daysUntilExpiry <= 3 ? 'text-amber-600 font-bold' : '');
                                        
                                        // Status badge class
                                        $statusClass = '';
                                        $statusLabel = '';
                                        
                                        switch ($item['donation_status']) {
                                            case 'available':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                $statusLabel = 'Available';
                                                break;
                                            case 'fully_allocated':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                $statusLabel = 'Fully Allocated';
                                                break;
                                            case 'expired':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                $statusLabel = 'Expired';
                                                break;
                                            default:
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                $statusLabel = $item['donation_status'];
                                        }
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
                                                                    // Path already has structure
                                                                    echo '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['product_name']) . '">';
                                                                } else if (strpos($imagePath, 'assets/') === 0) {
                                                                    // Just a path starting with assets/
                                                                    echo '<img src="../../' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($item['product_name']) . '">';
                                                                } else {
                                                                    // Just a filename
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
                                                        <div class="text-xs">
                                                            <span class="badge badge-sm <?= $statusClass ?>"><?= $statusLabel ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($item['category']) ?></td>
                                            <td><?= htmlspecialchars($item['quantity_available']) ?> units</td>
                                            <td>
                                                <div class="<?= $expiryClass ?>"><?= date('M d, Y', strtotime($expiryDate)) ?></div>
                                                <div class="text-xs"><?= $daysUntilExpiry ?> day<?= $daysUntilExpiry !== 1 ? 's' : '' ?> left</div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($item['auto_approval']): ?>
                                                    <span class="badge badge-success badge-sm">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge badge-ghost badge-sm">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $priorityClass = '';
                                                switch ($item['donation_priority']) {
                                                    case 'urgent':
                                                        $priorityClass = 'badge-error';
                                                        break;
                                                    case 'high':
                                                        $priorityClass = 'badge-warning';
                                                        break;
                                                    default:
                                                        $priorityClass = 'badge-info';
                                                }
                                                ?>
                                                <span class="badge <?= $priorityClass ?> badge-sm"><?= ucfirst($item['donation_priority']) ?></span>
                                            </td>
                                            <td>
                                                <div class="flex gap-1">
                                                    <button class="btn btn-xs btn-outline" 
                                                            onclick="viewProductDetails(<?= htmlspecialchars(json_encode($item)) ?>)">
                                                        View
                                                    </button>
                                                    <button class="btn btn-xs btn-primary" 
                                                            onclick="editDonation(<?= $item['donation_id'] ?>)">
                                                        Edit
                                                    </button>
                                                </div>
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
                        <div>
                            <div class="text-sm text-gray-600">Priority Level:</div>
                            <div id="modalPriority" class="font-medium"></div>
                        </div>
                    </div>
                </div>
                <div class="md:w-2/3">
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900">Branch Information</h4>
                        <div class="mt-1 space-y-1">
                            <p id="modalBranchName" class="font-medium"></p>
                            <p id="modalBranchAddress" class="text-gray-600"></p>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4 mb-4">
                        <h4 class="font-medium text-gray-900">Visibility Settings</h4>
                        <div class="flex flex-col mt-2 gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Auto-approval:</span>
                                <span id="modalAutoApproval" class="font-medium"></span>
                            </div>
                            <p class="text-xs text-gray-500">
                                Auto-approved donations are immediately approved for NGO pickup without requiring staff review.
                            </p>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4" id="requestsSection">
                        <h4 class="font-medium text-gray-900">NGO Requests</h4>
                        <div id="requestsInfo" class="mt-2">
                            <p class="text-gray-600">Loading NGO requests data...</p>
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
    
    <!-- Edit Donation Modal -->
    <dialog id="editDonationModal" class="modal">
        <div class="modal-box max-w-2xl">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="text-lg font-bold text-primarycol mb-4">Edit Donation Settings</h3>
            
            <form id="editDonationForm" method="POST" action="update_donation.php">
                <input type="hidden" id="edit_donation_id" name="donation_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-medium">Donation Priority</span>
                        </label>
                        <select id="edit_priority" name="donation_priority" class="select select-bordered w-full">
                            <option value="normal">Normal</option>
                            <option value="high">High Priority</option>
                            <option value="urgent">Urgent</option>
                        </select>
                        <label class="label">
                            <span class="label-text-alt">Higher priority items appear more prominently to NGOs</span>
                        </label>
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-medium">Auto-Approval</span>
                        </label>
                        <select id="edit_auto_approval" name="auto_approval" class="select select-bordered w-full">
                            <option value="1">Yes - Auto-approve for NGOs</option>
                            <option value="0">No - Manual approval required</option>
                        </select>
                        <label class="label">
                            <span class="label-text-alt">Auto-approved items don't need admin review</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text font-medium">Pickup Instructions</span>
                    </label>
                    <textarea id="edit_instructions" name="pickup_instructions" class="textarea textarea-bordered" rows="3" placeholder="Special handling or pickup instructions for NGOs"></textarea>
                </div>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Status</span>
                    </label>
                    <select id="edit_status" name="status" class="select select-bordered w-full">
                        <option value="available">Available</option>
                        <option value="fully_allocated">Fully Allocated</option>
                    </select>
                </div>
                
                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn">Cancel</button>
                    </form>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
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
                modalImage.src = item.image;
            } else if (item.image.startsWith('assets/')) {
                modalImage.src = '../../' + item.image;
            } else {
                modalImage.src = '../../uploads/products/' + item.image;
            }
        } else {
            modalImage.src = '../../assets/images/placeholder-food.jpg';
        }
        
        // Set other details
        document.getElementById('modalProductCategory').textContent = item.category;
        document.getElementById('modalProductQuantity').textContent = item.quantity_available + ' units';
        
        // Set expiry date
        const expiryDate = item.expiry_date || item.waste_date;
        const daysUntilExpiry = Math.ceil((new Date(expiryDate) - new Date()) / (1000 * 60 * 60 * 24));
        document.getElementById('modalProductExpiry').innerHTML = 
            formatDate(expiryDate) + 
            `<span class="text-sm text-${daysUntilExpiry <= 3 ? 'red' : daysUntilExpiry <= 7 ? 'amber' : 'green'}-600 ml-2">
                (${daysUntilExpiry} days left)
            </span>`;
        
        // Set status
        const statusLabels = {
            'available': 'Available for donation',
            'fully_allocated': 'Fully allocated to NGOs',
            'expired': 'Expired'
        };
        document.getElementById('modalProductStatus').textContent = statusLabels[item.donation_status] || item.donation_status;
        
        // Set priority
        const priorityLabels = {
            'normal': 'Normal',
            'high': 'High Priority',
            'urgent': 'Urgent'
        };
        const priorityColors = {
            'normal': 'blue',
            'high': 'amber',
            'urgent': 'red'
        };
        document.getElementById('modalPriority').innerHTML = 
            `<span class="text-${priorityColors[item.donation_priority] || 'gray'}-600 font-medium">
                ${priorityLabels[item.donation_priority] || item.donation_priority}
            </span>`;
        
        // Set location details
        document.getElementById('modalBranchName').textContent = item.branch_name;
        document.getElementById('modalBranchAddress').textContent = item.branch_address;
        
        // Set auto-approval status
        document.getElementById('modalAutoApproval').innerHTML = 
            item.auto_approval 
                ? '<span class="badge badge-success">Yes</span>' 
                : '<span class="badge badge-ghost">No</span>';
        
        // Update NGO requests info
        const requestsInfo = document.getElementById('requestsInfo');
        
        // Load NGO requests for this donation
        loadNGORequests(item.waste_id);
        
        // Open modal
        document.getElementById('productDetailsModal').showModal();
    }
    
    // Load NGO requests for a donation
    function loadNGORequests(wasteId) {
        const requestsInfo = document.getElementById('requestsInfo');
        requestsInfo.innerHTML = '<div class="flex items-center gap-2"><span class="loading loading-spinner loading-xs"></span> Loading requests...</div>';
        
        // Fetch NGO requests from server
        fetch(`get_ngo_requests.php?waste_id=${wasteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.requests && data.requests.length > 0) {
                    let html = '<div class="overflow-x-auto">';
                    html += '<table class="table table-xs table-zebra w-full">';
                    html += '<thead><tr><th>NGO</th><th>Quantity</th><th>Status</th><th>Request Date</th><th>Pickup Date</th></tr></thead>';
                    html += '<tbody>';
                    
                    data.requests.forEach(req => {
                        const statusClass = {
                            'pending': 'badge-warning',
                            'approved': 'badge-success',
                            'rejected': 'badge-error'
                        }[req.status] || 'badge-ghost';
                        
                        html += `<tr>
                            <td>${req.ngo_name}</td>
                            <td>${req.quantity_requested}</td>
                            <td><span class="badge badge-sm ${statusClass}">${req.status}</span></td>
                            <td>${formatDateTime(req.request_date)}</td>
                            <td>${formatDate(req.pickup_date)}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    requestsInfo.innerHTML = html;
                } else {
                    requestsInfo.innerHTML = '<p class="text-gray-500">No NGO requests have been made for this item yet.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading NGO requests:', error);
                requestsInfo.innerHTML = '<p class="text-red-500">Error loading request data. Please try again.</p>';
            });
    }
    
    // Edit donation
    function editDonation(donationId) {
        // Fetch donation details
        fetch(`get_donation.php?id=${donationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const donation = data.donation;
                    
                    // Populate form fields
                    document.getElementById('edit_donation_id').value = donationId;
                    document.getElementById('edit_priority').value = donation.donation_priority;
                    document.getElementById('edit_auto_approval').value = donation.auto_approval;
                    document.getElementById('edit_instructions').value = donation.pickup_instructions || '';
                    document.getElementById('edit_status').value = donation.status;
                    
                    // Show modal
                    document.getElementById('editDonationModal').showModal();
                } else {
                    alert('Error loading donation details: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading donation details. Please try again.');
            });
    }
    
    // Helper functions
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
    
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        const options = { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString('en-US', options);
    }
    
    // Submit form handler
    document.getElementById('editDonationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        
        fetch('update_donation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Donation updated successfully!');
                document.getElementById('editDonationModal').close();
                location.reload(); // Refresh the page to show updated data
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating donation. Please try again.');
        });
    });
</script>
</body>
</html>