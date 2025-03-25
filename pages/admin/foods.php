<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access
checkAuth(['admin']);

$pdo = getPDO();

// Auto-handle expired products
try {
    $currentDate = date('Y-m-d');
    $expiredItemsStmt = $pdo->prepare("
        UPDATE product_waste pw
        JOIN products p ON pw.product_id = p.id
        SET pw.donation_status = 'cancelled', 
            pw.admin_notes = CONCAT(IFNULL(pw.admin_notes, ''), '\n[System] Automatically cancelled due to expiration on ', ?)
        WHERE pw.disposal_method = 'donation'
        AND pw.donation_status IN ('pending', 'in-progress')
        AND p.expiry_date < ?
        AND NOT EXISTS (
            SELECT 1 FROM ngo_donation_requests ndr 
            WHERE ndr.donation_request_id = pw.id AND ndr.status = 'approved'
        )
    ");
    $expiredItemsStmt->execute([$currentDate, $currentDate]);
    
    $changedRows = $expiredItemsStmt->rowCount();
    if ($changedRows > 0) {
        $successMessage = "System automatically updated $changedRows expired donation items.";
    }
} catch (PDOException $e) {
    $errorMessage = "Error handling expired products: " . $e->getMessage();
}

// Initialize variables
$successMessage = isset($successMessage) ? $successMessage : '';
$errorMessage = isset($errorMessage) ? $errorMessage : '';
$searchTerm = '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$branchFilter = isset($_GET['branch_id']) ? $_GET['branch_id'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Add expiry filter handling
$expiryFilter = isset($_GET['expiry_status']) ? $_GET['expiry_status'] : 'all';

// Process donation status updates
if (isset($_POST['update_status'])) {
    $donationId = $_POST['donation_id'];
    $newStatus = $_POST['new_status'];
    $notes = $_POST['admin_notes'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE product_waste 
            SET donation_status = ?, admin_notes = ?, updated_at = NOW() 
            WHERE id = ? AND disposal_method = 'donation'
        ");
        
        $stmt->execute([$newStatus, $notes, $donationId]);
        $successMessage = "Donation status updated successfully.";
    } catch (PDOException $e) {
        $errorMessage = "Error updating status: " . $e->getMessage();
    }
}

// Search handling
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
}

// Build the query
$query = "
    SELECT 
        pw.id as waste_id,
        p.name as product_name,
        p.category as product_category,
        p.image as product_image,
        p.expiry_date,
        pw.waste_date,
        pw.waste_quantity,
        pw.waste_value,
        pw.waste_reason,
        pw.notes,
        pw.donation_status,
        pw.admin_notes,
        CONCAT(s.fname, ' ', s.lname) as staff_name,
        b.name as branch_name,
        b.id as branch_id
    FROM product_waste pw
    JOIN products p ON pw.product_id = p.id
    JOIN users s ON pw.staff_id = s.id
    JOIN branches b ON pw.branch_id = b.id
    WHERE pw.disposal_method = 'donation'
";

// Add filters
$params = [];

if (!empty($searchTerm)) {
    $query .= " AND (p.name LIKE ? OR pw.notes LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($statusFilter !== 'all') {
    $query .= " AND pw.donation_status = ?";
    $params[] = $statusFilter;
}

if (!empty($branchFilter)) {
    $query .= " AND pw.branch_id = ?";
    $params[] = $branchFilter;
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(pw.waste_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(pw.waste_date) <= ?";
    $params[] = $dateTo;
}

if ($expiryFilter !== 'all') {
    $currentDate = date('Y-m-d');
    $soonDate = date('Y-m-d', strtotime('+3 days'));
    
    if ($expiryFilter === 'expired') {
        $query .= " AND p.expiry_date < ?";
        $params[] = $currentDate;
    } elseif ($expiryFilter === 'expiring_soon') {
        $query .= " AND p.expiry_date >= ? AND p.expiry_date <= ?";
        $params[] = $currentDate;
        $params[] = $soonDate;
    } elseif ($expiryFilter === 'valid') {
        $query .= " AND p.expiry_date > ?";
        $params[] = $currentDate;
    }
}

$query .= " ORDER BY pw.waste_date DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch branches for filter dropdown
    $branchStmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
    $branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    $donations = [];
    $branches = [];
}

