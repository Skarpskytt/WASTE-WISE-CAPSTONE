<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Initialize variables for form data
$systemName = '';
$contactEmail = '';
$contactPhone = '';
$adminNotifications = true;
$donationExpiry = 7;
$autoApproveUsers = false;
$recordsPerPage = 10;
$success_message = '';
$error_message = '';

// Process backup and restore actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'backup':
            try {
                $backupFile = createDatabaseBackup($pdo);
                $success_message = "Database backup created successfully! File: " . $backupFile;
            } catch (Exception $e) {
                $error_message = "Backup failed: " . $e->getMessage();
            }
            break;
            
        case 'restore':
            if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
                try {
                    restoreDatabaseFromBackup($_FILES['backup_file']['tmp_name']);
                    $success_message = "Database restored successfully!";
                } catch (Exception $e) {
                    $error_message = "Restore failed: " . $e->getMessage();
                }
            } else {
                $error_message = "Please select a valid backup file.";
            }
            break;
            
        case 'clear_logs':
            try {
                $result = clearSystemLogs($pdo);
                $success_message = "System logs cleared successfully! Removed " . $result . " log entries.";
            } catch (Exception $e) {
                $error_message = "Failed to clear logs: " . $e->getMessage();
            }
            break;
    }
}

// Check if settings table exists, create if it doesn't
try {
    $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($tableCheckStmt->rowCount() == 0) {
        // Create settings table
        $pdo->exec("CREATE TABLE system_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT,
            setting_description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert default settings
        $defaultSettings = [
            ['system_name', 'WasteWise', 'System name shown in emails and browser title'],
            ['contact_email', 'admin@wastewise.com', 'Primary contact email for system notifications'],
            ['contact_phone', '123-456-7890', 'Contact phone number'],
            ['admin_notifications', '1', 'Send email notifications to admin on important events'],
            ['donation_expiry_days', '7', 'Number of days before donations expire if not claimed'],
            ['auto_approve_users', '0', 'Automatically approve new user registrations'],
            ['records_per_page', '10', 'Number of records to display per page in tables']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $insertStmt->execute($setting);
        }
    }
    
    // Check if audit_logs table exists and create if needed
    try {
        $logsTableCheck = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
        if ($logsTableCheck->rowCount() == 0) {
            // Create audit_logs table
            $pdo->exec("CREATE TABLE audit_logs (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT NULL,
                entity_type VARCHAR(50) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL
            )");
        }
    } catch (PDOException $e) {
        $error_message .= " Audit logs table error: " . $e->getMessage();
    }
    
    // Load current settings
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Set variables from database
    $systemName = $settings['system_name'] ?? 'WasteWise';
    $contactEmail = $settings['contact_email'] ?? 'admin@wastewise.com';
    $contactPhone = $settings['contact_phone'] ?? '123-456-7890';
    $adminNotifications = (bool)($settings['admin_notifications'] ?? 1);
    $donationExpiry = (int)($settings['donation_expiry_days'] ?? 7);
    $autoApproveUsers = (bool)($settings['auto_approve_users'] ?? 0);
    $recordsPerPage = (int)($settings['records_per_page'] ?? 10);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    try {
        // Validate and process form data
        $systemName = trim($_POST['system_name'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $adminNotifications = isset($_POST['admin_notifications']);
        $donationExpiry = (int)($_POST['donation_expiry'] ?? 7);
        $autoApproveUsers = isset($_POST['auto_approve_users']);
        $recordsPerPage = (int)($_POST['records_per_page'] ?? 10);
        
        // Validation
        $errors = [];
        if (empty($systemName)) {
            $errors[] = "System name is required";
        }
        
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid contact email is required";
        }
        
        if ($donationExpiry < 1 || $donationExpiry > 30) {
            $errors[] = "Donation expiry days must be between 1 and 30";
        }
        
        if ($recordsPerPage < 5 || $recordsPerPage > 100) {
            $errors[] = "Records per page must be between 5 and 100";
        }
        
        if (empty($errors)) {
            // Update settings
            $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            
            $updateStmt->execute([$systemName, 'system_name']);
            $updateStmt->execute([$contactEmail, 'contact_email']);
            $updateStmt->execute([$contactPhone, 'contact_phone']);
            $updateStmt->execute([$adminNotifications ? '1' : '0', 'admin_notifications']);
            $updateStmt->execute([$donationExpiry, 'donation_expiry_days']);
            $updateStmt->execute([$autoApproveUsers ? '1' : '0', 'auto_approve_users']);
            $updateStmt->execute([$recordsPerPage, 'records_per_page']);
            
            // Add audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, entity_type, created_at)
                VALUES (?, 'Settings Updated', 'System settings were updated', 'settings', NOW())
            ");
            $auditStmt->execute([$_SESSION['user_id']]);
            
            $success_message = "Settings updated successfully!";
        } else {
            $error_message = implode("<br>", $errors);
        }
    } catch (PDOException $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

/**
 * Create a database backup
 *
 * @param PDO $pdo The PDO database connection
 * @return string The backup filename
 */
function createDatabaseBackup($pdo) {
    // Get database connection details from config
    $dbConfig = [
        'host' => 'localhost',
        'name' => 'wastewise',
        'user' => 'root',
        'pass' => ''
    ];
    
    // Create backup directory if it doesn't exist
    $backupDir = '../../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Generate backup filename with timestamp
    $backupFile = $backupDir . 'wastewise_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backupFilePath = realpath(dirname($backupFile)) . '\\' . basename($backupFile);
    
    // Try using native PHP backup method instead of mysqldump
    try {
        // Get all tables
        $tables = [];
        $tablesResult = $pdo->query("SHOW TABLES");
        while ($row = $tablesResult->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $output = "-- WasteWise Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Iterate through tables
        foreach ($tables as $table) {
            // Get create table syntax
            $tableQuery = $pdo->query("SHOW CREATE TABLE `$table`");
            $tableCreate = $tableQuery->fetch(PDO::FETCH_NUM);
            
            $output .= "-- Table structure for table `$table`\n\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $tableCreate[1] . ";\n\n";
            
            // Get table data
            $rowsQuery = $pdo->query("SELECT * FROM `$table`");
            $rows = $rowsQuery->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                $output .= "-- Data for table `$table`\n";
                
                // Build insert statements
                $columns = array_keys($rows[0]);
                $columnList = "`" . implode("`, `", $columns) . "`";
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = "NULL";
                        } else {
                            $values[] = $pdo->quote($value);
                        }
                    }
                    
                    $output .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(", ", $values) . ");\n";
                }
                
                $output .= "\n";
            }
        }
        
        // Save the SQL file
        file_put_contents($backupFile, $output);
        
        // Log the backup action - safely check if audit_logs table exists first
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
            if ($tableCheck->rowCount() > 0) {
                $user_id = $_SESSION['user_id'] ?? 0;
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, details, entity_type, created_at)
                    VALUES (?, 'Database Backup', 'Created database backup file: " . basename($backupFile) . "', 'system', NOW())
                ");
                $stmt->execute([$user_id]);
            }
        } catch (Exception $e) {
            // Silently continue if logging fails - the backup itself worked
        }
        
        return basename($backupFile);
    } catch (Exception $e) {
        throw new Exception("Database backup failed: " . $e->getMessage());
    }
}

