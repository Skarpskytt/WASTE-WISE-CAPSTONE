<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Get user ID from session
$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("User not found");
    }
} catch (PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($fname) || empty($lname) || empty($email)) {
        $errorMsg = "Name and email fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Invalid email format";
    } else {
        // Check if email is already in use by another user
        $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmailStmt->execute([$email, $userId]);
        if ($checkEmailStmt->rowCount() > 0) {
            $errorMsg = "Email is already in use by another account";
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Check if user wants to change password
                if (!empty($currentPassword)) {
                    // Verify current password
                    if (!password_verify($currentPassword, $user['password'])) {
                        throw new Exception("Current password is incorrect");
                    }
                    
                    // Validate new password
                    if (empty($newPassword)) {
                        throw new Exception("New password cannot be empty");
                    }
                    
                    if ($newPassword !== $confirmPassword) {
                        throw new Exception("New passwords do not match");
                    }
                    
                    // Password strength check
                    if (strlen($newPassword) < 8) {
                        throw new Exception("Password must be at least 8 characters long");
                    }
                    
                    // Hash the new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update user data including password
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET fname = ?, lname = ?, email = ?, password = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$fname, $lname, $email, $hashedPassword, $userId]);
                } else {
                    // Update user data without changing password
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET fname = ?, lname = ?, email = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$fname, $lname, $email, $userId]);
                }
                
                // Commit transaction
                $pdo->commit();
                
                // Update session data
                $_SESSION['fname'] = $fname;
                $_SESSION['lname'] = $lname;
                $_SESSION['email'] = $email;
                
                $successMsg = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $errorMsg = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile</title>
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
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('-translate-x-full');
    });

     $('#closeSidebar').on('click', function() {
        $('#sidebar').addClass('-translate-x-full');
    });

    // Toggle password change form
    $('#togglePasswordForm').on('click', function() {
        $('#passwordChangeFields').toggleClass('hidden');
        $(this).text(
            $(this).text() === 'Change Password' ? 'Cancel Password Change' : 'Change Password'
        );
    });

    // Show/hide password
    $('.toggle-password').on('click', function() {
        const passwordField = $($(this).attr('toggle'));
        const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', type);
        
        // Toggle icon
        $(this).find('svg').toggleClass('hidden');
    });
});
    function markTaskDone(button) {
      button.parentElement.style.textDecoration = 'line-through';
      button.disabled = true;
    }

 </script>
   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex min-h-screen bg-gray-50">

<?php include ('../layout/staff_nav.php'); ?>

<div class="p-6 w-full">
    <h1 class="text-3xl font-bold mb-6 text-primarycol">Edit Profile</h1>
    
    <?php if (!empty($successMsg)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($successMsg) ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($errorMsg)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($errorMsg) ?></p>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
        <h2 class="text-xl font-semibold mb-4 text-gray-800">Personal Information</h2>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="fname" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" name="fname" id="fname" value="<?= htmlspecialchars($user['fname']) ?>" required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primarycol focus:border-primarycol border p-2">
                </div>
                
                <div>
                    <label for="lname" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" name="lname" id="lname" value="<?= htmlspecialchars($user['lname']) ?>" required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primarycol focus:border-primarycol border p-2">
                </div>
            </div>
            
            <div class="mb-6">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required
                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primarycol focus:border-primarycol border p-2">
            </div>
            
            <div class="mb-6">
                <button type="button" id="togglePasswordForm" class="text-primarycol hover:text-fourth focus:outline-none text-sm font-medium">
                    Change Password
                </button>
                
                <div id="passwordChangeFields" class="hidden mt-4 space-y-4 border-t pt-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <div class="relative">
                            <input type="password" name="current_password" id="current_password"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primarycol focus:border-primarycol border p-2 pr-10">
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 px-3 flex items-center" toggle="#current_password">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <div class="relative">
                            <input type="password" name="new_password" id="new_password"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primarycol focus:border-primarycol border p-2 pr-10">
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 px-3 flex items-center" toggle="#new_password">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primarycol focus:border-primarycol border p-2 pr-10">
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 px-3 flex items-center" toggle="#confirm_password">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-md font-medium text-gray-700 mb-2">Account Information</h3>
                <div class="bg-gray-50 p-4 rounded-md">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="block text-sm font-medium text-gray-500">Account Type</span>
                            <span class="block text-md">
                                <?php 
                                    $role = $user['role'];
                                    if ($role === 'branch1_staff' || $role === 'branch2_staff') {
                                        echo 'Branch Staff';
                                    } else {
                                        echo ucfirst($role);
                                    }
                                ?>
                            </span>
                        </div>
                        
                        <div>
                            <span class="block text-sm font-medium text-gray-500">Branch</span>
                            <span class="block text-md">
                                <?php 
                                    if (!empty($user['branch_id'])) {
                                        echo "Branch #" . htmlspecialchars($user['branch_id']);
                                    } else {
                                        echo "N/A";
                                    }
                                ?>
                            </span>
                        </div>
                        
                        <div>
                            <span class="block text-sm font-medium text-gray-500">Account Created</span>
                            <span class="block text-md">
                                <?= date('F j, Y', strtotime($user['created_at'])) ?>
                            </span>
                        </div>
                        
                        <div>
                            <span class="block text-sm font-medium text-gray-500">Last Updated</span>
                            <span class="block text-md">
                                <?= date('F j, Y', strtotime($user['updated_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between">
                <button type="submit" class="bg-primarycol hover:bg-fourth text-white font-medium py-2 px-6 rounded-md focus:outline-none">
                    Save Changes
                </button>
                
                <a href="staff_dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded-md">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>