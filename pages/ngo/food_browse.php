<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for NGO access only
checkAuth(['ngo']);

// Add this near the top of your file, right after checkAuth(['ngo']);
$ngoId = $_SESSION['user_id']; // Make sure $ngoId is defined

// Initialize messages
$successMessage = null;
$errorMessage = null;

// Process donation request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donation_id'])) {
    // Get form data
    $donationId = isset($_POST['donation_id']) ? (int)$_POST['donation_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $pickupDate = isset($_POST['pickup_date']) ? $_POST['pickup_date'] : '';
    $pickupTime = isset($_POST['pickup_time']) ? $_POST['pickup_time'] : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Get NGO information
    $ngoId = $_SESSION['user_id'];
    
    // Validate input
    $errors = [];
    if ($donationId <= 0) {
        $errors[] = "Invalid donation selection.";
    }
    if ($quantity <= 0) {
        $errors[] = "Please enter a valid quantity.";
    }
    if (empty($pickupDate) || empty($pickupTime)) {
        $errors[] = "Please specify pickup date and time.";
    }
    
    // Check if the donation exists and has enough quantity
    try {
        $checkStmt = $pdo->prepare("
            SELECT pw.*, p.name as product_name, b.name as branch_name
            FROM product_waste pw
            JOIN products p ON pw.product_id = p.id
            JOIN branches b ON pw.branch_id = b.id
            WHERE pw.id = ? AND pw.disposal_method = 'donation'
        ");
        $checkStmt->execute([$donationId]);
        $donation = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donation) {
            $errors[] = "The requested donation is no longer available.";
        } else if ($donation['waste_quantity'] < $quantity) {
            $errors[] = "Requested quantity exceeds available amount.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    // If no errors, process the request
    if (empty($errors)) {
        try {
            // Start a transaction
            $pdo->beginTransaction();
            
            // Create donation request record
            $requestStmt = $pdo->prepare("
                INSERT INTO donation_requests (
                    product_waste_id, ngo_id, quantity_requested, 
                    pickup_date, pickup_time, notes, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $requestStmt->execute([
                $donationId,
                $ngoId,
                $quantity,
                $pickupDate,
                $pickupTime,
                $notes
            ]);
            
            // Update product_waste if entire quantity is requested
            if ($quantity == $donation['waste_quantity']) {
                $updateStmt = $pdo->prepare("
                    UPDATE product_waste
                    SET is_claimed = 1
                    WHERE id = ?
                ");
                $updateStmt->execute([$donationId]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $successMessage = "Your donation request has been submitted successfully! The donor will contact you soon.";
            
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

// Build query to get donations - FIXED: Changed p.expiry_date to pw.donation_expiry_date
$sql = "SELECT pw.*, p.name as product_name, p.category, pw.donation_expiry_date as expiry_date,
        CONCAT(u.fname, ' ', u.lname) as donor_name, b.name as branch_name
        FROM product_waste pw
        JOIN products p ON pw.product_id = p.id
        JOIN users u ON pw.user_id = u.id
        JOIN branches b ON pw.branch_id = b.id
        WHERE pw.disposal_method = 'donation' 
        AND pw.is_claimed = 0"; /* Make sure we only show unclaimed items */

$params = [];

// Apply filters
if (!empty($typeFilter) && $typeFilter !== 'All') {
    $sql .= " AND p.category = ?";
    $params[] = $typeFilter;
}

if ($quantityFilter > 0) {
    $sql .= " AND pw.waste_quantity >= ?";
    $params[] = $quantityFilter;
}

// FIXED: Changed p.expiry_date to pw.donation_expiry_date
if (!empty($dateFilter)) {
    $sql .= " AND pw.donation_expiry_date >= ?";
    $params[] = $dateFilter;
}

if (!empty($donorFilter)) {
    $sql .= " AND (u.fname LIKE ? OR u.lname LIKE ?)";
    $params[] = "%$donorFilter%";
    $params[] = "%$donorFilter%";
}

// FIXED: Changed p.expiry_date to pw.donation_expiry_date in ORDER BY
$sql .= " ORDER BY pw.donation_expiry_date ASC, pw.created_at DESC";

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

// Get NGO name for greeting
$ngoQuery = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as full_name, organization_name FROM users WHERE id = ?");
$ngoQuery->execute([$ngoId]);
$ngoInfo = $ngoQuery->fetch(PDO::FETCH_ASSOC);

// After loading donations, add this code to check which ones the NGO has already requested

// Get IDs of donations this NGO has already requested
$requestedQuery = $pdo->prepare("
    SELECT product_waste_id 
    FROM donation_requests 
    WHERE ngo_id = ?
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
                    <?php foreach ($donations as $item): ?>
                        <?php $alreadyRequested = in_array($item['id'], $requestedDonations); ?>
                        <div class="bg-white border <?= $alreadyRequested ? 'border-yellow-400' : 'border-gray-200' ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                            <!-- If already requested, show a badge -->
                            <?php if ($alreadyRequested): ?>
                                <div class="mb-2">
                                    <span class="inline-block bg-yellow-400 text-white text-xs px-2 py-1 rounded-full ml-1">
                                        Already Requested
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
                                    <div class="font-bold"><?= htmlspecialchars($item['waste_quantity']) ?> items</div>
                                    <a href="javascript:void(0)" 
                                       class="bg-primarycol hover:bg-fourth text-white py-1 px-3 rounded-md text-sm"
                                       onclick="showModal('<?= $item['id'] ?>', '<?= htmlspecialchars($item['product_name']) ?>', '<?= htmlspecialchars($item['waste_quantity']) ?>', '<?= htmlspecialchars($item['expiry_date']) ?>', '<?= htmlspecialchars($item['branch_name']) ?>')">
                                        Request Item
                                    </a>
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
        <h3 class="font-bold text-lg text-primarycol mb-4">Request Donation</h3>
        
        <form id="requestForm" method="POST" action="food_browse.php">
          <input type="hidden" id="donationId" name="donation_id" value="">
          <input type="hidden" id="productName" name="product_name" value="">
          
          <div class="mb-4">
            <div class="flex items-center gap-2 mb-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primarycol" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
              <span class="font-semibold text-primarycol">Item Details</span>
            </div>
            <div id="itemDetails" class="px-4 py-3 bg-sec rounded-md mb-4">
              <!-- Item details will be filled by JavaScript -->
            </div>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Quantity Needed <span class="text-red-500">*</span></label>
            <div class="flex items-center">
              <input type="number" name="quantity" required min="1" 
                     class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol">
              <span id="maxQuantity" class="ml-2 text-sm text-gray-500"></span>
            </div>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Preferred Pickup Date & Time <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-2">
              <input type="date" name="pickup_date" required min="<?= date('Y-m-d') ?>"
                     class="border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol">
              <input type="time" name="pickup_time" required
                     class="border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol">
            </div>
            <p class="text-xs text-gray-500 mt-1">Please choose a date and time during operating hours (8 AM - 5 PM)</p>
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-1">Remarks/Additional Notes</label>
            <textarea name="notes" rows="3" 
                      class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primarycol"
                      placeholder="Any dietary restrictions, specific handling instructions, or other requirements..."></textarea>
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
      
      // Display item details
      document.getElementById('itemDetails').innerHTML = `
        <div class="mb-1"><span class="font-semibold">Product:</span> ${productName}</div>
        <div class="mb-1"><span class="font-semibold">Available:</span> ${quantity} items</div>
        <div class="mb-1"><span class="font-semibold">Expires on:</span> ${expiryDate}</div>
        <div><span class="font-semibold">Branch:</span> ${branchName}</div>
      `;
      
      // Set max quantity hint
      document.getElementById('maxQuantity').textContent = `(Max: ${quantity})`;
      
      // Set max quantity attribute
      document.querySelector('input[name="quantity"]').setAttribute('max', quantity);
      
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
</body>
</html>