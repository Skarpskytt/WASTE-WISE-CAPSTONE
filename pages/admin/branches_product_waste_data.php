<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Get branch parameter instead of hard-coding
$branchId = isset($_GET['branch']) ? (int)$_GET['branch'] : 0; // 0 = all branches

// Get search parameters with proper error handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : '';

// -----------------------
// PRODUCT WASTE PAGINATION
// -----------------------
$prod_page = isset($_GET['prod_page']) && is_numeric($_GET['prod_page']) ? (int) $_GET['prod_page'] : 1;
$prod_per_page = 10;

// Build the query with filters - modified to support all branches or specific branch
$countQuery = "SELECT COUNT(*) FROM product_waste pw 
               JOIN product_info p ON pw.product_id = p.id
               JOIN users u ON pw.staff_id = u.id
               JOIN branches b ON pw.branch_id = b.id
               WHERE 1=1";
               
// Main data query - added branch name
$dataQuery = "SELECT pw.*, p.name as product_name, p.category,
              CONCAT(u.fname, ' ', u.lname) as staff_name,
              b.name as branch_name, b.branch_type
              FROM product_waste pw
              JOIN product_info p ON pw.product_id = p.id
              JOIN users u ON pw.staff_id = u.id
              JOIN branches b ON pw.branch_id = b.id
              WHERE 1=1";

$params = [];

// Add branch filter if specified
if ($branchId > 0) {
    $countQuery .= " AND pw.branch_id = ?";
    $dataQuery .= " AND pw.branch_id = ?";
    $params[] = $branchId;
}

// Add search filter
if (!empty($search)) {
    $countQuery .= " AND (p.name LIKE ? OR p.category LIKE ? OR 
                    pw.waste_reason LIKE ? OR pw.disposal_method LIKE ? OR
                    CONCAT(u.fname, ' ', u.lname) LIKE ? OR
                    b.name LIKE ?)";
    $dataQuery .= " AND (p.name LIKE ? OR p.category LIKE ? OR 
                   pw.waste_reason LIKE ? OR pw.disposal_method LIKE ? OR
                   CONCAT(u.fname, ' ', u.lname) LIKE ? OR
                   b.name LIKE ?)";
    
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add date range filter
if (!empty($start_date)) {
    $countQuery .= " AND DATE(pw.waste_date) >= ?";
    $dataQuery .= " AND DATE(pw.waste_date) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $countQuery .= " AND DATE(pw.waste_date) <= ?";
    $dataQuery .= " AND DATE(pw.waste_date) <= ?";
    $params[] = $end_date;
}

// First, get the total count with filters
$prodCountStmt = $pdo->prepare($countQuery);
$prodCountStmt->execute($params);
$prod_total = $prodCountStmt->fetchColumn();

// Then calculate pagination values
$prod_total_pages = ceil($prod_total / $prod_per_page);
$prod_offset = ($prod_page - 1) * $prod_per_page;

// Finally, add the LIMIT and OFFSET to the data query
$dataQuery .= " ORDER BY pw.waste_date DESC LIMIT " . (int)$prod_per_page . " OFFSET " . (int)$prod_offset;

// Fetch Product Waste Data with filters
$prodStmt = $pdo->prepare($dataQuery);
$prodStmt->execute($params);
$productWasteData = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch name if specific branch is selected
$branchName = "All Branches";
if ($branchId > 0) {
    $branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
    $branchStmt->execute([$branchId]);
    $branchName = $branchStmt->fetchColumn() ?: "Branch $branchId";
}

// Get all branches for the dropdown
$branchesStmt = $pdo->query("SELECT id, name, branch_type FROM branches ORDER BY branch_type, name");
$branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);

