<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for NGO access only
checkAuth(['ngo']);

$pdo = getPDO();
$ngoId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// Fetch current NGO profile information
$profileQuery = $pdo->prepare("
    SELECT * FROM ngo_profiles 
    WHERE user_id = ?
");
$profileQuery->execute([$ngoId]);
$ngoProfile = $profileQuery->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process organization name update
    if (isset($_POST['update_profile'])) {
        $organizationName = trim($_POST['organization_name']);
        
        if (empty($organizationName)) {
            $errorMessage = "Organization name cannot be empty";
        } else {
            // Check if logo was uploaded
            $logoPath = $ngoProfile['organization_logo'] ?? null;
            
            // Process logo upload if a file was selected
            if (isset($_FILES['organization_logo']) && $_FILES['organization_logo']['error'] == 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($_FILES['organization_logo']['type'], $allowedTypes)) {
                    $errorMessage = "Invalid file type. Please upload JPG, PNG, or GIF files only.";
                } elseif ($_FILES['organization_logo']['size'] > $maxSize) {
                    $errorMessage = "File size exceeds the limit of 5MB.";
                } else {
                    // Create upload directory if it doesn't exist
                    $uploadDir = '../../assets/uploads/ngo_logos/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'ngo_logo_' . $ngoId . '_' . time() . '_' . basename($_FILES['organization_logo']['name']);
                    $targetPath = $uploadDir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['organization_logo']['tmp_name'], $targetPath)) {
                        // Delete old logo if exists
                        if ($logoPath && file_exists('../../' . $logoPath)) {
                            unlink('../../' . $logoPath);
                        }
                        
                        $logoPath = 'assets/uploads/ngo_logos/' . $filename;
                    } else {
                        $errorMessage = "Failed to upload logo. Please try again.";
                    }
                }
            }
            
            // If no errors, update the profile in database
            if (empty($errorMessage)) {
                if ($ngoProfile) {
                    // Update existing profile
                    $updateStmt = $pdo->prepare("
                        UPDATE ngo_profiles 
                        SET organization_name = ?, organization_logo = ? 
                        WHERE user_id = ?
                    ");
                    $updateStmt->execute([$organizationName, $logoPath, $ngoId]);
                } else {
                    // Create new profile
                    $insertStmt = $pdo->prepare("
                        INSERT INTO ngo_profiles (user_id, organization_name, organization_logo) 
                        VALUES (?, ?, ?)
                    ");
                    $insertStmt->execute([$ngoId, $organizationName, $logoPath]);
                }
                
                $successMessage = "Profile updated successfully!";
                
                // Refresh profile data
                $profileQuery->execute([$ngoId]);
                $ngoProfile = $profileQuery->fetch(PDO::FETCH_ASSOC);
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
    <title>NGO Settings - WasteWise</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .preview-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="flex min-h-screen bg-gray-50">
        <?php include '../layout/ngo_nav.php'; ?>
        
        <main class="flex-1 p-4">
            <div class="settings-container">
                <h1 class="text-2xl font-bold mb-6">Organization Settings</h1>
                
                <?php if ($successMessage): ?>
                <div class="alert alert-success mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span><?= htmlspecialchars($successMessage) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                <div class="alert alert-error mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="card bg-white shadow-md">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Customize Your Organization</h2>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-6">
                                <label class="block text-gray-700 font-medium mb-2" for="organization_name">
                                    Organization Name
                                </label>
                                <input 
                                    type="text" 
                                    name="organization_name" 
                                    id="organization_name" 
                                    class="input input-bordered w-full" 
                                    value="<?= htmlspecialchars($ngoProfile['organization_name'] ?? '') ?>"
                                    required
                                >
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 font-medium mb-2" for="organization_logo">
                                    Organization Logo
                                </label>
                                
                                <div class="mb-4">
                                    <?php if (isset($ngoProfile['organization_logo']) && $ngoProfile['organization_logo']): ?>
                                        <div class="text-center">
                                            <p class="text-sm text-gray-500 mb-2">Current Logo:</p>
                                            <img 
                                                src="../../<?= htmlspecialchars($ngoProfile['organization_logo']) ?>" 
                                                alt="Current Logo"
                                                class="preview-image mx-auto mb-2" 
                                                id="currentLogo"
                                                onerror="this.src='../../assets/images/Logo.png'; this.onerror=null;"
                                            >
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center">
                                            <p class="text-sm text-gray-500 mb-2">No logo set</p>
                                            <img 
                                                src="../../assets/images/Logo.png" 
                                                alt="Default Logo"
                                                class="preview-image mx-auto mb-2"
                                                id="currentLogo"
                                            >
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center gap-4">
                                    <input 
                                        type="file" 
                                        name="organization_logo" 
                                        id="organization_logo" 
                                        class="file-input file-input-bordered w-full"
                                        accept=".jpg,.jpeg,.png,.gif"
                                        onchange="previewImage(this)"
                                    >
                                </div>
                                
                                <div class="mt-4">
                                    <p class="text-sm text-gray-500">
                                        Allowed formats: JPG, PNG, GIF. Maximum size: 5MB.<br>
                                        For best results, use a square image.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function previewImage(input) {
            const currentLogo = document.getElementById('currentLogo');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    currentLogo.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Toggle mobile sidebar
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
        });
        
        document.getElementById('closeSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
        });
    </script>
</body>
</html>