<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Get search parameters with proper error handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : '';

// -----------------------
// DONATIONS PAGINATION
// -----------------------
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 10;

// Build the query with filters
$countQuery = "SELECT COUNT(*) FROM donated_products dp 
               JOIN users u ON dp.ngo_id = u.id
               JOIN donation_requests dr ON dp.donation_request_id = dr.id
               JOIN products p ON dr.product_id = p.id";

// Full data query
$dataQuery = "SELECT dp.*, 
              COALESCE(u.organization_name, CONCAT(u.fname, ' ', u.lname)) as ngo_name,
              dp.received_by,
              dp.donation_request_id,
              dp.received_date,
              dp.received_quantity,
              dp.remarks,
              p.name as product_name,
              b.name as branch_name
              FROM donated_products dp
              JOIN users u ON dp.ngo_id = u.id
              JOIN donation_requests dr ON dp.donation_request_id = dr.id
              JOIN products p ON dr.product_id = p.id
              JOIN branches b ON dr.branch_id = b.id";

$params = [];

// Add search filter
if (!empty($search)) {
    $whereClause = " WHERE (p.name LIKE ? OR 
                    COALESCE(u.organization_name, CONCAT(u.fname, ' ', u.lname)) LIKE ? OR
                    dp.received_by LIKE ? OR 
                    dp.remarks LIKE ? OR
                    CAST(dp.donation_request_id AS CHAR) LIKE ?)";
    
    $countQuery .= $whereClause;
    $dataQuery .= $whereClause;
    
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add date range filter
if (!empty($start_date)) {
    $dateClause = empty($params) ? " WHERE" : " AND";
    $countQuery .= "$dateClause DATE(dp.received_date) >= ?";
    $dataQuery .= "$dateClause DATE(dp.received_date) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $dateClause = empty($params) ? " WHERE" : " AND";
    $countQuery .= "$dateClause DATE(dp.received_date) <= ?";
    $dataQuery .= "$dateClause DATE(dp.received_date) <= ?";
    $params[] = $end_date;
}

// Finalize the data query
// REMOVE THIS LINE COMPLETELY:
// $dataQuery .= " ORDER BY dp.received_date DESC LIMIT ? OFFSET ?";

// Get total count with filters
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$total_pages = ceil($total / $per_page);
$offset = ($page - 1) * $per_page;

// Add LIMIT and OFFSET directly to the SQL string
$dataQuery .= " ORDER BY dp.received_date DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

// Fetch donation data with filters - use only the filter params, not pagination ones
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);  // Don't add pagination parameters to this array
$donationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate pagination URL with filters preserved
function getPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Calculate totals for summary stats
$totalReceivedQuery = "SELECT SUM(dp.received_quantity) as total_quantity FROM donated_products dp";
if (!empty($params)) {
    $totalReceivedQuery .= " JOIN users u ON dp.ngo_id = u.id
                           JOIN donation_requests dr ON dp.donation_request_id = dr.id
                           JOIN products p ON dr.product_id = p.id";
    
    // Add same WHERE clauses as main query
    if (!empty($search)) {
        $totalReceivedQuery .= " WHERE (p.name LIKE ? OR 
                              COALESCE(u.organization_name, CONCAT(u.fname, ' ', u.lname)) LIKE ? OR
                              dp.received_by LIKE ? OR 
                              dp.remarks LIKE ? OR
                              CAST(dp.donation_request_id AS CHAR) LIKE ?)";
    }
    
    if (!empty($start_date)) {
        $dateClause = empty($search) ? " WHERE" : " AND";
        $totalReceivedQuery .= "$dateClause DATE(dp.received_date) >= ?";
    }
    
    if (!empty($end_date)) {
        $dateClause = (empty($search) && empty($start_date)) ? " WHERE" : " AND";
        $totalReceivedQuery .= "$dateClause DATE(dp.received_date) <= ?";
    }
}

$totalStmt = $pdo->prepare($totalReceivedQuery);
$totalStmt->execute($params);
$totalStats = $totalStmt->fetch(PDO::FETCH_ASSOC);

// Count unique NGOs receiving donations
$uniqueNgosQuery = "SELECT COUNT(DISTINCT dp.ngo_id) as unique_ngos FROM donated_products dp";
if (!empty($params)) {
    // Add the same WHERE clauses
    // [similar filters as above]
}

