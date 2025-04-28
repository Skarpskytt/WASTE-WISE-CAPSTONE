<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_type = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$entity_type = isset($_GET['entity_type']) ? $_GET['entity_type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Load system settings for records per page
try {
    $settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'records_per_page'");
    $recordsPerPage = (int)($settingsStmt->fetchColumn() ?: 10);
} catch (PDOException $e) {
    $recordsPerPage = 10; // Default if setting not available
}

// Current page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Build the query
$params = [];
$whereClauses = [];

// Base query
$baseQuery = "
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
";

// Add filters
if (!empty($start_date)) {
    $whereClauses[] = "a.created_at >= ?";
    $params[] = $start_date . ' 00:00:00';
}

if (!empty($end_date)) {
    $whereClauses[] = "a.created_at <= ?";
    $params[] = $end_date . ' 23:59:59';
}

if (!empty($user_id)) {
    $whereClauses[] = "a.user_id = ?";
    $params[] = $user_id;
}

if (!empty($action_type)) {
    $whereClauses[] = "a.action = ?";
    $params[] = $action_type;
}

if (!empty($entity_type)) {
    $whereClauses[] = "a.entity_type = ?";
    $params[] = $entity_type;
}

if (!empty($search)) {
    $whereClauses[] = "(a.details LIKE ? OR a.action LIKE ? OR CONCAT(u.fname, ' ', u.lname) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Combine WHERE clauses
$whereSQL = empty($whereClauses) ? "" : "WHERE " . implode(" AND ", $whereClauses);

// Count total records
$countQuery = "SELECT COUNT(*) " . $baseQuery . $whereSQL;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get records for current page
$query = "
    SELECT 
        a.id, 
        a.action, 
        a.details, 
        a.entity_type, 
        a.created_at, 
        a.ip_address,
        u.id as user_id, 
        CONCAT(u.fname, ' ', u.lname) as user_name
    " . $baseQuery . $whereSQL . "
    ORDER BY a.created_at DESC
    LIMIT $offset, $recordsPerPage
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique action types for filter
$actionTypes = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get unique entity types for filter
$entityTypes = $pdo->query("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users = $pdo->query("
    SELECT DISTINCT u.id, CONCAT(u.fname, ' ', u.lname) as name 
    FROM users u 
    INNER JOIN audit_logs a ON u.id = a.user_id 
    ORDER BY name
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Remove LIMIT from query for export
    $exportQuery = "
        SELECT 
            a.id, 
            a.action, 
            a.details, 
            a.entity_type, 
            a.created_at, 
            a.ip_address,
            CONCAT(u.fname, ' ', u.lname) as user_name
        " . $baseQuery . $whereSQL . "
        ORDER BY a.created_at DESC
    ";
    
    $exportStmt = $pdo->prepare($exportQuery);
    $exportStmt->execute($params);
    $exportLogs = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_export_' . date('Y-m-d') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header
    fputcsv($output, ['ID', 'Action', 'Details', 'Entity Type', 'Timestamp', 'IP Address', 'User']);
    
    // Add data rows
    foreach ($exportLogs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['action'],
            $log['details'],
            $log['entity_type'],
            $log['created_at'],
            $log['ip_address'],
            $log['user_name'] ?? 'System'
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}

// Function to build pagination URL
function buildUrl($page = null, $params = []) {
    $queryParams = $_GET;
    
    if ($page !== null) {
        $queryParams['page'] = $page;
    }
    
    foreach ($params as $key => $value) {
        $queryParams[$key] = $value;
    }
    
    return 'audit_logs.php?' . http_build_query($queryParams);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - WasteWise</title>
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
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold text-primarycol">System Audit Logs</h1>
                
                <div class="flex space-x-2">
                    <a href="settings.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Settings
                    </a>
                    
                    <a href="<?= buildUrl(null, ['export' => 'csv']) ?>" class="px-4 py-2 bg-primarycol text-white rounded-md hover:bg-fourth flex items-center">
                        <i class="fas fa-file-export mr-2"></i> Export CSV
                    </a>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Filter Logs</h2>
                
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Date Range -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                            <div class="flex space-x-2">
                                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <span class="self-center">to</span>
                                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                            </div>
                        </div>
                        
                        <!-- User Filter -->
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                            <select id="user_id" name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <option value="">All Users</option>
                                <option value="-1" <?= $user_id === -1 ? 'selected' : '' ?>>System</option>
                                <?php foreach ($users as $id => $name): ?>
                                <option value="<?= $id ?>" <?= $user_id === $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Action Type Filter -->
                        <div>
                            <label for="action_type" class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                            <select id="action_type" name="action_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <option value="">All Actions</option>
                                <?php foreach ($actionTypes as $type): ?>
                                <option value="<?= $type ?>" <?= $action_type === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Entity Type Filter -->
                        <div>
                            <label for="entity_type" class="block text-sm font-medium text-gray-700 mb-1">Entity Type</label>
                            <select id="entity_type" name="entity_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                                <option value="">All Entities</option>
                                <?php foreach ($entityTypes as $type): ?>
                                <option value="<?= $type ?>" <?= $entity_type === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Search -->
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search in logs..." 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-primarycol/30">
                        </div>
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="flex justify-end space-x-3">
                        <a href="audit_logs.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">
                            Reset
                        </a>
                        <button type="submit" class="px-4 py-2 bg-primarycol text-white rounded-md hover:bg-fourth focus:outline-none focus:ring-2 focus:ring-primarycol">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Results Summary -->
            <div class="flex justify-between items-center mb-4">
                <p class="text-gray-700">
                    Showing <?= min($totalRecords, ($page - 1) * $recordsPerPage + 1) ?> to 
                    <?= min($totalRecords, $page * $recordsPerPage) ?> of <?= $totalRecords ?> logs
                </p>
                
                <!-- Pagination (Top) -->
                <?php if ($totalPages > 1): ?>
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                    <a href="<?= buildUrl($page - 1) ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, min($page - 2, $totalPages - 4));
                    $endPage = min($totalPages, max($page + 2, 5));
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                    <a href="<?= buildUrl($i) ?>" class="px-3 py-1 rounded <?= $i === $page ? 'bg-primarycol text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= buildUrl($page + 1) ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Logs Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Details
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Entity
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date & Time
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                IP Address
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php
                                    // Different colors for different actions
                                    if (strpos($log['action'], 'Create') !== false || strpos($log['action'], 'Add') !== false) {
                                        echo 'bg-green-100 text-green-800';
                                    } elseif (strpos($log['action'], 'Delete') !== false || strpos($log['action'], 'Remove') !== false) {
                                        echo 'bg-red-100 text-red-800';
                                    } elseif (strpos($log['action'], 'Update') !== false || strpos($log['action'], 'Edit') !== false) {
                                        echo 'bg-blue-100 text-blue-800';
                                    } elseif (strpos($log['action'], 'Login') !== false) {
                                        echo 'bg-yellow-100 text-yellow-800';
                                    } elseif (strpos($log['action'], 'Backup') !== false || strpos($log['action'], 'Restore') !== false) {
                                        echo 'bg-purple-100 text-purple-800';
                                    } else {
                                        echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-md truncate" title="<?= htmlspecialchars($log['details']) ?>">
                                        <?= htmlspecialchars($log['details']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= !empty($log['user_name']) ? htmlspecialchars($log['user_name']) : 'System' ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($log['entity_type'] ?? 'general') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No logs found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination (Bottom) -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-center">
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                    <a href="<?= buildUrl($page - 1) ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, min($page - 2, $totalPages - 4));
                    $endPage = min($totalPages, max($page + 2, 5));
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                    <a href="<?= buildUrl($i) ?>" class="px-3 py-1 rounded <?= $i === $page ? 'bg-primarycol text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= buildUrl($page + 1) ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Add any JavaScript functionality here
    </script>
</body>
</html>