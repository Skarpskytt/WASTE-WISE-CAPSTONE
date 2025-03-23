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
            // Update request status
            $updateStmt = $pdo->prepare("
                UPDATE ngo_donation_requests 
                SET status = 'approved', 
                    admin_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$adminNotes, $requestId]);
            
            // Also update the status of the original donation request to 'reserved'
            // This ensures it won't show up for other NGOs
            $updateDonationStmt = $pdo->prepare("
                UPDATE donation_requests 
                SET status = 'reserved'
                WHERE id = (
                    SELECT donation_request_id FROM ngo_donation_requests WHERE id = ?
                )
            ");
            $updateDonationStmt->execute([$requestId]);
            
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

// Get all NGO donation requests
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
        p.expiry_date as donation_expiry_date,  /* Add this line */
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
        ndr.request_date DESC
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
                            <p><span class="font-medium">Pickup Time:</span> <?= date('h:i A', strtotime($request['pickup_time'])) ?></p>
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
    ?>
</body>
</html>