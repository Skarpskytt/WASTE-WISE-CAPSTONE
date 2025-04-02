<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

$pdo = getPDO();
$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("User not found");
    }
    
    // Simplified preferences - just store in session if needed
    $expiryAlertDays = $_SESSION['expiry_alert_days'] ?? 7; 
    $defaultSort = $_SESSION['default_sort'] ?? 'fefo';
} catch (PDOException $e) {
    $errorMsg = "Error fetching user data: " . $e->getMessage();
}

// Process settings form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $expiryAlertDays = intval($_POST['expiry_alert_days']);
    $defaultSort = $_POST['default_sort'];
    
    // Store in session for simplicity
    $_SESSION['expiry_alert_days'] = $expiryAlertDays;
    $_SESSION['default_sort'] = $defaultSort;
    
    $successMsg = "Settings updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Settings - WasteWise</title>
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

<body class="flex min-h-screen bg-gray-50">

<?php include ('../layout/staff_nav.php'); ?>

<div class="p-6 w-full">
    <h1 class="text-3xl font-bold mb-6 text-primarycol">Settings</h1>
    
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
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Simple Settings Form -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Your Preferences</h2>
            
            <form method="POST" action="">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Alert Days</label>
                        <select name="expiry_alert_days" class="select select-bordered w-full">
                            <?php for($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>" <?= ($expiryAlertDays == $i) ? 'selected' : '' ?>>
                                    <?= $i ?> days before expiry
                                </option>
                            <?php endfor; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">When to alert you about expiring products</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Inventory Sorting</label>
                        <select name="default_sort" class="select select-bordered w-full">
                            <option value="fefo" <?= ($defaultSort == 'fefo') ? 'selected' : '' ?>>First Expiry, First Out (FEFO)</option>
                            <option value="fifo" <?= ($defaultSort == 'fifo') ? 'selected' : '' ?>>First In, First Out (FIFO)</option>
                            <option value="name" <?= ($defaultSort == 'name') ? 'selected' : '' ?>>Product Name</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">How inventory will be sorted by default</p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" name="save_settings" class="px-4 py-2 bg-primarycol hover:bg-fourth text-white rounded-md">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Account Information -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Account Information</h2>
            
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500">Name</p>
                    <p class="font-medium"><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="font-medium"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Role</p>
                    <p class="font-medium">
                        <?php 
                            $role = $user['role'];
                            echo ucfirst($role);
                        ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Branch</p>
                    <p class="font-medium">
                        <?php 
                            if (!empty($user['branch_id'])) {
                                echo "Branch #" . htmlspecialchars($user['branch_id']);
                            } else {
                                echo "N/A";
                            }
                        ?>
                    </p>
                </div>
            </div>
            
            <div class="mt-6">
                <a href="edit_profile_staff.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md inline-block">
                    Edit Profile
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>