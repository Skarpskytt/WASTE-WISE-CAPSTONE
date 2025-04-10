<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Set branch ID for this page
$branchId = 1;  // Branch 1

// Get search parameters with proper error handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : '';

// -----------------------
// INGREDIENT WASTE PAGINATION
// -----------------------
$ing_page = isset($_GET['ing_page']) && is_numeric($_GET['ing_page']) ? (int) $_GET['ing_page'] : 1;
$ing_per_page = 10;

// Build the query with filters
$countQuery = "SELECT COUNT(*) FROM ingredients_waste iw 
               JOIN ingredients i ON iw.ingredient_id = i.id
               JOIN users u ON iw.staff_id = u.id
               WHERE iw.branch_id = ?";
               
// Checking ingredient table structure - using ingredient_name instead of name
$dataQuery = "SELECT iw.*, i.ingredient_name, i.category, i.unit,
              CONCAT(u.fname, ' ', u.lname) as staff_name
              FROM ingredients_waste iw
              JOIN ingredients i ON iw.ingredient_id = i.id
              JOIN users u ON iw.staff_id = u.id
              WHERE iw.branch_id = ?";

$params = [$branchId];

// Add search filter - adjusted to use ingredient_name instead of name
if (!empty($search)) {
    $countQuery .= " AND (i.ingredient_name LIKE ? OR i.category LIKE ? OR 
                    iw.waste_reason LIKE ? OR iw.disposal_method LIKE ? OR
                    iw.batch_number LIKE ? OR
                    CONCAT(u.fname, ' ', u.lname) LIKE ?)";
    $dataQuery .= " AND (i.ingredient_name LIKE ? OR i.category LIKE ? OR 
                   iw.waste_reason LIKE ? OR iw.disposal_method LIKE ? OR
                   iw.batch_number LIKE ? OR
                   CONCAT(u.fname, ' ', u.lname) LIKE ?)";
    
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
    $countQuery .= " AND DATE(iw.waste_date) >= ?";
    $dataQuery .= " AND DATE(iw.waste_date) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $countQuery .= " AND DATE(iw.waste_date) <= ?";
    $dataQuery .= " AND DATE(iw.waste_date) <= ?";
    $params[] = $end_date;
}

// 1. Get the total count first
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// 2. Calculate pagination values
$totalPages = ceil($total / $ing_per_page);
$offset = ($ing_page - 1) * $ing_per_page;

// 3. Add pagination directly to SQL
$dataQuery .= " ORDER BY iw.waste_date DESC LIMIT " . (int)$ing_per_page . " OFFSET " . (int)$offset;

// 4. Execute with original parameters
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);
$ingredientWasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch name
$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: "Branch 1";

