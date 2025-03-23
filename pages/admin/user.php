<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable('../../');
$dotenv->load();

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (fname, lname, email, role, password) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$fname, $lname, $email, $role, $hashedPassword]);
            $success = "User created successfully!";
            
            // Create notification
            $notification = "New user {$fname} {$lname} ({$role}) has been created.";
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (message, created_at) 
                VALUES (?, NOW())
            ");
            $notifStmt->execute([$notification]);
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch all users
$userQuery = "
    SELECT u.id, u.fname, u.lname, u.email, u.role, u.created_at, u.is_active,
           np.status as ngo_status, np.organization_name 
    FROM users u 
    LEFT JOIN ngo_profiles np ON u.id = np.user_id 
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