// Calculate stats
$totalDonations = count($donations);
$totalPendingDonations = 0;
$totalCompletedDonations = 0;
$totalDonationValue = 0;

foreach ($donations as $donation) {
    $totalDonationValue += $donation['waste_value'];
    if ($donation['donation_status'] === 'pending') {
        $totalPendingDonations++;
    } elseif ($donation['donation_status'] === 'completed') {
        $totalCompletedDonations++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Donation Management - WasteWise</title>
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
            
            // Auto-hide notification after 5 seconds
            setTimeout(function() {
                $('.notification').fadeOut();
            }, 5000);
            
            // Show modal for status update
            $('.update-status-btn').on('click', function() {
                const donationId = $(this).data('id');
                const status = $(this).data('status');
                const notes = $(this).data('notes') || '';
                
                $('#donationId').val(donationId);
                $('#statusSelect').val(status);
                $('#adminNotes').val(notes);
                $('#statusModal').removeClass('hidden');
            });
            
            // Close modal
            $('.close-modal').on('click', function() {
                $('#statusModal').addClass('hidden');
            });
            
            // Reset filters
            $('#resetFilters').on('click', function() {
                window.location.href = 'foods.php';
            });
        });
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .status-in-progress {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-completed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .status-cancelled {
            background-color: #FEE2E2;
            color: #B91C1C;
        }
    </style>
</head>

<body class="flex min-h-screen bg-gray-50">
    <?php include('../layout/nav.php'); ?>

    <div class="p-5 w-full">
        <!-- Statistics Cards -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-primarycol">Food Donation Management</h1>
                <p class="text-gray-600">Track and manage donated food items from all branches</p>
            </div>
            
            <div class="stats shadow">
                <div class="stat place-items-center">
                    <div class="stat-title">Total</div>
                    <div class="stat-value text-blue-500"><?= $totalDonations ?></div>
                </div>
                <div class="stat place-items-center">
                    <div class="stat-title">Pending</div>
                    <div class="stat-value text-yellow-500"><?= $totalPendingDonations ?></div>
                </div>
                <div class="stat place-items-center">
                    <div class="stat-title">Completed</div>
                    <div class="stat-value text-green-500"><?= $totalCompletedDonations ?></div>
                </div>
                <div class="stat place-items-center">
                    <div class="stat-title">Value</div>
                    <div class="stat-value text-purple-500">₱<?= number_format($totalDonationValue, 2) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Notification Messages -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Filters and Search -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($searchTerm) ?>" 
                        placeholder="Search products, staff..."
                        class="w-full border border-gray-300 rounded-md p-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-md p-2">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in-progress" <?= $statusFilter === 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" class="w-full border border-gray-300 rounded-md p-2">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>" <?= $branchFilter == $branch['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <div class="flex space-x-2">
                        <input 
                            type="date" 
                            name="date_from" 
                            value="<?= htmlspecialchars($dateFrom) ?>" 
                            class="w-full border border-gray-300 rounded-md p-2">
                        <input 
                            type="date" 
                            name="date_to" 
                            value="<?= htmlspecialchars($dateTo) ?>" 
                            class="w-full border border-gray-300 rounded-md p-2">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Status</label>
                    <select name="expiry_status" class="w-full border border-gray-300 rounded-md p-2">
                        <option value="all" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] === 'all' ? 'selected' : '' ?>>All Items</option>
                        <option value="expired" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] === 'expired' ? 'selected' : '' ?>>Expired Items</option>
                        <option value="expiring_soon" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon (3 days)</option>
                        <option value="valid" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] === 'valid' ? 'selected' : '' ?>>Valid Items</option>
                    </select>
                </div>
                
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-primarycol text-white px-4 py-2 rounded-md">
                        Apply Filters
                    </button>
                    <button type="button" id="resetFilters" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md">
                        Reset
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Donation List -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Qty</th>
                            <th>Value</th>
                            <th>Date</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($donations)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">
                                    No donation records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($donations as $donation): ?>
                                <tr class="hover">
                                    <td>
                                        <div>
                                            <div class="font-bold"><?= htmlspecialchars($donation['product_name']) ?></div>
                                            <div class="text-sm opacity-50"><?= htmlspecialchars($donation['product_category']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($donation['branch_name']) ?>
                                        <br/>
                                        <span class="badge badge-ghost badge-sm">
                                            <?= htmlspecialchars($donation['staff_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($donation['waste_quantity']) ?></td>
                                    <td>₱<?= number_format($donation['waste_value'], 2) ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($donation['waste_date'])) ?>
                                        <br/>
                                        <span class="text-xs text-gray-500">
                                            <?= date('h:i A', strtotime($donation['waste_date'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($donation['expiry_date'])): ?>
                                            <?php
                                            $today = time();
                                            $expiryTimestamp = strtotime($donation['expiry_date']);
                                            $daysUntilExpiry = round(($expiryTimestamp - $today) / 86400);
                                            
                                            if ($expiryTimestamp < $today): ?>
                                                <span class="badge badge-error">Expired</span>
                                            <?php elseif ($daysUntilExpiry <= 3): ?>
                                                <span class="badge badge-warning"><?= $daysUntilExpiry ?> days</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Valid</span>
                                            <?php endif; ?>
                                            <div class="text-xs mt-1">
                                                <?= date('M d, Y', $expiryTimestamp) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-ghost">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        switch ($donation['donation_status'] ?? 'pending') {
                                            case 'pending':
                                                echo '<span class="badge badge-warning">Pending</span>';
                                                break;
                                            case 'in-progress':
                                                echo '<span class="badge badge-info">In Progress</span>';
                                                break;
                                            case 'completed':
                                                echo '<span class="badge badge-success">Completed</span>';
                                                break;
                                            case 'cancelled':
                                                echo '<span class="badge badge-error">Cancelled</span>';
                                                break;
                                            default:
                                                echo '<span class="badge badge-warning">Pending</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button 
                                                class="update-status-btn btn btn-sm bg-primarycol text-white"
                                                data-id="<?= $donation['waste_id'] ?>"
                                                data-status="<?= htmlspecialchars($donation['donation_status'] ?? 'pending') ?>"
                                                data-notes="<?= htmlspecialchars($donation['admin_notes'] ?? '') ?>"
                                            >
                                                Update
                                            </button>
                                            <button 
                                                class="view-details-btn btn btn-sm btn-ghost"
                                                data-id="<?= $donation['waste_id'] ?>"
                                            >
                                                Details
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <dialog id="statusModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4">Update Donation Status</h3>
            <form method="POST">
                <input type="hidden" id="donationId" name="donation_id">
                
                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">Status</span>
                    </label>
                    <select id="statusSelect" name="new_status" class="select select-bordered w-full">
                        <option value="pending">Pending</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">Admin Notes</span>
                    </label>
                    <textarea 
                        id="adminNotes"
                        name="admin_notes" 
                        rows="3"
                        class="textarea textarea-bordered h-24"
                        placeholder="Add notes about this donation (optional)"
                    ></textarea>
                </div>
                
                <div class="modal-action">
                    <button type="button" class="btn" onclick="document.getElementById('statusModal').close()">
                        Cancel
                    </button>
                    <button type="submit" name="update_status" class="btn btn-primary bg-primarycol">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </dialog>
    
    <!-- Details Modal -->
    <?php foreach ($donations as $donation): ?>
        <dialog id="details_modal_<?= $donation['waste_id'] ?>" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Donation Details #<?= $donation['waste_id'] ?></h3>
                
                <div class="bg-sec rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-primarycol mb-2">Product Information</h4>
                    <p><span class="font-medium">Name:</span> <?= htmlspecialchars($donation['product_name']) ?></p>
                    <p><span class="font-medium">Category:</span> <?= htmlspecialchars($donation['product_category']) ?></p>
                    <p><span class="font-medium">Quantity:</span> <?= $donation['waste_quantity'] ?></p>
                    <p><span class="font-medium">Value:</span> ₱<?= number_format($donation['waste_value'], 2) ?></p>
                </div>
                
                <div class="bg-sec rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-primarycol mb-2">Branch Information</h4>
                    <p><span class="font-medium">Name:</span> <?= htmlspecialchars($donation['branch_name']) ?></p>
                    <p><span class="font-medium">Staff:</span> <?= htmlspecialchars($donation['staff_name']) ?></p>
                </div>
                
                <div class="bg-sec rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-primarycol mb-2">Donation Information</h4>
                    <p><span class="font-medium">Date:</span> <?= date('M d, Y h:i A', strtotime($donation['waste_date'])) ?></p>
                    <p><span class="font-medium">Reason:</span> <?= htmlspecialchars($donation['waste_reason']) ?></p>
                    <p><span class="font-medium">Status:</span> <?= ucfirst(htmlspecialchars($donation['donation_status'] ?? 'pending')) ?></p>
                    
                    <?php if (!empty($donation['expiry_date'])): ?>
                        <p>
                            <span class="font-medium">Expiry Date:</span> 
                            <?= date('M d, Y', strtotime($donation['expiry_date'])) ?>
                            
                            <?php
                            $today = time();
                            $expiryTimestamp = strtotime($donation['expiry_date']);
                            $daysUntilExpiry = round(($expiryTimestamp - $today) / 86400);
                            
                            if ($expiryTimestamp < $today): ?>
                                <span class="badge badge-error ml-2">Expired</span>
                            <?php elseif ($daysUntilExpiry <= 3): ?>
                                <span class="badge badge-warning ml-2">Expires in <?= $daysUntilExpiry ?> days</span>
                            <?php else: ?>
                                <span class="badge badge-success ml-2">Valid</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($donation['notes'])): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-blue-800 mb-2">Staff Notes</h4>
                        <p><?= nl2br(htmlspecialchars($donation['notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($donation['admin_notes'])): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-yellow-800 mb-2">Admin Notes</h4>
                        <p><?= nl2br(htmlspecialchars($donation['admin_notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="modal-action">
                    <button class="btn" onclick="document.getElementById('details_modal_<?= $donation['waste_id'] ?>').close()">
                        Close
                    </button>
                    <button 
                        class="update-status-btn btn btn-primary bg-primarycol"
                        data-id="<?= $donation['waste_id'] ?>"
                        data-status="<?= htmlspecialchars($donation['donation_status'] ?? 'pending') ?>"
                        data-notes="<?= htmlspecialchars($donation['admin_notes'] ?? '') ?>"
                    >
                        Update Status
                    </button>
                </div>
            </div>
        </dialog>
    <?php endforeach; ?>

<script>
    $(document).ready(function() {
        // Sidebar toggling
        $('#toggleSidebar').on('click', function() {
            $('#sidebar').toggleClass('-translate-x-full');
        });

        $('#closeSidebar').on('click', function() {
            $('#sidebar').addClass('-translate-x-full');
        });
        
        // Auto-hide notification after 5 seconds
        setTimeout(function() {
            $('.notification').fadeOut();
        }, 5000);
        
        // Show modal for status update
        $('.update-status-btn').on('click', function() {
            const donationId = $(this).data('id');
            const status = $(this).data('status');
            const notes = $(this).data('notes') || '';
            
            $('#donationId').val(donationId);
            $('#statusSelect').val(status);
            $('#adminNotes').val(notes);
            document.getElementById('statusModal').showModal();
        });
        
        // View details button
        $('.view-details-btn').on('click', function() {
            const id = $(this).data('id');
            document.getElementById('details_modal_' + id).showModal();
        });
        
        // Reset filters
        $('#resetFilters').on('click', function() {
            window.location.href = 'foods.php';
        });
    });
</script>
</body>
</html>