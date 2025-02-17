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
    try {
        // Handle icon upload
        if (isset($_FILES['website_icon']) && $_FILES['website_icon']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['website_icon']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (in_array(strtolower($filetype), $allowed)) {
                $newname = 'icon_'.time().'.'.$filetype;
                $upload_path = '../../assets/images/'.$newname;
                
                if (move_uploaded_file($_FILES['website_icon']['tmp_name'], $upload_path)) {
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'website_icon'");
                    $stmt->execute([$newname]);
                }
            }
        }

        // Update website title
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'website_title'");
        $stmt->execute([$_POST['website_title']]);

        // Update website name
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'website_name'");
        $stmt->execute([$_POST['website_name']]);

        $success = "Settings updated successfully!";
    } catch (Exception $e) {
        $error = "An error occurred while updating settings.";
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('website_title', 'website_name', 'website_icon')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($settings['website_title'] ?? 'WasteWise') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
    </script>
</head>

<body class="flex h-screen bg-slate-100">
    <?php include '../layout/nav.php' ?>

    <div class="p-6 w-full">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold text-primarycol mb-6">Website Settings</h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success mb-4">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6 max-w-2xl">
                <!-- Website Icon -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Website Icon
                    </label>
                    <?php if (!empty($settings['website_icon'])): ?>
                        <img src="../../assets/images/<?= htmlspecialchars($settings['website_icon']) ?>" 
                             alt="<?= htmlspecialchars($settings['website_name'] ?? 'Site Logo') ?>" 
                             class="w-16 h-16 mb-2 object-contain">
                    <?php endif; ?>
                    <input 
                        type="file" 
                        name="website_icon" 
                        accept=".jpg,.jpeg,.png,.gif"
                        class="file-input file-input-bordered w-full"
                    >
                    <p class="text-sm text-gray-500 mt-1">Recommended size: 32x32 pixels. Supported formats: JPG, PNG, GIF</p>
                </div>

                <!-- Website Title -->
                <div>
                    <label for="website_title" class="block text-sm font-medium text-gray-700 mb-2">
                        Website Title
                    </label>
                    <input 
                        type="text" 
                        id="website_title" 
                        name="website_title" 
                        value="<?= htmlspecialchars($settings['website_title'] ?? '') ?>"
                        class="input input-bordered w-full focus:ring-primarycol focus:border-primarycol"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">This appears in the browser tab and SEO title</p>
                </div>

                <!-- Website Name -->
                <div>
                    <label for="website_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Website Name
                    </label>
                    <input 
                        type="text" 
                        id="website_name" 
                        name="website_name" 
                        value="<?= htmlspecialchars($settings['website_name'] ?? '') ?>"
                        class="input input-bordered w-full focus:ring-primarycol focus:border-primarycol"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">This appears in the website header and footer</p>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" class="btn bg-primarycol text-white hover:bg-green-600">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>