<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check if user is admin or staff
checkAuth(['admin', 'staff']);

// Initialize filters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10; // Number of records per page
$offset = ($page - 1) * $perPage;

// Base query to get all donation requests with related information
$sql = "
    SELECT 
        dr.id,
        dr.product_waste_id,
        dr.quantity_requested,
        dr.pickup_date,
        dr.pickup_time,
        dr.notes as ngo_notes,
        dr.status,
        dr.is_received,
        dr.staff_notes,
        dr.created_at,
        dr.updated_at,
        dr.received_at,
        pw.waste_quantity as available_quantity,
        p.name as product_name,
        p.category,
        pw.donation_expiry_date,
        CONCAT(u.fname, ' ', u.lname) as ngo_name,
        u.organization_name,
        u.email as ngo_email,
        b.name as branch_name,
        b.address as branch_address
    FROM 
        donation_requests dr
    JOIN 
        product_waste pw ON dr.product_waste_id = pw.id
    JOIN 
        products p ON pw.product_id = p.id
    JOIN 
        users u ON dr.ngo_id = u.id
    JOIN 
        branches b ON pw.branch_id = b.id
    WHERE 1=1
";

// Add filters
$params = [];

// Search term filter
if (!empty($searchTerm)) {
    $sql .= " AND (
        p.name LIKE ? OR 
        p.category LIKE ? OR 
        u.organization_name LIKE ? OR 
        CONCAT(u.fname, ' ', u.lname) LIKE ? OR
        b.name LIKE ?
    )";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

// Date range filter
if (!empty($startDate)) {
    $sql .= " AND DATE(dr.updated_at) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $sql .= " AND DATE(dr.updated_at) <= ?";
    $params[] = $endDate;
}

// Status filter
if (!empty($statusFilter)) {
    if ($statusFilter === 'completed') {
        $sql .= " AND (dr.status = 'completed' OR dr.is_received = 1)";
    } else {
        $sql .= " AND dr.status = ?";
        $params[] = $statusFilter;
    }
}

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM (" . $sql . ") as count_query";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Order by most recent first and add pagination
$sql .= " ORDER BY dr.updated_at DESC LIMIT $perPage OFFSET $offset";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For export functionality
if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'])) {
    $exportType = $_GET['export'];
    
    // Set the appropriate headers based on export type
    if ($exportType === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="donation_history.xls"');
        header('Cache-Control: max-age=0');
        
        // Output the Excel file header
        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Donation History</title>
        </head>
        <body>
            <table border='1'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Organization</th>
                        <th>NGO Name</th>
                        <th>Food Item</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Processed Date</th>
                        <th>Received Date</th>
                    </tr>
                </thead>
                <tbody>";
                
        foreach ($donations as $donation) {
            $status = '';
            if ($donation['is_received'] || $donation['status'] === 'completed') {
                $status = 'Completed';
            } elseif ($donation['status'] === 'approved') {
                $status = 'Approved';
            } elseif ($donation['status'] === 'rejected') {
                $status = 'Rejected';
            } else {
                $status = 'Pending';
            }
            
            echo "<tr>
                    <td>{$donation['id']}</td>
                    <td>" . htmlspecialchars($donation['organization_name']) . "</td>
                    <td>" . htmlspecialchars($donation['ngo_name']) . "</td>
                    <td>" . htmlspecialchars($donation['product_name']) . "</td>
                    <td>" . htmlspecialchars($donation['category']) . "</td>
                    <td>{$donation['quantity_requested']}</td>
                    <td>" . htmlspecialchars($donation['branch_name']) . "</td>
                    <td>{$status}</td>
                    <td>" . date('M d, Y', strtotime($donation['updated_at'])) . "</td>
                    <td>" . ($donation['received_at'] ? date('M d, Y', strtotime($donation['received_at'])) : 'N/A') . "</td>
                </tr>";
        }
        
        echo "</tbody></table></body></html>";
        exit;
    } 
    else if ($exportType === 'pdf') {
        // For PDF export, you need a PDF library
        // This is a placeholder - you would integrate with a PDF library like TCPDF or mPDF
        // For now, we'll redirect with a message
        header('Location: donations.php?pdf_msg=PDF+functionality+requires+additional+setup');
        exit;
    }
}