// Generate pagination URL with filters preserved
function getPaginationUrl($page) {
    $params = $_GET;
    $params['ing_page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branchName) ?> - Ingredients Waste Data</title>
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
            window.location.href = 'branch1_ingredients_waste_data.php';
        });
        
        $('#toggleSidebar').on('click', function() {
            $('#sidebar').toggleClass('-translate-x-full');
        });

        $('#closeSidebar').on('click', function() {
            $('#sidebar').addClass('-translate-x-full');
        });

        // Action buttons for Edit and Delete (Waste)
        $('.edit-waste-btn').on('click', function() {
            let id = $(this).data('id');
            window.location.href = `edit_waste.php?id=${id}`;
        });

        $('.delete-waste-btn').on('click', function() {
            if (confirm('Are you sure you want to delete this waste record?')) {
                let id = $(this).data('id');
                window.location.href = `delete_waste.php?id=${id}`;
            }
        });

        // Simple date pickers without flatpickr for better compatibility
        $('#start_date, #end_date').on('change', function() {
          // Validate date range if both are filled
          if ($('#start_date').val() && $('#end_date').val()) {
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($('#end_date').val());
            if (startDate > endDate) {
              alert('End date must be after start date');
              $(this).val('');
            }
          }
        });

        // Print functionality
        window.printPage = function() {
            window.print();
        };
     });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
      <h1 class="text-3xl font-bold text-primarycol"><?= htmlspecialchars($branchName) ?> Ingredients Waste Data</h1>
      <p class="text-gray-600">Showing branch-specific ingredient waste records</p>
    </div>
    
    <!-- Filter Section - Enhanced with direct search form -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
      <h2 class="text-xl font-semibold mb-4 text-primarycol">Filter Records</h2>
      <form id="filterForm" action="" method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
          <label for="search" class="block mb-1 text-sm font-medium text-gray-700">Search</label>
          <input type="text" 
                id="search" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="Ingredient, category, batch..." 
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
          <a href="javascript:void(0)" onclick="printPage()" 
             class="py-2 px-3 bg-purple-600 hover:bg-purple-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print
          </a>
          <a href="generate_pdf.php?branch_id=<?= $branchId ?>&data_type=ingredient&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
             class="py-2 px-3 bg-red-600 hover:bg-red-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414a1 1 0 01-.293.707V19a2 2 0 01-2 2z" />
            </svg>
            PDF
          </a>
          <a href="generate_excel.php?branch_id=<?= $branchId ?>&data_type=ingredient&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
             class="py-2 px-3 bg-green-600 hover:bg-green-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Excel
          </a>
        </div>
        
        <!-- Keep any existing hidden fields -->
        <?php if(isset($_GET['ing_page'])): ?>
          <input type="hidden" name="ing_page" value="1">
        <?php endif; ?>
      </form>
    </div>

    <!-- Enhanced filter info display -->
    <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
      <div class="mb-4 p-3 bg-blue-50 text-blue-800 rounded-md border border-blue-200">
        <div class="flex items-center mb-1">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          <span class="font-semibold">Active filters:</span>
          <?php if (!empty($search)): ?><span class="ml-2 mr-2">Search: "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
          <?php if (!empty($start_date)): ?><span class="mr-2">From: <?= htmlspecialchars($start_date) ?></span><?php endif; ?>
          <?php if (!empty($end_date)): ?><span>To: <?= htmlspecialchars($end_date) ?></span><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Ingredient Waste Data Table -->
    <div>
      <h2 class="text-2xl font-bold mb-6 text-primarycol">Ingredients Waste Data</h2>
      <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">ID</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Ingredient</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Category</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Waste Date</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Quantity</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Value</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Reason</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Production Stage</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Disposal Method</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Batch Number</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Staff</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($ingredientWasteData): ?>
              <?php foreach ($ingredientWasteData as $ing): ?>
                <tr class="hover:bg-gray-100">
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['id']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['ingredient_name']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['category']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars(date('M d, Y', strtotime($ing['waste_date']))) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['waste_quantity']) ?> <?= htmlspecialchars($ing['unit']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm font-medium text-gray-700">₱<?= htmlspecialchars(number_format($ing['waste_value'], 2)) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars(ucfirst($ing['waste_reason'])) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars(ucfirst($ing['production_stage'])) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars(ucfirst($ing['disposal_method'])) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['batch_number']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['staff_name']) ?></td>
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['notes'] ?: 'N/A') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="12" class="py-4 px-6 text-center text-gray-500">No ingredient waste records found matching your criteria.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Ingredient Waste Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="flex justify-center mt-4">
        <nav class="inline-flex shadow-sm">
          <?php if ($ing_page > 1): ?>
            <a href="<?= getPaginationUrl($ing_page - 1) ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Prev</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?= getPaginationUrl($i) ?>" class="px-3 py-2 <?= $i == $ing_page ? 'bg-primarycol text-white' : 'bg-white text-gray-700' ?> border border-gray-300"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($ing_page < $totalPages): ?>
            <a href="<?= getPaginationUrl($ing_page + 1) ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Next</a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
