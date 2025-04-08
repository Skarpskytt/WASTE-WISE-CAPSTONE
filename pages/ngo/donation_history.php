<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../includes/mail/EmailService.php';

use App\Mail\EmailService;

// Check if user is NGO
checkAuth(['ngo']);

$pdo = getPDO();

$ngoId = $_SESSION['user_id'];

// Get NGO's information
$ngoStmt = $pdo->prepare("
    SELECT 
        CONCAT(fname, ' ', lname) as full_name,
        email,
        organization_name,
        profile_image
    FROM users 
    WHERE id = ? AND role = 'ngo'
");
$ngoStmt->execute([$ngoId]);
$ngoInfo = $ngoStmt->fetch(PDO::FETCH_ASSOC);
$userEmail = $ngoInfo['email'];

// Add donation stats
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN is_received = 1 THEN 1 ELSE 0 END) as received
    FROM ngo_donation_requests
    WHERE ngo_id = ?
");
$statsStmt->execute([$ngoId]);
$donationStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get NGO's donation history
$stmt = $pdo->prepare("
    SELECT 
        ndr.id,
        ndr.product_id,
        ndr.branch_id,
        ndr.waste_id,
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
        b.name as branch_name,
        b.address as branch_address
    FROM 
        ngo_donation_requests ndr
    JOIN 
        product_info p ON ndr.product_id = p.id
    JOIN 
        branches b ON ndr.branch_id = b.id
    WHERE 
        ndr.ngo_id = ?
    ORDER BY 
        ndr.request_date DESC
");
$stmt->execute([$ngoId]);
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Confirm receipt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receipt'])) {
    $requestId = (int)$_POST['request_id'];
    $receiptNotes = isset($_POST['receipt_notes']) ? $_POST['receipt_notes'] : '';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update the donation request status
        $updateStmt = $pdo->prepare("
            UPDATE ngo_donation_requests
            SET is_received = 1, 
                received_at = NOW(), 
                ngo_notes = CONCAT(IFNULL(ngo_notes, ''), '\n\nReceipt notes: ', ?)
            WHERE id = ? AND ngo_id = ?
        ");
        $updateStmt->execute([$receiptNotes, $requestId, $ngoId]);
        
        // Get donation details for the email
        $detailsStmt = $pdo->prepare("
            SELECT 
                p.name as product_name,
                ndr.quantity_requested,
                b.name as branch_name,
                b.address as branch_address,
                CONCAT(u.fname, ' ', u.lname) as ngo_name
            FROM ngo_donation_requests ndr
            JOIN product_info p ON ndr.product_id = p.id
            JOIN branches b ON ndr.branch_id = b.id
            JOIN users u ON ndr.ngo_id = u.id
            WHERE ndr.id = ?
        ");
        $detailsStmt->execute([$requestId]);
        $donationDetails = $detailsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Send confirmation email
        sendReceiptEmail(
            $userEmail, 
            $ngoInfo['full_name'], 
            $donationDetails, 
            $receiptNotes,
            $requestId
        );
        
        $pdo->commit();
        $successMessage = "Receipt confirmed successfully. Thank you for your contribution!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error confirming receipt: " . $e->getMessage();
    }
}

// Add pickup confirmation feature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_pickup'])) {
    $requestId = (int)$_POST['request_id'];
    $receivedBy = $_POST['received_by'];
    $receivedQuantity = (float)$_POST['received_quantity'];
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // First check if request exists and is approved
        $checkStmt = $pdo->prepare("
            SELECT 
                ndr.id, ndr.ngo_id, ndr.product_id, ndr.quantity_requested,
                ndr.branch_id, ndr.waste_id, /* Changed from donation_request_id to waste_id */
                p.name as product_name, b.name as branch_name,
                b.address as branch_address /* Added to avoid undefined index later */
            FROM ngo_donation_requests ndr
            JOIN product_info p ON ndr.product_id = p.id
            JOIN branches b ON ndr.branch_id = b.id
            WHERE ndr.id = ? AND ndr.ngo_id = ? AND ndr.status = 'approved'
        ");
        $checkStmt->execute([$requestId, $_SESSION['user_id']]);
        $requestDetails = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$requestDetails) {
            throw new Exception("Request not found or not approved.");
        }
        
        // Record donation receipt
        $insertStmt = $pdo->prepare("
            INSERT INTO donated_products (
                ngo_id, donation_request_id, received_by, received_date,
                received_quantity, remarks
            ) VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        $insertStmt->execute([
            $_SESSION['user_id'],
            $requestDetails['id'], // Use the request ID
            $receivedBy,
            $receivedQuantity,
            $remarks
        ]);
        
        // Update request status
        $updateStmt = $pdo->prepare("
            UPDATE ngo_donation_requests 
            SET is_received = 1, received_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$requestId]);
        
        // Notify admin
        $notifyStmt = $pdo->prepare("
            INSERT INTO notifications (
                target_role, message, notification_type, link, is_read
            ) VALUES (
                'admin',
                ?,
                'donation_received',
                '../admin/foods.php',
                0
            )
        ");
        $ngoName = $_SESSION['organization_name'] ?: ($_SESSION['fname'] . ' ' . $_SESSION['lname']);
        $message = "NGO '{$ngoName}' has confirmed pickup of {$receivedQuantity} {$requestDetails['product_name']} items.";
        $notifyStmt->execute([$message]);
        
        // Send receipt email
        $emailService = new EmailService();
        $emailData = [
            'ngo_name' => $ngoName,
            'ngo_email' => $_SESSION['email'],
            'id' => $requestId,
            'product_name' => $requestDetails['product_name'],
            'branch_name' => $requestDetails['branch_name'],
            'branch_address' => $requestDetails['branch_address'] ?? 'Address not available', // Added fallback
            'received_by' => $receivedBy,
            'received_quantity' => $receivedQuantity,
            'received_date' => date('Y-m-d H:i:s'),
            'remarks' => $remarks
        ];
        $emailService->sendDonationReceiptEmail($emailData);
        
        $pdo->commit();
        $successMessage = "Pickup confirmed successfully! A receipt has been sent to your email.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error confirming pickup: " . $e->getMessage();
    }
}

