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
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && !isset($_POST['edit_id']) 
    && !isset($_POST['approve_ngo']) 
    && !isset($_POST['reject_ngo'])
    && !isset($_POST['archive_user'])  // Add this line to exclude archive submissions
    && !isset($_POST['approve_staff'])  // Also exclude staff approval
    && !isset($_POST['reject_staff'])) {  // And staff rejection
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

// Add after existing handler code, before fetching users

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Base query for counting total records
$countQuery = "
    SELECT COUNT(u.id)
    FROM users u
    LEFT JOIN ngo_profiles np ON u.id = np.user_id
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE 1=1
";

// Base query for fetching users
$userQuery = "
    SELECT 
        u.id, u.fname, u.lname, u.email, u.role, u.is_active, u.created_at,
        u.gov_id_path, u.selfie_path, 
        np.status as ngo_status, np.organization_name,
        sp.status as staff_status
    FROM users u
    LEFT JOIN ngo_profiles np ON u.id = np.user_id
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE 1=1
";

// Add search conditions
$params = [];
if (!empty($search)) {
    $searchCondition = " AND (u.fname LIKE ? OR u.lname LIKE ? OR u.email LIKE ? OR u.role LIKE ?)";
    $countQuery .= $searchCondition;
    $userQuery .= $searchCondition;
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Handle archive action
if (isset($_POST['archive_user'])) {
    try {
        $userId = $_POST['archive_user'];
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        $_SESSION['success'] = "User archived successfully.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error archiving user: " . $e->getMessage();
    }
    header("Location: user.php");
    exit();
}

// Count total records for pagination
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total_users = $countStmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Add pagination to user query
$userQuery .= " ORDER BY u.created_at DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

// Execute the query
$userStmt = $pdo->prepare($userQuery);
$userStmt->execute($params);
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
<div>
  <!-- Search and filters row -->
  <div class="mb-6">
    <form method="GET" class="flex gap-2">
      <div class="flex-1">
        <div class="relative">
          <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>"
                 class="w-full input input-bordered rounded-md pl-10 pr-4">
          <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
        </div>
      </div>
      <button type="submit" class="btn bg-primarycol text-white hover:bg-primarycol/90">Search</button>
      <?php if (!empty($search)): ?>
        <a href="user.php" class="btn btn-outline">Reset</a>
      <?php endif; ?>
    </form>
  </div>
  
  <!-- Improved table container styling -->
  <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200 overflow-x-auto">
    <table class="min-w-full">
      <thead>
  <tr>
    <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Name</th>
    <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Email</th>
    <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Role</th>
    <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Status</th>
    <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Documents</th>
    <th class="py-2 px-4 border-b-2 border-gray-200 text-center text-sm font-semibold text-gray-700">Actions</th>
  </tr>
</thead>
      <tbody>
        <?php if (!empty($users)): ?>
          <?php foreach ($users as $user): ?>
            <tr class="hover:bg-gray-100">
              <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                <?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>
              </td>
              <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                <?= htmlspecialchars($user['email']) ?>
              </td>
              <!-- Role Cell (simplified) -->
<td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
  <span class="px-2 py-1 rounded-full text-xs font-semibold 
    <?php
    switch($user['role']) {
      case 'admin':
        echo 'bg-blue-100 text-blue-800';
        break;
      case 'ngo':
        echo 'bg-green-100 text-green-800';
        break;
      case 'branch1_staff':
        echo 'bg-purple-100 text-purple-800';
        break;
      case 'branch2_staff':
        echo 'bg-indigo-100 text-indigo-800';
        break;
      default:
        echo 'bg-gray-100 text-gray-800';
    }
    ?>">
    <?php
      $roleDisplay = $user['role'];
      if ($roleDisplay === 'branch1_staff') {
        $roleDisplay = 'Branch 1 Staff';
      } else if ($roleDisplay === 'branch2_staff') {
        $roleDisplay = 'Branch 2 Staff';
      }
      echo ucfirst($roleDisplay);
    ?>
  </span>
</td>

<!-- Status Cell (enhanced to show account status + approval status) -->
<td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
  <!-- Account active/inactive status -->
  <div class="flex flex-col gap-1">
    <span class="px-2 py-1 rounded-full text-xs font-semibold inline-block 
      <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
      <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
    </span>
    
    <!-- NGO approval status -->
    <?php if ($user['role'] === 'ngo' && !empty($user['ngo_status'])): ?>
      <span class="px-2 py-1 rounded-full text-xs font-semibold inline-block
        <?php
        switch($user['ngo_status']) {
          case 'pending':
            echo 'bg-yellow-100 text-yellow-800';
            break;
          case 'approved':
            echo 'bg-green-100 text-green-800';
            break;
          case 'rejected':
            echo 'bg-red-100 text-red-800';
            break;
          default:
            echo 'bg-gray-100 text-gray-800';
        }
        ?>">
        NGO: <?= ucfirst($user['ngo_status']) ?>
      </span>
    <?php endif; ?>
    
    <!-- Staff approval status -->
    <?php if (($user['role'] === 'branch1_staff' || $user['role'] === 'branch2_staff') && 
              !empty($user['staff_status'])): ?>
      <span class="px-2 py-1 rounded-full text-xs font-semibold inline-block
        <?php
        switch($user['staff_status']) {
          case 'pending':
            echo 'bg-yellow-100 text-yellow-800';
            break;
          case 'approved':
            echo 'bg-green-100 text-green-800';
            break;
          case 'rejected':
            echo 'bg-red-100 text-red-800';
            break;
          default:
            echo 'bg-gray-100 text-gray-800';
        }
        ?>">
        Staff: <?= ucfirst($user['staff_status']) ?>
      </span>
    <?php endif; ?>
  </div>
</td>
              <td class="py-2 px-4 border-b border-gray-200 text-sm">
                <div class="flex space-x-2">
                  <?php if (!empty($user['gov_id_path'])): ?>
                    <button onclick="viewDocument('<?= htmlspecialchars($user['gov_id_path']) ?>', 'ID Document')" 
                      class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded text-xs">
                      ID Document
                    </button>
                  <?php endif; ?>
                  
                  <?php if (!empty($user['selfie_path'])): ?>
                    <button onclick="viewDocument('<?= htmlspecialchars($user['selfie_path']) ?>', 'Selfie')" 
                      class="bg-green-100 text-green-700 hover:bg-green-200 px-2 py-1 rounded text-xs">
                      Selfie
                    </button>
                  <?php endif; ?>
                  
                  <?php if (empty($user['gov_id_path']) && empty($user['selfie_path'])): ?>
                    <span class="text-gray-400 text-xs">No documents</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="py-2 px-4 border-b border-gray-200 text-sm">
                <!-- Keep your existing action buttons -->
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
                  <!-- New View button -->
                  <button onclick="viewUser(<?= htmlspecialchars(json_encode($user)) ?>)" 
                     class='rounded-md bg-blue-50 hover:bg-blue-100 text-blue-600 p-2 flex items-center'>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View
                  </button>
                  
                  <!-- Archive button (only for active users that aren't the current admin) -->
                  <?php if ($user['is_active'] == 1 && $user['id'] != $_SESSION['user_id']): ?>
                    <button onclick="openArchiveModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>')" 
                        class='rounded-md bg-red-50 hover:bg-red-100 text-red-600 p-2 flex items-center'>
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                      </svg>
                      Archive
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="py-4 px-6 text-center text-gray-500">
              No users found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="mt-6 flex justify-center">
        <div class="join">
          <?php if ($page > 1): ?>
            <a href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="join-item btn">«</a>
            <a href="?page=<?= $page-1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="join-item btn">‹</a>
          <?php else: ?>
            <button class="join-item btn btn-disabled">«</button>
            <button class="join-item btn btn-disabled">‹</button>
          <?php endif; ?>
          
          <?php 
          $start_page = max(1, $page - 2);
          $end_page = min($total_pages, $page + 2);
          
          for ($i = $start_page; $i <= $end_page; $i++): 
          ?>
            <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
               class="join-item btn <?= $i === $page ? 'btn-active' : '' ?>">
               <?= $i ?>
            </a>
          <?php endfor; ?>
          
          <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page+1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="join-item btn">›</a>
            <a href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="join-item btn">»</a>
          <?php else: ?>
            <button class="join-item btn btn-disabled">›</button>
            <button class="join-item btn btn-disabled">»</button>
          <?php endif; ?>
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
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
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
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
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

<!-- Document Viewer Modal -->
<dialog id="document_modal" class="modal">
  <div class="modal-box w-11/12 max-w-3xl">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
    </form>
    <h3 id="document-title" class="font-bold text-lg mb-4">Document Preview</h3>
    <div class="flex justify-center">
      <img id="document-image" src="" alt="Document Preview" class="max-h-[70vh] object-contain">
    </div>
  </div>
</dialog>

<!-- Document viewer script -->
<script>
function viewDocument(path, type) {
  const modal = document.getElementById('document_modal');
  const title = document.getElementById('document-title');
  const image = document.getElementById('document-image');
  
  // Set modal content
  title.textContent = type + ' Preview';
  image.src = path;
  
  // Show modal
  modal.showModal();
}
</script>

<!-- Add this after your existing modals -->
<dialog id="view_modal" class="modal">
  <div class="modal-box max-w-2xl">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
    </form>
    <h3 class="font-bold text-lg mb-4">User Details</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div class="form-control">
        <label class="label font-medium">First Name</label>
        <div id="view_fname" class="bg-gray-50 p-2 rounded border"></div>
      </div>
      
      <div class="form-control">
        <label class="label font-medium">Last Name</label>
        <div id="view_lname" class="bg-gray-50 p-2 rounded border"></div>
      </div>
      
      <div class="form-control">
        <label class="label font-medium">Email</label>
        <div id="view_email" class="bg-gray-50 p-2 rounded border"></div>
      </div>
      
      <div class="form-control">
        <label class="label font-medium">Role</label>
        <div id="view_role" class="bg-gray-50 p-2 rounded border"></div>
      </div>
      
      <div class="form-control">
        <label class="label font-medium">Status</label>
        <div id="view_status" class="bg-gray-50 p-2 rounded border"></div>
      </div>
      
      <div class="form-control">
        <label class="label font-medium">Created At</label>
        <div id="view_created" class="bg-gray-50 p-2 rounded border"></div>
      </div>
    </div>
    
    <!-- NGO specific fields -->
    <div id="view_ngo_section" class="hidden mt-4 pt-4 border-t">
      <h4 class="font-semibold mb-3">NGO Details</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-control">
          <label class="label font-medium">Organization Name</label>
          <div id="view_org_name" class="bg-gray-50 p-2 rounded border"></div>
        </div>
        <div class="form-control">
          <label class="label font-medium">Status</label>
          <div id="view_ngo_status" class="bg-gray-50 p-2 rounded border"></div>
        </div>
      </div>
    </div>
    
    <!-- Documents Section -->
    <div id="view_documents_section" class="hidden mt-4 pt-4 border-t">
      <h4 class="font-semibold mb-3">Uploaded Documents</h4>
      <div class="flex flex-wrap gap-4">
        <div id="view_id_doc" class="hidden">
          <p class="font-medium mb-1">ID Document</p>
          <button id="view_id_btn" class="btn btn-sm btn-outline">View ID</button>
        </div>
        <div id="view_selfie_doc" class="hidden">
          <p class="font-medium mb-1">Selfie</p>
          <button id="view_selfie_btn" class="btn btn-sm btn-outline">View Selfie</button>
        </div>
      </div>
    </div>
  </div>
</dialog>

<!-- Add this to your existing scripts section -->
<script>
function viewUser(user) {
  // Fill basic user details
  document.getElementById('view_fname').textContent = user.fname;
  document.getElementById('view_lname').textContent = user.lname;
  document.getElementById('view_email').textContent = user.email;
  document.getElementById('view_role').textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
  document.getElementById('view_status').textContent = user.is_active == 1 ? 'Active' : 'Inactive';
  document.getElementById('view_created').textContent = new Date(user.created_at).toLocaleString();
  
  // Handle NGO specific details
  const ngoSection = document.getElementById('view_ngo_section');
  if (user.role === 'ngo' && user.organization_name) {
    ngoSection.classList.remove('hidden');
    document.getElementById('view_org_name').textContent = user.organization_name;
    document.getElementById('view_ngo_status').textContent = user.ngo_status ? 
      user.ngo_status.charAt(0).toUpperCase() + user.ngo_status.slice(1) : 'Unknown';
  } else {
    ngoSection.classList.add('hidden');
  }
  
  // Handle documents
  const documentsSection = document.getElementById('view_documents_section');
  const idDocSection = document.getElementById('view_id_doc');
  const selfieDocSection = document.getElementById('view_selfie_doc');
  const idButton = document.getElementById('view_id_btn');
  const selfieButton = document.getElementById('view_selfie_btn');
  
  if (user.gov_id_path || user.selfie_path) {
    documentsSection.classList.remove('hidden');
    
    if (user.gov_id_path) {
      idDocSection.classList.remove('hidden');
      idButton.onclick = function() { viewDocument(user.gov_id_path, 'ID Document'); };
    } else {
      idDocSection.classList.add('hidden');
    }
    
    if (user.selfie_path) {
      selfieDocSection.classList.remove('hidden');
      selfieButton.onclick = function() { viewDocument(user.selfie_path, 'Selfie'); };
    } else {
      selfieDocSection.classList.add('hidden');
    }
  } else {
    documentsSection.classList.add('hidden');
  }
  
  // Show the modal
  document.getElementById('view_modal').showModal();
}
</script>

<!-- Archive Confirmation Modal -->
<dialog id="archive_modal" class="modal">
  <div class="modal-box">
    <h3 class="font-bold text-lg text-red-600">Archive User</h3>
    <p class="py-4">Are you sure you want to archive user <span id="archive_user_name" class="font-semibold"></span>? This will deactivate their account.</p>
    <form method="POST" id="archive_form">
      <input type="hidden" id="archive_user_id" name="archive_user" value="">
      <div class="modal-action">
        <button type="submit" class="btn bg-red-600 hover:bg-red-700 text-white">Archive User</button>
        <button type="button" onclick="document.getElementById('archive_modal').close()" class="btn">Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<script>
function openArchiveModal(userId, userName) {
  document.getElementById('archive_user_name').textContent = userName;
  document.getElementById('archive_user_id').value = userId;
  document.getElementById('archive_modal').showModal();
}
</script>

</body>
</html>
<?php
// Flush output buffer at the end
ob_end_flush();
?>