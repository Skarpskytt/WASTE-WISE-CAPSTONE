<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

// Set branch ID for this page
$branchId = 2;  // Branch 2

// -----------------------
// INGREDIENT WASTE PAGINATION
// -----------------------
$ing_page = isset($_GET['ing_page']) && is_numeric($_GET['ing_page']) ? (int) $_GET['ing_page'] : 1;
$ing_per_page = 10;

// Get total ingredient waste count FOR THIS BRANCH
$ingCountStmt = $pdo->prepare("SELECT COUNT(*) FROM ingredients_waste WHERE branch_id = ?");
$ingCountStmt->execute([$branchId]);
$ing_total = $ingCountStmt->fetchColumn();
$ing_total_pages = ceil($ing_total / $ing_per_page);
$ing_offset = ($ing_page - 1) * $ing_per_page;

// Fetch Ingredient Waste Data with LIMIT/OFFSET FOR THIS BRANCH
$ingStmt = $pdo->prepare("
    SELECT iw.id, i.ingredient_name, i.category, i.unit,
           iw.waste_date, iw.waste_quantity, iw.waste_value, 
           iw.waste_reason, iw.production_stage, iw.disposal_method,
           iw.batch_number, iw.responsible_person, iw.notes
    FROM ingredients_waste iw
    JOIN ingredients i ON iw.ingredient_id = i.id
    WHERE iw.branch_id = :branch_id
    ORDER BY iw.waste_date DESC
    LIMIT :limit OFFSET :offset
");
$ingStmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
$ingStmt->bindValue(':limit', $ing_per_page, PDO::PARAM_INT);
$ingStmt->bindValue(':offset', $ing_offset, PDO::PARAM_INT);
$ingStmt->execute();
$ingredientWasteData = $ingStmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch name
$branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$branchStmt->execute([$branchId]);
$branchName = $branchStmt->fetchColumn() ?: "Branch 2";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branchName) ?> - Ingredients Waste Data</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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

     $(document).ready(function() {
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

        // Print functionality
        window.printPage = function() {
            window.print();
        };
     });
     </script>
       <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
       <script>
         function printPage() {
           window.print();
         }
       </script>
</head>

<body class="flex h-screen">
<?php include '../layout/nav.php'?>

  <div class="flex-1 p-6 overflow-auto space-y-12">
    <!-- Branch Header -->
    <div class="bg-sec p-4 rounded-lg shadow mb-6">
      <h1 class="text-3xl font-bold text-primarycol"><?= htmlspecialchars($branchName) ?> Ingredients Waste Data</h1>
      <p class="text-gray-600">Showing branch-specific ingredient waste records</p>
    </div>
    
    <div class="mb-6 flex justify-between gap-4">
      
      <div class="flex gap-4">
        <a href="export_waste_report.php?branch_id=<?= $branchId ?>&type=ingredient" 
           class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md inline-flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Export to Excel
        </a>
        
        <a href="export_waste_report_pdf.php?branch_id=<?= $branchId ?>&type=ingredient" 
           class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-md inline-flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
          Export to PDF
        </a>
        
        <a href="javascript:void(0)" onclick="printPage()" 
           class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-md inline-flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z" />
          </svg>
          Print Data
        </a>
      </div>
    </div>

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
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Stage</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Disposal</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Batch Number</th>
              <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Responsible Person</th>
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
                  <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700"><?= htmlspecialchars($ing['responsible_person']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="11" class="py-4 px-6 text-center text-gray-500">No ingredient waste records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Ingredient Waste Pagination -->
      <?php if ($ing_total_pages > 1): ?>
      <div class="flex justify-center mt-4">
        <nav class="inline-flex shadow-sm">
          <?php if ($ing_page > 1): ?>
            <a href="?ing_page=<?= $ing_page - 1 ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Prev</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $ing_total_pages; $i++): ?>
            <a href="?ing_page=<?= $i ?>" class="px-3 py-2 <?= $i == $ing_page ? 'bg-primarycol text-white' : 'bg-white text-gray-700' ?> border border-gray-300"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($ing_page < $ing_total_pages): ?>
            <a href="?ing_page=<?= $ing_page + 1 ?>" class="px-3 py-2 bg-white border border-gray-300 text-gray-700">Next</a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
