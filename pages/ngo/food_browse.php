<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for NGO access only
checkAuth(['ngo']);

$pdo = getPDO();

// Add this near the top of your file, right after checkAuth(['ngo']);
$ngoId = $_SESSION['user_id']; // Make sure $ngoId is defined



// Get NGO name for greeting - MOVE THIS TO THE TOP
$ngoQuery = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as full_name, organization_name FROM users WHERE id = ?");
$ngoQuery->execute([$ngoId]);
$ngoInfo = $ngoQuery->fetch(PDO::FETCH_ASSOC);

// Initialize messages
$successMessage = null;
$errorMessage = null;

// Process donation request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donation_id'])) {
    // Get form data
    $donationRequestId = isset($_POST['donation_id']) ? (int)$_POST['donation_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $pickupDate = isset($_POST['pickup_date']) ? $_POST['pickup_date'] : '';
    $pickupTime = isset($_POST['pickup_time']) ? $_POST['pickup_time'] : '';
    
    // Validate input
    $errors = [];
    if ($donationRequestId <= 0) {
        $errors[] = "Invalid donation selection.";
    }
    if ($quantity < 20) {
        $errors[] = "Please request at least 20 items.";
    }
    if ($quantity > 30) {
        $errors[] = "Maximum request limit is 30 items.";
    }
    if (empty($pickupDate)) {
        $errors[] = "Please select a pickup date.";
    }
    if (empty($pickupTime)) {
        $errors[] = "Please select a pickup time.";
    }
    
    // Check if the donation exists and has enough quantity
    try {
        $checkStmt = $pdo->prepare("
            SELECT 
                dr.id,
                dr.product_id,
                dr.branch_id,
                dr.quantity,
                p.name as product_name,
                b.name as branch_name
            FROM 
                donation_requests dr
            JOIN 
                products p ON dr.product_id = p.id
            JOIN 
                branches b ON dr.branch_id = b.id
            WHERE 
                dr.id = ? AND dr.status = 'prepared' AND dr.quantity >= ?
        ");
        $checkStmt->execute([$donationRequestId, $quantity]);
        $donation = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donation) {
            $errors[] = "The requested donation is not available or has insufficient quantity.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    // If no errors, process the request
    if (empty($errors)) {
        try {
            // Start a transaction
            $pdo->beginTransaction();
            
            // Create a pending request in the NGO donation requests table
            $insertStmt = $pdo->prepare("
                INSERT INTO ngo_donation_requests (
                    product_id,
                    ngo_id,
                    branch_id,
                    donation_request_id,
                    request_date,
                    pickup_date,
                    pickup_time,
                    status,
                    quantity_requested,
                    ngo_notes
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'pending', ?, ?)
            ");
            
            $insertStmt->execute([
                $donation['product_id'],
                $ngoId,
                $donation['branch_id'],
                $donationRequestId,
                $pickupDate,
                $pickupTime,
                $quantity,
                $notes
            ]);
            
            // Create notification for admin
            $notifyAdminStmt = $pdo->prepare("
                INSERT INTO notifications (
                    target_role,
                    message,
                    notification_type,
                    link,
                    is_read
                ) VALUES (
                    'admin',
                    ?,
                    'ngo_donation_request',
                    '/capstone/WASTE-WISE-CAPSTONE/pages/admin/ngo.php',
                    0
                )
            ");
            
            $orgName = $ngoInfo['organization_name'] ?: $ngoInfo['full_name'];
            $notifyMessage = "New donation request from {$orgName} for {$donation['product_name']}";
            $notifyAdminStmt->execute([$notifyMessage]);
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $successMessage = "Your donation request has been submitted and is awaiting approval.";
            
        } catch (PDOException $e) {
            // Rollback on error
            $pdo->rollBack();
            $errorMessage = "Error processing request: " . $e->getMessage();
        }
    } else {
        $errorMessage = implode(" ", $errors);
    }
}

// Initialize filter variables
$typeFilter = isset($_GET['food_type']) ? $_GET['food_type'] : '';
$quantityFilter = isset($_GET['min_quantity']) && is_numeric($_GET['min_quantity']) ? (int)$_GET['min_quantity'] : 0;
$dateFilter = isset($_GET['expiry_date']) ? $_GET['expiry_date'] : '';
$donorFilter = isset($_GET['donor_name']) ? $_GET['donor_name'] : '';

// Add this line BEFORE you start building your SQL query (around line 159)

$params = [$ngoId]; // Initialize the params array

// Replace your current SQL query with this one:

$sql = "SELECT 
    dr.id, 
    p.id as product_id,
    p.name as product_name,
    p.category,
    dr.quantity as available_quantity,
    p.expiry_date,
    b.id as branch_id,
    b.name as branch_name,
    dr.notes,
    CONCAT(u.fname, ' ', u.lname) as donor_name
FROM 
    donation_requests dr
JOIN 
    products p ON dr.product_id = p.id
JOIN 
    branches b ON dr.branch_id = b.id
JOIN 
    users u ON dr.staff_id = u.id
WHERE 
    dr.status = 'prepared'
    AND p.expiry_date > NOW()
    AND dr.id NOT IN (
        /* Exclude donations that are already approved for this NGO */
        SELECT ndr.donation_request_id 
        FROM ngo_donation_requests ndr 
        WHERE ndr.ngo_id = ? 
        AND (ndr.status = 'approved' OR ndr.status = 'completed')
    )
";

// Apply filters (needs adjusting for the new query structure)
if (!empty($typeFilter) && $typeFilter !== 'All') {
    $sql .= " AND p.category = ?";
    $params[] = $typeFilter;
}

if ($quantityFilter > 0) {
    $sql .= " AND dr.quantity >= ?";
    $params[] = $quantityFilter;
}

if (!empty($dateFilter)) {
    $sql .= " AND DATE(p.expiry_date) >= ?";
    $params[] = $dateFilter;
}

if (!empty($donorFilter)) {
    $sql .= " AND (u.fname LIKE ? OR u.lname LIKE ?)";
    $params[] = "%$donorFilter%";
    $params[] = "%$donorFilter%";
}

$sql .= " ORDER BY p.expiry_date ASC";

// Execute query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $donations = [];
}

// Get unique categories for filter dropdown
$catSql = "SELECT DISTINCT category FROM products ORDER BY category";
$catStmt = $pdo->query($catSql);
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Get IDs of donations this NGO has already requested
$requestedQuery = $pdo->prepare("
    SELECT dr.product_id
    FROM ngo_donation_requests ndr
    JOIN donation_requests dr ON ndr.donation_request_id = dr.id
    WHERE ndr.ngo_id = ? AND (ndr.status = 'pending' OR ndr.status = 'approved')
");
$requestedQuery->execute([$ngoId]);
$requestedDonations = $requestedQuery->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Dashboard - Food Browse</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
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
    </script>
</head>

<body class="flex h-screen bg-slate-100">
    <?php include '../layout/ngo_nav.php' ?>

    <div class="flex flex-col w-full p-6 space-y-6 overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div>
                <div class="text-2xl font-bold text-primarycol">Browse Available Donations</div>
                <div class="text-sm text-gray-500">Welcome, <?= htmlspecialchars($ngoInfo['organization_name'] ?? $ngoInfo['full_name']) ?></div>
            </div>
            <div>
                <a href="donation_history.php" class="btn btn-sm bg-primarycol text-white">View My Requests</a>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'requested'): ?>
            <div class="alert alert-success shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Your donation request has been submitted successfully! The donor will contact you soon.</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error shadow-lg mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Filter/Search Options -->
        <div class="bg-white p-4 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-primarycol mb-4">Filter/Search Options</h2>
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-gray-700">Type of Food</label>
                        <select name="food_type" class="w-full p-2 border rounded-lg">
                            <option value="All" <?= $typeFilter === 'All' ? 'selected' : '' ?>>All</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" 
                                        <?= $typeFilter === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700">Quantity Available</label>
                        <input type="number" name="min_quantity" class="w-full p-2 border rounded-lg" 
                               placeholder="Minimum quantity" value="<?= htmlspecialchars($quantityFilter) ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700">Expiration Date</label>
                        <input type="date" name="expiry_date" class="w-full p-2 border rounded-lg"
                               value="<?= htmlspecialchars($dateFilter) ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700">Donor Name</label>
                        <input type="text" name="donor_name" class="w-full p-2 border rounded-lg" 
                               placeholder="Donor name" value="<?= htmlspecialchars($donorFilter) ?>">
                    </div>
                </div>
                <button type="submit" class="mt-4 px-4 py-2 bg-primarycol text-white rounded-lg">Search</button>
                <a href="food_browse.php" class="mt-4 px-4 py-2 bg-gray-500 text-white rounded-lg ml-2">Reset Filters</a>
            </form>
        </div>

        <!-- Food Listing -->
        <div class="bg-white p-4 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-primarycol mb-4">Available Donations</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (count($donations) > 0): ?>
                    <?php 
                    // Update the logic for determining if an item has been requested
                    $checkRequestStmt = $pdo->prepare("
                        SELECT status FROM ngo_donation_requests 
                        WHERE ngo_id = ? AND donation_request_id = ?
                    ");

                    foreach ($donations as $item): 
                        $checkRequestStmt->execute([$ngoId, $item['id']]);
                        $requestStatus = $checkRequestStmt->fetchColumn();
                        $alreadyRequested = ($requestStatus === 'pending');
                        $alreadyApproved = ($requestStatus === 'approved');
                        
                        // Skip this item if it's already approved - belt and suspenders approach
                        if ($alreadyApproved) continue;
                    ?>
                        <div class="bg-white border <?= $alreadyRequested ? 'border-yellow-400' : 'border-gray-200' ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                            <!-- If already requested, show a badge -->
                            <?php if ($alreadyRequested): ?>
                                <div class="mb-2">
                                    <span class="inline-block bg-yellow-400 text-white text-xs px-2 py-1 rounded-full ml-1">
                                        Pending Approval
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="flex flex-col h-full">
                                <div class="mb-2">
                                    <span class="inline-block bg-primarycol text-white text-xs px-2 py-1 rounded-full">
                                        <?= htmlspecialchars($item['category']) ?>
                                    </span>
                                    <?php if (isset($item['expiry_date'])): 
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        $today = new DateTime();
                                        $daysUntilExpiry = $today->diff($expiryDate)->days;
                                        
                                        $expiryClass = 'bg-green-500';
                                        if ($daysUntilExpiry < 3) {
                                            $expiryClass = 'bg-red-500';
                                        } elseif ($daysUntilExpiry < 7) {
                                            $expiryClass = 'bg-yellow-500';
                                        }
                                    ?>
                                    <span class="inline-block <?= $expiryClass ?> text-white text-xs px-2 py-1 rounded-full ml-1">
                                        Expires in <?= $daysUntilExpiry ?> days
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-lg font-semibold text-primarycol"><?= htmlspecialchars($item['product_name']) ?></h3>
                                <p class="text-sm text-gray-600 mb-2">From: <?= htmlspecialchars($item['branch_name']) ?></p>
                                <p class="text-sm text-gray-600 mb-2">Donor: <?= htmlspecialchars($item['donor_name']) ?></p>
                                
                                <?php if (!empty($item['notes'])): ?>
                                <p class="text-sm text-gray-600 mb-4">
                                    <?= htmlspecialchars(substr($item['notes'], 0, 100)) ?>
                                    <?= strlen($item['notes']) > 100 ? '...' : '' ?>
                                </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['waste_reason'])): ?>
                                <p class="text-sm text-gray-600 mb-4">
                                    Reason: <?= htmlspecialchars(ucfirst($item['waste_reason'])) ?>
                                </p>
                                <?php endif; ?>
                                
                                <div class="mt-auto pt-4 flex justify-between items-center">
                                    <div class="font-bold"><?= htmlspecialchars($item['available_quantity']) ?> items</div>
                                    <button class="btn btn-primary btn-sm request-btn" 
                                            data-id="<?= $item['id'] ?>"
                                            data-name="<?= htmlspecialchars($item['product_name']) ?>"
                                            data-branch="<?= $item['branch_id'] ?>">
                                        Request
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- No donations found message -->
                    <div class="col-span-full flex flex-col items-center justify-center py-8 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 20h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-700">No donations available</h3>
                        <p class="text-gray-500 mt-2">There are currently no food items marked for donation that match your criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Donation Request Modal -->
    <div id="requestModal" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg mb-4">Request Donation</h3>
        <div id="itemDetails" class="p-4 bg-gray-100 rounded-md mb-4">
          <!-- Item details filled by JavaScript -->
        </div>
        
        <form id="requestForm" method="POST" action="">
          <input type="hidden" id="donationId" name="donation_id" value="">
          <input type="hidden" id="productName" name="product_name" value="">
          <input type="hidden" id="branchId" name="branch_id" value="">
          
          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Quantity <span id="maxQuantity" class="text-sm text-gray-500"></span></label>
            <input type="number" name="quantity" required min="20" max="30" value="20"
                   class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol">
            <p class="text-xs text-gray-500 mt-1">Minimum: 20, Maximum: 30 items</p>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Pickup Date</label>
            <input type="date" name="pickup_date" required min="<?= date('Y-m-d') ?>"
                   class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol">
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Pickup Time</label>
            <input type="time" name="pickup_time" required
                   class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol">
            <p class="text-xs text-gray-500 mt-1">Please choose a time during operating hours (8 AM - 5 PM)</p>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Notes</label>
            <textarea name="notes" rows="3" 
                      class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol"
                      placeholder="Any additional requirements..."></textarea>
          </div>
          
          <div class="flex justify-end gap-2 mt-6">
            <button type="button" class="btn bg-gray-300 hover:bg-gray-400 text-gray-800" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn bg-primarycol hover:bg-fourth text-white">Submit Request</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    // Modal functionality
    function showModal(id, productName, quantity, expiryDate, branchName) {
      // Set hidden fields
      document.getElementById('donationId').value = id;
      document.getElementById('productName').value = productName;
      
      // Get branch id from the button that was clicked
      const branchId = document.querySelector(`.request-btn[data-id="${id}"]`).getAttribute('data-branch');
      document.getElementById('branchId').value = branchId;
      
      // Display item details
      document.getElementById('itemDetails').innerHTML = `
        <div class="mb-1"><span class="font-semibold">Product:</span> ${productName}</div>
        <div class="mb-1"><span class="font-semibold">Available:</span> ${quantity} items</div>
        <div class="mb-1"><span class="font-semibold">Expires in:</span> ${expiryDate} days</div>
        <div><span class="font-semibold">Branch:</span> ${branchName}</div>
      `;
      
      // Set max quantity hint
      document.getElementById('maxQuantity').textContent = `(Available: ${quantity}, Request 20-30)`;
      
      // Set max quantity attribute
      const maxQuantity = Math.min(30, parseFloat(quantity));
      const quantityInput = document.querySelector('input[name="quantity"]');
      quantityInput.setAttribute('max', maxQuantity);
      // If available is less than 20, disable the form
      if (parseFloat(quantity) < 20) {
        quantityInput.setAttribute('disabled', true);
        document.querySelector('button[type="submit"]').setAttribute('disabled', true);
        document.querySelector('button[type="submit"]').classList.add('opacity-50');
        // Add a notice
        const notice = document.createElement('p');
        notice.className = 'text-red-500 text-sm mt-1';
        notice.textContent = 'Not enough quantity available to meet minimum request requirement.';
        quantityInput.parentNode.appendChild(notice);
      } else {
        // Set default value to 20 or max available if less than 30
        quantityInput.value = Math.min(20, maxQuantity);
      }
      
      // Show the modal
      document.getElementById('requestModal').classList.add('modal-open');
    }

    function closeModal() {
      document.getElementById('requestModal').classList.remove('modal-open');
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
      const modal = document.getElementById('requestModal');
      if (event.target === modal) {
        closeModal();
      }
    });
    </script>

    <script>
    // Attach event listeners to all request buttons
    document.addEventListener('DOMContentLoaded', function() {
        const requestButtons = document.querySelectorAll('.request-btn');
        
        requestButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                // Find the parent card to extract additional information
                const card = this.closest('.bg-white.border');
                
                // Extract quantity, expiry date and branch name from the card
                const quantityElement = card.querySelector('.font-bold');
                const quantity = quantityElement ? quantityElement.textContent.split(' ')[0] : 'N/A';
                
                // Extract expiry information (if available)
                const expiryElement = card.querySelector('[class*="bg-"][class*="-500"]');
                const expiryDate = expiryElement ? expiryElement.textContent.replace('Expires in ', '').replace(' days', '') : 'N/A';
                
                // Extract branch name
                const branchElement = card.querySelector('p.text-sm.text-gray-600');
                const branchName = branchElement ? branchElement.textContent.replace('From: ', '') : 'Unknown';
                
                // Now show the modal with this information
                showModal(id, name, quantity, expiryDate, branchName);
            });
        });
    });
    </script>
</body>
</html>