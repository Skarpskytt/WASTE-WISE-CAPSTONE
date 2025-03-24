<?php
// Start output buffering to ensure headers can be sent
ob_start();

require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../vendor/autoload.php';

$pdo = getPDO();

// Try to load environment variables with better path handling
try {
    $rootPath = dirname(dirname(__DIR__)); // Get absolute path to project root
    $dotenv = Dotenv\Dotenv::createImmutable($rootPath);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Silent fail - continue without .env if it can't be found
    // You could add error logging here if needed
}

use App\Mail\EmailService;

// Check for admin access only
checkAuth(['admin']);

// Handle NGO approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_ngo'])) {
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['approve_ngo'];
        
        // Update user active status
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'ngo'");
        $stmt->execute([$userId]);
        
        // Update NGO profile status
        $stmt = $pdo->prepare("UPDATE ngo_profiles SET status = 'approved' WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Get NGO details for notification
        $stmt = $pdo->prepare("
            SELECT u.fname, u.lname, u.email, np.organization_name 
            FROM users u 
            JOIN ngo_profiles np ON u.id = np.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $ngoData = $stmt->fetch();
        
        // Get admin user ID (assuming there's at least one admin)
        $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminStmt->execute();
        $adminId = $adminStmt->fetchColumn();
        
        // Notification for admin
        $adminNotification = "NGO " . $ngoData['organization_name'] . " has been approved";
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, is_read, created_at) 
            VALUES (?, ?, 0, NOW())
        ");
        $notifStmt->execute([$adminId, $adminNotification]);
        
        // Notification for NGO
        $ngoNotification = "Your NGO account has been approved. You can now log in.";
        $notifStmt->execute([$userId, $ngoNotification]);
        
        // Send email notification
        $emailService = new EmailService();
        $emailData = [
            'name' => $ngoData['fname'] . ' ' . $ngoData['lname'],
            'email' => $ngoData['email'],
            'organization_name' => $ngoData['organization_name']
        ];
        
        if (!$emailService->sendNGOApprovalEmail($emailData)) {
            throw new Exception("Failed to send approval email");
        }
        
        $pdo->commit();
        $_SESSION['success'] = "NGO account approved successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error approving NGO: " . $e->getMessage();
    }
    
    header("Location: user.php");
    exit();
}

// Add this after your NGO approval handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_ngo'])) {
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['reject_ngo'];
        
        // Get NGO details for notification
        $stmt = $pdo->prepare("
            SELECT u.fname, u.lname, u.email, np.organization_name 
            FROM users u 
            JOIN ngo_profiles np ON u.id = np.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $ngoData = $stmt->fetch();
        
        if (!$ngoData) {
            throw new Exception("NGO not found");
        }
        
        // Update NGO profile status
        $stmt = $pdo->prepare("UPDATE ngo_profiles SET status = 'rejected' WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Create notification for NGO user
        $ngoNotification = "Your NGO partnership application has been rejected.";
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, is_read, created_at) 
            VALUES (?, ?, 0, NOW())
        ");
        $notifStmt->execute([$userId, $ngoNotification]);
        
        // Send rejection email
        $emailService = new EmailService();
        $emailData = [
            'name' => $ngoData['fname'] . ' ' . $ngoData['lname'],
            'email' => $ngoData['email'],
            'organization_name' => $ngoData['organization_name']
        ];
        
        if (!$emailService->sendNGORejectionEmail($emailData)) {
            throw new Exception("Failed to send rejection email");
        }
        
        $pdo->commit();
        $_SESSION['success'] = "NGO account rejected successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error rejecting NGO: " . $e->getMessage();
    }
    
    header("Location: user.php");
    exit();
}

// Add after the NGO rejection handler (around line 142)

