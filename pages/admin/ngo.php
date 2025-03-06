<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin', 'staff']);

// Handle approve/reject actions
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $staffNotes = isset($_POST['staff_notes']) ? $_POST['staff_notes'] : '';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get request details for notification
        $requestInfoStmt = $pdo->prepare("
            SELECT 
                dr.ngo_id, 
                p.name as product_name
            FROM 
                donation_requests dr
            JOIN 
                product_waste pw ON dr.product_waste_id = pw.id
            JOIN 
                products p ON pw.product_id = p.id
            WHERE 
                dr.id = ?
        ");
        $requestInfoStmt->execute([$requestId]);
        $requestInfo = $requestInfoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$requestInfo) {
            throw new Exception("Request not found");
        }
        
        $ngoId = $requestInfo['ngo_id'];
        $productName = $requestInfo['product_name'];
        
        if ($action === 'approve') {
            // Update request status
            $stmt = $pdo->prepare("
                UPDATE donation_requests 
                SET status = 'approved', 
                    updated_at = NOW(),
                    staff_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$staffNotes, $requestId]);
            
            // Create notification
            $message = "Your donation request for {$productName} has been approved! " . 
                      (!empty($staffNotes) ? "Note: $staffNotes" : "");
            
            // Before creating a notification, verify the user is an NGO
            $checkRoleStmt = $pdo->prepare("
                SELECT role FROM users WHERE id = ?
            ");
            $checkRoleStmt->execute([$ngoId]);
            $userRole = $checkRoleStmt->fetchColumn();

            // Only create notification if the recipient is an NGO
            if ($userRole === 'ngo') {
                // Insert notification code
                $notifyStmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id, 
                        target_role,
                        message, 
                        notification_type,
                        link, 
                        is_read
                    ) VALUES (
                        ?, ?, ?, ?, ?, 0
                    )
                ");
                $notifyStmt->execute([
                    $ngoId,
                    'ngo',
                    $message,
                    'donation_request_approved',
                    "/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php"
                ]);
            }
            
            $successMessage = "Request #$requestId has been approved and the NGO has been notified.";
        } 
        else if ($action === 'reject') {
            // Update request status
            $stmt = $pdo->prepare("
                UPDATE donation_requests 
                SET status = 'rejected', 
                    updated_at = NOW(),
                    staff_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$staffNotes, $requestId]);
            
            // If rejecting, make the food available again
            $updateWasteStmt = $pdo->prepare("
                UPDATE product_waste pw
                JOIN donation_requests dr ON pw.id = dr.product_waste_id
                SET pw.is_claimed = 0
                WHERE dr.id = ?
            ");
            $updateWasteStmt->execute([$requestId]);
            
            // Create notification
            $message = "Your donation request for {$productName} has been rejected. " . 
                      (!empty($staffNotes) ? "Reason: $staffNotes" : "");
            
            // Before creating a notification, verify the user is an NGO
            $checkRoleStmt = $pdo->prepare("
                SELECT role FROM users WHERE id = ?
            ");
            $checkRoleStmt->execute([$ngoId]);
            $userRole = $checkRoleStmt->fetchColumn();

            // Only create notification if the recipient is an NGO
            if ($userRole === 'ngo') {
                // Insert notification code
                $notifyStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, message, link, is_read)
                    VALUES (?, ?, ?, 0)
                ");
                $notifyStmt->execute([
                    $ngoId, 
                    $message,
                    "/capstone/WASTE-WISE-CAPSTONE/pages/ngo/donation_history.php"
                ]);
            }
            
            $successMessage = "Request #$requestId has been rejected and the NGO has been notified.";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Get all donation requests with related information
$stmt = $pdo->query("
    SELECT 
        dr.id,
        dr.product_waste_id,
        dr.quantity_requested,
        dr.pickup_date,
        dr.pickup_time,
        dr.notes as ngo_notes,
        dr.status,
        dr.staff_notes,
        dr.created_at,
        dr.updated_at,
        pw.waste_quantity as available_quantity,
        p.name as product_name,
        p.category,
        pw.donation_expiry_date,
        CONCAT(u.fname, ' ', u.lname) as ngo_name,
        u.email as ngo_email,
        b.name as branch_name
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
    ORDER BY 
        dr.created_at DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group requests by status for easier display
$pendingRequests = array_filter($requests, function($req) {
    return $req['status'] === 'pending';
});

$processedRequests = array_filter($requests, function($req) {
    return $req['status'] !== 'pending';
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGO Management</title>
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
    </script>
</head>

<body class="flex h-screen bg-slate-100">
    
    <?php include '../layout/nav.php' ?>
    
    <div class="flex flex-col w-full p-6 space-y-6 overflow-y-auto">
        <div>
            <h2 class="text-3xl font-bold mb-6 text-primarycol">NGO Food Requests</h2>
        </div>
        
        <!-- Status Messages -->
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
        
        <!-- Pending Requests Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold mb-4 text-primarycol flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Pending Requests
                <?php if (count($pendingRequests) > 0): ?>
                    <span class="ml-3 bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center">
                        <?= count($pendingRequests) ?>
                    </span>
                <?php endif; ?>
            </h3>
            
            <?php if (count($pendingRequests) === 0): ?>
                <div class="text-center py-6 text-gray-500">No pending requests found.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead class="bg-sec text-primarycol">
                            <tr>
                                <th class="py-3">Request ID</th>
                                <th>NGO</th>
                                <th>Food Item</th>
                                <th>Quantity</th>
                                <th>Pickup Date</th>
                                <th>Requested On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                                <tr>
                                    <td><?= $request['id'] ?></td>
                                    <td><?= htmlspecialchars($request['ngo_name']) ?></td>
                                    <td><?= htmlspecialchars($request['product_name']) ?></td>
                                    <td><?= htmlspecialchars($request['quantity_requested']) ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($request['pickup_date'])) ?>
                                        <span class="text-xs text-gray-500">
                                            (<?= date('h:i A', strtotime($request['pickup_time'])) ?>)
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button class="btn btn-sm bg-primarycol text-white view-details-btn" 
                                                    data-id="<?= $request['id'] ?>">
                                                Details
                                            </button>
                                            <button class="btn btn-sm bg-green-600 text-white respond-btn" 
                                                    data-id="<?= $request['id'] ?>" 
                                                    data-action="approve">
                                                Approve
                                            </button>
                                            <button class="btn btn-sm bg-red-600 text-white respond-btn" 
                                                    data-id="<?= $request['id'] ?>" 
                                                    data-action="reject">
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
                                    <td><?= htmlspecialchars($request['ngo_name']) ?></td>
                                    <td><?= htmlspecialchars($request['product_name']) ?></td>
                                    <td><?= htmlspecialchars($request['quantity_requested']) ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'approved'): ?>
                                            <span class="badge bg-green-100 text-green-800 px-2 py-1">Approved</span>
                                        <?php elseif ($request['status'] === 'rejected'): ?>
                                            <span class="badge bg-red-100 text-red-800 px-2 py-1">Rejected</span>
                                        <?php elseif ($request['status'] === 'completed'): ?>
                                            <span class="badge bg-blue-100 text-blue-800 px-2 py-1">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($request['updated_at'] ?? $request['created_at'])) ?></td>
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
                            <p><span class="font-medium">Expiry Date:</span> 
                               <?= date('M d, Y', strtotime($request['donation_expiry_date'])) ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-4 bg-sec rounded-lg p-4">
                        <h4 class="font-semibold text-primarycol mb-2">Request Details</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <p><span class="font-medium">Quantity Requested:</span> <?= htmlspecialchars($request['quantity_requested']) ?></p>
                            <p><span class="font-medium">Available Quantity:</span> <?= htmlspecialchars($request['available_quantity']) ?></p>
                            <p><span class="font-medium">Pickup Date:</span> <?= date('M d, Y', strtotime($request['pickup_date'])) ?></p>
                            <p><span class="font-medium">Pickup Time:</span> <?= date('h:i A', strtotime($request['pickup_time'])) ?></p>
                        </div>
                        
                        <?php if (!empty($request['ngo_notes'])): ?>
                        <div class="mt-3">
                            <h5 class="font-medium">Notes from NGO:</h5>
                            <p class="bg-white p-2 rounded mt-1"><?= nl2br(htmlspecialchars($request['ngo_notes'])) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['staff_notes'])): ?>
                        <div class="mt-3">
                            <h5 class="font-medium">Staff Notes:</h5>
                            <p class="bg-white p-2 rounded mt-1"><?= nl2br(htmlspecialchars($request['staff_notes'])) ?></p>
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
                                  name="staff_notes"
                                  placeholder="Add any notes for the NGO regarding this request..."></textarea>
                    </div>
                    
                    <div class="modal-action">
                        <button type="button" class="btn close-modal">Cancel</button>
                        <button type="submit" class="btn bg-primarycol text-white">Submit</button>
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
            default:
                return 'bg-yellow-100 text-yellow-800';
        }
    }
    ?>
</body>
</html>