// Generate pagination URL with filters preserved
function getPaginationUrl($page) {
    $params = $_GET;
    $params['prod_page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branchName) ?> - Product Waste Data</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
        // Initialize date pickers with improved UX
        $(".datepicker").flatpickr({
            dateFormat: "Y-m-d",
            allowInput: true,
            wrap: true
        });
        
        // Form submission
        $('#filterForm').on('submit', function() {
            // Make sure empty dates don't get submitted
            if ($('#start_date').val() === '') {
                $('#start_date').prop('disabled', true);
            }
            if ($('#end_date').val() === '') {
                $('#end_date').prop('disabled', true);
            }
        });
        
        // Clear filters
        $('#clearFilters').on('click', function() {
            window.location.href = 'branches_product_waste_data.php';
        });
        
        $('#toggleSidebar').on('click', function() {
            $('#sidebar').toggleClass('-translate-x-full');
        });

        $('#closeSidebar').on('click', function() {
            $('#sidebar').addClass('-translate-x-full');
        });

        // Print functionality
        window.printPage = function() {
            window.print();
        };
     });
    </script>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #sidebar, nav, .hidden-print, button, .filter-section {
                display: none !important;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table, th, td {
                border: 1px solid #ddd;
            }
            th {
                background-color: #f2f2f2 !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
            }
            .print-header {
                text-align: center;
                margin-bottom: 20px;
            }
        }
    </style>
</head>