// Handle Staff Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_staff'])) {
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['approve_staff'];
        
        // Get staff details for notification
        $stmt = $pdo->prepare("
            SELECT u.fname, u.lname, u.email, u.role 
            FROM users u 
            JOIN staff_profiles sp ON u.id = sp.user_id
            WHERE u.id = ? AND (u.role = 'branch1_staff' OR u.role = 'branch2_staff')
        ");
        $stmt->execute([$userId]);
        $staffData = $stmt->fetch();
        
        if (!$staffData) {
            throw new Exception("Staff user not found");
        }
        
        // Update user active status
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Update staff profile status
        $stmt = $pdo->prepare("UPDATE staff_profiles SET status = 'approved' WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Create notification for staff user
        $staffNotification = "Your staff account has been approved. You can now log in.";
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, is_read, created_at) 
            VALUES (?, ?, 0, NOW())
        ");
        $notifStmt->execute([$userId, $staffNotification]);
        
        // Send email notification
        $emailService = new EmailService();
        $emailData = [
            'name' => $staffData['fname'] . ' ' . $staffData['lname'],
            'email' => $staffData['email'],
            'role' => $staffData['role']
        ];
        
        $emailService->sendStaffApprovalEmail($emailData);
        
        $pdo->commit();
        $_SESSION['success'] = "Staff account approved successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error approving staff: " . $e->getMessage();
    }
    
    header("Location: user.php");
    exit();
}

// Handle Staff Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_staff'])) {
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['reject_staff'];
        
        // Get staff details for notification
        $stmt = $pdo->prepare("
            SELECT u.fname, u.lname, u.email, u.role 
            FROM users u 
            JOIN staff_profiles sp ON u.id = sp.user_id
            WHERE u.id = ? AND (u.role = 'branch1_staff' OR u.role = 'branch2_staff')
        ");
        $stmt->execute([$userId]);
        $staffData = $stmt->fetch();
        
        if (!$staffData) {
            throw new Exception("Staff user not found");
        }
        
        // Update staff profile status
        $stmt = $pdo->prepare("UPDATE staff_profiles SET status = 'rejected' WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Create notification for staff user
        $staffNotification = "Your staff account application has been rejected.";
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, is_read, created_at) 
            VALUES (?, ?, 0, NOW())
        ");
        $notifStmt->execute([$userId, $staffNotification]);
        
        // Send rejection email
        $emailService = new EmailService();
        $emailData = [
            'name' => $staffData['fname'] . ' ' . $staffData['lname'],
            'email' => $staffData['email'],
            'role' => $staffData['role']
        ];
        
        $emailService->sendStaffRejectionEmail($emailData);
        
        $pdo->commit();
        $_SESSION['success'] = "Staff account rejected successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error rejecting staff: " . $e->getMessage();
    }
    
    header("Location: user.php");
    exit();
}

// Handle user update
if (isset($_POST['update_user'])) {
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['edit_id'];
        $fname = trim($_POST['edit_fname']);
        $lname = trim($_POST['edit_lname']);
        $email = trim($_POST['edit_email']);
        $role = trim($_POST['edit_role']);
        
        // Validate inputs
        $errors = [];
        if (empty($fname)) $errors[] = "First name is required";
        if (empty($lname)) $errors[] = "Last name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        
        // Check if email exists for other users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already exists";
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET fname = ?, lname = ?, email = ?, role = ?
                WHERE id = ?
            ");
            $stmt->execute([$fname, $lname, $email, $role, $userId]);
            
            // Create notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, is_read, created_at) 
                VALUES (?, ?, 0, NOW())
            ");
            $notifStmt->execute([
                $userId, 
                "Your account information has been updated."
            ]);
            
            $pdo->commit();
            $_SESSION['success'] = "User updated successfully!";
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating user: " . $e->getMessage();
    }
    
    header("Location: user.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id']) && !isset($_POST['approve_ngo']) && !isset($_POST['reject_ngo'])) {
    try {
        $pdo->beginTransaction();
        
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $role = trim($_POST['role']);
        $password = $_POST['password'];
        $conpassword = $_POST['conpassword'];
        $errors = [];

        // Validate inputs
        if (empty($fname)) $errors[] = "First name is required";
        if (empty($lname)) $errors[] = "Last name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        if (empty($role)) $errors[] = "Role is required";
        if (empty($password)) $errors[] = "Password is required";
        if ($password !== $conpassword) $errors[] = "Passwords do not match";

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already exists";
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Determine branch_id based on role if it's a staff
            $branch_id = null;
            if ($role === 'branch1_staff') {
                $branch_id = 1;
            } elseif ($role === 'branch2_staff') {
                $branch_id = 2;
            }
            
            // Set is_active based on role (only NGOs need approval when created by admin)
            $is_active = $role === 'ngo' ? 0 : 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO users (fname, lname, email, role, password, branch_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fname, $lname, $email, $role, $hashedPassword, $branch_id, $is_active]);
            $user_id = $pdo->lastInsertId();
            
            // Handle NGO profile creation if it's an NGO account
            if ($role === 'ngo') {
                $org_name = trim($_POST['org_name']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address']);
                
                $stmt = $pdo->prepare("
                    INSERT INTO ngo_profiles (user_id, organization_name, phone, address, status) 
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$user_id, $org_name, $phone, $address]);
            }
            
            // Create staff profile entry for staff users with approved status
            if ($role === 'branch1_staff' || $role === 'branch2_staff') {
                $stmt = $pdo->prepare('INSERT INTO staff_profiles (user_id, status) VALUES (?, ?)');
                $stmt->execute([$user_id, 'approved']);
            }
            
            // Create notification
            $notification = "New user {$fname} {$lname} ({$role}) has been created by admin.";
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (message, created_at) 
                VALUES (?, NOW())
            ");
            $notifStmt->execute([$notification]);
            
            $pdo->commit();
            $_SESSION['success'] = "User created successfully!";
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error creating user: " . $e->getMessage();
    }
    
    header("Location: user.php");
    exit();
}

