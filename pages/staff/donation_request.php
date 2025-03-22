<?php
// filepath: c:\xampp\htdocs\capstone\WASTE-WISE-CAPSTONE\pages\staff\donation_request.php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Get all donation requests for this branch
$stmt = $pdo->prepare("
    SELECT 
        dr.id,
        dr.product_id,
        dr.quantity,
        dr.request_date,
        dr.status,
        dr.notes,
        p.name as product_name,
        p.category,
        p.expiry_date,
        p.stock_quantity as current_stock,
        CONCAT(u.fname, ' ', u.lname) as requester_name
    FROM 
        donation_requests dr
    JOIN 
        products p ON dr.product_id = p.id
    JOIN 
        users u ON dr.requested_by = u.id
    WHERE 
        dr.branch_id = ? AND 
        (dr.status = 'pending' OR dr.status = 'prepared')
    ORDER BY 
        dr.request_date DESC
");
$stmt->execute([$_SESSION['branch_id']]);
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission (updating status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $staffNotes = isset($_POST['staff_notes']) ? trim($_POST['staff_notes']) : '';
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'prepare') {
            // Get the donation request details
            $requestStmt = $pdo->prepare("
                SELECT dr.product_id, dr.quantity
                FROM donation_requests dr
                WHERE dr.id = ? AND dr.branch_id = ? AND dr.status = 'pending'
            ");
            $requestStmt->execute([$requestId, $_SESSION['branch_id']]);
            $requestDetails = $requestStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$requestDetails) {
                throw new Exception("Donation request not found or already processed.");
            }
            
            // Verify stock is sufficient
            $stockStmt = $pdo->prepare("
                SELECT stock_quantity, name 
                FROM products 
                WHERE id = ?
            ");
            $stockStmt->execute([$requestDetails['product_id']]);
            $productDetails = $stockStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($productDetails['stock_quantity'] < $requestDetails['quantity']) {
                throw new Exception("Not enough stock available for {$productDetails['name']}. Available: {$productDetails['stock_quantity']}");
            }
            
            // Deduct from stock
            $updateStockStmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ?
            ");
            $updateStockStmt->execute([$requestDetails['quantity'], $requestDetails['product_id']]);
            
            // Update request status
            $updateStmt = $pdo->prepare("
                UPDATE donation_requests 
                SET status = 'prepared', 
                    notes = CONCAT(IFNULL(notes, ''), '\n\nStaff Notes: ', ?)
                WHERE id = ? AND branch_id = ?
            ");
            $updateStmt->execute([$staffNotes, $requestId, $_SESSION['branch_id']]);
            
            // Create notification for NGOs
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications 
                (target_role, message, notification_type, link, is_read) 
                VALUES ('ngo', ?, 'donation_prepared', '/capstone/WASTE-WISE-CAPSTONE/pages/ngo/food_browse.php', 0)
            ");
            
            $message = "A donation is ready for pickup! Please check available donations.";
            $notifyStmt->execute([$message]);
            
            $successMessage = "Request marked as prepared and stock updated. NGOs have been notified.";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error: " . $e->getMessage();
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
            // Prepare button click
            $('.prepare-btn').on('click', function() {
                const requestId = $(this).data('id');
                const productName = $(this).data('product');
                
                $('#request_id').val(requestId);
                $('#product_name_display').text(productName);
                document.getElementById('prepare_modal').showModal();
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
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Pending Donation Requests</h2>
            
            <?php if (empty($pendingRequests)): ?>
                <div class="text-center py-8 text-gray-500">
                    No pending donation requests found
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr class="bg-sec">
                                <th>ID</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Current Stock</th> <!-- New column -->
                                <th>Requested</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                                <tr class="hover">
                                    <td><?= $request['id'] ?></td>
                                    <td><?= htmlspecialchars($request['product_name']) ?></td>
                                    <td><?= $request['quantity'] ?></td>
                                    <td><?= $request['current_stock'] ?></td> <!-- New column -->
                                    <td><?= date('M d, Y', strtotime($request['request_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($request['expiry_date'])) ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <span class="badge bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php elseif ($request['status'] === 'prepared'): ?>
                                            <span class="badge bg-green-100 text-green-800">Prepared</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <?php if ($request['current_stock'] >= $request['quantity']): ?>
                                                <button class="prepare-btn btn btn-sm bg-primarycol text-white"
                                                        data-id="<?= $request['id'] ?>"
                                                        data-product="<?= htmlspecialchars($request['product_name']) ?>">
                                                    Mark as Prepared
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm bg-red-500 text-white" disabled title="Insufficient stock">
                                                    Insufficient Stock
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">Awaiting Pickup</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
</body>
</html>