// Handle bulk pickup confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_bulk_pickup'])) {
    if (!isset($_POST['request_ids']) || !is_array($_POST['request_ids'])) {
        $errorMessage = "No donations selected for pickup.";
    } else {
        try {
            $pdo->beginTransaction();
            $requestIds = $_POST['request_ids'];
            $receivedBy = $_POST['received_by'];
            $remarks = $_POST['remarks'] ?? '';
            $successCount = 0;
            
            // For bulk receipt - collect all products
            $allProducts = [];
            $totalQuantity = 0;
            $branchName = "";
            $branchAddress = "";
            
            foreach ($requestIds as $requestId) {
                // Existing code for checking and processing each request
                $requestId = (int)$requestId;
                $receivedQuantity = (float)($_POST['quantities'][$requestId] ?? 0);
                
                if ($receivedQuantity <= 0) {
                    continue; // Skip if quantity is invalid
                }
                
                // Check if request exists and is approved
                $checkStmt = $pdo->prepare("
                    SELECT 
                        ndr.id, ndr.ngo_id, ndr.product_id, ndr.quantity_requested,
                        ndr.branch_id, ndr.waste_id,
                        p.name as product_name, b.name as branch_name,
                        b.address as branch_address
                    FROM ngo_donation_requests ndr
                    JOIN product_info p ON ndr.product_id = p.id
                    JOIN branches b ON ndr.branch_id = b.id
                    WHERE ndr.id = ? AND ndr.ngo_id = ? AND ndr.status = 'approved' AND ndr.is_received = 0
                ");
                $checkStmt->execute([$requestId, $_SESSION['user_id']]);
                $requestDetails = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$requestDetails) {
                    continue; // Skip if request not found or already received
                }
                
                // Record donation receipt
                $insertStmt = $pdo->prepare("
                    INSERT INTO donated_products (
                        ngo_id, donation_request_id, received_by, received_date,
                        received_quantity, remarks
                    ) VALUES (?, ?, ?, NOW(), ?, ?)
                ");
                $insertStmt->execute([
                    $_SESSION['user_id'],
                    $requestDetails['id'],  // Use request ID instead of waste_id
                    $receivedBy,
                    $receivedQuantity,
                    $remarks
                ]);
                
                // Update request status
                $updateStmt = $pdo->prepare("
                    UPDATE ngo_donation_requests 
                    SET is_received = 1, received_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$requestId]);
                
                // Collect product info for bulk receipt
                $allProducts[] = [
                    'id' => $requestId,
                    'product_name' => $requestDetails['product_name'],
                    'quantity' => $receivedQuantity
                ];
                $totalQuantity += $receivedQuantity;
                
                // Store branch info (assuming all products are from same branch)
                $branchName = $requestDetails['branch_name'];
                $branchAddress = $requestDetails['branch_address'] ?? 'Address not available';
                
                $successCount++;
            }
            
            // Send a single bulk notification to admin AFTER the loop instead of individual ones
            if ($successCount > 0) {
                $notifyStmt = $pdo->prepare("
                    INSERT INTO notifications (
                        target_role, 
                        message, 
                        notification_type,
                        link, 
                        is_read
                    ) VALUES (
                        'admin',
                        ?,
                        'donation_received',
                        '../admin/donation_history.php', 
                        0
                    )
                ");
                
                $ngoName = $_SESSION['organization_name'] ?: ($_SESSION['fname'] . ' ' . $_SESSION['lname']);
                
                // Format product list for notification
                $productSummary = '';
                if (count($allProducts) > 3) {
                    $topProducts = array_slice($allProducts, 0, 3);
                    $productNames = array_column($topProducts, 'product_name');
                    $productSummary = implode(', ', $productNames) . ' and ' . (count($allProducts) - 3) . ' more';
                } else {
                    $productNames = array_column($allProducts, 'product_name');
                    $productSummary = implode(', ', $productNames);
                }
                
                $message = "NGO '{$ngoName}' has confirmed bulk pickup of {$successCount} items ({$totalQuantity} total qty): {$productSummary}";
                $notifyStmt->execute([$message]);
            }
            
            // Send a single bulk receipt email if products were processed
            if (count($allProducts) > 0) {
                $emailService = new EmailService();
                $bulkEmailData = [
                    'ngo_name' => $ngoName,
                    'ngo_email' => $_SESSION['email'],
                    'date' => date('Y-m-d H:i:s'),
                    'branch_name' => $branchName,
                    'branch_address' => $branchAddress,
                    'received_by' => $receivedBy,
                    'products' => $allProducts,
                    'total_quantity' => $totalQuantity,
                    'remarks' => $remarks,
                    'is_bulk' => true
                ];
                
                $emailService->sendBulkDonationReceiptEmail($bulkEmailData);
            }
            
            $pdo->commit();
            $successMessage = "Successfully confirmed pickup of {$successCount} donations! A receipt has been sent to your email.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error confirming pickups: " . $e->getMessage();
        }
    }
}

