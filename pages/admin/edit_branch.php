<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Get branch ID from URL
$branch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize messages
$success_message = '';
$error_message = '';

// Fetch branch details
try {
    $branchStmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $branchStmt->execute([$branch_id]);
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        header("Location: manage_branches.php");
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Error retrieving branch data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_branch'])) {
    // Extract form data
    $branch_name = trim($_POST['branch_name'] ?? '');
    $branch_address = trim($_POST['branch_address'] ?? '');
    $branch_location = trim($_POST['location'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $branch_type = $_POST['branch_type'] ?? 'internal';
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    // Validate input
    if (empty($branch_name) || empty($branch_address)) {
        $error_message = "Branch name and address are required.";
    } else {
        try {
            // Update the branch
            $stmt = $pdo->prepare("
                UPDATE branches 
                SET name = ?, 
                    address = ?, 
                    location = ?, 
                    contact_person = ?, 
                    phone = ?, 
                    branch_type = ?,
                    admin_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $branch_name, 
                $branch_address, 
                $branch_location,
                $contact_person,
                $phone,
                $branch_type,
                $admin_notes,
                $branch_id
            ]);
            
            $success_message = "Branch updated successfully!";
            
            // Fetch updated branch data
            $branchStmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
            $branchStmt->execute([$branch_id]);
            $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
            
            // Create audit log entry
            $audit_stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    user_id, action, details, entity_type, entity_id, created_at
                ) VALUES (
                    ?, 'Branch Updated', ?, 'branch', ?, NOW()
                )
            ");
            
            $audit_stmt->execute([
                $_SESSION['user_id'],
                "Updated branch: $branch_name",
                $branch_id
            ]);
            
        } catch (PDOException $e) {
            $error_message = "Error updating branch: " . $e->getMessage();
        }
    }
}

// Get assigned staff
$staffStmt = $pdo->prepare("
    SELECT id, fname, lname, email
    FROM users
    WHERE branch_id = ? AND role = 'staff'
    ORDER BY fname, lname
");
$staffStmt->execute([$branch_id]);
$assignedStaff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch waste statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT id) as waste_records,
        COALESCE(SUM(waste_quantity), 0) as total_waste_qty,
        COALESCE(SUM(waste_value), 0) as total_waste_value
    FROM product_waste
    WHERE branch_id = ?
");
$statsStmt->execute([$branch_id]);
$wasteStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get top wasted products for this branch
$topWasteStmt = $pdo->prepare("
    SELECT 
        pi.name as product_name,
        pi.category,
        COUNT(pw.id) as record_count,
        SUM(pw.waste_quantity) as total_quantity,
        SUM(pw.waste_value) as total_value
    FROM product_waste pw
    JOIN product_info pi ON pw.product_id = pi.id
    WHERE pw.branch_id = ?
    GROUP BY pw.product_id
    ORDER BY total_quantity DESC
    LIMIT 5
");
$topWasteStmt->execute([$branch_id]);
$topWasteProducts = $topWasteStmt->fetchAll(PDO::FETCH_ASSOC);

// Get waste reason distribution
$reasonStmt = $pdo->prepare("
    SELECT 
        waste_reason,
        COUNT(*) as count,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM product_waste WHERE branch_id = ?)) * 100, 1) as percentage
    FROM product_waste
    WHERE branch_id = ?
    GROUP BY waste_reason
    ORDER BY count DESC
