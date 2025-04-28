<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access
checkAuth(['admin']);

$pdo = getPDO();

// Get filters from form submission
$reportType = isset($_POST['report_type']) ? $_POST['report_type'] : 'donations';
$format = isset($_POST['format']) ? $_POST['format'] : 'csv';
$dateRange = isset($_POST['date_range']) ? $_POST['date_range'] : 'this_month';
$branchId = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
$ngoId = isset($_POST['ngo_id']) ? (int)$_POST['ngo_id'] : 0;
$includeExpired = isset($_POST['include_expired']) && $_POST['include_expired'] === '1';

// Set date range based on selection
$startDate = date('Y-m-01'); // First day of current month by default
$endDate = date('Y-m-t');    // Last day of current month by default

if ($dateRange === 'today') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($dateRange === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($dateRange === 'this_month') {
    // Already set as default
} elseif ($dateRange === 'last_month') {
    $startDate = date('Y-m-01', strtotime('first day of last month'));
    $endDate = date('Y-m-t', strtotime('last day of last month'));
} elseif ($dateRange === 'custom' && isset($_POST['start_date'], $_POST['end_date'])) {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
}

// Create export functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $fileName = 'wastewise_' . $reportType . '_' . date('Y-m-d') . ($branchId ? '_branch' . $branchId : '') . ($ngoId ? '_ngo' . $ngoId : '');
    
    // Export logic based on report type
    if ($reportType === 'donations') {
        exportDonationReport($pdo, $format, $fileName, $startDate, $endDate, $branchId, $includeExpired);
    } elseif ($reportType === 'ngo_requests') {
        exportNGORequestsReport($pdo, $format, $fileName, $startDate, $endDate, $branchId, $ngoId);
    } elseif ($reportType === 'received_donations') {
        exportReceivedDonationsReport($pdo, $format, $fileName, $startDate, $endDate, $branchId, $ngoId);
    }
}

// Get all branches for dropdown
$branchQuery = $pdo->query("SELECT id, name FROM branches ORDER BY name");
$branches = $branchQuery->fetchAll(PDO::FETCH_ASSOC);