// Get stats for the donation overview
$statsQuery = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' AND is_received = 0 THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN is_received = 1 OR status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM donation_requests
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History | WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <!-- Add Date Range Picker CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
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
    <?php include '../layout/nav.php'; ?>

    <div class="flex flex-col w-full p-6 space-y-6 overflow-y-auto">
        <div class="flex justify-between items-center">
            <h2 class="text-3xl font-bold text-primarycol">Donation History</h2>
            
            <div class="flex space-x-2">
                <a href="?export=excel<?= !empty($searchTerm) ? '&search='.$searchTerm : '' ?><?= !empty($startDate) ? '&start_date='.$startDate : '' ?><?= !empty($endDate) ? '&end_date='.$endDate : '' ?><?= !empty($statusFilter) ? '&status='.$statusFilter : '' ?>" 
                   class="btn btn-sm bg-green-600 hover:bg-green-700 text-white">
                    Export Excel
                </a>
                <a href="?export=pdf<?= !empty($searchTerm) ? '&search='.$searchTerm : '' ?><?= !empty($startDate) ? '&start_date='.$startDate : '' ?><?= !empty($endDate) ? '&end_date='.$endDate : '' ?><?= !empty($statusFilter) ? '&status='.$statusFilter : '' ?>" 
                   class="btn btn-sm bg-red-600 hover:bg-red-700 text-white">
                    Export PDF
                </a>
            </div>
        </div>
        
        <?php if (isset($_GET['pdf_msg'])): ?>
            <div class="alert alert-info shadow-lg mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($_GET['pdf_msg']) ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Donation Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <span class="text-gray-500 text-sm">Total Donations</span>
                <h3 class="text-2xl font-bold"><?= $stats['total'] ?></h3>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <span class="text-gray-500 text-sm">Pending</span>
                <h3 class="text-2xl font-bold text-yellow-500"><?= $stats['pending'] ?></h3>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <span class="text-gray-500 text-sm">Approved</span>
                <h3 class="text-2xl font-bold text-blue-500"><?= $stats['approved'] ?></h3>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <span class="text-gray-500 text-sm">Completed</span>
                <h3 class="text-2xl font-bold text-green-500"><?= $stats['completed'] ?></h3>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <span class="text-gray-500 text-sm">Rejected</span>
                <h3 class="text-2xl font-bold text-red-500"><?= $stats['rejected'] ?></h3>
            </div>
        </div>
        
        <!-- Enhanced Filter Section with search and date picker in the same row -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" placeholder="Search by product, NGO, etc..." 
                           class="input input-bordered w-full" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" name="start_date" class="input input-bordered w-full" 
                               value="<?= htmlspecialchars($startDate) ?>" placeholder="From">
                        <span class="self-center">to</span>
                        <input type="date" name="end_date" class="input input-bordered w-full" 
                               value="<?= htmlspecialchars($endDate) ?>" placeholder="To">
                    </div>
                </div>
                
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="select select-bordered w-full">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                
                <div class="md:col-span-1 flex items-end justify-end space-x-2">
                    <a href="donations.php" class="btn btn-outline">Reset</a>
                    <button type="submit" class="btn bg-primarycol text-white">Filter</button>
                </div>
            </form>
        </div>
        
        <!-- Donations Table -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <?php if (empty($donations)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 20h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-xl font-medium">No donations found</p>
                    <p class="mt-1">Try adjusting your filters or search criteria</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead class="bg-sec text-primarycol">
                            <tr>
                                <th>ID</th>
                                <th>Organization</th>
                                <th>NGO Name</th>
                                <th>Food Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th>Processed Date</th>
                                <th>Received Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donations as $donation): ?>
                                <tr>
                                    <td><?= $donation['id'] ?></td>
                                    <td><?= htmlspecialchars($donation['organization_name'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($donation['ngo_name']) ?></td>
                                    <td><?= htmlspecialchars($donation['product_name']) ?></td>
                                    <td><?= htmlspecialchars($donation['category']) ?></td>
                                    <td><?= $donation['quantity_requested'] ?></td>
                                    <td><?= htmlspecialchars($donation['branch_name']) ?></td>
                                    <td>
                                        <?php if ($donation['is_received'] || $donation['status'] === 'completed'): ?>
                                            <span class="badge badge-success">Completed</span>
                                        <?php elseif ($donation['status'] === 'approved'): ?>
                                            <span class="badge badge-warning">Approved</span>
                                        <?php elseif ($donation['status'] === 'rejected'): ?>
                                            <span class="badge badge-error">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($donation['updated_at'])) ?></td>
                                    <td><?= $donation['received_at'] ? date('M d, Y', strtotime($donation['received_at'])) : 'N/A' ?></td>
                                    <td>
                                        <button onclick="openDonationModal(<?= $donation['id'] ?>)" 
                                                class="btn btn-xs bg-primarycol text-white">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <?php if($totalPages > 1): ?>
                <div class="flex justify-center mt-6">
                    <div class="join">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page-1 ?><?= !empty($searchTerm) ? '&search='.$searchTerm : '' ?><?= !empty($startDate) ? '&start_date='.$startDate : '' ?><?= !empty($endDate) ? '&end_date='.$endDate : '' ?><?= !empty($statusFilter) ? '&status='.$statusFilter : '' ?>" 
                               class="join-item btn">«</a>
                        <?php else: ?>
                            <button class="join-item btn btn-disabled">«</button>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        // Always show first page
                        if($startPage > 1): ?>
                            <a href="?page=1<?= !empty($searchTerm) ? '&search='.$searchTerm : '' ?><?= !empty($startDate) ? '&start_date='.$startDate : '' ?><?= !empty($endDate) ? '&end_date='.$endDate : '' ?><?= !empty($statusFilter) ? '&status='.$statusFilter : '' ?>" 
                               class="join-item btn">1</a>
                            <?php if($startPage > 2): ?>
                                <button class="join-item btn btn-disabled">...</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if($i == $page): ?>
                                <button class="join-item btn btn-active"><?= $i ?></button>
                            <?php else: ?>
                                <a href="?page=<?= $i ?><?= !empty($searchTerm) ? '&search='.$searchTerm : '' ?><?= !empty($startDate) ? '&start_date='.$startDate : '' ?><?= !empty($endDate) ? '&end_date='.$endDate : '' ?><?= !empty($statusFilter) ? '&status='.$statusFilter : '' ?>" 
                                   class="join-item btn"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <!-- Always show last page -->
                        <?php if($endPage < $totalPages): ?>
                            <?php if($endPage < $totalPages - 1): ?>
                                <button class="join-item btn btn-disabled">...</button>
                            <?php endif; ?>
                            <a href="?page=<?= $totalPages ?><?= !empty($searchTerm) ? '&search='.$searchTerm : '' ?><?= !empty($startDate) ? '&start_date='.$startDate : '' ?><?= !empty($endDate) ? '&end_date='.$endDate : '' ?><?= !empty($statusFilter) ? '&status='.$statusFilter : '' ?>" 
                               class="join-item btn"><?= $totalPages ?></a>
                        <?php endif; ?>
                        
                        <?php if($page < $totalPages): ?>
                            <a href="?page=<?= $page+1 ?><?= !empty($searchTerm) ? '&search='.$searchTerm : '' ?><?= !empty($startDate) ? '&start_date='.$startDate : '' ?><?= !empty($endDate) ? '&end_date='.$endDate : '' ?><?= !empty($statusFilter) ? '&status='.$statusFilter : '' ?>" 
                               class="join-item btn">»</a>
                        <?php else: ?>
                            <button class="join-item btn btn-disabled">»</button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center text-sm text-gray-600 mt-2">
                    Showing <?= ($offset + 1) ?> to <?= min($offset + $perPage, $totalRecords) ?> of <?= $totalRecords ?> entries
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add jQuery and Date Range Picker JS -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            // Optional: Enhanced date range picker functionality
            $('input[name="start_date"], input[name="end_date"]').on('change', function() {
                const startDate = $('input[name="start_date"]').val();
                const endDate = $('input[name="end_date"]').val();
                
                // If start date is selected and end date is empty, set end date to start date
                if (startDate && !endDate) {
                    $('input[name="end_date"]').val(startDate);
                }
                
                // If end date is before start date, adjust start date
                if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
                    $('input[name="start_date"]').val(endDate);
                }
            });
        });

        function openDonationModal(donationId) {
            const modal = document.getElementById('donation_modal');
            const modalContent = document.getElementById('modal_content');
            
            // Show loading state
            modalContent.innerHTML = '<div class="flex justify-center"><span class="loading loading-spinner loading-lg text-primarycol"></span></div>';
            
            // Open the modal
            modal.showModal();
            
            // Fetch donation details
            fetch(`get_donation_details.php?id=${donationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalContent.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                        return;
                    }
                    
                    // Format donation status with appropriate color
                    let statusBadge = '';
                    if (data.is_received || data.status === 'completed') {
                        statusBadge = '<span class="badge badge-success">Completed</span>';
                    } else if (data.status === 'approved') {
                        statusBadge = '<span class="badge badge-warning">Approved</span>';
                    } else if (data.status === 'rejected') {
                        statusBadge = '<span class="badge badge-error">Rejected</span>';
                    } else {
                        statusBadge = '<span class="badge badge-info">Pending</span>';
                    }
                    
                    // Build the content HTML
                    const html = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <div class="bg-sec bg-opacity-30 p-3 rounded-lg">
                                    <h4 class="font-semibold text-lg">Donation Information</h4>
                                    <div class="divider my-1"></div>
                                    <p><span class="font-medium">Request ID:</span> #${data.id}</p>
                                    <p><span class="font-medium">Status:</span> ${statusBadge}</p>
                                    <p><span class="font-medium">Created:</span> ${new Date(data.created_at).toLocaleString()}</p>
                                    <p><span class="font-medium">Updated:</span> ${new Date(data.updated_at).toLocaleString()}</p>
                                    <p><span class="font-medium">Received:</span> ${data.received_at ? new Date(data.received_at).toLocaleString() : 'Not received yet'}</p>
                                </div>
                                
                                <div class="bg-sec bg-opacity-30 p-3 rounded-lg">
                                    <h4 class="font-semibold text-lg">NGO Information</h4>
                                    <div class="divider my-1"></div>
                                    <p><span class="font-medium">Organization:</span> ${data.organization_name || 'N/A'}</p>
                                    <p><span class="font-medium">Contact Person:</span> ${data.ngo_name}</p>
                                    <p><span class="font-medium">Email:</span> ${data.ngo_email}</p>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <div class="bg-sec bg-opacity-30 p-3 rounded-lg">
                                    <h4 class="font-semibold text-lg">Product Information</h4>
                                    <div class="divider my-1"></div>
                                    <p><span class="font-medium">Product:</span> ${data.product_name}</p>
                                    <p><span class="font-medium">Category:</span> ${data.category}</p>
                                    <p><span class="font-medium">Quantity Requested:</span> ${data.quantity_requested}</p>
                                    <p><span class="font-medium">Available Quantity:</span> ${data.available_quantity}</p>
                                    <p><span class="font-medium">Expiry Date:</span> ${new Date(data.donation_expiry_date).toLocaleDateString()}</p>
                                </div>
                                
                                <div class="bg-sec bg-opacity-30 p-3 rounded-lg">
                                    <h4 class="font-semibold text-lg">Pickup Details</h4>
                                    <div class="divider my-1"></div>
                                    <p><span class="font-medium">Branch:</span> ${data.branch_name}</p>
                                    <p><span class="font-medium">Address:</span> ${data.branch_address}</p>
                                    <p><span class="font-medium">Pickup Date:</span> ${new Date(data.pickup_date).toLocaleDateString()}</p>
                                    <p><span class="font-medium">Pickup Time:</span> ${data.pickup_time}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-sec bg-opacity-30 p-3 rounded-lg mt-4">
                            <h4 class="font-semibold text-lg">Notes</h4>
                            <div class="divider my-1"></div>
                            <p><span class="font-medium">NGO Notes:</span> ${data.ngo_notes || 'No notes provided'}</p>
                            <p><span class="font-medium">Staff Notes:</span> ${data.staff_notes || 'No staff notes'}</p>
                        </div>
                    `;
                    
                    modalContent.innerHTML = html;
                })
                .catch(error => {
                    modalContent.innerHTML = `<div class="alert alert-error">Error loading donation details: ${error.message}</div>`;
                });
        }
    </script>

    <!-- Donation Details Modal -->
    <dialog id="donation_modal" class="modal">
        <div class="modal-box max-w-3xl">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-bold text-xl text-primarycol mb-4">Donation Details</h3>
            
            <div id="modal_content" class="space-y-4">
                <!-- Content will be loaded dynamically -->
                <div class="flex justify-center">
                    <span class="loading loading-spinner loading-lg text-primarycol"></span>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</body>
</html>