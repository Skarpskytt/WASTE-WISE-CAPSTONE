<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../config/app_config.php';

// Check for staff access
checkAuth(['staff', 'company']);

$pdo = getPDO();
$userId = $_SESSION['user_id'];
$branchId = $_SESSION['branch_id'];
$successMsg = '';
$errorMsg = '';

// Fetch user and branch data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, b.name as branch_name, b.address, b.phone as branch_phone, 
               b.contact_person, b.business_permit_number 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        die("User not found");
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching data: " . $e->getMessage();
}

// Process branch update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_branch']) && $_SESSION['role'] === 'company') {
    try {
        // Handle logo upload
        if (isset($_FILES['branch_logo']) && $_FILES['branch_logo']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($_FILES['branch_logo']['type'], $allowedTypes)) {
                throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
            }

            if ($_FILES['branch_logo']['size'] > $maxSize) {
                throw new Exception('File size too large. Maximum size is 2MB.');
            }

            // Create directory if it doesn't exist
            $uploadDir = "../../assets/uploads/branch_logos/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $fileName = time() . '_' . basename($_FILES['branch_logo']['name']);
            $targetPath = $uploadDir . $fileName;

            // Remove old logo if exists
            $oldLogoStmt = $pdo->prepare("SELECT logo_path FROM branches WHERE id = ?");
            $oldLogoStmt->execute([$branchId]);
            $oldLogo = $oldLogoStmt->fetchColumn();
            
            if ($oldLogo && file_exists($uploadDir . $oldLogo)) {
                unlink($uploadDir . $oldLogo);
            }

            if (move_uploaded_file($_FILES['branch_logo']['tmp_name'], $targetPath)) {
                // Update database with new logo path
                $stmt = $pdo->prepare("
                    UPDATE branches 
                    SET logo_path = ?,
                        address = ?,
                        phone = ?,
                        contact_person = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $fileName,
                    $_POST['address'],
                    $_POST['branch_phone'],
                    $_POST['contact_person'],
                    $branchId
                ]);
            } else {
                throw new Exception('Failed to upload logo.');
            }
        } else {
            // Update without changing logo
            $stmt = $pdo->prepare("
                UPDATE branches 
                SET address = ?,
                    phone = ?,
                    contact_person = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['address'],
                $_POST['branch_phone'],
                $_POST['contact_person'],
                $branchId
            ]);
        }

        $successMsg = "Branch details updated successfully!";
        
        // Refresh branch data
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
    } catch (Exception $e) {
        $errorMsg = "Error updating branch: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - WasteWise</title>
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

<?php include('../layout/staff_nav.php'); ?>

<div class="p-6 w-full">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-primarycol">Account Settings</h1>
        
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-error mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Branch Information -->
            <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200">
                <h2 class="text-xl font-semibold mb-4 text-primarycol">Branch Information</h2>
                
                <?php if ($_SESSION['role'] === 'company'): ?>
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                        <!-- Current Logo Display -->
                        <div class="mb-4">
                            <label class="label">
                                <span class="label-text font-medium">Current Branch Logo</span>
                            </label>
                            <div class="flex items-center space-x-4">
                                <?php
                                $logoStmt = $pdo->prepare("SELECT logo_path FROM branches WHERE id = ?");
                                $logoStmt->execute([$branchId]);
                                $logoPath = $logoStmt->fetchColumn();
                                
                                $currentLogo = $logoPath ? "../../assets/uploads/branch_logos/" . htmlspecialchars($logoPath) : "../../assets/images/Company Logo.jpg";
                                ?>
                                <img src="<?= $currentLogo ?>" alt="Branch Logo" class="h-20 w-20 object-contain rounded-lg border-2 border-primarycol">
                            </div>
                        </div>

                        <!-- Logo Upload -->
                        <div>
                            <label class="label">
                                <span class="label-text font-medium">Update Branch Logo</span>
                            </label>
                            <input type="file" 
                                   name="branch_logo" 
                                   accept="image/*"
                                   class="file-input file-input-bordered w-full" />
                            <p class="text-xs text-gray-500 mt-1">Recommended size: 200x200px. Max file size: 2MB</p>
                        </div>

                        <!-- Existing fields -->
                        <div>
                            <label class="label">
                                <span class="label-text font-medium">Branch Name</span>
                            </label>
                            <input type="text" value="<?= htmlspecialchars($userData['branch_name']) ?>" 
                                   class="input input-bordered w-full" disabled />
                        </div>

                        <div>
                            <label class="label">
                                <span class="label-text font-medium">Address</span>
                            </label>
                            <textarea name="address" class="textarea textarea-bordered w-full" 
                                    required><?= htmlspecialchars($userData['address']) ?></textarea>
                        </div>

                        <div>
                            <label class="label">
                                <span class="label-text font-medium">Contact Number</span>
                            </label>
                            <input type="tel" name="branch_phone" value="<?= htmlspecialchars($userData['branch_phone']) ?>" 
                                   class="input input-bordered w-full" required />
                        </div>

                        <div>
                            <label class="label">
                                <span class="label-text font-medium">Contact Person</span>
                            </label>
                            <input type="text" name="contact_person" value="<?= htmlspecialchars($userData['contact_person']) ?>" 
                                   class="input input-bordered w-full" required />
                        </div>

                        <div class="pt-4">
                            <button type="submit" name="update_branch" 
                                    class="btn btn-primary bg-primarycol hover:bg-fourth text-white">
                                Update Branch Details
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-gray-500">Branch Name</label>
                            <p class="font-medium"><?= htmlspecialchars($userData['branch_name']) ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Address</label>
                            <p class="font-medium"><?= htmlspecialchars($userData['address']) ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Contact Number</label>
                            <p class="font-medium"><?= htmlspecialchars($userData['branch_phone'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Account Information -->
            <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200">
                <h2 class="text-xl font-semibold mb-4 text-primarycol">Account Information</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Name</label>
                        <p class="font-medium"><?= htmlspecialchars($userData['fname'] . ' ' . $userData['lname']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Email</label>
                        <p class="font-medium"><?= htmlspecialchars($userData['email']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Role</label>
                        <p class="font-medium"><?= ucfirst(htmlspecialchars($userData['role'])) ?></p>
                    </div>
                    
                    <div class="pt-4">
                        <a href="edit_profile_staff.php" 
                           class="btn btn-outline btn-primary">
                            Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>