/**
 * Restore database from backup file
 *
 * @param string $tempFilePath Path to the uploaded backup file
 * @return void
 */
function restoreDatabaseFromBackup($tempFilePath) {
    // Get database connection details from config
    $dbConfig = [
        'host' => 'localhost',
        'name' => 'wastewise',
        'user' => 'root',
        'pass' => ''
    ];
    
    // Verify the file is a SQL backup file
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $tempFilePath);
    finfo_close($fileInfo);
    
    if ($mimeType !== 'text/plain' && $mimeType !== 'application/octet-stream' && $mimeType !== 'application/sql') {
        throw new Exception("Invalid backup file format. Must be a SQL file.");
    }
    
    // Set up the mysql restore command
    $command = "mysql --host={$dbConfig['host']} --user={$dbConfig['user']}" . 
               (!empty($dbConfig['pass']) ? " --password={$dbConfig['pass']}" : "") . 
               " {$dbConfig['name']} < {$tempFilePath}";
    
    // Execute the command
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    if ($returnVar !== 0) {
        throw new Exception("Database restore failed. Error code: " . $returnVar);
    }
    
    // Log the restore action
    $pdo = getPDO();
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, entity_type, created_at)
        VALUES (?, 'Database Restore', 'Restored database from backup file', 'system', NOW())
    ");
    $stmt->execute([$user_id]);
}

