<?php
// Set timezone to Philippine time at the very top
date_default_timezone_set('Asia/Manila');

require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../includes/mail/EmailService.php';
use App\Mail\EmailService;

// Check for admin access only
checkAuth(['admin', 'staff']);

$pdo = getPDO();

// Process approval/rejection
if (isset($_POST['process_request'])) {
    $groupId = $_POST['group_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        // Get all items in this request group
        $groupItemsQuery = $pdo->prepare("
            SELECT id, ngo_id, product_id, waste_id, quantity_requested 
            FROM ngo_donation_requests
            WHERE request_group_id = ?
        ");
        $groupItemsQuery->execute([$groupId]);
        $groupItems = $groupItemsQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // Get NGO details for notification
        $ngoQuery = $pdo->prepare("
            SELECT u.id, u.email, COALESCE(np.organization_name, u.organization_name) as organization_name 
            FROM users u 
            LEFT JOIN ngo_profiles np ON u.id = np.user_id 
            WHERE u.id = ?
            LIMIT 1
        ");
        $ngoQuery->execute([$groupItems[0]['ngo_id']]);
        $ngoDetails = $ngoQuery->fetch(PDO::FETCH_ASSOC);
        
        // Check if NGO details were found
        if (!$ngoDetails) {
            // Create a fallback with minimal data
            $ngoDetails = [
                'id' => $groupItems[0]['ngo_id'],
                'email' => 'no-email-found@example.com', // Fallback email
                'organization_name' => 'NGO Organization'
            ];
            // Log the issue
            error_log("Warning: No NGO details found for user ID: " . $groupItems[0]['ngo_id']);
        }
        
        // Update status for all items in the group
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        $updateStatusStmt = $pdo->prepare("
            UPDATE ngo_donation_requests
            SET status = ?, admin_notes = ?
            WHERE request_group_id = ?
        ");
        $updateStatusStmt->execute([$newStatus, $adminNotes, $groupId]);
        
        // If approved, update product_waste status
        if ($action === 'approve') {
            foreach ($groupItems as $item) {
                $updateWaste = $pdo->prepare("
                    UPDATE product_waste 
                    SET donation_status = 'approved' 
                    WHERE id = ? AND disposal_method = 'donation'
                ");
                $updateWaste->execute([$item['waste_id']]);
            }
            
            // Create notification for NGO
            $notifyNGOStmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, 
                    message, 
                    notification_type,
                    link, 
                    is_read,
                    created_at
                ) VALUES (
                    ?, 
                    'Your donation request has been approved! Please check your donation history for details.', 
                    'donation_approved',
                    '../ngo/donation_history.php', 
                    0,
                    NOW()
                )
            ");
            $notifyNGOStmt->execute([$ngoDetails['id']]);

            // Clear any pending admin notifications about this request
            $clearAdminNotifications = $pdo->prepare("
                DELETE FROM notifications 
                WHERE target_role = 'admin' 
                AND notification_type = 'donation_requested'
                AND message LIKE CONCAT('%', ?, '%')
            ");
            $clearAdminNotifications->execute([$ngoDetails['organization_name']]);
            
            // Prepare items array for batch email
            $approvedItems = [];
            foreach ($requestItems[$groupId] ?? $groupItems as $item) {
                // Get product and branch details with error checking
                $productQuery = $pdo->prepare("
                    SELECT pi.name as product_name, b.name as branch_name 
                    FROM product_info pi
                    LEFT JOIN branches b ON pi.branch_id = b.id
                    WHERE pi.id = ?
                ");
                $productQuery->execute([$item['product_id']]);
                $productData = $productQuery->fetch(PDO::FETCH_ASSOC);
                
                // If no data found, use fallback values
                if (!$productData) {
                    error_log("Warning: No product data found for product ID: " . $item['product_id']);
                    $productData = [
                        'product_name' => 'Unknown Product (ID: ' . $item['product_id'] . ')',
                        'branch_name' => 'Unknown Branch'
                    ];
                }
                
                $approvedItems[] = [
                    'product_name' => $productData['product_name'],
                    'quantity' => $item['quantity_requested'],
                    'branch_name' => $productData['branch_name'],
                    'pickup_date' => date('Y-m-d', strtotime($group['pickup_date'] ?? '+1 day')),
                    'pickup_time' => date('H:i:s', strtotime($group['pickup_time'] ?? '09:00:00'))
                ];
            }
            
            // Send batch email with all approved items
            $emailService = new EmailService();
            $emailService->sendBatchDonationStatusEmail([
                'status' => 'batch_approved',
                'name' => $ngoDetails['organization_name'],
                'email' => $ngoDetails['email'],
                'notes' => $adminNotes,
                'approved_items' => $approvedItems
            ]);
        } else {
            // If rejected, update product_waste back to pending status
            foreach ($groupItems as $item) {
                $updateWaste = $pdo->prepare("
                    UPDATE product_waste 
                    SET donation_status = 'pending' 
                    WHERE id = ? AND disposal_method = 'donation'
                ");
                $updateWaste->execute([$item['waste_id']]);
            }
            
            // Create notification for NGO
            $notifyNGOStmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, 
                    message, 
                    notification_type,
                    link, 
                    is_read,
                    created_at
                ) VALUES (
                    ?, 
                    'Your donation request has been rejected. Please check your requests page for details.', 
                    'donation_rejected',
                    '../ngo/donation_history.php', 
                    0,
                    NOW()
                )
            ");
            $notifyNGOStmt->execute([$ngoDetails['id']]);
            
            // Prepare items array for batch email
            $rejectedItems = [];
            foreach ($requestItems[$groupId] ?? $groupItems as $item) {
                // Get product name
                $productQuery = $pdo->prepare("SELECT name FROM product_info WHERE id = ?");
                $productQuery->execute([$item['product_id']]);
                $productName = $productQuery->fetchColumn();
                
                $rejectedItems[] = [
                    'product_name' => $productName,
                    'quantity' => $item['quantity_requested']
                ];
            }
            
            // Send batch email with all rejected items
            $emailService = new EmailService();
            $emailService->sendBatchDonationStatusEmail([
                'status' => 'batch_rejected',
                'name' => $ngoDetails['organization_name'],
                'email' => $ngoDetails['email'],
                'notes' => $adminNotes,
                'rejected_items' => $rejectedItems
            ]);
        }
        
        $pdo->commit();
        $successMessage = "Request has been " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errorMessage = "Error processing request: " . $e->getMessage();
    }
}

// Get request groups data
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// Filter by status
$statusFilter = 'pending';
if ($activeTab === 'approved') {
    $statusFilter = 'approved';
} elseif ($activeTab === 'rejected') {
    $statusFilter = 'rejected';
}

// Get specific group if ID is provided
$groupFilter = '';
$groupParams = [];
if (isset($_GET['group']) && !empty($_GET['group'])) {
    $groupFilter = " AND r.request_group_id = ? ";
    $groupParams[] = $_GET['group'];
}

// Query to get request groups
$groupsQuery = $pdo->prepare("
    SELECT 
        request_group_id,
        request_date,
        COUNT(*) as item_count,
        SUM(quantity_requested) as total_quantity,
        ngo_id,
        MAX(pickup_date) as pickup_date,
        MAX(pickup_time) as pickup_time
    FROM ngo_donation_requests
    WHERE status = ?
    GROUP BY request_group_id, ngo_id
    ORDER BY request_date DESC
");
$groupsQuery->execute([$statusFilter]);
$requestGroups = $groupsQuery->fetchAll(PDO::FETCH_ASSOC);

// Then fetch NGO details separately if needed
foreach ($requestGroups as &$group) {
    $ngoQuery = $pdo->prepare("
        SELECT u.email, u.phone, 
            COALESCE(np.organization_name, u.organization_name) as organization_name 
        FROM users u 
        LEFT JOIN ngo_profiles np ON u.id = np.user_id 
        WHERE u.id = ?
    ");
    $ngoQuery->execute([$group['ngo_id']]);
    $ngoDetails = $ngoQuery->fetch(PDO::FETCH_ASSOC);
    
    $group['ngo_name'] = $ngoDetails['organization_name'] ?? 'Unknown NGO';
    $group['ngo_email'] = $ngoDetails['email'] ?? '';
    $group['ngo_phone'] = $ngoDetails['phone'] ?? '';
    
    // Set default values for pickup date/time if they're NULL
    if (empty($group['pickup_date'])) {
        $group['pickup_date'] = date('Y-m-d', strtotime('+1 day'));
    }
    if (empty($group['pickup_time'])) {
        $group['pickup_time'] = '09:00:00';
    }
}

// Get items for each group
$requestItems = [];
if (!empty($requestGroups)) {
    $groupIds = array_column($requestGroups, 'request_group_id');
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    
    $itemsQuery = $pdo->prepare("
        SELECT 
            ndr.id,
            ndr.request_group_id,
            ndr.product_id,
            ndr.branch_id,
            ndr.waste_id,
            ndr.status,
            ndr.quantity_requested,
            ndr.ngo_notes,
            ndr.admin_notes,
            pi.name as product_name,
            pi.category as product_category,
            pi.image as product_image,
            b.name as branch_name,
            b.address as branch_address,
            pw.waste_date as expiry_date
        FROM ngo_donation_requests ndr
        LEFT JOIN product_info pi ON ndr.product_id = pi.id
        LEFT JOIN product_waste pw ON ndr.waste_id = pw.id
        LEFT JOIN branches b ON ndr.branch_id = b.id
        WHERE ndr.request_group_id IN ($placeholders)
        ORDER BY ndr.request_date DESC
    ");
    $itemsQuery->execute($groupIds);
    $allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Group items by request_group_id
    foreach ($allItems as $item) {
        $requestItems[$item['request_group_id']][] = $item;
    }
}

// Get counts for each status
$pendingCount = $pdo->query("SELECT COUNT(DISTINCT request_group_id) FROM ngo_donation_requests WHERE status = 'pending'")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(DISTINCT request_group_id) FROM ngo_donation_requests WHERE status = 'approved'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(DISTINCT request_group_id) FROM ngo_donation_requests WHERE status = 'rejected'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGO Donation Requests | WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primarycol: '#47663B',
                        primarylight: '#5d8a4e',
                        primarydark: '#385029',
                        sec: '#E8ECD7',
                        third: '#EED3B1',
                        fourth: '#1F4529',
                        accent: '#ffa62b',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        /* Custom animations */
        .card-hover {
            transition: all 0.2s ease-in-out;
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .btn-hover {
            transition: all 0.2s ease;
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #47663B;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #385029;
        }
        
        /* Status pills */
        .status-pill {
            @apply px-3 py-1 text-xs font-medium rounded-full;
        }
        
        .status-pending {
            @apply bg-amber-100 text-amber-800;
        }
        
        .status-approved {
            @apply bg-green-100 text-green-800;
        }
        
        .status-rejected {
            @apply bg-red-100 text-red-800;
        }
    </style>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include '../layout/nav.php' ?>
    
    <div class="flex flex-col w-full overflow-y-auto">
        <!-- Page Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-primarycol flex items-center gap-2">
                            <i class="fas fa-handshake"></i>
                            NGO Donation Requests
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">
                            Manage and process donation requests from partner NGOs
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <div class="stats shadow-md">
                            <div class="stat place-items-center p-2 sm:p-4">
                                <div class="stat-title text-xs sm:text-sm">Pending</div>
                                <div class="stat-value text-lg sm:text-xl text-amber-600"><?= $pendingCount ?></div>
                            </div>
                            <div class="stat place-items-center p-2 sm:p-4">
                                <div class="stat-title text-xs sm:text-sm">Approved</div>
                                <div class="stat-value text-lg sm:text-xl text-green-600"><?= $approvedCount ?></div>
                            </div>
                            <div class="stat place-items-center p-2 sm:p-4">
                                <div class="stat-title text-xs sm:text-sm">Rejected</div>
                                <div class="stat-value text-lg sm:text-xl text-red-600"><?= $rejectedCount ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($successMessage)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="alert alert-success shadow-lg">
                <div>
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($successMessage) ?></span>
                </div>
                <button class="btn btn-sm btn-circle btn-ghost" onclick="this.parentElement.remove()">×</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="alert alert-error shadow-lg">
                <div>
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
                <button class="btn btn-sm btn-circle btn-ghost" onclick="this.parentElement.remove()">×</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="bg-white border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div role="tablist" class="tabs tabs-lifted">
                    <a href="?tab=pending" role="tab" class="tab <?= $activeTab === 'pending' ? 'tab-active' : '' ?> gap-2">
                        <i class="fas fa-clock"></i> Pending
                        <?php if($pendingCount > 0): ?>
                            <span class="badge badge-sm badge-warning"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=approved" role="tab" class="tab <?= $activeTab === 'approved' ? 'tab-active' : '' ?> gap-2">
                        <i class="fas fa-check-circle"></i> Approved
                        <?php if($approvedCount > 0): ?>
                            <span class="badge badge-sm badge-success"><?= $approvedCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=rejected" role="tab" class="tab <?= $activeTab === 'rejected' ? 'tab-active' : '' ?> gap-2">
                        <i class="fas fa-times-circle"></i> Rejected
                        <?php if($rejectedCount > 0): ?>
                            <span class="badge badge-sm badge-error"><?= $rejectedCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Request Groups List -->
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <?php if (empty($requestGroups)): ?>
                <div class="text-center py-16 bg-white rounded-lg shadow-sm">
                    <div class="flex justify-center">
                        <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-4">
                            <?php if ($activeTab === 'pending'): ?>
                                <i class="fas fa-inbox fa-3x"></i>
                            <?php elseif ($activeTab === 'approved'): ?>
                                <i class="fas fa-check-circle fa-3x"></i>
                            <?php elseif ($activeTab === 'rejected'): ?>
                                <i class="fas fa-times-circle fa-3x"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 class="text-xl font-medium text-gray-900 mt-2">No <?= $activeTab ?> requests found</h3>
                    <p class="mt-1 text-gray-500 max-w-md mx-auto">
                        <?php if ($activeTab === 'pending'): ?>
                            There are currently no pending donation requests that require your attention.
                        <?php elseif ($activeTab === 'approved'): ?>
                            No donation requests have been approved yet. You can approve pending requests to see them here.
                        <?php elseif ($activeTab === 'rejected'): ?>
                            No donation requests have been rejected yet. This area will show any requests you've declined.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($requestGroups as $group): ?>
                        <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-100 card-hover">
                            <div class="p-5 border-b border-gray-200 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar">
                                            <div class="w-12 h-12 rounded-full bg-sec flex items-center justify-center text-primarycol">
                                                <i class="fas fa-building fa-lg"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                <?= htmlspecialchars($group['ngo_name']) ?>
                                            </h3>
                                            <div class="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1 mt-0.5">
                                                <span class="flex items-center gap-1">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <?= date('M j, Y g:i A', strtotime($group['request_date'])) ?> PHT
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <i class="fas fa-box"></i>
                                                    <?= $group['item_count'] ?> <?= $group['item_count'] > 1 ? 'items' : 'item' ?>
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <i class="fas fa-weight"></i>
                                                    <?= $group['total_quantity'] ?> total qty
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 sm:justify-end">
                                    <?php if ($statusFilter === 'pending'): ?>
                                        <button onclick="showApprovalForm('<?= $group['request_group_id'] ?>')" class="btn btn-success btn-sm gap-1 btn-hover">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button onclick="showRejectionForm('<?= $group['request_group_id'] ?>')" class="btn btn-error btn-sm gap-1 btn-hover">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php else: ?>
                                        <span class="status-pill <?= $statusFilter === 'approved' ? 'status-approved' : 'status-rejected' ?>">
                                            <?= ucfirst($statusFilter) ?>
                                        </span>
                                    <?php endif; ?>
                                    <button onclick="toggleDetails('<?= $group['request_group_id'] ?>')" class="btn btn-outline btn-primary btn-sm gap-1 btn-hover">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                </div>
                            </div>
                            
                            <!-- NGO Contact Info -->
                            <div class="bg-gray-50 p-4 border-b border-gray-200">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center justify-center bg-sec rounded-full w-10 h-10 text-primarycol shrink-0">
                                            <i class="fas fa-id-card"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-500 uppercase">Contact</h4>
                                            <p class="text-sm">
                                                <?php if(!empty($group['ngo_email']) || !empty($group['ngo_phone'])): ?>
                                                    <?= !empty($group['ngo_email']) ? htmlspecialchars($group['ngo_email']) : '' ?>
                                                    <?= !empty($group['ngo_email']) && !empty($group['ngo_phone']) ? ' • ' : '' ?>
                                                    <?= !empty($group['ngo_phone']) ? htmlspecialchars($group['ngo_phone']) : '' ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400">No contact information</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center justify-center bg-sec rounded-full w-10 h-10 text-primarycol shrink-0">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-500 uppercase">Pickup</h4>
                                            <p class="text-sm">
                                                <?= date('M j, Y', strtotime($group['pickup_date'] ?? 'now')) ?> at 
                                                <?= date('g:i A', strtotime($group['pickup_time'] ?? '09:00:00')) ?> PHT
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Request Details (Hidden by default) -->
                            <div id="details-<?= $group['request_group_id'] ?>" class="hidden animate-fade-in">
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra w-full">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="bg-gray-50">Product</th>
                                                <th class="bg-gray-50">Category</th>
                                                <th class="bg-gray-50">Quantity</th>
                                                <th class="bg-gray-50">Branch</th>
                                                <th class="bg-gray-50">Expiry</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($requestItems[$group['request_group_id']])): ?>
                                                <?php foreach ($requestItems[$group['request_group_id']] as $item): ?>
                                                    <tr>
                                                        <td class="flex items-center gap-3">
                                                            <div class="avatar">
                                                                <div class="w-12 h-12 rounded bg-gray-100 flex items-center justify-center overflow-hidden">
                                                                    <?php if (!empty($item['product_image'])): ?>
                                                                        <img src="<?= htmlspecialchars('../../uploads/products/' . $item['product_image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="object-cover w-full h-full">
                                                                    <?php else: ?>
                                                                        <i class="fas fa-cookie text-gray-400 text-xl"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <div class="font-medium"><?= htmlspecialchars($item['product_name']) ?></div>
                                                                <div class="text-xs text-gray-500">ID: <?= htmlspecialchars($item['product_id']) ?></div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-ghost"><?= htmlspecialchars($item['product_category']) ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="font-medium"><?= htmlspecialchars($item['quantity_requested']) ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="font-medium"><?= htmlspecialchars($item['branch_name']) ?></div>
                                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($item['branch_address'] ?? '') ?></div>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $expiryDate = strtotime($item['expiry_date']); 
                                                            $today = strtotime('today');
                                                            $daysRemaining = round(($expiryDate - $today) / (60 * 60 * 24));
                                                            $badgeClass = 'badge-success';
                                                            
                                                            if ($daysRemaining < 3) {
                                                                $badgeClass = 'badge-error';
                                                            } elseif ($daysRemaining < 7) {
                                                                $badgeClass = 'badge-warning';
                                                            }
                                                            ?>
                                                            <div><?= date('M j, Y', $expiryDate) ?> PHT</div>
                                                            <span class="badge <?= $badgeClass ?> badge-sm"><?= $daysRemaining > 0 ? $daysRemaining.' days left' : 'Expired' ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-gray-500">No items found in this request</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if (!empty($item['ngo_notes']) || !empty($item['admin_notes'])): ?>
                                <div class="p-5 bg-gray-50 border-t border-gray-200">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <?php if (!empty($item['ngo_notes'])): ?>
                                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                            <h4 class="font-medium text-sm flex items-center gap-2 mb-2 text-primarycol">
                                                <i class="fas fa-comment-alt"></i> NGO Notes
                                            </h4>
                                            <div class="text-gray-700 text-sm border-l-4 border-sec pl-3"><?= nl2br(htmlspecialchars($item['ngo_notes'])) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($item['admin_notes'])): ?>
                                        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                            <h4 class="font-medium text-sm flex items-center gap-2 mb-2 text-primarycol">
                                                <i class="fas fa-clipboard-check"></i> Admin Notes
                                            </h4>
                                            <div class="text-gray-700 text-sm border-l-4 border-third pl-3"><?= nl2br(htmlspecialchars($item['admin_notes'])) ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Approval Modal -->
    <dialog id="approvalModal" class="modal">
        <div class="modal-box relative">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <div class="bg-green-50 text-green-800 p-4 rounded-lg mb-4 flex gap-3 items-start">
                <div class="mt-0.5"><i class="fas fa-check-circle text-green-600 text-lg"></i></div>
                <div>
                    <h3 class="font-bold text-lg text-green-800">Approve Donation Request</h3>
                    <p class="text-sm">
                        Once approved, the NGO will be notified and can proceed with pickup arrangements. 
                        This action will mark the items as reserved for this NGO.
                    </p>
                </div>
            </div>
            <form method="POST" action="" class="mt-4">
                <input type="hidden" name="group_id" id="approve_group_id">
                <input type="hidden" name="action" value="approve">
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Admin Notes (optional)</span>
                        <span class="label-text-alt">Will be visible to the NGO</span>
                    </label>
                    <textarea name="admin_notes" class="textarea textarea-bordered" placeholder="Add any notes about this approval, such as pickup instructions or special considerations."></textarea>
                </div>
                
                <div class="modal-action mt-6">
                    <button type="submit" name="process_request" class="btn btn-success gap-2">
                        <i class="fas fa-check"></i> Approve Request
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('approvalModal')">Cancel</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
    
    <!-- Rejection Modal -->
    <dialog id="rejectionModal" class="modal">
        <div class="modal-box relative">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <div class="bg-red-50 text-red-800 p-4 rounded-lg mb-4 flex gap-3 items-start">
                <div class="mt-0.5"><i class="fas fa-exclamation-circle text-red-600 text-lg"></i></div>
                <div>
                    <h3 class="font-bold text-lg text-red-800">Reject Donation Request</h3>
                    <p class="text-sm">
                        The NGO will be notified about this rejection. Items will return to the available pool
                        for other NGOs to request.
                    </p>
                </div>
            </div>
            <form method="POST" action="" class="mt-4">
                <input type="hidden" name="group_id" id="reject_group_id">
                <input type="hidden" name="action" value="reject">
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Rejection Reason</span>
                        <span class="label-text-alt text-red-500">Required</span>
                    </label>
                    <textarea name="admin_notes" class="textarea textarea-bordered" placeholder="Please provide a reason for rejection that will be shared with the NGO" required></textarea>
                </div>
                
                <div class="modal-action mt-6">
                    <button type="submit" name="process_request" class="btn btn-error gap-2">
                        <i class="fas fa-times"></i> Reject Request
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="closeModal('rejectionModal')">Cancel</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
    
    <script>
        function toggleDetails(groupId) {
            const detailsElement = document.getElementById(`details-${groupId}`);
            if (detailsElement.classList.contains('hidden')) {
                // Close any open details first
                document.querySelectorAll('[id^="details-"]').forEach(el => {
                    if (!el.classList.contains('hidden')) {
                        el.classList.add('hidden');
                    }
                });
                // Open this one
                detailsElement.classList.remove('hidden');
                
                // Scroll into view smoothly if needed
                setTimeout(() => {
                    const rect = detailsElement.getBoundingClientRect();
                    if (rect.bottom > window.innerHeight) {
                        detailsElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 100);
            } else {
                detailsElement.classList.add('hidden');
            }
        }
        
        function showApprovalForm(groupId) {
            document.getElementById('approve_group_id').value = groupId;
            document.getElementById('approvalModal').showModal();
        }
        
        function showRejectionForm(groupId) {
            document.getElementById('reject_group_id').value = groupId;
            document.getElementById('rejectionModal').showModal();
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).close();
        }
        
        // Add animation class for modals
        document.addEventListener('DOMContentLoaded', function() {
            tailwind.config.theme.extend.keyframes = {
                fadeIn: {
                    '0%': { opacity: 0 },
                    '100%': { opacity: 1 }
                }
            };
            
            tailwind.config.theme.extend.animation = {
                'fade-in': 'fadeIn 0.3s ease-out'
            };
        });
    </script>
</body>
</html>