");
$reasonStmt->execute([$branch_id, $branch_id]);
$wasteReasons = $reasonStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Branch - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/LGU.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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
<body class="bg-gray-50 flex h-screen">
    <?php include '../layout/nav.php'; ?>
    
    <div class="py-8 px-6">
        <div class="flex items-center mb-6">
            <a href="manage_branches.php" class="text-primarycol hover:text-fourth mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-primarycol">Edit Branch: <?= htmlspecialchars($branch['name']) ?></h1>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?= htmlspecialchars($success_message) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Edit Branch Form -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold mb-4 text-primarycol">Branch Details</h2>
                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="branch_name" class="block text-sm font-medium text-gray-700 mb-1">Branch Name *</label>
                            <input type="text" id="branch_name" name="branch_name" required value="<?= htmlspecialchars($branch['name']) ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                        </div>
                        
                        <div>
                            <label for="branch_address" class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                            <input type="text" id="branch_address" name="branch_address" required value="<?= htmlspecialchars($branch['address']) ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location Description</label>
                            <input type="text" id="location" name="location" value="<?= htmlspecialchars($branch['location'] ?? '') ?>"
                                   placeholder="e.g. Main Location, Warehouse, etc."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                        </div>
                        
                        <div>
                            <label for="branch_type" class="block text-sm font-medium text-gray-700 mb-1">Branch Type</label>
                            <select id="branch_type" name="branch_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <option value="internal" <?= $branch['branch_type'] === 'internal' ? 'selected' : '' ?>>Internal Branch</option>
                                <option value="company_branch" <?= $branch['branch_type'] === 'company_branch' ? 'selected' : '' ?>>Company Branch</option>
                                <option value="company_main" <?= $branch['branch_type'] === 'company_main' ? 'selected' : '' ?>>Company Main</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($branch['contact_person'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($branch['phone'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-1">Admin Notes</label>
                        <textarea id="admin_notes" name="admin_notes" rows="3"
                                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30"><?= htmlspecialchars($branch['admin_notes'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_branch" 
                            class="bg-primarycol hover:bg-fourth text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                        Update Branch
                    </button>
                </form>
            </div>
            
            <!-- Branch Statistics Sidebar -->
            <div>
                <!-- Branch Stats Summary -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-xl font-bold mb-4 text-primarycol">Waste Statistics</h2>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-md">
                            <div class="text-sm text-gray-500">Waste Records</div>
                            <div class="text-2xl font-bold text-primarycol"><?= number_format($wasteStats['waste_records']) ?></div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-md">
                            <div class="text-sm text-gray-500">Total Waste Quantity</div>
                            <div class="text-2xl font-bold text-primarycol"><?= number_format($wasteStats['total_waste_qty'], 2) ?></div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-md col-span-2">
                            <div class="text-sm text-gray-500">Total Waste Value</div>
                            <div class="text-2xl font-bold text-primarycol">₱<?= number_format($wasteStats['total_waste_value'], 2) ?></div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="branches_product_waste_data.php?branch=<?= $branch_id ?>" class="text-primarycol hover:text-fourth text-sm flex items-center justify-end">
                            View Detailed Waste Data
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Top Wasted Products -->
                <?php if (!empty($topWasteProducts)): ?>
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-xl font-bold mb-4 text-primarycol">Top Wasted Products</h2>
                    
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($topWasteProducts as $product): ?>
                            <li class="py-3">
                                <div class="flex justify-between">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($product['product_name']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($product['category']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold"><?= number_format($product['total_quantity'], 2) ?> units</p>
                                        <p class="text-sm text-gray-500">₱<?= number_format($product['total_value'], 2) ?></p>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Assigned Staff -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold mb-4 text-primarycol">Assigned Staff</h2>
                    
                    <?php if (empty($assignedStaff)): ?>
                        <p class="text-gray-500 italic">No staff assigned to this branch yet.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($assignedStaff as $staff): ?>
                                <li class="py-3">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-medium"><?= htmlspecialchars($staff['fname'] . ' ' . $staff['lname']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($staff['email']) ?></p>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="manage_branches.php" class="text-primarycol hover:text-fourth text-sm flex items-center justify-end">
                            Manage Staff Assignments
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Waste Reason Distribution Chart -->
        <?php if (!empty($wasteReasons)): ?>
        <div class="mt-6 bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4 text-primarycol">Waste Reason Distribution</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distribution</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($wasteReasons as $reason): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $reason['waste_reason']))) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $reason['count'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $reason['percentage'] ?>%</td>
                                <td class="px-6 py-4">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-primarycol h-2.5 rounded-full" style="width: <?= $reason['percentage'] ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>