<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Handling form submission for new branch creation
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_branch'])) {
        // Extract form data
        $branch_name = trim($_POST['branch_name'] ?? '');
        $branch_address = trim($_POST['branch_address'] ?? '');
        $branch_location = trim($_POST['location'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $branch_type = $_POST['branch_type'] ?? 'internal';
        
        // Validate input
        if (empty($branch_name) || empty($branch_address)) {
            $error_message = "Branch name and address are required.";
        } else {
            try {
                // Insert the new branch
                $stmt = $pdo->prepare("
                    INSERT INTO branches (
                        name, address, location, contact_person, 
                        phone, branch_type, approval_status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 'approved'
                    )
                ");
                
                $stmt->execute([
                    $branch_name, 
                    $branch_address, 
                    $branch_location,
                    $contact_person,
                    $phone,
                    $branch_type
                ]);
                
                $new_branch_id = $pdo->lastInsertId();
                $success_message = "Branch created successfully! Branch ID: " . $new_branch_id;
                
                // Create audit log entry
                $audit_stmt = $pdo->prepare("
                    INSERT INTO audit_logs (
                        user_id, action, details, entity_type, entity_id, created_at
                    ) VALUES (
                        ?, 'Branch Created', ?, 'branch', ?, NOW()
                    )
                ");
                
                $audit_stmt->execute([
                    $_SESSION['user_id'],
                    "Created new branch: $branch_name",
                    $new_branch_id
                ]);
                
            } catch (PDOException $e) {
                $error_message = "Error creating branch: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['assign_staff'])) {
        // Handle staff assignment
        $staff_id = $_POST['staff_id'] ?? 0;
        $branch_id = $_POST['branch_id'] ?? 0;
        
        if ($staff_id && $branch_id) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET branch_id = ? WHERE id = ? AND role = 'staff'");
                $stmt->execute([$branch_id, $staff_id]);
                
                $success_message = "Staff successfully assigned to branch.";
            } catch (PDOException $e) {
                $error_message = "Error assigning staff: " . $e->getMessage();
            }
        } else {
            $error_message = "Invalid staff or branch selection.";
        }
    }
}

// Fetch all branches for display
$branchQuery = $pdo->query("
    SELECT b.*, 
           COUNT(DISTINCT u.id) as staff_count,
           COUNT(DISTINCT pw.id) as waste_records,
           COALESCE(SUM(pw.waste_quantity), 0) as total_waste_qty,
           COALESCE(SUM(pw.waste_value), 0) as total_waste_value
    FROM branches b
    LEFT JOIN users u ON b.id = u.branch_id AND u.role = 'staff'
    LEFT JOIN product_waste pw ON b.id = pw.branch_id
    GROUP BY b.id
    ORDER BY b.name
");
$branches = $branchQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch unassigned staff
$staffQuery = $pdo->query("
    SELECT id, fname, lname, email, branch_id 
    FROM users 
    WHERE role = 'staff'
    ORDER BY fname, lname
");
$staff = $staffQuery->fetchAll(PDO::FETCH_ASSOC);

// Group staff by branch for easier display
$staffByBranch = [];
$unassignedStaff = [];

foreach ($staff as $member) {
    if (!empty($member['branch_id'])) {
        if (!isset($staffByBranch[$member['branch_id']])) {
            $staffByBranch[$member['branch_id']] = [];
        }
        $staffByBranch[$member['branch_id']][] = $member;
    } else {
        $unassignedStaff[] = $member;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Branches - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
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
        <h1 class="text-3xl font-bold mb-6 text-primarycol">Branch Management</h1>
        
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
        
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Create Branch Form -->
            <div class="lg:w-1/3 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold mb-4 text-primarycol">Create New Branch</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="branch_name" class="block text-sm font-medium text-gray-700 mb-1">Branch Name *</label>
                        <input type="text" id="branch_name" name="branch_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                    </div>
                    
                    <div class="mb-4">
                        <label for="branch_address" class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                        <input type="text" id="branch_address" name="branch_address" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                    </div>
                    
                    <div class="mb-4">
                        <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location Description</label>
                        <input type="text" id="location" name="location" placeholder="e.g. Main Location, Warehouse, etc."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                    </div>
                    
                    <div class="mb-4">
                        <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                    </div>
                    
                    <div class="mb-4">
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" id="phone" name="phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                    </div>
                    
                    <div class="mb-6">
                        <label for="branch_type" class="block text-sm font-medium text-gray-700 mb-1">Branch Type</label>
                        <select id="branch_type" name="branch_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                            <option value="internal">Internal Branch</option>
                            <option value="company_branch">Company Branch</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="create_branch" 
                            class="w-full bg-primarycol hover:bg-fourth text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                        Create Branch
                    </button>
                </form>
            </div>
            
            <!-- Assign Staff to Branch -->
            <div class="lg:w-1/3 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold mb-4 text-primarycol">Assign Staff to Branch</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Select Staff</label>
                        <select id="staff_id" name="staff_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                            <option value="">-- Select Staff --</option>
                            <?php foreach ($staff as $member): ?>
                                <option value="<?= $member['id'] ?>" <?= $member['branch_id'] ? 'data-current="'.$member['branch_id'].'"' : '' ?>>
                                    <?= htmlspecialchars($member['fname'] . ' ' . $member['lname']) ?> 
                                    <?= $member['branch_id'] ? ' (Currently assigned)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Select Branch</label>
                        <select id="branch_id" name="branch_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="assign_staff" 
                            class="w-full bg-primarycol hover:bg-fourth text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                        Assign Staff to Branch
                    </button>
                </form>
                
                <?php if (!empty($unassignedStaff)): ?>
                <div class="mt-6 bg-amber-50 p-4 rounded-md border border-amber-200">
                    <h3 class="font-semibold text-amber-800 mb-2">Unassigned Staff Members</h3>
                    <ul class="list-disc list-inside text-sm text-amber-700">
                        <?php foreach ($unassignedStaff as $member): ?>
                            <li><?= htmlspecialchars($member['fname'] . ' ' . $member['lname']) ?> (<?= htmlspecialchars($member['email']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Branch Stats Summary -->
            <div class="lg:w-1/3 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold mb-4 text-primarycol">Branch Waste Statistics</h2>
                <div class="overflow-y-auto max-h-[380px]">
                    <?php if (empty($branches)): ?>
                        <p class="text-gray-500 italic text-center py-4">No branches found</p>
                    <?php else: ?>
                        <?php foreach ($branches as $branch): ?>
                            <div class="mb-4 p-4 border border-gray-200 rounded-md hover:bg-gray-50">
                                <h3 class="font-semibold mb-2"><?= htmlspecialchars($branch['name']) ?></h3>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="text-gray-600">Staff Members:</div>
                                    <div class="font-medium"><?= $branch['staff_count'] ?></div>
                                    
                                    <div class="text-gray-600">Waste Records:</div>
                                    <div class="font-medium"><?= $branch['waste_records'] ?></div>
                                    
                                    <div class="text-gray-600">Total Waste Qty:</div>
                                    <div class="font-medium"><?= number_format($branch['total_waste_qty'], 2) ?> units</div>
                                    
                                    <div class="text-gray-600">Total Waste Value:</div>
                                    <div class="font-medium">₱<?= number_format($branch['total_waste_value'], 2) ?></div>
                                </div>
                                <div class="mt-2 flex justify-end">
                                    <a href="branches_product_waste_data.php?branch=<?= $branch['id'] ?>" 
                                       class="text-primarycol hover:text-fourth text-sm">
                                        View Details →
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Branch List Table -->
        <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4 text-primarycol">All Branches</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Records</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($branches as $branch): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $branch['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($branch['name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($branch['address']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($branch['branch_type'] === 'internal'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Internal
                                        </span>
                                    <?php elseif ($branch['branch_type'] === 'company_main'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Company Main
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                            Company Branch
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $branch['staff_count'] ?>
                                    <?php if (isset($staffByBranch[$branch['id']]) && count($staffByBranch[$branch['id']]) > 0): ?>
                                        <span class="relative cursor-pointer group">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block ml-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div class="absolute left-0 top-full mt-2 w-48 bg-white shadow-lg rounded-md p-2 text-xs z-10 hidden group-hover:block">
                                                <div class="font-bold mb-1">Assigned Staff:</div>
                                                <ul class="list-disc list-inside">
                                                    <?php foreach ($staffByBranch[$branch['id']] as $member): ?>
                                                        <li><?= htmlspecialchars($member['fname'] . ' ' . $member['lname']) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $branch['waste_records'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="branches_product_waste_data.php?branch=<?= $branch['id'] ?>" class="text-primarycol hover:text-fourth">
                                        View Waste
                                    </a>
                                    <span class="text-gray-300 mx-1">|</span>
                                    <a href="edit_branch.php?id=<?= $branch['id'] ?>" class="text-primarycol hover:text-fourth">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // When staff selection changes, update visual indication of current assignment
            const staffSelect = document.getElementById('staff_id');
            staffSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const currentBranch = selectedOption.getAttribute('data-current');
                
                if (currentBranch) {
                    const branchSelect = document.getElementById('branch_id');
                    for (let i = 0; i < branchSelect.options.length; i++) {
                        if (branchSelect.options[i].value === currentBranch) {
                            branchSelect.options[i].innerHTML += ' (Current)';
                            break;
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>