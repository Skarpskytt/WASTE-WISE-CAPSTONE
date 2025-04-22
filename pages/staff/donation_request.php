<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../includes/mail/EmailService.php';
use App\Mail\EmailService;

// Check for staff access
checkAuth(['staff', 'company']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Get all NGO donation requests APPROVED by admin but not yet prepared
// Only show products that were donated by this specific branch
$stmt = $pdo->prepare("
    SELECT 
        ndr.id,
        ndr.product_id,
        ndr.branch_id,
        ndr.waste_id,
        ndr.donation_product_id, /* Add this field */
        ndr.quantity_requested,
        ndr.request_date,
        ndr.pickup_date,
        ndr.pickup_time,
        ndr.status,
        ndr.ngo_notes,
        ndr.admin_notes,
        pi.name as product_name,
        pi.category,
        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
        u.email as ngo_email,
        u.phone as ngo_phone,
        ndr.ngo_id,
        dp.expiry_date, /* Use donation_products table for expiry */
        dp.quantity_available
    FROM 
        ngo_donation_requests ndr
    JOIN 
        product_info pi ON ndr.product_id = pi.id
    JOIN 
        product_waste pw ON ndr.waste_id = pw.id
    LEFT JOIN
        donation_products dp ON ndr.donation_product_id = dp.id /* Join with donation_products */
    JOIN 
        users u ON ndr.ngo_id = u.id
    LEFT JOIN
        ngo_profiles np ON u.id = np.user_id
    WHERE 
        ndr.branch_id = ? AND 
        pw.branch_id = ? AND  /* Ensure waste was from this branch */
        pw.staff_id IN (SELECT id FROM users WHERE branch_id = ?) AND  /* Staff from this branch */
        ndr.status = 'approved' AND
        pw.disposal_method = 'donation' AND
        NOT EXISTS (
            SELECT 1 FROM donation_prepared dp 
            WHERE dp.ngo_request_id = ndr.id
        )
    ORDER BY 
        ndr.pickup_date ASC, ndr.pickup_time ASC
");
$stmt->execute([$branchId, $branchId, $branchId]);
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group requests by NGO
$requestsByNgo = [];
foreach ($pendingRequests as $request) {
    $ngoId = $request['ngo_id']; // Ensure you select ngo_id in your query
    if (!isset($requestsByNgo[$ngoId])) {
        $requestsByNgo[$ngoId] = [
            'ngo_name' => $request['ngo_name'],
            'pickup_date' => $request['pickup_date'],
            'items' => []
        ];
    }
    $requestsByNgo[$ngoId]['items'][] = $request;
}

// Sort by pickup date
uasort($requestsByNgo, function($a, $b) {
    return strtotime($a['pickup_date']) - strtotime($b['pickup_date']);
});

// Handle preparing items for NGO pickup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $staffNotes = isset($_POST['staff_notes']) ? trim($_POST['staff_notes']) : '';
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'prepare') {
            // Get the donation request details with all needed information
            $requestStmt = $pdo->prepare("
                SELECT 
                    ndr.product_id, 
                    ndr.quantity_requested, 
                    ndr.ngo_id,
                    ndr.branch_id,
                    ndr.pickup_date,
                    ndr.pickup_time,
                    pi.name as product_name,
                    u.email as ngo_email,
                    COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
                    b.name as branch_name,
                    b.address as branch_address
                FROM ngo_donation_requests ndr
                JOIN product_info pi ON ndr.product_id = pi.id
                JOIN users u ON ndr.ngo_id = u.id
                JOIN branches b ON ndr.branch_id = b.id
                LEFT JOIN ngo_profiles np ON u.id = np.user_id
                WHERE ndr.id = ? AND ndr.branch_id = ? AND ndr.status = 'approved'
            ");
            $requestStmt->execute([$requestId, $branchId]);
            $requestDetails = $requestStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$requestDetails) {
                throw new Exception("Donation request not found or already processed.");
            }
            
            // Record the preparation
            $prepareStmt = $pdo->prepare("
                INSERT INTO donation_prepared (
                    ngo_request_id,
                    staff_id,
                    prepared_date,
                    staff_notes
                ) VALUES (?, ?, NOW(), ?)
            ");
            $prepareStmt->execute([$requestId, $_SESSION['user_id'], $staffNotes]);
            
            // Create notification for NGO
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    message,
                    notification_type,
                    link,
                    is_read
                ) VALUES (
                    ?,
                    ?,
                    'donation_prepared',
                    '../ngo/donation_history.php',
                    0
                )
            ");
            
            $message = "Your donation request for {$requestDetails['product_name']} is ready for pickup!";
            $notifyStmt->execute([$requestDetails['ngo_id'], $message]);
            
            // Send email to NGO
            try {
                $emailService = new EmailService();
                $emailData = [
                    'name' => $requestDetails['ngo_name'],
                    'email' => $requestDetails['ngo_email'],
                    'product_name' => $requestDetails['product_name'],
                    'quantity' => $requestDetails['quantity_requested'],
                    'branch_name' => $requestDetails['branch_name'],
                    'branch_address' => $requestDetails['branch_address'],
                    'pickup_date' => $requestDetails['pickup_date'],
                    'pickup_time' => formatTimeSlot($requestDetails['pickup_time']),
                    'staff_notes' => $staffNotes
                ];
                
                $emailService->sendDonationPreparationEmail($emailData);
            } catch (Exception $emailErr) {
                // Log the error but don't fail the operation
                error_log("Failed to send preparation email: " . $emailErr->getMessage());
            }
            
            $successMessage = "Request marked as prepared. NGO has been notified via app and email.";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Add this to your existing PHP code where you handle other POST requests

// Handle bulk preparation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_request_ids'], $_POST['action']) && $_POST['action'] === 'bulk_prepare') {
    // Initialize arrays for prepared products
    $preparedProducts = [];
    $productNames = [];
    
    $requestIds = json_decode($_POST['bulk_request_ids'], true);
    $staffNotes = isset($_POST['staff_notes']) ? trim($_POST['staff_notes']) : '';
    
    // Validate request IDs
    if (!is_array($requestIds) || empty($requestIds)) {
        $errorMessage = "No valid requests selected for preparation.";
    } else {
        try {
            $pdo->beginTransaction();
            $successCount = 0;
            $ngoId = null;
            $ngoName = null;
            $productNames = [];
            $preparedProducts = [];
            
            foreach ($requestIds as $requestId) {
                // Get the donation request details
                $requestStmt = $pdo->prepare("
                    SELECT 
                        ndr.product_id, 
                        ndr.quantity_requested, 
                        ndr.ngo_id,
                        ndr.branch_id,
                        ndr.pickup_date,
                        ndr.pickup_time, 
                        pi.name as product_name,
                        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
                        u.email as ngo_email,
                        b.name as branch_name,
                        b.address as branch_address
                    FROM ngo_donation_requests ndr
                    JOIN product_info pi ON ndr.product_id = pi.id
                    JOIN users u ON ndr.ngo_id = u.id
                    JOIN branches b ON ndr.branch_id = b.id
                    LEFT JOIN ngo_profiles np ON u.id = np.user_id
                    WHERE ndr.id = ? AND ndr.branch_id = ? AND ndr.status = 'approved'
                ");
                $requestStmt->execute([$requestId, $branchId]);
                $requestDetails = $requestStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$requestDetails) {
                    continue; // Skip if not found or not valid
                }
                
                // Store NGO info for notification
                if (!$ngoId) {
                    $ngoId = $requestDetails['ngo_id'];
                    $ngoName = $requestDetails['ngo_name'];
                    $ngoEmail = $requestDetails['ngo_email'];
                    $branchName = $requestDetails['branch_name'];
                    $branchAddress = $requestDetails['branch_address'];
                    $pickupDate = $requestDetails['pickup_date'];
                }
                
                // Store product details for email
                $preparedProducts[] = [
                    'product_name' => $requestDetails['product_name'],
                    'quantity' => $requestDetails['quantity_requested']
                ];
                
                // Add product name to list for notification
                $productNames[] = $requestDetails['product_name'];
                
                // Record the preparation
                $prepareStmt = $pdo->prepare("
                    INSERT INTO donation_prepared (
                        ngo_request_id,
                        staff_id,
                        prepared_date,
                        staff_notes
                    ) VALUES (?, ?, NOW(), ?)
                ");
                $prepareStmt->execute([$requestId, $_SESSION['user_id'], $staffNotes]);
                
                $successCount++;
            }
            
            // Send single notification for all prepared items
            if ($ngoId && $successCount > 0) {
                // Create notification for NGO
                $notifyStmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        message,
                        notification_type,
                        link,
                        is_read
                    ) VALUES (
                        ?,
                        ?,
                        'donation_prepared',
                        '../ngo/donation_history.php',
                        0
                    )
                ");
                
                // Check if the user is an NGO
                $checkNgoStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $checkNgoStmt->execute([$ngoId]);
                $userRole = $checkNgoStmt->fetchColumn();

                if ($userRole !== 'ngo') {
                    throw new Exception("Only NGOs can receive donation requests.");
                }

                // Format product names nicely
                $productList = count($productNames) > 3 
                    ? implode(', ', array_slice($productNames, 0, 3)) . " and " . (count($productNames) - 3) . " more"
                    : implode(', ', $productNames);
                
                $message = "{$successCount} items ({$productList}) are now ready for pickup!";
                $notifyStmt->execute([$ngoId, $message]);
                // Send bulk email notification
                try {
                    $emailService = new EmailService();
                    $emailData = [
                        'name' => $ngoName,
                        'email' => $ngoEmail,
                        'branch_name' => $branchName,
                        'branch_address' => $branchAddress,
                        'pickup_date' => $pickupDate,
                        'staff_notes' => $staffNotes,
                        'products' => $preparedProducts,
                        'products_count' => count($preparedProducts),
                        'item_count' => $successCount
                    ];
                    
                    $emailService->sendBulkDonationPreparationEmail($emailData);
                } catch (Exception $emailErr) {
                    // Log the error but don't fail the operation
                    error_log("Failed to send bulk preparation email: " . $emailErr->getMessage());
                }
            }
            
            $pdo->commit();
            $successMessage = "{$successCount} items have been marked as prepared for {$ngoName}. NGO has been notified.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error processing bulk preparation: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Requests - WasteWise</title>
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
            // Existing prepare button click handler
            $('.prepare-btn').on('click', function() {
                const requestId = $(this).data('id');
                const productName = $(this).data('product');
                
                $('#request_id').val(requestId);
                $('#product_name_display').text(productName);
                document.getElementById('prepare_modal').showModal();
            });
            
            // Toggle all checkboxes for an NGO
            $('.select-all-ngo').on('change', function() {
                const ngoId = $(this).data('ngo-id');
                const isChecked = $(this).prop('checked');
                
                $(`.request-checkbox[data-ngo-id="${ngoId}"]`).prop('checked', isChecked);
                updateBulkButtonVisibility(ngoId);
            });
            
            // Update bulk button when individual checkboxes change
            $('.request-checkbox').on('change', function() {
                const ngoId = $(this).data('ngo-id');
                updateBulkButtonVisibility(ngoId);
                
                // Update "select all" checkbox state
                const totalCheckboxes = $(`.request-checkbox[data-ngo-id="${ngoId}"]`).length;
                const checkedCheckboxes = $(`.request-checkbox[data-ngo-id="${ngoId}"]:checked`).length;
                
                $(`.select-all-ngo[data-ngo-id="${ngoId}"]`).prop('checked', 
                    totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
            });
            
            // Update bulk button visibility based on selected checkboxes
            function updateBulkButtonVisibility(ngoId) {
                const selectedCount = $(`.request-checkbox[data-ngo-id="${ngoId}"]:checked`).length;
                const bulkButton = $(`.bulk-prepare-btn[data-ngo-id="${ngoId}"]`);
                
                if (selectedCount > 0) {
                    bulkButton.removeClass('hidden');
                    bulkButton.find('.selected-count').text(selectedCount);
                } else {
                    bulkButton.addClass('hidden');
                }
            }
            
            // Handle bulk prepare button click
            $('.bulk-prepare-btn').on('click', function() {
                const ngoId = $(this).data('ngo-id');
                const ngoName = $(this).data('ngo-name');
                
                // Get all checked request IDs for this NGO
                const selectedIds = [];
                const selectedProducts = [];
                
                $(`.request-checkbox[data-ngo-id="${ngoId}"]:checked`).each(function() {
                    selectedIds.push($(this).data('id'));
                    selectedProducts.push($(this).data('product'));
                });
                
                // Populate the bulk modal
                $('#bulk_ngo_name').text(ngoName);
                $('#bulk_request_ids').val(JSON.stringify(selectedIds));
                
                // Create list of products
                let listHtml = '';
                selectedProducts.forEach(product => {
                    listHtml += `<div class="text-sm py-1">${product}</div>`;
                });
                $('#bulk_items_list').html(listHtml);
                
                // Show the modal
                document.getElementById('bulk_prepare_modal').showModal();
            });
        });
    </script>
</head>
<body class="flex h-screen">
    <?php include '../layout/staff_nav.php' ?>
    
    <div class="p-7 w-full overflow-auto">
        <div>
            <nav class="mb-4">
                <ol class="flex items-center gap-2 text-gray-600">
                    <li><a href="staff_dashboard.php" class="hover:text-primarycol">Dashboard</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="donation_request.php" class="hover:text-primarycol">Donation Requests</a></li>
                </ol>
            </nav>
            <h1 class="text-3xl font-bold mb-2 text-primarycol">Donation Requests</h1>
            <p class="text-gray-500 mb-6">Manage and prepare donations for NGOs</p>
        </div>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>
        
        <div class="alert bg-sec text-primarycol mb-4">
            <div>
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-bold">Admin-Approved NGO Requests</span>
                    <p class="text-sm">These are donation requests approved by admin that need to be prepared for NGO pickup.</p>
                    <p class="text-sm mt-1">Mark items as prepared when they're ready for the NGO to collect.</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold mb-2">Pending Donation Requests</h2>
                <div class="badge badge-primary badge-lg">
                    Total: <?= count($pendingRequests) ?> items
                </div>
            </div>
            
            <?php if (empty($pendingRequests)): ?>
                <div class="text-center py-8 text-gray-500">
                    No pending donation requests found
                </div>
            <?php else: ?>
                <!-- Display grouped by NGO -->
                <?php foreach ($requestsByNgo as $ngoId => $ngoData): ?>
                    <div class="card bg-white shadow-md mb-6">
                        <div class="card-body">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-primarycol">
                                        <?= htmlspecialchars($ngoData['ngo_name']) ?>
                                        <span class="badge badge-primary ml-2">
                                            <?= count($ngoData['items']) ?> items
                                        </span>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Pickup on: <?= date('M d, Y', strtotime($ngoData['pickup_date'])) ?>
                                    </p>
                                </div>
                                <button data-ngo-id="<?= $ngoId ?>" 
                                        data-ngo-name="<?= htmlspecialchars($ngoData['ngo_name']) ?>" 
                                        class="bulk-prepare-btn btn btn-success btn-sm hidden">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Bulk Prepare (<span class="selected-count">0</span>)
                                </button>
                            </div>
                            
                            <!-- Display items table -->
                            <div class="overflow-x-auto mt-4">
                                <table class="table w-full">
                                    <thead>
                                        <tr class="bg-sec">
                                            <th class="w-10">
                                                <input type="checkbox" class="select-all-ngo checkbox checkbox-sm" data-ngo-id="<?= $ngoId ?>">
                                            </th>
                                            <th>ID</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Pickup Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ngoData['items'] as $request): ?>
                                            <tr class="hover">
                                                <td>
                                                    <input type="checkbox" 
                                                           class="request-checkbox checkbox checkbox-sm" 
                                                           data-id="<?= $request['id'] ?>"
                                                           data-ngo-id="<?= $ngoId ?>"
                                                           data-product="<?= htmlspecialchars($request['product_name']) ?>">
                                                </td>
                                                <td><?= $request['id'] ?></td>
                                                <td><?= htmlspecialchars($request['product_name']) ?></td>
                                                <td><?= $request['quantity_requested'] ?></td>
                                                <td>
                                                    <div class="text-sm">
                                                        <?= formatTimeSlot($request['pickup_time']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="prepare-btn btn btn-sm bg-primarycol text-white"
                                                            data-id="<?= $request['id'] ?>"
                                                            data-product="<?= htmlspecialchars($request['product_name']) ?>">
                                                        Mark as Prepared
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Prepare Modal -->
    <dialog id="prepare_modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg text-primarycol">Mark Donation as Prepared</h3>
            <p class="py-2">Confirm preparation of <span id="product_name_display" class="font-semibold"></span> for donation</p>
            
            <form method="POST">
                <input type="hidden" id="request_id" name="request_id">
                <input type="hidden" name="action" value="prepare">
                
                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">Additional Notes (Optional)</span>
                    </label>
                    <textarea name="staff_notes" class="textarea textarea-bordered h-24"
                              placeholder="Add preparation details, pickup instructions, etc."></textarea>
                </div>
                
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('prepare_modal').close();" class="btn">Cancel</button>
                    <button type="submit" class="btn bg-primarycol text-white">Confirm Preparation</button>
                </div>
            </form>
        </div>
    </dialog>
    
    <!-- Add this after the existing prepare_modal -->
    <dialog id="bulk_prepare_modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg text-primarycol">Bulk Preparation</h3>
            <p class="py-2">Prepare multiple items for <span id="bulk_ngo_name" class="font-semibold"></span></p>
            
            <div id="bulk_items_list" class="py-2 px-4 my-2 bg-gray-50 rounded-lg max-h-40 overflow-y-auto">
                <!-- Will be populated by JavaScript -->
            </div>
            
            <form method="POST">
                <input type="hidden" id="bulk_request_ids" name="bulk_request_ids">
                <input type="hidden" name="action" value="bulk_prepare">
                
                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">Additional Notes (Optional)</span>
                    </label>
                    <textarea name="staff_notes" class="textarea textarea-bordered h-24"
                              placeholder="Add preparation details, pickup instructions, etc."></textarea>
                </div>
                
                <div class="alert alert-info mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>The NGO will be notified when these items are prepared.</span>
                </div>
                
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('bulk_prepare_modal').close();" class="btn">Cancel</button>
                    <button type="submit" class="btn bg-primarycol text-white">Prepare Selected Items</button>
                </div>
            </form>
        </div>
    </dialog>
</body>
</html>

<?php
// Add this helper function at the bottom of the file
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
?>