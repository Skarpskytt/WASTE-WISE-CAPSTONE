<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check if user is admin
checkAuth(['admin']);

// Get donation ID from query parameter
$donationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($donationId <= 0) {
    // Invalid ID, redirect to list page
    header('Location: donations.php');
    exit;
}

// Get donation details
$stmt = $pdo->prepare("
    SELECT 
        dr.id,
        dr.product_waste_id,
        dr.quantity_requested,
        dr.pickup_date,
        dr.pickup_time,
        dr.status,
        dr.notes as ngo_notes,
        dr.staff_notes,
        dr.created_at,
        dr.updated_at,
        dr.is_received,
        dr.received_at,
        pw.waste_quantity as available_quantity,
        p.name as product_name,
        p.category,
        pw.donation_expiry_date,
        CONCAT(u.fname, ' ', u.lname) as ngo_name,
        u.email as ngo_email,
        u.id as ngo_id,
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
    WHERE 
        dr.id = ?
");

$stmt->execute([$donationId]);
$donation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donation) {
    // Donation not found
    header('Location: donations.php');
    exit;
}

// Mark notification as read if it exists
$markReadStmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE notification_type = 'donation_received'
    AND link LIKE ?
");
$markReadStmt->execute(["%donation_details.php?id=$donationId%"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Details | WasteWise</title>
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
    <?php include '../layout/nav.php'; ?>

    <div class="flex flex-col w-full p-6 space-y-6 overflow-y-auto">
        <div class="flex justify-between items-center">
            <div class="text-2xl font-bold text-primarycol">Donation Request #<?= $donation['id'] ?></div>
            <a href="donations.php" class="btn btn-sm bg-primarycol text-white">
                Back to List
            </a>
        </div>
        
        <?php if ($donation['is_received']): ?>
            <div class="alert alert-success shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-bold">Donation Received!</span>
                    <p class="text-sm">This donation was received on <?= date('F j, Y \a\t h:i A', strtotime($donation['received_at'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold mb-4 text-primarycol">Donation Details</h3>
                
                <div class="mb-6">
                    <h4 class="font-semibold mb-2">Product Information</h4>
                    <div class="bg-sec rounded-lg p-4">
                        <p><span class="font-medium">Name:</span> <?= htmlspecialchars($donation['product_name']) ?></p>
                        <p><span class="font-medium">Category:</span> <?= htmlspecialchars($donation['category']) ?></p>
                        <p><span class="font-medium">Quantity:</span> <?= $donation['quantity_requested'] ?></p>
                        <p><span class="font-medium">Expiry Date:</span> <?= date('M d, Y', strtotime($donation['donation_expiry_date'])) ?></p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-semibold mb-2">Pickup Information</h4>
                    <div class="bg-sec rounded-lg p-4">
                        <p><span class="font-medium">Date:</span> <?= date('M d, Y', strtotime($donation['pickup_date'])) ?></p>
                        <p><span class="font-medium">Time:</span> <?= date('h:i A', strtotime($donation['pickup_time'])) ?></p>
                        <p><span class="font-medium">Status:</span> 
                            <?php if ($donation['status'] === 'approved'): ?>
                                <?php if ($donation['is_received']): ?>
                                    <span class="badge badge-success">Received</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Ready for Pickup</span>
                                <?php endif; ?>
                            <?php elseif ($donation['status'] === 'rejected'): ?>
                                <span class="badge badge-error">Rejected</span>
                            <?php elseif ($donation['status'] === 'pending'): ?>
                                <span class="badge badge-info">Pending</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold mb-4 text-primarycol">Partner Information</h3>
                
                <div class="mb-6">
                    <h4 class="font-semibold mb-2">NGO Details</h4>
                    <div class="bg-sec rounded-lg p-4">
                        <p><span class="font-medium">Name:</span> <?= htmlspecialchars($donation['ngo_name']) ?></p>
                        <p><span class="font-medium">Email:</span> <?= htmlspecialchars($donation['ngo_email']) ?></p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-semibold mb-2">Branch Information</h4>
                    <div class="bg-sec rounded-lg p-4">
                        <p><span class="font-medium">Name:</span> <?= htmlspecialchars($donation['branch_name']) ?></p>
                        <p><span class="font-medium">Address:</span> <?= htmlspecialchars($donation['branch_address']) ?></p>
                    </div>
                </div>
                
                <?php if (!empty($donation['staff_notes'])): ?>
                    <div class="mb-6">
                        <h4 class="font-semibold mb-2">Staff Notes</h4>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <p><?= nl2br(htmlspecialchars($donation['staff_notes'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($donation['ngo_notes'])): ?>
                    <div class="mb-6">
                        <h4 class="font-semibold mb-2">NGO Notes</h4>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p><?= nl2br(htmlspecialchars($donation['ngo_notes'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>