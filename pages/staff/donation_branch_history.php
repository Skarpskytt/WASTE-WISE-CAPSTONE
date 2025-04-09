<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Fix for missing branch_name - fetch it from database if not in session
if (!isset($_SESSION['branch_name'])) {
    $branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
    $branchStmt->execute([$branchId]);
    $branchName = $branchStmt->fetchColumn();
    
    // Store it in the session for future use
    $_SESSION['branch_name'] = $branchName ?: 'Your Branch'; // Fallback value
} else {
    $branchName = $_SESSION['branch_name'];
}

// Get status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$dateFilter = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$ngoFilter = isset($_GET['ngo']) ? (int)$_GET['ngo'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Date range for filtering
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

if ($dateFilter === 'today') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($dateFilter === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($dateFilter === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($dateFilter === 'custom' && isset($_GET['start_date'], $_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// Build the base query with proper joins
$baseQuery = "
    SELECT 
        ndr.id,
        ndr.product_id,
        ndr.branch_id,
        ndr.waste_id,
        ndr.quantity_requested,
        ndr.request_date,
        ndr.pickup_date,
        ndr.pickup_time,
        ndr.status,
        ndr.is_received,
        ndr.received_at,
        ndr.ngo_notes,
        ndr.admin_notes,
        pi.name as product_name,
        pi.category,
        pi.image,
        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
        u.email as ngo_email,
        u.phone as ngo_phone,
        u.id as ngo_id,
        pw.waste_value,
        pw.auto_approval,
        pw.donation_priority,
        dp.prepared_date,
        dp.staff_notes
    FROM 
        ngo_donation_requests ndr
    JOIN 
        product_info pi ON ndr.product_id = pi.id
    JOIN 
        product_waste pw ON ndr.waste_id = pw.id
    JOIN 
        users u ON ndr.ngo_id = u.id
    LEFT JOIN
        ngo_profiles np ON u.id = np.user_id
    LEFT JOIN
        donation_prepared dp ON ndr.id = dp.ngo_request_id
    WHERE 
        ndr.branch_id = ? AND 
        pw.branch_id = ? AND
        DATE(ndr.request_date) BETWEEN ? AND ?
";

// Apply status filter
if ($statusFilter !== 'all') {
    if ($statusFilter === 'received') {
        $baseQuery .= " AND ndr.is_received = 1";
    } elseif ($statusFilter === 'prepared') {
        $baseQuery .= " AND dp.prepared_date IS NOT NULL AND ndr.is_received = 0";
    } elseif ($statusFilter === 'approved') {
        $baseQuery .= " AND ndr.status = 'approved' AND dp.prepared_date IS NULL";
    } elseif ($statusFilter === 'pending') {
        $baseQuery .= " AND ndr.status = 'pending'";
    } elseif ($statusFilter === 'rejected') {
        $baseQuery .= " AND ndr.status = 'rejected'";
    }
}

// Apply NGO filter
if ($ngoFilter > 0) {
    $baseQuery .= " AND ndr.ngo_id = ?";
}

// Apply search filter
if (!empty($search)) {
    $baseQuery .= " AND (pi.name LIKE ? OR pi.category LIKE ? OR np.organization_name LIKE ? OR u.organization_name LIKE ?)";
}

// Order by
$baseQuery .= " ORDER BY ndr.request_date DESC, ndr.id DESC";

// Prepare and execute query
$params = [$branchId, $branchId, $startDate, $endDate];

if ($ngoFilter > 0) {
    $params[] = $ngoFilter;
}

if (!empty($search)) {
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$stmt = $pdo->prepare($baseQuery);
$stmt->execute($params);
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_donations,
        SUM(CASE WHEN ndr.is_received = 1 THEN 1 ELSE 0 END) AS total_completed,
        SUM(CASE WHEN dp.prepared_date IS NOT NULL AND ndr.is_received = 0 THEN 1 ELSE 0 END) AS total_prepared,
        SUM(CASE WHEN ndr.status = 'approved' AND dp.prepared_date IS NULL THEN 1 ELSE 0 END) AS total_approved,
        SUM(CASE WHEN ndr.status = 'pending' THEN 1 ELSE 0 END) AS total_pending,
        SUM(ndr.quantity_requested * pi.price_per_unit) AS total_value,
        COUNT(DISTINCT ndr.ngo_id) AS total_ngos
    FROM ngo_donation_requests ndr
    JOIN product_info pi ON ndr.product_id = pi.id
    JOIN product_waste pw ON ndr.waste_id = pw.id
    LEFT JOIN donation_prepared dp ON ndr.id = dp.ngo_request_id
    WHERE 
        ndr.branch_id = ? AND 
        pw.branch_id = ?
");
$statsQuery->execute([$branchId, $branchId]);
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Get list of NGOs for filter dropdown
$ngosQuery = $pdo->prepare("
    SELECT DISTINCT 
        u.id,
        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name
    FROM ngo_donation_requests ndr
    JOIN users u ON ndr.ngo_id = u.id
    LEFT JOIN ngo_profiles np ON u.id = np.user_id
    WHERE ndr.branch_id = ?
    ORDER BY ngo_name ASC
");
$ngosQuery->execute([$branchId]);
$ngos = $ngosQuery->fetchAll(PDO::FETCH_ASSOC);

// Helper function to format time slots
function formatTimeSlot($time) {
    $hour = (int)date('G', strtotime($time));
    
    switch($hour) {
        case 9:
            return '9:00 AM - 10:00 AM';
        case 10:
            return '10:00 AM - 11:00 AM';
        case 11:
            return '11:00 AM - 12:00 PM';
        case 13:
            return '1:00 PM - 2:00 PM';
        case 14:
            return '2:00 PM - 3:00 PM';
        case 15:
            return '3:00 PM - 4:00 PM';
        case 16:
            return '4:00 PM - 5:00 PM';
        default:
            return date('h:i A', strtotime($time));
    }
}

// Group donations by date
$donationsByDate = [];
foreach ($donations as $donation) {
    $date = date('Y-m-d', strtotime($donation['request_date']));
    if (!isset($donationsByDate[$date])) {
        $donationsByDate[$date] = [];
    }
    $donationsByDate[$date][] = $donation;
}
// Sort by date descending
krsort($donationsByDate);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
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
    <style>
        /* Status badge styles */
        .status-badge {
            @apply px-2 py-1 rounded-full text-xs font-medium;
        }
        .status-pending {
            @apply bg-yellow-100 text-yellow-800;
        }
        .status-approved {
            @apply bg-blue-100 text-blue-800;
        }
        .status-prepared {
            @apply bg-green-100 text-green-800;
        }
        .status-received {
            @apply bg-purple-100 text-purple-800;
        }
        .status-rejected {
            @apply bg-red-100 text-red-800;
        }
        
        /* Priority badge styles */
        .priority-normal {
            @apply bg-gray-100 text-gray-800;
        }
        .priority-high {
            @apply bg-orange-100 text-orange-800;
        }
        .priority-urgent {
            @apply bg-red-100 text-red-800;
        }
    </style>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include '../layout/staff_nav.php' ?>
    
    <div class="p-7 w-full overflow-auto">
        <nav class="mb-4">
            <ol class="flex items-center gap-2 text-gray-600">
                <li><a href="staff_dashboard.php" class="hover:text-primarycol">Dashboard</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="donation_request.php" class="hover:text-primarycol">Donation Requests</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="donation_branch_history.php" class="text-primarycol font-medium">Donation History</a></li>
            </ol>
        </nav>
        
        <div class="flex justify-between items-start mb-4">
            <div>
                <h1 class="text-3xl font-bold mb-2 text-primarycol">Branch Donation History</h1>
                <p class="text-gray-500">Track all donations from <?= htmlspecialchars($branchName) ?></p>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Donations</p>
                        <p class="text-2xl font-bold"><?= number_format($stats['total_donations'] ?? 0) ?></p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-2">
                        <i class="fas fa-gift text-blue-500"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Completed</p>
                        <p class="text-2xl font-bold"><?= number_format($stats['total_completed'] ?? 0) ?></p>
                    </div>
                    <div class="rounded-full bg-green-100 p-2">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-purple-500">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-gray-600">NGO Recipients</p>
                        <p class="text-2xl font-bold"><?= number_format($stats['total_ngos'] ?? 0) ?></p>
                    </div>
                    <div class="rounded-full bg-purple-100 p-2">
                        <i class="fas fa-users text-purple-500"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-amber-500">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Value</p>
                        <p class="text-2xl font-bold">₱<?= number_format($stats['total_value'] ?? 0, 2) ?></p>
                    </div>
                    <div class="rounded-full bg-amber-100 p-2">
                        <i class="fas fa-coins text-amber-500"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <h2 class="text-lg font-semibold mb-4">Filter Donations</h2>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="select select-bordered w-full">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="prepared" <?= $statusFilter === 'prepared' ? 'selected' : '' ?>>Prepared</option>
                        <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                
                <!-- Date Range Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <select name="date_range" id="date_range" class="select select-bordered w-full">
                        <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="this_week" <?= $dateFilter === 'this_week' ? 'selected' : '' ?>>This Week</option>
                        <option value="this_month" <?= $dateFilter === 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="custom" <?= $dateFilter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <!-- Custom Date Range (initially hidden) -->
                <div id="custom_date_container" class="<?= $dateFilter === 'custom' ? '' : 'hidden' ?> md:col-span-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="input input-bordered w-full">
                    </div>
                </div>
                
                <!-- NGO Filter -->
                <div class="<?= $dateFilter !== 'custom' ? 'md:col-span-2' : '' ?>">
                    <label class="block text-sm font-medium text-gray-700 mb-1">NGO</label>
                    <select name="ngo" class="select select-bordered w-full">
                        <option value="0">All NGOs</option>
                        <?php foreach ($ngos as $ngo): ?>
                            <option value="<?= $ngo['id'] ?>" <?= $ngoFilter === $ngo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ngo['ngo_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Search -->
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by product name, category, or NGO name" 
                           class="input input-bordered w-full">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn bg-primarycol text-white w-full">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Donations List -->
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Donation Records</h2>
                <span class="badge badge-lg bg-primarycol text-white">
                    <?= count($donations) ?> records found
                </span>
            </div>
            
            <?php if (empty($donations)): ?>
                <div class="text-center py-8">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-box-open text-gray-400 text-4xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">No donation records found</h3>
                    <p class="mt-1 text-gray-500">Try adjusting your filters to see more results</p>
                </div>
            <?php else: ?>
                <?php foreach ($donationsByDate as $date => $dayDonations): ?>
                    <div class="mb-6">
                        <h3 class="font-semibold text-md bg-gray-50 p-2 rounded flex items-center mb-3 sticky top-0">
                            <i class="fas fa-calendar-day mr-2 text-primarycol"></i>
                            <?= date('l, F j, Y', strtotime($date)) ?>
                            <span class="ml-2 text-sm text-gray-500">(<?= count($dayDonations) ?> items)</span>
                        </h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($dayDonations as $donation): ?>
                                <div class="border rounded-lg overflow-hidden">
                                    <!-- Donation Header -->
                                    <div class="bg-gray-50 p-3 border-b flex justify-between items-center">
                                        <div class="flex items-center">
                                            <div class="mr-3">
                                                <?php 
                                                // Determine status class
                                                $statusClass = 'status-pending';
                                                $statusText = 'Pending';
                                                
                                                if ($donation['is_received']) {
                                                    $statusClass = 'status-received';
                                                    $statusText = 'Received';
                                                } elseif ($donation['prepared_date']) {
                                                    $statusClass = 'status-prepared';
                                                    $statusText = 'Prepared';
                                                } elseif ($donation['status'] === 'approved') {
                                                    $statusClass = 'status-approved';
                                                    $statusText = 'Approved';
                                                } elseif ($donation['status'] === 'rejected') {
                                                    $statusClass = 'status-rejected';
                                                    $statusText = 'Rejected';
                                                }
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                                
                                                <?php if ($donation['auto_approval']): ?>
                                                    <span class="status-badge bg-green-100 text-green-800 ml-1">Auto-Approved</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="font-medium"><?= htmlspecialchars($donation['product_name']) ?></span>
                                                <span class="text-sm text-gray-500 ml-2">(<?= htmlspecialchars($donation['category']) ?>)</span>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="text-sm mr-2">ID: <?= $donation['id'] ?></span>
                                            <?php 
                                            // Show priority badge if not normal
                                            if ($donation['donation_priority'] !== 'normal'): 
                                                $priorityClass = 'priority-' . $donation['donation_priority'];
                                            ?>
                                                <span class="status-badge <?= $priorityClass ?>">
                                                    <?= ucfirst($donation['donation_priority']) ?> Priority
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Donation Details -->
                                    <div class="p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <!-- Left Column: Product Details -->
                                            <div class="flex">
                                                <div class="w-16 h-16 bg-gray-100 rounded overflow-hidden mr-3 flex-shrink-0">
                                                    <?php if (!empty($donation['image'])): ?>
                                                        <img src="<?= htmlspecialchars('../../assets/uploads/products/' . $donation['image']) ?>" 
                                                             alt="<?= htmlspecialchars($donation['product_name']) ?>"
                                                             class="w-full h-full object-cover"
                                                             onerror="this.src='../../assets/images/default-product.jpg'">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <i class="fas fa-box text-gray-400"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="text-sm">
                                                        <span class="font-medium">Quantity:</span> 
                                                        <?= $donation['quantity_requested'] ?> units
                                                    </div>
                                                    <div class="text-sm">
                                                        <span class="font-medium">Value:</span> 
                                                        ₱<?= number_format($donation['waste_value'], 2) ?>
                                                    </div>
                                                    <div class="text-sm">
                                                        <span class="font-medium">Requested:</span> 
                                                        <?= date('M j, g:i A', strtotime($donation['request_date'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Middle Column: NGO Details -->
                                            <div>
                                                <div class="text-sm">
                                                    <span class="font-medium">NGO:</span> 
                                                    <?= htmlspecialchars($donation['ngo_name']) ?>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="font-medium">Contact:</span> 
                                                    <?= !empty($donation['ngo_phone']) ? htmlspecialchars($donation['ngo_phone']) : 'N/A' ?>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="font-medium">Email:</span> 
                                                    <?= htmlspecialchars($donation['ngo_email']) ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Right Column: Pickup Details -->
                                            <div>
                                                <div class="text-sm">
                                                    <span class="font-medium">Pickup Date:</span> 
                                                    <?= date('M j, Y', strtotime($donation['pickup_date'])) ?>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="font-medium">Pickup Time:</span> 
                                                    <?= formatTimeSlot($donation['pickup_time']) ?>
                                                </div>
                                                <?php if ($donation['is_received']): ?>
                                                <div class="text-sm text-green-600">
                                                    <span class="font-medium">Received:</span> 
                                                    <?= date('M j, g:i A', strtotime($donation['received_at'])) ?>
                                                </div>
                                                <?php elseif ($donation['prepared_date']): ?>
                                                <div class="text-sm text-blue-600">
                                                    <span class="font-medium">Prepared:</span> 
                                                    <?= date('M j, g:i A', strtotime($donation['prepared_date'])) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Notes Section -->
                                        <?php if (!empty($donation['ngo_notes']) || !empty($donation['admin_notes']) || !empty($donation['staff_notes'])): ?>
                                        <div class="mt-4 border-t pt-4">
                                            <h4 class="text-sm font-semibold mb-2">Notes</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <?php if (!empty($donation['ngo_notes'])): ?>
                                                <div class="text-sm bg-blue-50 p-2 rounded">
                                                    <div class="font-medium text-blue-700">NGO Notes:</div>
                                                    <div><?= nl2br(htmlspecialchars($donation['ngo_notes'])) ?></div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($donation['admin_notes'])): ?>
                                                <div class="text-sm bg-purple-50 p-2 rounded">
                                                    <div class="font-medium text-purple-700">Admin Notes:</div>
                                                    <div><?= nl2br(htmlspecialchars($donation['admin_notes'])) ?></div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($donation['staff_notes'])): ?>
                                                <div class="text-sm bg-green-50 p-2 rounded">
                                                    <div class="font-medium text-green-700">Preparation Notes:</div>
                                                    <div><?= nl2br(htmlspecialchars($donation['staff_notes'])) ?></div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Show/hide custom date range fields
        document.getElementById('date_range').addEventListener('change', function() {
            const customDateContainer = document.getElementById('custom_date_container');
            if (this.value === 'custom') {
                customDateContainer.classList.remove('hidden');
            } else {
                customDateContainer.classList.add('hidden');
            }
        });
    </script>
</body>
</html>