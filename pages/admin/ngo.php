<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../includes/mail/EmailService.php';
use App\Mail\EmailService;

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();


// Handle approve/reject actions
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    file_put_contents(__DIR__ . '/admin_approval_debug.log', 
        date('Y-m-d H:i:s') . ' APPROVAL DATA: ' . print_r($_POST, true) . "\n", 
        FILE_APPEND
    );
    
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $adminNotes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get request details
        $requestInfoStmt = $pdo->prepare("
            SELECT 
                ndr.ngo_id, 
                p.name as product_name
            FROM 
                ngo_donation_requests ndr
            JOIN 
                products p ON ndr.product_id = p.id
            WHERE 
                ndr.id = ?
        ");
        $requestInfoStmt->execute([$requestId]);
        $requestInfo = $requestInfoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$requestInfo) {
            throw new Exception("Request not found");
        }
        
        $ngoId = $requestInfo['ngo_id'];
        $productName = $requestInfo['product_name'];
        
        if ($action === 'approve') {
            // First get the donation_request_id for this NGO request
            $donationIdStmt = $pdo->prepare("
                SELECT donation_request_id, product_id, quantity_requested 
                FROM ngo_donation_requests 
                WHERE id = ?
            ");
            $donationIdStmt->execute([$requestId]);
            $requestInfo = $donationIdStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$requestInfo) {
                throw new Exception("Original donation request not found");
            }
            
            $donationRequestId = $requestInfo['donation_request_id'];
            $productId = $requestInfo['product_id'];
            $quantityRequested = $requestInfo['quantity_requested'];
            
            // Get the waste quantity before updating
            $wasteStmt = $pdo->prepare("
                SELECT waste_quantity 
                FROM product_waste 
                WHERE id = ?
            ");
            $wasteStmt->execute([$donationRequestId]);
            $currentWasteQuantity = $wasteStmt->fetchColumn();
            $newQuantity = $currentWasteQuantity - $quantityRequested;

            // Update product_waste quantity (don't delete if still has items)
            if ($newQuantity > 0) {
                // Update quantity only
                $updateStmt = $pdo->prepare("
                    UPDATE product_waste
                    SET waste_quantity = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$newQuantity, $donationRequestId]);
            } else {
                // If no quantity left, update status to completed
                $updateStmt = $pdo->prepare("
                    UPDATE product_waste
                    SET waste_quantity = 0,
                        donation_status = 'completed',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$donationRequestId]);
            }
            
            // Update NGO request status
            $updateStmt = $pdo->prepare("
                UPDATE ngo_donation_requests 
                SET status = 'approved', 
                    admin_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$adminNotes, $requestId]);
            
            // Update the status of the original donation request
            $updateDonationStmt = $pdo->prepare("
                UPDATE donation_requests 
                SET status = 'reserved'
                WHERE id = ?
            ");
            $updateDonationStmt->execute([$donationRequestId]);
            
            // Get email data
            $detailsStmt = $pdo->prepare("
                SELECT 
                    ndr.pickup_date,
                    ndr.pickup_time,
                    p.name as product_name,
                    b.name as branch_name,
                    u.fname,
                    u.lname,
                    u.email,
                    u.organization_name
                FROM 
                    ngo_donation_requests ndr
                JOIN 
                    products p ON ndr.product_id = p.id
                JOIN 
                    branches b ON ndr.branch_id = b.id
                JOIN
                    users u ON ndr.ngo_id = u.id
                WHERE 
                    ndr.id = ?
            ");
            $detailsStmt->execute([$requestId]);
            $requestDetails = $detailsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification for NGO
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    target_role,
                    message,
                    notification_type,
                    link,
                    is_read
                ) VALUES (
                    ?,
                    'ngo',
                    ?,
                    'donation_request_approved',
                    '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php',
                    0
                )
            ");
            
            $message = "Your donation request for {$productName} has been approved.";
            $notifyStmt->execute([$ngoId, $message]);
            
            // Send email if we have details
            if ($requestDetails) {
                $emailService = new EmailService();
                $emailData = [
                    'status' => 'approved',
                    'name' => !empty($requestDetails['organization_name']) ? 
                            $requestDetails['organization_name'] : 
                            $requestDetails['fname'] . ' ' . $requestDetails['lname'],
                    'email' => $requestDetails['email'],
                    'product_name' => $requestDetails['product_name'],
                    'branch_name' => $requestDetails['branch_name'],
                    'pickup_date' => date('M d, Y', strtotime($requestDetails['pickup_date'])),
                    'pickup_time' => date('h:i A', strtotime($requestDetails['pickup_time'])),
                    'notes' => $adminNotes
                ];
                
                // Send email notification
                $emailService->sendDonationRequestStatusEmail($emailData);
            }
            
        } elseif ($action === 'reject') {
            // Update request status
            $updateStmt = $pdo->prepare("
                UPDATE ngo_donation_requests 
                SET status = 'rejected', 
                    admin_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$adminNotes, $requestId]);
            
            // Create notification for NGO
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    target_role,
                    message,
                    notification_type,
                    link,
                    is_read
                ) VALUES (
                    ?,
                    'ngo',
                    ?,
                    'donation_request_rejected',
                    '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php',
                    0
                )
            ");
            
            $message = "Your donation request for {$productName} has been rejected.";
            $notifyStmt->execute([$ngoId, $message]);
            
            // Get email data for rejection
            $detailsStmt = $pdo->prepare("
                SELECT 
                    u.fname,
                    u.lname,
                    u.email,
                    u.organization_name,
                    p.name as product_name
                FROM 
                    ngo_donation_requests ndr
                JOIN 
                    products p ON ndr.product_id = p.id
                JOIN
                    users u ON ndr.ngo_id = u.id
                WHERE 
                    ndr.id = ?
            ");
            $detailsStmt->execute([$requestId]);
            $requestDetails = $detailsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Send rejection email
            if ($requestDetails) {
                $emailService = new EmailService();
                $emailData = [
                    'status' => 'rejected',
                    'name' => !empty($requestDetails['organization_name']) ? 
                            $requestDetails['organization_name'] : 
                            $requestDetails['fname'] . ' ' . $requestDetails['lname'],
                    'email' => $requestDetails['email'],
                    'product_name' => $requestDetails['product_name'],
                    'notes' => $adminNotes
                ];
                
                // Send email notification
                $emailService->sendDonationRequestStatusEmail($emailData);
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Add this code right after your existing handler for single approve/reject

// Handle batch approvals
if (isset($_POST['batch_action']) && isset($_POST['request_ids'])) {
    $requestIds = $_POST['request_ids'];
    $action = $_POST['batch_action'];
    $adminNotes = isset($_POST['batch_admin_notes']) ? $_POST['batch_admin_notes'] : '';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get NGO info - assuming all selected requests are from the same NGO
        $firstRequestId = $requestIds[0];
        $ngoInfoStmt = $pdo->prepare("
            SELECT 
                ndr.ngo_id,
                u.email,
                u.fname,
                u.lname,
                u.organization_name
            FROM 
                ngo_donation_requests ndr
            JOIN 
                users u ON ndr.ngo_id = u.id
            WHERE 
                ndr.id = ?
        ");
        $ngoInfoStmt->execute([$firstRequestId]);
        $ngoInfo = $ngoInfoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ngoInfo) {
            throw new Exception("NGO information not found");
        }
        
        $ngoId = $ngoInfo['ngo_id'];
        $ngoName = !empty($ngoInfo['organization_name']) ? 
                    $ngoInfo['organization_name'] : 
                    $ngoInfo['fname'] . ' ' . $ngoInfo['lname'];
        
        // Process each request
        $approvedItems = [];
        $rejectedItems = [];
        
        foreach ($requestIds as $requestId) {
            $requestInfoStmt = $pdo->prepare("
                SELECT 
                    ndr.donation_request_id,
                    ndr.quantity_requested,
                    p.name as product_name,
                    b.name as branch_name,
                    ndr.pickup_date,
                    ndr.pickup_time
                FROM 
                    ngo_donation_requests ndr
                JOIN 
                    products p ON ndr.product_id = p.id
                JOIN 
                    branches b ON ndr.branch_id = b.id
                WHERE 
                    ndr.id = ?
            ");
            $requestInfoStmt->execute([$requestId]);
            $requestInfo = $requestInfoStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$requestInfo) {
                continue; // Skip this request if info not found
            }
            
            $donationRequestId = $requestInfo['donation_request_id'];
            $quantityRequested = $requestInfo['quantity_requested'];
            $productName = $requestInfo['product_name'];
            
            if ($action === 'approve') {
                // Handle approval logic (same as in your single approval code)
                $wasteStmt = $pdo->prepare("
                    SELECT waste_quantity 
                    FROM product_waste 
                    WHERE id = ?
                ");
                $wasteStmt->execute([$donationRequestId]);
                $currentWasteQuantity = $wasteStmt->fetchColumn();
                $newQuantity = $currentWasteQuantity - $quantityRequested;

                // Update product_waste quantity
                if ($newQuantity > 0) {
                    $updateStmt = $pdo->prepare("
                        UPDATE product_waste
                        SET waste_quantity = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$newQuantity, $donationRequestId]);
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE product_waste
                        SET waste_quantity = 0,
                            donation_status = 'completed',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$donationRequestId]);
                }
                
                // Update NGO request status
                $updateStmt = $pdo->prepare("
                    UPDATE ngo_donation_requests 
                    SET status = 'approved', 
                        admin_notes = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$adminNotes, $requestId]);
                
                $approvedItems[] = [
                    'product_name' => $productName,
                    'quantity' => $quantityRequested,
                    'branch_name' => $requestInfo['branch_name'],
                    'pickup_date' => $requestInfo['pickup_date'],
                    'pickup_time' => $requestInfo['pickup_time']
                ];
                
            } elseif ($action === 'reject') {
                // Update request status to rejected
                $updateStmt = $pdo->prepare("
                    UPDATE ngo_donation_requests 
                    SET status = 'rejected', 
                        admin_notes = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$adminNotes, $requestId]);
                
                $rejectedItems[] = [
                    'product_name' => $productName,
                    'quantity' => $quantityRequested
                ];
            }
        }
        
        // Create a single notification for the NGO
        if (!empty($approvedItems)) {
            $itemCount = count($approvedItems);
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    target_role,
                    message,
                    notification_type,
                    link,
                    is_read
                ) VALUES (
                    ?,
                    'ngo',
                    ?,
                    'donation_request_approved',
                    '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php',
                    0
                )
            ");
            
            $message = "Your donation requests for $itemCount items have been approved.";
            $notifyStmt->execute([$ngoId, $message]);
            
            // Send batch approval email
            $emailService = new EmailService();
            $emailData = [
                'status' => 'batch_approved',
                'name' => $ngoName,
                'email' => $ngoInfo['email'],
                'approved_items' => $approvedItems,
                'notes' => $adminNotes
            ];
            
            $emailService->sendBatchDonationStatusEmail($emailData);
        }
        
        if (!empty($rejectedItems)) {
            $itemCount = count($rejectedItems);
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    target_role,
                    message,
                    notification_type,
                    link,
                    is_read
                ) VALUES (
                    ?,
                    'ngo',
                    ?,
                    'donation_request_rejected',
                    '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php',
                    0
                )
            ");
            
            $message = "Your donation requests for $itemCount items have been rejected.";
            $notifyStmt->execute([$ngoId, $message]);
            
            // Send batch rejection email
            $emailService = new EmailService();
            $emailData = [
                'status' => 'batch_rejected',
                'name' => $ngoName,
                'email' => $ngoInfo['email'],
                'rejected_items' => $rejectedItems,
                'notes' => $adminNotes
            ];
            
            $emailService->sendBatchDonationStatusEmail($emailData);
        }
        
        $pdo->commit();
        $successMessage = "Batch " . ucfirst($action) . " completed successfully for " . 
                          count($requestIds) . " requests from $ngoName.";
                          
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Replace your existing query to get all NGO donation requests
$stmt = $pdo->query("
    SELECT 
        ndr.id,
        ndr.product_id,
        ndr.ngo_id,
        ndr.branch_id,
        ndr.request_date,
        ndr.pickup_date,
        ndr.pickup_time,
        ndr.status,
        ndr.quantity_requested,
        ndr.ngo_notes,
        ndr.admin_notes,
        ndr.is_received,
        ndr.received_at,
        p.name as product_name,
        p.category,
        p.expiry_date as donation_expiry_date,
        CONCAT(u.fname, ' ', u.lname) as ngo_name,
        u.organization_name,
        u.email as ngo_email,
        b.name as branch_name
    FROM 
        ngo_donation_requests ndr
    JOIN 
        products p ON ndr.product_id = p.id
    JOIN 
        users u ON ndr.ngo_id = u.id
    JOIN 
        branches b ON ndr.branch_id = b.id
    ORDER BY 
        u.organization_name, u.fname, u.lname, ndr.request_date DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Split requests into pending and processed for stats
$pendingRequests = array_filter($requests, function($request) {
    return $request['status'] === 'pending';
});

$processedRequests = array_filter($requests, function($request) {
    return $request['status'] !== 'pending';
});

// Group requests by NGO organization
$requestsByNgo = [];
foreach ($requests as $request) {
    $ngoIdentifier = !empty($request['organization_name']) ? 
        $request['organization_name'] : 
        $request['ngo_name'];
    
    $ngoId = $request['ngo_id'];
    $key = $ngoId . '_' . $ngoIdentifier;
    
    if (!isset($requestsByNgo[$key])) {
        $requestsByNgo[$key] = [
            'ngo_id' => $ngoId,
            'ngo_name' => $ngoIdentifier,
            'pending_count' => 0,
            'requests' => []
        ];
    }
    
    $requestsByNgo[$key]['requests'][] = $request;
    
    if ($request['status'] === 'pending') {
        $requestsByNgo[$key]['pending_count']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGO Management</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Logo.png">
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
            // Show request details modal
            $('.view-details-btn').click(function() {
                const requestId = $(this).data('id');
                $('#requestDetailsModal_' + requestId).addClass('modal-open');
            });
            
            // Close modal
            $('.close-modal').click(function() {
                $('.modal').removeClass('modal-open');
            });
            
            // Show response modal (approve/reject)
            $('.respond-btn').click(function() {
                const requestId = $(this).data('id');
                const action = $(this).data('action');
                
                $('#responseAction').val(action);
                $('#responseRequestId').val(requestId);
                $('#actionLabel').text(action === 'approve' ? 'Approve' : 'Reject');
                $('#responseModal').addClass('modal-open');
            });
            
            // Outside click to close modals
            $(window).click(function(e) {
                if ($(e.target).hasClass('modal')) {
                    $('.modal').removeClass('modal-open');
                }
            });
        });

        // Add this to your existing JavaScript
        $(document).ready(function() {
            // Track selected requests
            let selectedRequests = [];
            
            // Handle checkbox clicks
            $('.request-checkbox').on('change', function() {
                const requestId = $(this).data('request-id');
                
                if ($(this).prop('checked')) {
                    // Add to selected
                    if (!selectedRequests.includes(requestId)) {
                        selectedRequests.push(requestId);
                    }
                } else {
                    // Remove from selected
                    selectedRequests = selectedRequests.filter(id => id !== requestId);
                }
                
                updateBatchToolbar();
            });
            
            // Handle "Select All" for an NGO
            $('.select-all-ngo').on('change', function() {
                const ngoId = $(this).data('ngo-id');
                const isChecked = $(this).prop('checked');
                
                // Find all checkboxes for this NGO
                $(`.request-checkbox[data-ngo-id="${ngoId}"]`).each(function() {
                    $(this).prop('checked', isChecked);
                    
                    const requestId = $(this).data('request-id');
                    
                    if (isChecked) {
                        // Add to selected if not already there
                        if (!selectedRequests.includes(requestId)) {
                            selectedRequests.push(requestId);
                        }
                    } else {
                        // Remove from selected
                        selectedRequests = selectedRequests.filter(id => id !== requestId);
                    }
                });
                
                updateBatchToolbar();
            });
            
            // Clear selection button
            $('#clearSelection').on('click', function() {
                // Uncheck all checkboxes
                $('.request-checkbox, .select-all-ngo').prop('checked', false);
                
                // Clear selected list
                selectedRequests = [];
                
                updateBatchToolbar();
            });
            
            // Batch approve button
            $('#batchApprove').on('click', function() {
                if (selectedRequests.length === 0) return;
                
                $('#batchActionType').val('approve');
                $('#batchModalTitle').text('Approve Multiple Requests');
                $('#batchItemCount').text(selectedRequests.length);
                
                // Create hidden inputs for selected requests
                updateSelectedRequestsInputs();
                
                $('#batchActionModal').addClass('modal-open');
            });
            
            // Batch reject button
            $('#batchReject').on('click', function() {
                if (selectedRequests.length === 0) return;
                
                $('#batchActionType').val('reject');
                $('#batchModalTitle').text('Reject Multiple Requests');
                $('#batchItemCount').text(selectedRequests.length);
                
                // Create hidden inputs for selected requests
                updateSelectedRequestsInputs();
                
                $('#batchActionModal').addClass('modal-open');
            });
            
            // Update the batch toolbar visibility and count
            function updateBatchToolbar() {
                if (selectedRequests.length > 0) {
                    $('#batchToolbar').removeClass('hidden');
                    $('#selectedCount').text(selectedRequests.length);
                } else {
                    $('#batchToolbar').addClass('hidden');
                }
            }
            
            // Create hidden inputs for the form
            function updateSelectedRequestsInputs() {
                const container = $('#selectedRequestsContainer');
                container.empty();
                
                selectedRequests.forEach(requestId => {
                    container.append(`<input type="hidden" name="request_ids[]" value="${requestId}">`);
                });
            }
        });
    </script>
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #47663B;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #3b5231;
        }
    </style>