// Fetch all users
$userQuery = "
    SELECT 
        u.id, 
        u.fname, 
        u.lname, 
        u.email, 
        u.role, 
        u.is_active, 
        u.created_at,
        np.status as ngo_status,
        sp.status as staff_status
    FROM 
        users u
    LEFT JOIN ngo_profiles np ON u.id = np.user_id
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    ORDER BY u.created_at DESC
";
$userStmt = $pdo->prepare($userQuery);
$userStmt->execute();
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management</title>
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
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('-translate-x-full');
    });

     $('#closeSidebar').on('click', function() {
        $('#sidebar').addClass('-translate-x-full');
    });
});
 </script>
   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-screen">

<?php include '../layout/nav.php'?>

<div class="flex-1 p-6 overflow-auto">
    <h1 class="text-3xl font-bold mb-6 text-primarycol">User Management</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <?= $_SESSION['error'] ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Add this button -->
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-xl font-semibold text-gray-700">User Accounts</h2>
        <button onclick="document.getElementById('create_modal').showModal()" 
                class="bg-primarycol hover:bg-primarycol/90 text-white font-bold py-2 px-4 rounded flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Create New User
        </button>
    </div>

    <!-- User List Section -->
    
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">#</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Name</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Email</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Role</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Created At</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-center text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-100">
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($user['id']) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($user['email']) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                        <?php
                                        switch($user['role']) {
                                            case 'admin':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'ngo':
                                                echo $user['ngo_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                        <?php if ($user['role'] === 'ngo'): ?>
                                            (<?= ucfirst(htmlspecialchars($user['ngo_status'])) ?>)
                                        <?php endif; ?>
                                        <?php if (($user['role'] === 'branch1_staff' || $user['role'] === 'branch2_staff') && isset($user['staff_status']) && $user['staff_status'] === 'pending'): ?>
                                            (<?= ucfirst(htmlspecialchars($user['staff_status'])) ?>)
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= date('M j, Y g:i A', strtotime($user['created_at'])) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm">
    <div class='flex justify-center space-x-2'>
        <?php if ($user['role'] === 'ngo' && $user['ngo_status'] === 'pending'): ?>
            <form method="POST" class="inline">
                <input type="hidden" name="approve_ngo" value="<?= $user['id'] ?>">
                <button type="submit" 
                        class="rounded-md hover:bg-blue-100 text-blue-600 p-2 flex items-center mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" 
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M5 13l4 4L19 7"/>
                    </svg>
                    Approve NGO
                </button>
            </form>
            <form method="POST" class="inline">
                <input type="hidden" name="reject_ngo" value="<?= $user['id'] ?>">
                <button type="submit" 
                        class="rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" 
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reject NGO
                </button>
            </form>
        <?php endif; ?>
        <?php if (($user['role'] === 'branch1_staff' || $user['role'] === 'branch2_staff') && isset($user['staff_status']) && $user['staff_status'] === 'pending'): ?>
    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
        Pending Approval
    </span>
    
    <form method="POST" class="mt-2">
        <input type="hidden" name="approve_staff" value="<?= $user['id'] ?>">
        <button type="submit" class="text-xs bg-green-100 hover:bg-green-200 text-green-800 py-1 px-2 rounded">
            Approve
        </button>
    </form>
    
    <form method="POST" class="mt-1">
        <input type="hidden" name="reject_staff" value="<?= $user['id'] ?>">
        <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-800 py-1 px-2 rounded">
            Reject
        </button>
    </form>
<?php endif; ?>
        <a href="#" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" 
           class='rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center'>
            <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                      d='M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13' />
            </svg>
            Edit
        </a>
    </div>
</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-4 px-6 text-center text-gray-500">
                                No users found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add pagination if needed -->
        <?php if (isset($totalPages) && $totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <div class="btn-group">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="btn <?= ($i == ($page ?? 1)) ? 'btn-active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add modals for edit/delete -->
<dialog id="edit_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Edit User</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" id="edit_id" name="edit_id">
            <div class="form-control">
                <label class="label">First Name</label>
                <input type="text" id="edit_fname" name="edit_fname" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label">Last Name</label>
                <input type="text" id="edit_lname" name="edit_lname" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label">Email</label>
                <input type="email" id="edit_email" name="edit_email" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label">Role</label>
                <select id="edit_role" name="edit_role" class="select select-bordered" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="modal-action">
                <button type="submit" name="update_user" class="btn btn-primary">Update</button>
                <button type="button" onclick="closeEditModal()" class="btn">Cancel</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Create User Modal -->
<dialog id="create_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Create New User</h3>
        <form method="POST" class="space-y-4 mt-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="form-control">
                    <label class="label">First Name</label>
                    <input type="text" name="fname" class="input input-bordered" required>
                </div>
                <div class="form-control">
                    <label class="label">Last Name</label>
                    <input type="text" name="lname" class="input input-bordered" required>
                </div>
            </div>
            
            <div class="form-control">
                <label class="label">Email</label>
                <input type="email" name="email" class="input input-bordered" required>
            </div>

            <div class="form-control">
                <label class="label">Role</label>
                <select name="role" class="select select-bordered" required>
                    <option value="">Select a role</option>
                    <option value="branch1_staff">Branch 1 Staff</option>
                    <option value="branch2_staff">Branch 2 Staff</option>
                    <option value="admin">Admin</option>
                    <option value="ngo">NGO Partner</option>
                </select>
            </div>

            <!-- NGO-specific fields (hidden by default) -->
            <div id="ngo-fields" class="hidden space-y-4">
                <div class="form-control">
                    <label class="label">Organization Name</label>
                    <input type="text" id="org_name" name="org_name" class="input input-bordered">
                </div>
                <div class="form-control">
                    <label class="label">Contact Phone</label>
                    <input type="tel" id="phone" name="phone" class="input input-bordered">
                </div>
                <div class="form-control">
                    <label class="label">Organization Address</label>
                    <textarea id="address" name="address" rows="3" class="textarea textarea-bordered"></textarea>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-control">
                    <label class="label">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="input input-bordered w-full" required>
                        <button type="button" onclick="togglePasswordVisibility('password')" 
                                class="absolute right-2 top-2.5 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-control">
                    <label class="label">Confirm Password</label>
                    <div class="relative">
                        <input type="password" id="conpassword" name="conpassword" class="input input-bordered w-full" required>
                        <button type="button" onclick="togglePasswordVisibility('conpassword')" 
                                class="absolute right-2 top-2.5 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-action">
                <button type="submit" class="btn bg-primarycol hover:bg-primarycol/90 text-white">Create User</button>
                <button type="button" onclick="document.getElementById('create_modal').close()" class="btn">Cancel</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Add these scripts -->
<script>
// Toggle password visibility
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Add event listener for role selection to show/hide NGO fields
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    const ngoFields = document.getElementById('ngo-fields');
    
    if (roleSelect && ngoFields) {
        roleSelect.addEventListener('change', function() {
            if (this.value === 'ngo') {
                ngoFields.classList.remove('hidden');
                document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = true);
            } else {
                ngoFields.classList.add('hidden');
                document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = false);
            }
        });
    }
});
</script>

<script>
function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_fname').value = user.fname;
    document.getElementById('edit_lname').value = user.lname;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    
    // If using DaisyUI modal
    document.getElementById('edit_modal').showModal();
}

function closeEditModal() {
    document.getElementById('edit_modal').close();
}
</script>

</body>
</html>
<?php
// Flush output buffer at the end
ob_end_flush();
?>