/**
 * Send receipt acknowledgment via email
 */
function sendReceiptEmail($recipientEmail, $recipientName, $donationDetails, $notes, $requestId) {
    try {
        // Create donation receipt data
        $receiptData = [
            'id' => $requestId,
            'ngo_email' => $recipientEmail,
            'ngo_name' => $recipientName,
            'branch_name' => $donationDetails['branch_name'],
            'branch_address' => $donationDetails['branch_address'] ?? 'Address not available', // Add fallback
            'product_name' => $donationDetails['product_name'],
            'quantity_requested' => $donationDetails['quantity_requested'],
            'notes' => $notes
        ];
        
        // Create EmailService instance and send receipt
        $emailService = new EmailService();
        $emailService->sendDonationReceiptEmail($receiptData);
        
        return true;
    } catch (Exception $e) {
        // Log email error but don't display to user
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}
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
    <?php include '../layout/ngo_nav.php'; ?>

    <div class="flex flex-col w-full p-6 space-y-6 overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div>
                <div class="text-2xl font-bold text-primarycol">Donation History</div>
                <div class="text-sm text-gray-500">
                    <?= htmlspecialchars($ngoInfo['organization_name'] ?? $ngoInfo['full_name']) ?>
                </div>
            </div>
            
            <div class="stats shadow">
                <div class="stat place-items-center">
                    <div class="stat-title">Approved</div>
                    <div class="stat-value text-green-500"><?= $donationStats['approved'] ?></div>
                </div>
                <div class="stat place-items-center">
                    <div class="stat-title">Pending</div>
                    <div class="stat-value text-yellow-500"><?= $donationStats['pending'] ?></div>
                </div>
                <div class="stat place-items-center">
                    <div class="stat-title">Received</div>
                    <div class="stat-value text-blue-500"><?= $donationStats['received'] ?></div>
                </div>
            </div>
        </div>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold">Your Donations</h3>
                <button id="bulk-confirm-btn" 
                        class="btn btn-primary btn-sm bg-primarycol hidden">
                    Confirm Selected Pickups
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="select-all" class="checkbox checkbox-sm">
                            </th>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Branch</th>
                            <th>Pickup Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donations) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">
                                    No donation history found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($donations as $donation): ?>
                                <tr class="hover">
                                    <td>
                                        <?php if ($donation['status'] === 'approved' && !$donation['is_received']): ?>
                                        <input type="checkbox" 
                                               class="donation-checkbox checkbox checkbox-sm" 
                                               data-id="<?= $donation['id'] ?>"
                                               data-product="<?= htmlspecialchars($donation['product_name']) ?>"
                                               data-quantity="<?= $donation['quantity_requested'] ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $donation['id'] ?></td>
                                    <td><?= htmlspecialchars($donation['product_name']) ?></td>
                                    <td><?= $donation['quantity_requested'] ?></td>
                                    <td><?= htmlspecialchars($donation['branch_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($donation['pickup_date'])) ?> 
                                        <?= date('h:i A', strtotime($donation['pickup_time'])) ?></td>
                                    <td>
                                        <?php if ($donation['status'] === 'approved'): ?>
                                            <?php if ($donation['is_received']): ?>
                                                <span class="badge badge-success">Received</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Ready for Pickup</span>
                                            <?php endif; ?>
                                        <?php elseif ($donation['status'] === 'rejected'): ?>
                                            <span class="badge badge-error">Rejected</span>
                                        <?php elseif ($donation['status'] === 'pending'): ?>
                                            <span class="badge badge-warning">Awaiting Approval</span>
                                        <?php elseif ($donation['status'] === 'completed'): ?>
                                            <span class="badge badge-success">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($donation['status'] === 'approved' && !$donation['is_received']): ?>
                                            <button data-id="<?= $donation['id'] ?>" 
                                                    data-product="<?= htmlspecialchars($donation['product_name']) ?>"
                                                    data-quantity="<?= $donation['quantity_requested'] ?>"
                                                    class="confirm-pickup-btn btn btn-sm btn-success">
                                                Confirm Pickup
                                            </button>
                                        <?php endif; ?>
                                        <button data-id="<?= $donation['id'] ?>"
                                                class="view-details-btn btn btn-sm bg-primarycol text-white">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Receipt Confirmation Modal -->
    <dialog id="receipt_modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4">Confirm Donation Receipt</h3>
            <form method="POST" action="">
                <input type="hidden" name="request_id" id="modal_request_id">
                <p class="mb-4">
                    You are confirming receipt of: <span id="modal_product_name" class="font-semibold"></span>
                </p>
                
                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">Additional Notes (Optional)</span>
                    </label>
                    <textarea name="receipt_notes" class="textarea textarea-bordered h-24" 
                              placeholder="Any notes about the condition, quantity, etc."></textarea>
                </div>
                
                <div class="alert alert-info mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>A receipt confirmation will be sent to your email (<?= htmlspecialchars($userEmail) ?>).</span>
                </div>
                
                <div class="modal-action">
                    <button type="button" class="btn" onclick="document.getElementById('receipt_modal').close()">
                        Cancel
                    </button>
                    <button type="submit" name="confirm_receipt" class="btn btn-primary bg-primarycol">
                        Confirm Receipt
                    </button>
                </div>
            </form>
        </div>
    </dialog>
    
    <!-- Pickup Confirmation Modal - Add this after your receipt_modal -->
<dialog id="pickup_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Confirm Donation Pickup</h3>
        <form method="POST" action="">
            <input type="hidden" name="request_id" id="pickup_request_id">
            
            <p class="mb-4">
                You are confirming pickup of: <span id="pickup_product_name" class="font-semibold"></span>
            </p>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Received By (Name of person who picked up)</span>
                </label>
                <input type="text" name="received_by" class="input input-bordered" 
                       value="<?= htmlspecialchars($ngoInfo['full_name']) ?>" required>
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Quantity Received</span>
                </label>
                <input type="number" name="received_quantity" id="pickup_quantity" class="input input-bordered" 
                       step="0.01" min="0.01" required>
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Remarks (Optional)</span>
                </label>
                <textarea name="remarks" class="textarea textarea-bordered h-24" 
                          placeholder="Any notes about the pickup, condition, etc."></textarea>
            </div>
            
            <div class="alert alert-info mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>A receipt confirmation will be sent to your email (<?= htmlspecialchars($userEmail) ?>). This will also appear in the admin donation history.</span>
            </div>
            
            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('pickup_modal').close()">
                    Cancel
                </button>
                <button type="submit" name="confirm_pickup" class="btn btn-primary bg-primarycol">
                    Confirm Pickup
                </button>
            </div>
        </form>
    </div>
</dialog>
    
    <!-- Details Modal -->
    <?php foreach ($donations as $donation): ?>
        <dialog id="details_modal_<?= $donation['id'] ?>" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Donation Request #<?= $donation['id'] ?></h3>
                
                <div class="bg-sec rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-primarycol mb-2">Product Information</h4>
                    <p><span class="font-medium">Name:</span> <?= htmlspecialchars($donation['product_name']) ?></p>
                    <p><span class="font-medium">Category:</span> <?= htmlspecialchars($donation['category']) ?></p>
                    <p><span class="font-medium">Quantity Requested:</span> <?= $donation['quantity_requested'] ?></p>
                </div>
                
                <div class="bg-sec rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-primarycol mb-2">Branch Information</h4>
                    <p><span class="font-medium">Name:</span> <?= htmlspecialchars($donation['branch_name']) ?></p>
                    <?php if (isset($donation['branch_address'])): ?>
                    <p><span class="font-medium">Address:</span> <?= htmlspecialchars($donation['branch_address']) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-sec rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-primarycol mb-2">Pickup Information</h4>
                    <p><span class="font-medium">Date:</span> <?= date('M d, Y', strtotime($donation['request_date'])) ?></p>
                </div>
                
                <?php if (!empty($donation['admin_notes'])): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-yellow-800 mb-2">Admin Notes</h4>
                        <p><?= nl2br(htmlspecialchars($donation['admin_notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($donation['ngo_notes'])): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-blue-800 mb-2">Your Notes</h4>
                        <p><?= nl2br(htmlspecialchars($donation['ngo_notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($donation['is_received']): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-green-800 mb-2">Receipt Information</h4>
                        <p><span class="font-medium">Received On:</span> <?= date('M d, Y h:i A', strtotime($donation['received_at'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="modal-action">
                    <button class="btn" onclick="document.getElementById('details_modal_<?= $donation['id'] ?>').close()">
                        Close
                    </button>
                </div>
            </div>
        </dialog>
    <?php endforeach; ?>
    
    <!-- Add this after your existing modals -->
<dialog id="bulk_pickup_modal" class="modal">
    <div class="modal-box max-w-3xl">
        <h3 class="font-bold text-lg mb-4">Bulk Donation Pickup Confirmation</h3>
        <form method="POST" action="">
            <input type="hidden" name="bulk_pickup" value="1">
            <div id="selected-donations-container">
                <!-- Will be populated by JS -->
            </div>
            
            <div class="form-control mb-4 mt-6">
                <label class="label">
                    <span class="label-text">Received By (Name of person who picked up)</span>
                </label>
                <input type="text" name="received_by" class="input input-bordered" 
                       value="<?= htmlspecialchars($ngoInfo['full_name']) ?>" required>
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Remarks (Optional - applies to all selected donations)</span>
                </label>
                <textarea name="remarks" class="textarea textarea-bordered h-24" 
                          placeholder="Any notes about the pickup, condition, etc."></textarea>
            </div>
            
            <div class="alert alert-info mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>A receipt confirmation will be sent to your email (<?= htmlspecialchars($userEmail) ?>).</span>
            </div>
            
            <div class="modal-action">
                <button type="button" class="btn" onclick="document.getElementById('bulk_pickup_modal').close()">
                    Cancel
                </button>
                <button type="submit" name="confirm_bulk_pickup" class="btn btn-primary bg-primarycol">
                    Confirm All Pickups
                </button>
            </div>
        </form>
    </div>
</dialog>

    <script>
        // Show details modal
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                document.getElementById('details_modal_' + requestId).showModal();
            });
        });
        
        // Show receipt confirmation modal
        document.querySelectorAll('.confirm-receipt-btn').forEach(button => {
            button.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-product');
                
                document.getElementById('modal_request_id').value = requestId;
                document.getElementById('modal_product_name').textContent = productName;
                document.getElementById('receipt_modal').showModal();
            });
        });

        // Show pickup confirmation modal (new code)
        document.querySelectorAll('.confirm-pickup-btn').forEach(button => {
            button.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-product');
                const quantity = this.getAttribute('data-quantity');
                
                document.getElementById('pickup_request_id').value = requestId;
                document.getElementById('pickup_product_name').textContent = productName;
                document.getElementById('pickup_quantity').value = quantity;
                document.getElementById('pickup_modal').showModal();
            });
        });

        // Existing scripts...

        // Bulk donation selection functionality
        const selectAllCheckbox = document.getElementById('select-all');
        const donationCheckboxes = document.querySelectorAll('.donation-checkbox');
        const bulkConfirmBtn = document.getElementById('bulk-confirm-btn');
        
        // Toggle all checkboxes
        selectAllCheckbox?.addEventListener('change', function() {
            const isChecked = this.checked;
            donationCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateBulkButtonVisibility();
        });
        
        // Update button visibility when individual checkboxes change
        donationCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkButtonVisibility);
        });
        
        function updateBulkButtonVisibility() {
            const checkedBoxes = document.querySelectorAll('.donation-checkbox:checked');
            if (checkedBoxes.length > 0) {
                bulkConfirmBtn.classList.remove('hidden');
            } else {
                bulkConfirmBtn.classList.add('hidden');
            }
        }
        
        // Handle bulk confirm button click
        bulkConfirmBtn?.addEventListener('click', function() {
            const selectedCheckboxes = document.querySelectorAll('.donation-checkbox:checked');
            const selectedDonationsContainer = document.getElementById('selected-donations-container');
            
            // Clear previous content
            selectedDonationsContainer.innerHTML = '';
            
            if (selectedCheckboxes.length === 0) {
                return;
            }
            
            // Add a table showing selected donations
            let html = `
                <div class="overflow-x-auto">
                    <table class="table w-full mb-4">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            selectedCheckboxes.forEach(checkbox => {
                const id = checkbox.getAttribute('data-id');
                const product = checkbox.getAttribute('data-product');
                const quantity = checkbox.getAttribute('data-quantity');
                
                html += `
                    <tr>
                        <td>${id}</td>
                        <td>${product}</td>
                        <td>
                            <input type="hidden" name="request_ids[]" value="${id}">
                            <input type="number" name="quantities[${id}]" class="input input-bordered input-sm w-24"
                                   value="${quantity}" min="0.01" step="0.01" required>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            selectedDonationsContainer.innerHTML = html;
            document.getElementById('bulk_pickup_modal').showModal();
        });
    </script>
</body>
</html>