</head>

<body class="flex h-screen bg-slate-100">
    
    <?php include '../layout/nav.php' ?>
    
    <div class="flex flex-col w-full p-6 space-y-6 overflow-y-auto custom-scrollbar bg-gradient-to-br from-white to-sec/20">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h2 class="text-3xl font-bold text-primarycol">NGO Food Requests</h2>
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-500">Total Requests:</span>
                    <span class="px-3 py-1 text-sm font-semibold bg-primarycol text-white rounded-full">
                        <?= count($requests) ?>
                    </span>
                </div>
            </div>
            <div class="flex space-x-4">
                <div class="stats shadow">
                    <div class="stat place-items-center">
                        <div class="stat-title text-primarycol">Pending</div>
                        <div class="stat-value text-yellow-600"><?= count($pendingRequests) ?></div>
                    </div>
                    <div class="stat place-items-center">
                        <div class="stat-title text-primarycol">Processed</div>
                        <div class="stat-value text-green-600"><?= count($processedRequests) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Status Messages -->
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success shadow-lg mb-6 animate-fadeIn">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($successMessage) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Enhanced Pending Requests Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-primarycol flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Pending Requests
                    <?php if (count($pendingRequests) > 0): ?>
                        <span class="ml-3 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full animate-pulse">
                            <?= count($pendingRequests) ?>
                        </span>
                    <?php endif; ?>
                </h3>
            </div>

            <!-- Enhanced Table -->
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr class="bg-primarycol text-white">
                            <th>ID</th>
                            <th>NGO</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Branch</th>
                            <th>Requested</th>
                            <th>Pickup Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($pendingRequests as $request): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium">#<?= $request['id'] ?></td>
                                <td class="px-4 py-3"><?= !empty($request['organization_name']) ? 
                                    htmlspecialchars($request['organization_name']) : 
                                    htmlspecialchars($request['ngo_name']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($request['product_name']) ?></td>
                                <td class="px-4 py-3"><?= $request['quantity_requested'] ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($request['branch_name']) ?></td>
                                <td class="px-4 py-3"><?= date('M d, Y', strtotime($request['request_date'])) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium"><?= date('M d, Y', strtotime($request['pickup_date'])) ?></span>
                                        <span class="text-xs text-gray-500"><?= date('h:i A', strtotime($request['pickup_time'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        <button class="btn btn-sm bg-primarycol hover:bg-primarycol/90 text-white transition-colors view-details-btn" 
                                                data-id="<?= $request['id'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Details
                                        </button>
                                        <button class="btn btn-sm bg-green-600 hover:bg-green-700 text-white transition-colors respond-btn" 
                                                data-id="<?= $request['id'] ?>" 
                                                data-action="approve">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Approve
                                        </button>
                                        <button class="btn btn-sm bg-red-600 hover:bg-red-700 text-white transition-colors respond-btn" 
                                                data-id="<?= $request['id'] ?>" 
                                                data-action="reject">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Processed Requests Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold mb-4 text-primarycol flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Processed Requests
            </h3>
            
            <?php if (count($processedRequests) === 0): ?>
                <div class="text-center py-6 text-gray-500">No processed requests found.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead class="bg-sec text-primarycol">
                            <tr>
                                <th>Request ID</th>
                                <th>NGO</th>
                                <th>Food Item</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Processed On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processedRequests as $request): ?>
                                <tr>
                                    <td><?= $request['id'] ?></td>
                                    <td><?= !empty($request['organization_name']) ? 
                                        htmlspecialchars($request['organization_name']) : 
                                        htmlspecialchars($request['ngo_name']) ?></td>
                                    <td><?= htmlspecialchars($request['product_name']) ?></td>
                                    <td><?= htmlspecialchars($request['quantity_requested']) ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'approved' && !$request['is_received']): ?>
                                            <span class="badge bg-green-100 text-green-800 px-2 py-1">Approved</span>
                                        <?php elseif ($request['status'] === 'rejected'): ?>
                                            <span class="badge bg-red-100 text-red-800 px-2 py-1">Rejected</span>
                                        <?php elseif ($request['status'] === 'completed' || $request['is_received']): ?>
                                            <span class="badge bg-blue-100 text-blue-800 px-2 py-1">Completed</span>
                                        <?php elseif ($request['status'] === 'pending'): ?>
                                            <span class="badge bg-yellow-100 text-yellow-800 px-2 py-1">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($request['request_date'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm bg-primarycol text-white view-details-btn" 
                                                data-id="<?= $request['id'] ?>">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Request Details Modals -->
        <?php foreach ($requests as $request): ?>
            <div id="requestDetailsModal_<?= $request['id'] ?>" class="modal">
                <div class="modal-box max-w-2xl">
                    <h3 class="font-bold text-lg text-primarycol mb-4 flex justify-between">
                        Request Details #<?= $request['id'] ?>
                        <span class="badge <?= getStatusBadgeClass($request['status']) ?>">
                            <?= ucfirst($request['status']) ?>
                        </span>
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-sec rounded-lg p-4">
                            <h4 class="font-semibold text-primarycol mb-2">NGO Information</h4>
                            <p><span class="font-medium">Name:</span> <?= htmlspecialchars($request['ngo_name']) ?></p>
                            <p><span class="font-medium">Email:</span> <?= htmlspecialchars($request['ngo_email']) ?></p>
                        </div>
                        
                        <div class="bg-sec rounded-lg p-4">
                            <h4 class="font-semibold text-primarycol mb-2">Donation Information</h4>
                            <p><span class="font-medium">Product:</span> <?= htmlspecialchars($request['product_name']) ?></p>
                            <p><span class="font-medium">Category:</span> <?= htmlspecialchars($request['category']) ?></p>
                            <p><span class="font-medium">Branch:</span> <?= htmlspecialchars($request['branch_name']) ?></p>
                            <?php if (isset($request['donation_expiry_date']) && !empty($request['donation_expiry_date'])): ?>
                            <p><span class="font-medium">Expiry Date:</span> 
                               <?= date('M d, Y', strtotime($request['donation_expiry_date'])) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4 bg-sec rounded-lg p-4">
                        <h4 class="font-semibold text-primarycol mb-2">Request Details</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <p><span class="font-medium">Quantity Requested:</span> <?= htmlspecialchars($request['quantity_requested']) ?></p>
                            <p><span class="font-medium">Pickup Date:</span> <?= date('M d, Y', strtotime($request['pickup_date'])) ?></p>
                            <p>
                              <span class="font-medium">Pickup Time:</span> 
                              <?= formatTimeSlot($request['pickup_time']) ?>
                            </p>
                        </div>
                        
                        <?php if (!empty($request['ngo_notes'])): ?>
                        <div class="mt-3">
                            <h5 class="font-medium">Notes from NGO:</h5>
                            <p class="bg-white p-2 rounded mt-1"><?= nl2br(htmlspecialchars($request['ngo_notes'])) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['admin_notes'])): ?>
                        <div class="mt-3">
                            <h5 class="font-medium">Admin Notes:</h5>
                            <p class="bg-white p-2 rounded mt-1"><?= nl2br(htmlspecialchars($request['admin_notes'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="modal-action">
                        <button class="btn close-modal">Close</button>
                        
                        <?php if ($request['status'] === 'pending'): ?>
                            <button class="btn bg-green-600 text-white respond-btn" 
                                    data-id="<?= $request['id'] ?>" 
                                    data-action="approve">
                                Approve
                            </button>
                            <button class="btn bg-red-600 text-white respond-btn" 
                                    data-id="<?= $request['id'] ?>" 
                                    data-action="reject">
                                Reject
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Response Modal (Approve/Reject) -->
        <div id="responseModal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg text-primarycol mb-4" id="responseModalTitle">
                    <span id="actionLabel">Respond</span> to Request
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="request_id" id="responseRequestId">
                    <input type="hidden" name="action" id="responseAction">
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Notes (Optional)</span>
                        </label>
                        <textarea class="textarea textarea-bordered h-24" 
                                  name="admin_notes"
                                  placeholder="Add any notes for the NGO regarding this request..."></textarea>
                    </div>
                    
                    <div class="modal-action">
                        <button type="button" class="btn close-modal">Cancel</button>
                        <button type="submit" class="btn bg-primarycol text-white">Submit</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Add this right after your existing status messages section -->
        <div class="batch-processing-toolbar bg-white rounded-xl shadow-lg p-4 mb-6 hidden" id="batchToolbar">
            <div class="flex items-center justify-between">
                <div>
                    <span class="font-medium">Selected:</span> 
                    <span id="selectedCount" class="font-bold">0</span> requests
                </div>
                <div class="flex space-x-2">
                    <button id="batchApprove" class="btn btn-sm bg-green-600 hover:bg-green-700 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Approve Selected
                    </button>
                    <button id="batchReject" class="btn btn-sm bg-red-600 hover:bg-red-700 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Reject Selected
                    </button>
                    <button id="clearSelection" class="btn btn-sm bg-gray-600 hover:bg-gray-700 text-white">
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>

        <!-- Group requests by NGO -->
        <?php foreach ($requestsByNgo as $ngoKey => $ngoData): ?>
            <?php 
            // Only show NGOs with pending requests in the pending section
            if ($ngoData['pending_count'] == 0) continue; 
            ?>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-primarycol">
                        <?= htmlspecialchars($ngoData['ngo_name']) ?>
                        <span class="ml-3 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                            <?= $ngoData['pending_count'] ?> pending
                        </span>
                    </h3>
                    
                    <!-- Select all for this NGO -->
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" class="select-all-ngo checkbox checkbox-primary mr-2" 
                                   data-ngo-id="<?= $ngoData['ngo_id'] ?>">
                            <span>Select All</span>
                        </label>
                    </div>
                </div>

                <!-- Requests table for this NGO -->
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr class="bg-primarycol text-white">
                                <th style="width: 40px;"><input type="checkbox" class="checkbox checkbox-sm checkbox-primary"></th>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Branch</th>
                                <th>Requested</th>
                                <th>Pickup Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ngoData['requests'] as $request): ?>
                                <?php if ($request['status'] !== 'pending') continue; ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td>
                                        <input type="checkbox" class="request-checkbox checkbox checkbox-primary" 
                                               data-request-id="<?= $request['id'] ?>"
                                               data-ngo-id="<?= $request['ngo_id'] ?>">
                                    </td>
                                    <td class="px-4 py-3 font-medium">#<?= $request['id'] ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($request['product_name']) ?></td>
                                    <td class="px-4 py-3"><?= $request['quantity_requested'] ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($request['branch_name']) ?></td>
                                    <td class="px-4 py-3"><?= date('M d, Y', strtotime($request['request_date'])) ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-medium"><?= date('M d, Y', strtotime($request['pickup_date'])) ?></span>
                                            <span class="text-xs text-gray-500"><?= date('h:i A', strtotime($request['pickup_time'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex space-x-2">
                                            <!-- Your existing buttons -->
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Batch Action Modal -->
        <div id="batchActionModal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg text-primarycol mb-4" id="batchModalTitle">
                    Process Multiple Requests
                </h3>
                
                <form method="POST" id="batchActionForm">
                    <input type="hidden" name="batch_action" id="batchActionType" value="">
                    <div id="selectedRequestsContainer">
                        <!-- Hidden inputs will be inserted here by JavaScript -->
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Notes (Optional)</span>
                        </label>
                        <textarea class="textarea textarea-bordered h-24" 
                                  name="batch_admin_notes"
                                  placeholder="Add notes that will apply to all selected requests..."></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <div class="bg-yellow-50 p-3 rounded-md border border-yellow-200">
                            <div class="flex">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <p class="font-medium text-yellow-700">You are about to process <span id="batchItemCount">0</span> requests at once.</p>
                                    <p class="text-sm text-yellow-600 mt-1">This action will apply to all selected requests.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-action">
                        <button type="button" class="btn close-modal">Cancel</button>
                        <button type="submit" class="btn bg-primarycol text-white">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php
    // Helper function to get badge classes based on status
    function getStatusBadgeClass($status) {
        switch ($status) {
            case 'approved':
                return 'bg-green-100 text-green-800';
            case 'rejected':
                return 'bg-red-100 text-red-800';
            case 'completed':
                return 'bg-blue-100 text-blue-800';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }

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
</body>
</html>