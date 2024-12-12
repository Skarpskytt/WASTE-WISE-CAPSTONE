<?php
session_start();
include('../../config/db_connect.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
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
                INSERT INTO notifications (message, type) 
                VALUES (?, 'info')
            ");
            $notifStmt->execute([$notification]);
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch all users
$userQuery = "
    SELECT id, fname, lname, email, role, created_at 
    FROM users 
    ORDER BY created_at DESC
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

  <div class="p-6">
    <h2 class="text-2xl font-semibold mb-10">User Management</h2>
        <div class="stats shadow-2xl ml-7">
          <div class="stat">
            <div class="grid grid-cols-2 place-content-end">
            <div class="stat-title">Active  Staffs</div>
            <div class="stat-figure text-primarycol justify-self-end">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
              </svg>
             </div>
            </div>
           
         <div class="stat-value text-primarycol">18</div> 
         <div class="mt-4"> 
          <!-- You can open the modal using ID.showModal() method -->
        <button class="btn btn-wide bg-primarycol text-white" onclick="my_modal_4.showModal()">Add User</button>
          <dialog id="my_modal_4" class="modal">
        <div class="modal-box w-auto max-w-5xl">
          <h3 class="text-lg font-bold">Hello!</h3>
          <p class="py-4">Click the button below to close</p>
          <div class="mt-4 flex flex-col lg:flex-row items-center justify-between">
          </div>
          <form action="#" method="POST" class="space-y-4">
          <!-- Your form elements go here -->
           <div class="flex flex-row gap-2">
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">First Name</label>
            <input type="text" id="fname" name="fname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Last Name</label>
            <input type="text" id="lname" name="lame" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
        </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="text" id="email" name="email" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Role</label>
            <input type="text" id="role" name="role" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" id="password" name="password" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" id="conpassword" name="conpassword" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
        
          <div>
            <button type="submit" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">Sign Up</button>
          </div>
        </form>
        <div class="modal-action">
          <form method="dialog">
           <button class="btn">Close</button>
          </form>
          </div>
         </div>
        </dialog>
        </div>
        </div>
        
    
</div>

<div class="mt-8">
    <h2 class="text-2xl font-semibold mb-4">User List</h2>
    <div class="overflow-x-auto">
        <table class="table w-full">
            <thead>
                <tr class="bg-primarycol text-white">
                    <th class="py-2 px-4">#</th>
                    <th class="py-2 px-4">Name</th>
                    <th class="py-2 px-4">Email</th>
                    <th class="py-2 px-4">Role</th>
                    <th class="py-2 px-4">Created At</th>
                    <th class="py-2 px-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-100">
                            <td class="py-2 px-4"><?= htmlspecialchars($user['id']) ?></td>
                            <td class="py-2 px-4">
                                <?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?>
                            </td>
                            <td class="py-2 px-4"><?= htmlspecialchars($user['email']) ?></td>
                            <td class="py-2 px-4">
                                <span class="badge <?= $user['role'] === 'admin' ? 'badge-primary' : 'badge-secondary' ?>">
                                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                </span>
                            </td>
                            <td class="py-2 px-4">
                                <?= date('M j, Y g:i A', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="py-2 px-4">
                                <button onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                        class="btn btn-sm btn-info mr-2">
                                    Edit
                                </button>
                                <button onclick="deleteUser(<?= htmlspecialchars($user['id']) ?>)" 
                                        class="btn btn-sm btn-error">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="py-4 text-center text-gray-500">
                            No users found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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

<dialog id="delete_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Confirm Delete</h3>
        <p class="py-4">Are you sure you want to delete this user?</p>
        <div class="modal-action">
            <form method="POST">
                <input type="hidden" id="delete_id" name="delete_id">
                <button type="submit" name="delete_user" class="btn btn-error">Delete</button>
                <button type="button" onclick="closeDeleteModal()" class="btn">Cancel</button>
            </form>
        </div>
    </div>
</dialog>

<script>
function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_fname').value = user.fname;
    document.getElementById('edit_lname').value = user.lname;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_modal').showModal();
}

function closeEditModal() {
    document.getElementById('edit_modal').close();
}

function deleteUser(userId) {
    document.getElementById('delete_id').value = userId;
    document.getElementById('delete_modal').showModal();
}

function closeDeleteModal() {
    document.getElementById('delete_modal').close();
}
</script>

</body>
</html>