$uniqueNgosStmt = $pdo->prepare($uniqueNgosQuery);
$uniqueNgosStmt->execute($params);
$uniqueNgos = $uniqueNgosStmt->fetch(PDO::FETCH_ASSOC)['unique_ngos'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History</title>
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
        // Initialize date pickers
        $(".datepicker").flatpickr({
            dateFormat: "Y-m-d",
            allowInput: true,
            wrap: true
        });
        
        // Form submission
        $('#filterForm').on('submit', function() {
            if ($('#start_date').val() === '') {
                $('#start_date').prop('disabled', true);
            }
            if ($('#end_date').val() === '') {
                $('#end_date').prop('disabled', true);
            }
            
            // Reset to page 1 when filtering
            $('<input>').attr({
              type: 'hidden',
              name: 'page',
              value: '1'
            }).appendTo($(this));
            
            return true;
        });
        
        // Clear filters button
        $('#clearFilters').on('click', function() {
            window.location.href = 'donation_history.php';
        });

        // View details modal
        $('.view-details-btn').on('click', function() {
            const id = $(this).data('id');
            $('#details_' + id).addClass('modal-open');
        });
        
        $('.close-modal').on('click', function() {
            $('.modal').removeClass('modal-open');
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
    <!-- Page Header -->
    <div class="bg-sec p-4 rounded-lg shadow mb-6">
      <h1 class="text-3xl font-bold text-primarycol">Donation History</h1>
      <p class="text-gray-600">Track all completed donations to NGOs</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-primarycol">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Total Donations</p>
                    <p class="text-2xl font-bold text-primarycol"><?= $total ?></p>
                </div>
                <div class="p-3 rounded-full bg-primarycol/10 text-primarycol">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Total Quantity Donated</p>
                    <p class="text-2xl font-bold text-green-600"><?= number_format($totalStats['total_quantity'] ?? 0, 2) ?></p>
                </div>
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">NGOs Served</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $uniqueNgos ?></p>
                </div>
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
      <h2 class="text-xl font-semibold mb-4 text-primarycol">Filter Donations</h2>
      <form id="filterForm" action="" method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
          <label for="search" class="block mb-1 text-sm font-medium text-gray-700">Search</label>
          <input type="text" 
                id="search" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="NGO, product, notes..." 
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
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 01-2 2z" />
            </svg>
            Print
          </a>
          <a href="export_donation_history.php?format=pdf&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
             class="py-2 px-3 bg-red-600 hover:bg-red-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414a1 1 0 01-.293.707V19a2 2 0 01-2 2z" />
            </svg>
            PDF
          </a>
          <a href="export_donation_history.php?format=excel&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
             class="py-2 px-3 bg-green-600 hover:bg-green-700 text-white rounded-md shadow-sm inline-flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Excel
          </a>
        </div>
      </form>
    </div>
    
    <!-- Active filters display -->
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
   
    <!-- Donation History Table -->
    <div>
      <h2 class="text-2xl font-bold mb-6 text-primarycol">Donation History</h2>
      <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200 overflow-x-auto print-section">
        <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
        <div class="print-header hidden">
          <h2 class="text-xl font-bold">Donation History Report</h2>
          <p>Filtered by: 
            <?php if (!empty($search)): ?>Search: "<?= htmlspecialchars($search) ?>"<?php endif; ?>
            <?php if (!empty($start_date)): ?> | From: <?= htmlspecialchars($start_date) ?><?php endif; ?>
            <?php if (!empty($end_date)): ?> | To: <?= htmlspecialchars($end_date) ?><?php endif; ?>
          </p>
        </div>
        <?php endif; ?>
        
        <table class="table w-full">
          <thead>
            <tr>
              <th>ID</th>
              <th>NGO Name</th>
              <th>Received By</th>
              <th>Product</th>
              <th>Branch</th>
              <th>Date</th>
              <th>Quantity</th>
              <th class="hidden-print">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($donationData) > 0): ?>
              <?php foreach ($donationData as $donation): ?>
                <tr class="hover">
                  <td class="font-medium">#<?= htmlspecialchars($donation['id']) ?></td>
                  <td>
                    <div class="font-bold"><?= htmlspecialchars($donation['ngo_name']) ?></div>
                    <div class="text-sm opacity-50">Request #<?= htmlspecialchars($donation['donation_request_id']) ?></div>
                  </td>
                  <td><?= htmlspecialchars($donation['received_by']) ?></td>
                  <td>
                    <div class="font-medium"><?= htmlspecialchars($donation['product_name']) ?></div>
                  </td>
                  <td><?= htmlspecialchars($donation['branch_name']) ?></td>
                  <td>
                    <?= date('M d, Y', strtotime($donation['received_date'])) ?>
                    <br/>
                    <span class="text-xs text-gray-500">
                      <?= date('h:i A', strtotime($donation['received_date'])) ?>
                    </span>
                  </td>
                  <td>
                    <span class="font-medium"><?= htmlspecialchars($donation['received_quantity']) ?></span>
                  </td>
                  <td class="hidden-print">
                    <button class="view-details-btn btn btn-sm bg-primarycol text-white" data-id="<?= $donation['id'] ?>">
                      View Details
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="text-center py-4 text-gray-500">
                  No donation records found matching your criteria.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination (Keep your existing pagination code) -->
      <?php if ($total_pages > 1): ?>
      <div class="flex justify-center mt-4 hidden-print">
        <!-- Your existing pagination code -->
        <nav class="inline-flex shadow-sm">
          <?php if ($page > 1): ?>
            <a href="<?= getPaginationUrl($page - 1) ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Prev</a>
          <?php endif; ?>
          
          <?php
          // Show limited page numbers with ellipsis
          $start_page = max(1, min($page - 2, $total_pages - 4));
          $end_page = min($total_pages, max($page + 2, 5));
          
          if ($start_page > 1) {
              echo '<a href="' . getPaginationUrl(1) . '" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">1</a>';
              if ($start_page > 2) {
                  echo '<span class="px-3 py-2 border border-gray-300 text-gray-500">...</span>';
              }
          }
          
          for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="<?= getPaginationUrl($i) ?>" class="px-3 py-2 <?= $i == $page ? 'bg-primarycol text-white' : 'bg-white text-gray-700' ?> border border-gray-300"><?= $i ?></a>
          <?php endfor;
          
          if ($end_page < $total_pages) {
              if ($end_page < $total_pages - 1) {
                  echo '<span class="px-3 py-2 border border-gray-300 text-gray-500">...</span>';
              }
              echo '<a href="' . getPaginationUrl($total_pages) . '" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">' . $total_pages . '</a>';
          }
          ?>
          
          <?php if ($page < $total_pages): ?>
            <a href="<?= getPaginationUrl($page + 1) ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Next</a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Details Modals -->
    <?php foreach ($donationData as $donation): ?>
    <div id="details_<?= $donation['id'] ?>" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg text-primarycol mb-4">
          Donation Details #<?= $donation['id'] ?>
        </h3>
        
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-sec rounded-lg p-4">
            <h4 class="font-semibold text-primarycol mb-2">NGO Information</h4>
            <p><span class="font-medium">Organization:</span> <?= htmlspecialchars($donation['ngo_name']) ?></p>
            <p><span class="font-medium">Received By:</span> <?= htmlspecialchars($donation['received_by']) ?></p>
          </div>
          
          <div class="bg-sec rounded-lg p-4">
            <h4 class="font-semibold text-primarycol mb-2">Donation Details</h4>
            <p><span class="font-medium">Request ID:</span> #<?= htmlspecialchars($donation['donation_request_id']) ?></p>
            <p><span class="font-medium">Product:</span> <?= htmlspecialchars($donation['product_name']) ?></p>
            <p><span class="font-medium">Branch:</span> <?= htmlspecialchars($donation['branch_name']) ?></p>
          </div>
        </div>
        
        <div class="mt-4 bg-sec rounded-lg p-4">
          <h4 class="font-semibold text-primarycol mb-2">Receipt Information</h4>
          <div class="grid grid-cols-2 gap-4">
            <p><span class="font-medium">Received Date:</span> <?= date('M d, Y h:i A', strtotime($donation['received_date'])) ?></p>
            <p><span class="font-medium">Quantity:</span> <?= htmlspecialchars($donation['received_quantity']) ?></p>
          </div>
          
          <?php if (!empty($donation['remarks'])): ?>
          <div class="mt-3">
            <h5 class="font-medium">Remarks:</h5>
            <p class="bg-white p-2 rounded mt-1"><?= nl2br(htmlspecialchars($donation['remarks'])) ?></p>
          </div>
          <?php endif; ?>
        </div>
        
        <div class="modal-action">
          <button class="btn close-modal">Close</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</body>
</html>