// Get all NGOs for dropdown
$ngoQuery = $pdo->query("
    SELECT 
        u.id,
        COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as name
    FROM users u
    LEFT JOIN ngo_profiles np ON u.id = np.user_id
    WHERE u.role = 'ngo'
    ORDER BY name
");
$ngos = $ngoQuery->fetchAll(PDO::FETCH_ASSOC);

/**
 * Export donation products report
 */
function exportDonationReport($pdo, $format, $fileName, $startDate, $endDate, $branchId = 0, $includeExpired = false) {
    $sql = "
        SELECT 
            dp.id as donation_id,
            pi.name as product_name,
            pi.category,
            dp.quantity_available,
            dp.expiry_date,
            dp.status,
            dp.donation_priority,
            dp.auto_approval,
            dp.creation_date,
            b.name as branch_name,
            b.address as branch_address,
            (SELECT COUNT(*) FROM ngo_donation_requests WHERE waste_id = dp.waste_id) as request_count
        FROM donation_products dp
        JOIN product_info pi ON dp.product_id = pi.id
        JOIN branches b ON dp.branch_id = b.id
        WHERE dp.creation_date BETWEEN :start_date AND :end_date
    ";
    
    $params = [
        ':start_date' => $startDate . ' 00:00:00',
        ':end_date' => $endDate . ' 23:59:59'
    ];
    
    if ($branchId > 0) {
        $sql .= " AND dp.branch_id = :branch_id";
        $params[':branch_id'] = $branchId;
    }
    
    if (!$includeExpired) {
        $sql .= " AND dp.status != 'expired'";
    }
    
    $sql .= " ORDER BY dp.creation_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare data for export
    $exportData = [];
    
    // Add headers
    $headers = [
        'ID', 'Product Name', 'Category', 'Quantity Available', 'Expiry Date', 
        'Status', 'Priority', 'Auto-Approval', 'Created Date', 'Branch', 'Branch Address',
        'Request Count'
    ];
    $exportData[] = $headers;
    
    // Add rows
    foreach ($data as $row) {
        $exportData[] = [
            $row['donation_id'],
            $row['product_name'],
            $row['category'],
            $row['quantity_available'],
            $row['expiry_date'],
            $row['status'],
            $row['donation_priority'],
            $row['auto_approval'] ? 'Yes' : 'No',
            $row['creation_date'],
            $row['branch_name'],
            $row['branch_address'],
            $row['request_count']
        ];
    }
    
    // Export the data
    outputReport($exportData, $format, $fileName);
}

/**
 * Export NGO requests report
 */
function exportNGORequestsReport($pdo, $format, $fileName, $startDate, $endDate, $branchId = 0, $ngoId = 0) {
    $sql = "
        SELECT 
            ndr.id as request_id,
            COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
            pi.name as product_name,
            pi.category,
            ndr.quantity_requested,
            ndr.request_date,
            ndr.pickup_date,
            ndr.pickup_time,
            ndr.status,
            ndr.is_received,
            ndr.received_at,
            b.name as branch_name,
            ndr.ngo_notes,
            ndr.admin_notes
        FROM ngo_donation_requests ndr
        JOIN users u ON ndr.ngo_id = u.id
        LEFT JOIN ngo_profiles np ON u.id = np.user_id
        JOIN product_info pi ON ndr.product_id = pi.id
        JOIN branches b ON ndr.branch_id = b.id
        WHERE ndr.request_date BETWEEN :start_date AND :end_date
    ";
    
    $params = [
        ':start_date' => $startDate . ' 00:00:00',
        ':end_date' => $endDate . ' 23:59:59'
    ];
    
    if ($branchId > 0) {
        $sql .= " AND ndr.branch_id = :branch_id";
        $params[':branch_id'] = $branchId;
    }
    
    if ($ngoId > 0) {
        $sql .= " AND ndr.ngo_id = :ngo_id";
        $params[':ngo_id'] = $ngoId;
    }
    
    $sql .= " ORDER BY ndr.request_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare data for export
    $exportData = [];
    
    // Add headers
    $headers = [
        'Request ID', 'NGO Name', 'Product Name', 'Category', 'Quantity Requested', 
        'Request Date', 'Pickup Date', 'Pickup Time', 'Status', 'Received', 'Received Date',
        'Branch', 'NGO Notes', 'Admin Notes'
    ];
    $exportData[] = $headers;
    
    // Add rows
    foreach ($data as $row) {
        $exportData[] = [
            $row['request_id'],
            $row['ngo_name'],
            $row['product_name'],
            $row['category'],
            $row['quantity_requested'],
            $row['request_date'],
            $row['pickup_date'],
            $row['pickup_time'],
            $row['status'],
            $row['is_received'] ? 'Yes' : 'No',
            $row['received_at'] ?: 'N/A',
            $row['branch_name'],
            $row['ngo_notes'],
            $row['admin_notes']
        ];
    }
    
    // Export the data
    outputReport($exportData, $format, $fileName);
}

/**
 * Export received donations report
 */
function exportReceivedDonationsReport($pdo, $format, $fileName, $startDate, $endDate, $branchId = 0, $ngoId = 0) {
    $sql = "
        SELECT 
            dp.id as donation_id,
            COALESCE(np.organization_name, u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
            pi.name as product_name,
            pi.category,
            dp.received_quantity,
            dp.received_date,
            ndr.request_date,
            ndr.pickup_date,
            ndr.quantity_requested,
            b.name as branch_name,
            CONCAT(s.fname, ' ', s.lname) as staff_name,
            dp.notes
        FROM donated_products dp
        JOIN ngo_donation_requests ndr ON dp.donation_request_id = ndr.id
        JOIN users u ON ndr.ngo_id = u.id
        LEFT JOIN ngo_profiles np ON u.id = np.user_id
        JOIN product_info pi ON ndr.product_id = pi.id
        JOIN branches b ON ndr.branch_id = b.id
        LEFT JOIN users s ON dp.staff_id = s.id
        WHERE dp.received_date BETWEEN :start_date AND :end_date
    ";
    
    $params = [
        ':start_date' => $startDate . ' 00:00:00',
        ':end_date' => $endDate . ' 23:59:59'
    ];
    
    if ($branchId > 0) {
        $sql .= " AND ndr.branch_id = :branch_id";
        $params[':branch_id'] = $branchId;
    }
    
    if ($ngoId > 0) {
        $sql .= " AND ndr.ngo_id = :ngo_id";
        $params[':ngo_id'] = $ngoId;
    }
    
    $sql .= " ORDER BY dp.received_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare data for export
    $exportData = [];
    
    // Add headers
    $headers = [
        'Receipt ID', 'NGO Name', 'Product Name', 'Category', 'Received Quantity',
        'Received Date', 'Request Date', 'Pickup Date', 'Requested Quantity',
        'Branch', 'Staff Name', 'Notes'
    ];
    $exportData[] = $headers;
    
    // Add rows
    foreach ($data as $row) {
        $exportData[] = [
            $row['donation_id'],
            $row['ngo_name'],
            $row['product_name'],
            $row['category'],
            $row['received_quantity'],
            $row['received_date'],
            $row['request_date'],
            $row['pickup_date'],
            $row['quantity_requested'],
            $row['branch_name'],
            $row['staff_name'],
            $row['notes']
        ];
    }
    
    // Export the data
    outputReport($exportData, $format, $fileName);
}

/**
 * Output report data in specified format
 */
function outputReport($data, $format, $fileName) {
    // Set appropriate headers to prevent caching
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $fileName . '.csv"');
    header('Pragma: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write each row to CSV
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    // Close output stream
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Donation Reports - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/LGU.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primarycol: '#47663B',
                        primarylight: '#5d8a4e',
                        primarydark: '#385029',
                        sec: '#E8ECD7',
                        third: '#EED3B1',
                        fourth: '#1F4529',
                        accent: '#ffa62b',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .report-option {
            transition: all 0.2s ease-in-out;
            border: 2px solid transparent;
        }
        
        .report-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .report-option.selected {
            border-color: #47663B;
            background-color: #E8ECD7;
        }
    </style>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include '../layout/nav.php' ?>
    
    <div class="flex-1 overflow-auto p-6">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-primarycol">Export Donation Reports</h1>
                    <p class="text-sm text-gray-500">Generate and download reports for donation activities</p>
                </div>
                <a href="donation_history_admin.php" class="btn btn-sm bg-gray-200 text-gray-700 hover:bg-gray-300 flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to History
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="">
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold text-primarycol mb-4">1. Choose Report Type</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="report-option rounded-lg p-4 bg-white hover:bg-sec cursor-pointer" data-report-type="donations">
                                <div class="flex items-center justify-center mb-3">
                                    <div class="bg-primarycol bg-opacity-10 p-3 rounded-full">
                                        <i class="fas fa-box text-primarycol text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="font-medium text-center">Donation Products</h3>
                                <p class="text-xs text-gray-500 text-center mt-2">All donation products with availability and request counts</p>
                                <input type="radio" name="report_type" value="donations" class="hidden report-radio" checked>
                            </div>
                            
                            <div class="report-option rounded-lg p-4 bg-white hover:bg-sec cursor-pointer" data-report-type="ngo_requests">
                                <div class="flex items-center justify-center mb-3">
                                    <div class="bg-blue-500 bg-opacity-10 p-3 rounded-full">
                                        <i class="fas fa-clipboard-list text-blue-500 text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="font-medium text-center">NGO Requests</h3>
                                <p class="text-xs text-gray-500 text-center mt-2">All NGO requests with status and pickup details</p>
                                <input type="radio" name="report_type" value="ngo_requests" class="hidden report-radio">
                            </div>
                            
                            <div class="report-option rounded-lg p-4 bg-white hover:bg-sec cursor-pointer" data-report-type="received_donations">
                                <div class="flex items-center justify-center mb-3">
                                    <div class="bg-green-500 bg-opacity-10 p-3 rounded-full">
                                        <i class="fas fa-hand-holding-heart text-green-500 text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="font-medium text-center">Received Donations</h3>
                                <p class="text-xs text-gray-500 text-center mt-2">Completed donations received by NGOs</p>
                                <input type="radio" name="report_type" value="received_donations" class="hidden report-radio">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold text-primarycol mb-4">2. Export Format</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="format-option report-option rounded-lg p-4 bg-white hover:bg-sec cursor-pointer" data-format="csv">
                                <div class="flex items-center justify-center mb-3">
                                    <div class="bg-red-500 bg-opacity-10 p-3 rounded-full">
                                        <i class="fas fa-file-csv text-red-500 text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="font-medium text-center">CSV</h3>
                                <p class="text-xs text-gray-500 text-center mt-2">Compatible with Excel, Google Sheets, etc.</p>
                                <input type="radio" name="format" value="csv" class="hidden format-radio" checked>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold text-primarycol mb-4">3. Filter Options</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                                <select name="date_range" id="date_range" class="select select-bordered w-full">
                                    <option value="this_month">This Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="this_week">This Week</option>
                                    <option value="today">Today</option>
                                    <option value="custom">Custom Date Range</option>
                                </select>
                                
                                <div id="custom_date_container" class="mt-4 hidden grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                        <input type="date" name="start_date" class="input input-bordered w-full" value="<?= date('Y-m-01') ?>">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                        <input type="date" name="end_date" class="input input-bordered w-full" value="<?= date('Y-m-t') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                                <select name="branch_id" class="select select-bordered w-full">
                                    <option value="0">All Branches</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="ngo-filter">
                                <label class="block text-sm font-medium text-gray-700 mb-1">NGO</label>
                                <select name="ngo_id" class="select select-bordered w-full">
                                    <option value="0">All NGOs</option>
                                    <?php foreach ($ngos as $ngo): ?>
                                        <option value="<?= $ngo['id'] ?>"><?= htmlspecialchars($ngo['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="donations-option">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Include Expired Items</label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="include_expired" value="1" class="checkbox checkbox-sm">
                                    <span class="label-text">Yes, include expired items in report</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-6 flex justify-end">
                        <button type="submit" name="export" class="btn bg-primarycol hover:bg-primarydark text-white flex items-center gap-2">
                            <i class="fas fa-file-export"></i> Generate & Download Report
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="mt-8 bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-primarycol mb-4">Report Descriptions</h2>
                
                <div class="space-y-4">
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-medium">Donation Products Report</h3>
                        <p class="text-sm text-gray-600 mt-1">This report includes all donation products available in the system with their current status, quantities, and request counts. Use this report to track what items are available for NGOs to request.</p>
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Included fields:</strong> Product ID, Product Name, Category, Quantity Available, Expiry Date, Status, Priority, Auto-Approval, Creation Date, Branch, Branch Address, Request Count
                        </div>
                    </div>
                    
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-medium">NGO Requests Report</h3>
                        <p class="text-sm text-gray-600 mt-1">This report includes all donation requests submitted by NGOs, including pending, approved, and rejected requests. Use this report to analyze NGO request patterns and approval rates.</p>
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Included fields:</strong> Request ID, NGO Name, Product Name, Category, Quantity Requested, Request Date, Pickup Date, Pickup Time, Status, Received Status, Received Date, Branch, NGO Notes, Admin Notes
                        </div>
                    </div>
                    
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-medium">Received Donations Report</h3>
                        <p class="text-sm text-gray-600 mt-1">This report includes all donations that have been successfully received by NGOs. Use this report to track completed donations and impact metrics.</p>
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Included fields:</strong> Receipt ID, NGO Name, Product Name, Category, Received Quantity, Received Date, Request Date, Pickup Date, Requested Quantity, Branch, Staff Name, Notes
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide custom date range fields
        document.getElementById('date_range').addEventListener('change', function() {
            const customDateContainer = document.getElementById('custom_date_container');
            if (this.value === 'custom') {
                customDateContainer.classList.remove('hidden');
            } else {
                customDateContainer.classList.add('hidden');
            }
        });
        
        // Report type selection
        document.querySelectorAll('.report-option').forEach(function(option) {
            option.addEventListener('click', function() {
                // Update visual selection
                document.querySelectorAll('.report-option').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                
                // Update hidden radio button
                const reportType = this.getAttribute('data-report-type');
                document.querySelector(`input[name="report_type"][value="${reportType}"]`).checked = true;
                
                // Show/hide NGO filter based on report type
                const ngoFilter = document.querySelector('.ngo-filter');
                if (reportType === 'ngo_requests' || reportType === 'received_donations') {
                    ngoFilter.classList.remove('hidden');
                } else {
                    ngoFilter.classList.add('hidden');
                }
                
                // Show/hide donation options
                const donationsOption = document.querySelector('.donations-option');
                if (reportType === 'donations') {
                    donationsOption.classList.remove('hidden');
                } else {
                    donationsOption.classList.add('hidden');
                }
            });
        });
        
        // Format selection
        document.querySelectorAll('.format-option').forEach(function(option) {
            option.addEventListener('click', function() {
                // Update visual selection
                document.querySelectorAll('.format-option').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                
                // Update hidden radio button
                const format = this.getAttribute('data-format');
                document.querySelector(`input[name="format"][value="${format}"]`).checked = true;
            });
        });
        
        // Set initial selections
        document.querySelector('.report-option[data-report-type="donations"]').classList.add('selected');
        document.querySelector('.format-option[data-format="csv"]').classList.add('selected');
    </script>
</body>
</html>