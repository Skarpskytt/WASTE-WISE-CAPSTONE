<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // 1. Handle file upload for website icon
        if (isset($_FILES['website_icon']) && $_FILES['website_icon']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['website_icon']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (!in_array(strtolower($filetype), $allowed)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
            }

            $newname = 'icon_' . time() . '.' . $filetype;
            $upload_path = '../../assets/images/' . $newname;
            
            if (!move_uploaded_file($_FILES['website_icon']['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload icon file.");
            }

            updateSetting($pdo, 'website_icon', $newname);
        }

        // 2. Update text-based settings
        $textSettings = [
            'website_title',
            'website_name',
            'company_address',
            'contact_email',
            'contact_phone',
            'notification_email'
        ];

        foreach ($textSettings as $key) {
            if (isset($_POST[$key])) {
                updateSetting($pdo, $key, $_POST[$key]);
            }
        }

        // 3. Update numeric settings with validation
        $numericSettings = [
            'waste_threshold_alert' => ['min' => 1, 'max' => 100],
            'expiry_notification_days' => ['min' => 1, 'max' => 365],
            'default_pagination' => ['min' => 10, 'max' => 100]
        ];

        foreach ($numericSettings as $key => $limits) {
            if (isset($_POST[$key])) {
                $value = intval($_POST[$key]);
                if ($value < $limits['min'] || $value > $limits['max']) {
                    throw new Exception("Invalid value for {$key}. Must be between {$limits['min']} and {$limits['max']}.");
                }
                updateSetting($pdo, $key, $value);
            }
        }

        // 4. Update boolean settings
        $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
        updateSetting($pdo, 'maintenance_mode', $maintenanceMode);

        // Commit transaction
        $pdo->commit();
        $success = "Settings updated successfully!";

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Helper function to update settings
function updateSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$value, $key]);
}

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key IN (
    'website_title', 
    'website_name', 
    'website_icon',
    'company_address',
    'contact_email',
    'contact_phone',
    'notification_email',
    'waste_threshold_alert',
    'expiry_notification_days',
    'maintenance_mode',
    'default_pagination'
)");
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
    <link rel="icon" type="image/x-icon" href="../../assets/images/Logo.png">
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
                <!-- Existing Website Settings Section -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Website Settings</h3>
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
                </div>

                <!-- Company Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Company Information</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="company_address" class="block text-sm font-medium text-gray-700 mb-2">
                                Company Address
                            </label>
                            <textarea 
                                id="company_address" 
                                name="company_address" 
                                rows="3"
                                class="textarea textarea-bordered w-full"
                            ><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">
                                Contact Email
                            </label>
                            <input 
                                type="email" 
                                id="contact_email" 
                                name="contact_email"
                                value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>"
                                class="input input-bordered w-full"
                            >
                        </div>

                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Contact Phone
                            </label>
                            <input 
                                type="tel" 
                                id="contact_phone" 
                                name="contact_phone"
                                value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>"
                                class="input input-bordered w-full"
                            >
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Notification Settings</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="notification_email" class="block text-sm font-medium text-gray-700 mb-2">
                                Notification Email
                            </label>
                            <input 
                                type="email" 
                                id="notification_email" 
                                name="notification_email"
                                value="<?= htmlspecialchars($settings['notification_email'] ?? '') ?>"
                                class="input input-bordered w-full"
                            >
                            <p class="text-sm text-gray-500 mt-1">Email address for system notifications</p>
                        </div>

                        <div>
                            <label for="waste_threshold_alert" class="block text-sm font-medium text-gray-700 mb-2">
                                Waste Threshold Alert (%)
                            </label>
                            <input 
                                type="number" 
                                id="waste_threshold_alert" 
                                name="waste_threshold_alert"
                                min="1" 
                                max="100"
                                value="<?= htmlspecialchars($settings['waste_threshold_alert'] ?? '20') ?>"
                                class="input input-bordered w-full"
                            >
                            <p class="text-sm text-gray-500 mt-1">Alert when waste reaches this percentage of inventory</p>
                        </div>

                        <div>
                            <label for="expiry_notification_days" class="block text-sm font-medium text-gray-700 mb-2">
                                Expiry Notification Days
                            </label>
                            <input 
                                type="number" 
                                id="expiry_notification_days" 
                                name="expiry_notification_days"
                                min="1"
                                value="<?= htmlspecialchars($settings['expiry_notification_days'] ?? '7') ?>"
                                class="input input-bordered w-full"
                            >
                            <p class="text-sm text-gray-500 mt-1">Days before expiry to send notification</p>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">System Settings</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    name="maintenance_mode" 
                                    class="checkbox"
                                    <?= ($settings['maintenance_mode'] ?? '') === '1' ? 'checked' : '' ?>
                                >
                                <span class="ml-2">Maintenance Mode</span>
                            </label>
                            <p class="text-sm text-gray-500 mt-1">Enable maintenance mode to temporarily disable access</p>
                        </div>

                        <div>
                            <label for="default_pagination" class="block text-sm font-medium text-gray-700 mb-2">
                                Items Per Page
                            </label>
                            <select 
                                id="default_pagination" 
                                name="default_pagination"
                                class="select select-bordered w-full"
                            >
                                <?php foreach ([10, 25, 50, 100] as $value): ?>
                                    <option value="<?= $value ?>" <?= ($settings['default_pagination'] ?? '25') == $value ? 'selected' : '' ?>>
                                        <?= $value ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Default number of items to show per page</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4">
                    <button type="submit" class="btn bg-primarycol text-white hover:bg-green-600">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>