<body class="flex h-screen">
<?php include '../layout/nav.php'?>

  <div class="flex-1 p-6 overflow-auto space-y-12">
    <!-- Branch Header -->
    <div class="bg-sec p-4 rounded-lg shadow mb-6">
      <h1 class="text-3xl font-bold text-primarycol"><?= htmlspecialchars($branchName) ?> Product Excess Data</h1>
      <p class="text-gray-600">View and manage product excess records across <?= $branchId > 0 ? 'this branch' : 'all branches' ?></p>
    </div>
    
    <!-- Filter Section - Enhanced with branch dropdown -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
      <h2 class="text-xl font-semibold mb-4 text-primarycol">Filter Records</h2>
      <form id="filterForm" action="" method="GET" class="flex flex-wrap gap-3 items-end">
        <!-- New Branch Selection Dropdown -->
        <div>
          <label for="branch" class="block mb-1 text-sm font-medium text-gray-700">Select Branch</label>
          <select 
            id="branch" 
            name="branch" 
            class="border w-60 p-2 rounded focus:ring focus:border-primarycol"
            onchange="this.form.submit()"
          >
            <option value="0">All Branches</option>
            <optgroup label="Internal Branches">
              <?php foreach ($branches as $branch): ?>
                <?php if ($branch['branch_type'] == 'internal'): ?>
                  <option value="<?= $branch['id'] ?>" <?= $branchId == $branch['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($branch['name']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Company Branches">
              <?php foreach ($branches as $branch): ?>
                <?php if ($branch['branch_type'] == 'company_main' || $branch['branch_type'] == 'company_branch'): ?>
                  <option value="<?= $branch['id'] ?>" <?= $branchId == $branch['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($branch['name']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>
        
        <div>
          <label for="search" class="block mb-1 text-sm font-medium text-gray-700">Search</label>
          <input type="text" 
                id="search" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="Product, category, reason..." 
                class="border w-60 p-2 rounded focus:ring focus:border-primarycol">
        </div>
        
        <div>
          <label for="start_date" class="block mb-1 text-sm font-medium text-gray-700">From Date</label>
          <input type="date" 
                id="start_date" 
                name="start_date" 
                value="<?= htmlspecialchars($start_date) ?>" 
                class="border p-2 rounded focus:ring focus:border-primarycol">
        </div>
        
        <div>
          <label for="end_date" class="block mb-1 text-sm font-medium text-gray-700">To Date</label>
          <input type="date" 
                id="end_date" 
                name="end_date" 
                value="<?= htmlspecialchars($end_date) ?>" 
                class="border p-2 rounded focus:ring focus:border-primarycol">
        </div>
        
        <div class="flex space-x-3">
          <button type="button" id="clearFilters" 
                 class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primarycol">
            Clear Filters
          </button>
          <button type="submit" 
                 class="py-2 px-4 bg-primarycol border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-fourth focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primarycol">
            Search
          </button>
        </div>
        
        <!-- Export Options -->
        <div class="flex items-center space-x-2 ml-auto">
          <div class="text-sm font-medium text-gray-700">Export as:</div>
         
          <a href="generate_pdf.php?branch_id=<?= $branchId ?>&data_type=product&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
             class="py-2 px-3 bg-red-600 hover:bg-red-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414a1 1 0 01-.293.707V19a2 2 0 01-2 2z" />
            </svg>
            PDF
          </a>
          <a href="generate_excel.php?branch_id=<?= $branchId ?>&data_type=product&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
             class="py-2 px-3 bg-green-600 hover:bg-green-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Excel
          </a>
          <button onclick="window.printPage()" class="py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print
          </button>
        </div>
        
        <!-- Keep any existing hidden fields -->
        <?php if(isset($_GET['prod_page'])): ?>
          <input type="hidden" name="prod_page" value="1">
        <?php endif; ?>
      </form>
    </div>

    <!-- Product Waste Data Table -->
    <div class="print-section">
      <h2 class="text-2xl font-bold mb-6 text-primarycol">Product Waste Data</h2>
      <!-- Enhanced filter info display -->
      <?php if (!empty($search) || !empty($start_date) || !empty($end_date) || $branchId > 0): ?>
        <div class="mb-4 p-3 bg-blue-50 text-blue-800 rounded-md border border-blue-200">
          <div class="flex items-center mb-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            <span class="font-semibold">Active filters:</span>
            <?php if ($branchId > 0): ?><span class="ml-2 mr-2">Branch: "<?= htmlspecialchars($branchName) ?>"</span><?php endif; ?>
            <?php if (!empty($search)): ?><span class="ml-2 mr-2">Search: "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
            <?php if (!empty($start_date)): ?><span class="mr-2">From: <?= htmlspecialchars($start_date) ?></span><?php endif; ?>
            <?php if (!empty($end_date)): ?><span>To: <?= htmlspecialchars($end_date) ?></span><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
      
      <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200 overflow-x-auto">
        <!-- Print header only visible when printing -->
        <div class="print-header hidden print:block">
          <h1 class="text-2xl font-bold">WasteWise - Product Excess Report</h1>
          <p>Generated on <?= date('F j, Y') ?></p>
          <p>Branch: <?= htmlspecialchars($branchName) ?></p>
          <?php if (!empty($start_date) || !empty($end_date)): ?>
            <p>Period: <?= !empty($start_date) ? htmlspecialchars($start_date) : 'All time' ?> to <?= !empty($end_date) ? htmlspecialchars($end_date) : 'present' ?></p>
          <?php endif; ?>
          <hr class="my-4">
        </div>
        
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">ID</th>
              <?php if ($branchId == 0): // Only show branch column when viewing all branches ?>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Branch</th>
              <?php endif; ?>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Product</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Category</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Record Date</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Quantity</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Value</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Reason</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Disposal Method</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Staff</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($productWasteData): ?>
              <?php foreach ($productWasteData as $item): ?>
                <tr class="hover:bg-gray-100">
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['id']) ?></td>
                  <?php if ($branchId == 0): // Only show branch column when viewing all branches ?>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                    <?= htmlspecialchars($item['branch_name']) ?>
                    <?php if ($item['branch_type'] == 'company_main'): ?>
                      <span class="text-xs bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">Company</span>
                    <?php elseif ($item['branch_type'] == 'company_branch'): ?>
                      <span class="text-xs bg-green-100 text-green-800 px-1.5 py-0.5 rounded">Company Branch</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['product_name']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['category']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars(date('M d, Y', strtotime($item['waste_date']))) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['waste_quantity']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm font-medium text-gray-700">₱<?= htmlspecialchars(number_format($item['waste_value'], 2)) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars(ucfirst($item['waste_reason'])) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars(ucfirst($item['disposal_method'])) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['staff_name']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($item['notes'] ?: 'N/A') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="<?= $branchId == 0 ? 11 : 10 ?>" class="py-4 px-6 text-center text-gray-500">No product waste records found matching your criteria.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Product Waste Pagination -->
      <?php if ($prod_total_pages > 1): ?>
      <div class="flex justify-center mt-4">
        <nav class="inline-flex shadow-sm">
          <?php if ($prod_page > 1): ?>
            <a href="<?= getPaginationUrl($prod_page - 1) ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Prev</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $prod_total_pages; $i++): ?>
            <a href="<?= getPaginationUrl($i) ?>" class="px-3 py-2 <?= $i == $prod_page ? 'bg-primarycol text-white' : 'bg-white text-gray-700' ?> border border-gray-300"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($prod_page < $prod_total_pages): ?>
            <a href="<?= getPaginationUrl($prod_page + 1) ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Next</a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Summary Statistics Card -->
    <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200">
      <h3 class="text-xl font-bold mb-4 text-primarycol">Summary</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php
          // Calculate summary statistics
          $totalQuantity = 0;
          $totalValue = 0;
          $categoryTotals = [];
          
          foreach ($productWasteData as $item) {
              $totalQuantity += $item['waste_quantity'];
              $totalValue += $item['waste_value'];
              
              $category = $item['category'];
              if (!isset($categoryTotals[$category])) {
                  $categoryTotals[$category] = [
                      'quantity' => 0,
                      'value' => 0
                  ];
              }
              $categoryTotals[$category]['quantity'] += $item['waste_quantity'];
              $categoryTotals[$category]['value'] += $item['waste_value'];
          }
        ?>
        
        <!-- Total Items Card -->
        <div class="bg-sec p-4 rounded-md">
          <p class="text-gray-700 font-medium">Total Items</p>
          <p class="text-3xl font-bold text-primarycol"><?= number_format($totalQuantity) ?></p>
          <p class="text-sm text-gray-500">product units</p>
        </div>
        
        <!-- Total Value Card -->
        <div class="bg-sec p-4 rounded-md">
          <p class="text-gray-700 font-medium">Total Value</p>
          <p class="text-3xl font-bold text-fourth">₱<?= number_format($totalValue, 2) ?></p>
          <p class="text-sm text-gray-500">worth of product excess</p>
        </div>
        
        <!-- Categories Card -->
        <div class="bg-sec p-4 rounded-md">
          <p class="text-gray-700 font-medium">Categories</p>
          <p class="text-3xl font-bold text-primarycol"><?= count($categoryTotals) ?></p>
          <p class="text-sm text-gray-500">different product categories</p>
        </div>
      </div>
      
      <?php if (!empty($categoryTotals)): ?>
      <div class="mt-6">
        <h4 class="text-lg font-semibold mb-2 text-primarycol">Breakdown by Category</h4>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr>
                <th class="py-2 px-4 border-b-2 border-gray-200 text-left font-semibold text-gray-700">Category</th>
                <th class="py-2 px-4 border-b-2 border-gray-200 text-left font-semibold text-gray-700">Quantity</th>
                <th class="py-2 px-4 border-b-2 border-gray-200 text-left font-semibold text-gray-700">Value</th>
                <th class="py-2 px-4 border-b-2 border-gray-200 text-left font-semibold text-gray-700">% of Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categoryTotals as $category => $data): ?>
              <tr class="hover:bg-gray-50">
                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($category) ?></td>
                <td class="py-2 px-4 border-b border-gray-200"><?= number_format($data['quantity']) ?></td>
                <td class="py-2 px-4 border-b border-gray-200">₱<?= number_format($data['value'], 2) ?></td>
                <td class="py-2 px-4 border-b border-gray-200">
                  <?php $percent = ($data['value'] / $totalValue) * 100; ?>
                  <div class="flex items-center">
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                      <div class="bg-primarycol h-2.5 rounded-full" style="width: <?= number_format($percent, 0) ?>%"></div>
                    </div>
                    <span class="ml-2"><?= number_format($percent, 1) ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>