/**
 * Clear system logs
 *
 * @param PDO $pdo The PDO database connection
 * @return int Number of cleared logs
 */
function clearSystemLogs($pdo) {
    // Get the count of logs before deleting
    $countStmt = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $count = $countStmt->fetchColumn();
    
    // Delete logs older than 30 days
    $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    
    // Log the action
    $user_id = $_SESSION['user_id'] ?? 0;
    $logStmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, entity_type, created_at)
        VALUES (?, 'Logs Cleared', 'Cleared " . $count . " log entries older than 30 days', 'system', NOW())
    ");
    $logStmt->execute([$user_id]);
    
    return $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/LGU.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
<body class="bg-gray-50 flex h-screen">
    <?php include '../layout/nav.php'; ?>
    
    <div class="flex-1 p-6 overflow-auto">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center mb-6">
                <h1 class="text-3xl font-bold text-primarycol">System Settings</h1>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">General Settings</h2>
                </div>
                
                <form method="POST" action="" class="p-6 space-y-6">
                    <!-- System Information -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">System Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="system_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    System Name *
                                </label>
                                <input type="text" id="system_name" name="system_name" value="<?= htmlspecialchars($systemName) ?>" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <p class="text-xs text-gray-500 mt-1">Displayed in emails and browser title</p>
                            </div>
                            
                            <div>
                                <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Contact Email *
                                </label>
                                <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($contactEmail) ?>" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <p class="text-xs text-gray-500 mt-1">Primary email for system notifications</p>
                            </div>
                            
                            <div>
                                <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    Contact Phone
                                </label>
                                <input type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($contactPhone) ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Behavior -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="text-lg font-medium text-gray-700 mb-4">System Behavior</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="donation_expiry" class="block text-sm font-medium text-gray-700 mb-1">
                                    Donation Expiry (days)
                                </label>
                                <input type="number" id="donation_expiry" name="donation_expiry" value="<?= $donationExpiry ?>" min="1" max="30"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <p class="text-xs text-gray-500 mt-1">Days before unclaimed donations expire</p>
                            </div>
                            
                            <div>
                                <label for="records_per_page" class="block text-sm font-medium text-gray-700 mb-1">
                                    Records Per Page
                                </label>
                                <input type="number" id="records_per_page" name="records_per_page" value="<?= $recordsPerPage ?>" min="5" max="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <p class="text-xs text-gray-500 mt-1">Number of records to display in tables</p>
                            </div>
                        </div>
                        
                        <div class="mt-4 space-y-4">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" id="admin_notifications" name="admin_notifications" <?= $adminNotifications ? 'checked' : '' ?>
                                           class="h-4 w-4 text-primarycol border-gray-300 rounded focus:ring-primarycol">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="admin_notifications" class="font-medium text-gray-700">Enable Admin Notifications</label>
                                    <p class="text-gray-500">Receive email notifications for important system events</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" id="auto_approve_users" name="auto_approve_users" <?= $autoApproveUsers ? 'checked' : '' ?>
                                           class="h-4 w-4 text-primarycol border-gray-300 rounded focus:ring-primarycol">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="auto_approve_users" class="font-medium text-gray-700">Auto-Approve Users</label>
                                    <p class="text-gray-500">Automatically approve new user registrations (not recommended for NGO accounts)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3">
                        <button type="reset" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">
                            Reset
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primarycol text-white rounded-md hover:bg-fourth focus:outline-none focus:ring-2 focus:ring-primarycol">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Additional Settings Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                <!-- Backup & Restore -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-database text-primarycol mr-3 text-xl"></i>
                        <h3 class="text-lg font-medium text-gray-800">Backup & Restore</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Create a backup of your system data or restore from a previous backup.</p>
                    
                    <div class="flex flex-wrap space-x-3 mb-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="backup">
                            <button type="submit" class="px-4 py-2 mb-2 bg-primarycol text-white rounded-md hover:bg-fourth focus:outline-none focus:ring-2 focus:ring-primarycol">
                                <i class="fas fa-download mr-1"></i> Backup Data
                            </button>
                        </form>
                        
                        <button onclick="document.getElementById('restore-modal').classList.remove('hidden')" class="px-4 py-2 mb-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">
                            <i class="fas fa-upload mr-1"></i> Restore
                        </button>
                    </div>
                    
                    <!-- Backup List -->
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Recent Backups:</h4>
                        <div class="overflow-y-auto max-h-32 text-sm">
                            <?php
                            $backupDir = '../../backups/';
                            if (file_exists($backupDir)) {
                                $backupFiles = glob($backupDir . '*.sql');
                                usort($backupFiles, function($a, $b) {
                                    return filemtime($b) - filemtime($a);
                                });
                                
                                if (count($backupFiles) > 0) {
                                    foreach (array_slice($backupFiles, 0, 5) as $file) {
                                        $filename = basename($file);
                                        $date = date('M d, Y H:i', filemtime($file));
                                        echo "<div class='py-1 flex justify-between'>";
                                        echo "<span class='text-gray-700'>{$filename}</span>";
                                        echo "<span class='text-gray-500 text-xs'>{$date}</span>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<p class='text-gray-500'>No backups found</p>";
                                }
                            } else {
                                echo "<p class='text-gray-500'>Backup directory not found</p>";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Restore Modal -->
                    <div id="restore-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Restore Database</h3>
                            <p class="text-red-600 mb-4 text-sm">Warning: This will overwrite your current database. Make sure you have a backup first!</p>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="restore">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Backup File</label>
                                    <input type="file" name="backup_file" accept=".sql" class="block w-full text-sm text-gray-500
                                        file:mr-4 file:py-2 file:px-4
                                        file:rounded-md file:border-0
                                        file:text-sm file:font-semibold
                                        file:bg-primarycol file:text-white
                                        hover:file:bg-fourth">
                                </div>
                                
                                <div class="flex justify-end space-x-3">
                                    <button type="button" onclick="document.getElementById('restore-modal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                        Restore Database
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- System Logs -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-history text-primarycol mr-3 text-xl"></i>
                        <h3 class="text-lg font-medium text-gray-800">System Logs</h3>
                    </div>
                    <p class="text-gray-600 mb-4">View system activity logs and audit trails for troubleshooting.</p>
                    <div class="flex space-x-3">
                        <a href="audit_logs.php" class="px-4 py-2 bg-primarycol text-white rounded-md hover:bg-fourth focus:outline-none focus:ring-2 focus:ring-primarycol">
                            <i class="fas fa-list-ul mr-1"></i> View Logs
                        </a>
                        
                        <!-- Clear Logs Form -->
                        <button onclick="confirmClearLogs()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">
                            <i class="fas fa-trash-alt mr-1"></i> Clear Old Logs
                        </button>
                        
                        <form id="clear-logs-form" method="POST" class="hidden">
                            <input type="hidden" name="action" value="clear_logs">
                        </form>
                    </div>
                    
                    <!-- Recent Logs -->
                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Recent System Activity:</h4>
                        <div class="overflow-y-auto max-h-32 text-sm">
                            <?php
                            try {
                                $logsStmt = $pdo->query("
                                    SELECT a.action, a.details, a.created_at, u.fname, u.lname 
                                    FROM audit_logs a 
                                    LEFT JOIN users u ON a.user_id = u.id 
                                    ORDER BY a.created_at DESC 
                                    LIMIT 5
                                ");
                                $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($logs) > 0) {
                                    foreach ($logs as $log) {
                                        $date = date('M d, Y H:i', strtotime($log['created_at']));
                                        $user = !empty($log['fname']) ? $log['fname'] . ' ' . $log['lname'] : 'System';
                                        echo "<div class='py-1'>";
                                        echo "<div class='flex justify-between'>";
                                        echo "<span class='font-medium text-gray-700'>{$log['action']}</span>";
                                        echo "<span class='text-gray-500 text-xs'>{$date}</span>";
                                        echo "</div>";
                                        echo "<p class='text-gray-600 text-xs'>{$log['details']} - by {$user}</p>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<p class='text-gray-500'>No logs found</p>";
                                }
                            } catch (Exception $e) {
                                echo "<p class='text-red-500'>Error loading logs: " . $e->getMessage() . "</p>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Confirmation for clearing logs
        function confirmClearLogs() {
            if (confirm('Are you sure you want to clear logs older than 30 days? This action cannot be undone.')) {
                document.getElementById('clear-logs-form').submit();
            }
        }
    </script>
</body>
</html>