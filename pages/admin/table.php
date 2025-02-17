<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct
require_once '../../vendor/autoload.php';

// Capture search term and date range from GET
$search    = isset($_GET['search']) ? trim($_GET['search']) : null;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate   = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Pagination Variables
$limit = 10; // Records per page
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Construct base query
$query = "
    SELECT 
        waste.id,
        waste.waste_date,
        inventory.name AS item_name,
        inventory.quantity,
        waste.waste_quantity AS total_waste,
        waste.waste_value,
        waste.waste_reason,
        waste.responsible_person,
        inventory.image
    FROM waste
    LEFT JOIN inventory ON waste.inventory_id = inventory.id
    WHERE 1=1
";

// Prepare parameters
$params = [];

// Date range filter
if ($startDate && $endDate) {
    $query .= " AND waste_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $startDate;
    $params[':end_date']   = $endDate;
}

// Search filter (e.g., match item_name)
if (!empty($search)) {
    $query .= " AND inventory.name LIKE :search";
    $params[':search'] = "%{$search}%";
}

// Sort & pagination
$query .= " ORDER BY waste.waste_date DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);

// Bind date params
if ($startDate && $endDate) {
    $stmt->bindParam(':start_date', $params[':start_date']);
    $stmt->bindParam(':end_date', $params[':end_date']);
}

// Bind search param
if (!empty($search)) {
    $stmt->bindParam(':search', $params[':search']);
}

// Bind limit and offset
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total records for pagination
$countQuery = "
    SELECT COUNT(*) FROM (
        SELECT waste.id
        FROM waste
        LEFT JOIN inventory ON waste.inventory_id = inventory.id
        WHERE 1=1
";

// Repeat date filters
if ($startDate && $endDate) {
    $countQuery .= " AND waste_date BETWEEN :start_date AND :end_date";
}
// Repeat search filter
if (!empty($search)) {
    $countQuery .= " AND inventory.name LIKE :search";
}

$countQuery .= " GROUP BY waste.id ) AS subquery";

$countStmt = $pdo->prepare($countQuery);
if ($startDate && $endDate) {
    $countStmt->bindParam(':start_date', $params[':start_date']);
    $countStmt->bindParam(':end_date', $params[':end_date']);
}
if (!empty($search)) {
    $countStmt->bindParam(':search', $params[':search']);
}
$countStmt->execute();
$totalRecords = $countStmt->rowCount();
$totalPages = ceil($totalRecords / $limit);

// Add PDF Export Function
if (isset($_POST['export_pdf'])) {
    try {
        // Create custom PDF class with header
        class MYPDF extends TCPDF {
            public function Header() {
                // Logo
                $image_file = '../../assets/images/Logo.png';
                $this->Image($image_file, 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                
                // Set font
                $this->SetFont('helvetica', 'B', 20);
                
                // Title
                $this->Cell(0, 30, 'WasteWise Management System', 0, false, 'C', 0, '', 0, false, 'M', 'M');
                
                // Line break
                $this->Ln(20);
                
                // Subtitle
                $this->SetFont('helvetica', 'B', 15);
                $this->Cell(0, 10, 'Waste Records Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
                
                // Line break
                $this->Ln(15);
            }
        }

        // Initialize PDF
        $pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('WasteWise');
        $pdf->SetAuthor('WasteWise Admin');
        $pdf->SetTitle('Waste Records Report');
        
        // Set margins
        $pdf->SetMargins(15, 50, 15);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Add report generation date
        $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y, g:i a'), 0, 1, 'R');
        $pdf->Ln(5);
        
        // Create table
        $html = '<table border="1" cellpadding="4">
            <tr style="background-color: #47663B; color: white;">
                <th>ID</th>
                <th>Item Name</th>
                <th>Waste Date</th>
                <th>Quantity</th>
                <th>Value</th>
                <th>Reason</th>
                <th>Responsible Person</th>
            </tr>';
        
        // Fetch waste data
        $stmt = $pdo->query("
            SELECT 
                w.id,
                i.name as item_name,
                w.waste_date,
                w.waste_quantity,
                w.waste_value,
                w.waste_reason,
                w.responsible_person
            FROM waste w
            JOIN inventory i ON w.inventory_id = i.id
            ORDER BY w.waste_date DESC
        ");
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['id']) . '</td>
                <td>' . htmlspecialchars($row['item_name']) . '</td>
                <td>' . htmlspecialchars($row['waste_date']) . '</td>
                <td>' . htmlspecialchars($row['waste_quantity']) . '</td>
                <td>₱' . htmlspecialchars(number_format($row['waste_value'], 2)) . '</td>
                <td>' . htmlspecialchars($row['waste_reason']) . '</td>
                <td>' . htmlspecialchars($row['responsible_person']) . '</td>
            </tr>';
        }
        
        $html .= '</table>';
        
        // Output the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Add footer
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'WasteWise Management System - Page '.$pdf->getAliasNumPage().'/'.$pdf->getAliasNbPages(), 0, false, 'C');
        
        // Output PDF
        $pdf->Output('waste_report_' . date('Y-m-d') . '.pdf', 'D');
        exit;
    } catch (Exception $e) {
        $error = "Failed to generate PDF: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Data</title>
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
    });
     </script>
       <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-screen">
<?php include '../layout/nav.php'?>

  <div class="flex-1 p-6 overflow-auto">
    <h1 class="text-3xl font-bold mb-6 text-primarycol">Waste Data</h1>

    <!-- Search & Date Filter Form -->
    <form method="GET" class="flex flex-wrap gap-4 mb-6 items-end">
        <!-- Search Field -->
        <div class="ml-auto">
            <label for="search" class="text-sm font-medium mb-1">Search Item</label>
            <input type="text" name="search" id="search" 
                   value="<?php echo htmlspecialchars($search ?? ''); ?>"
                   class="input input-bordered w-64"
                   placeholder="Enter item name..."/>
        </div>
        <!-- Date Range Fields -->
        <div>
            <label for="start_date" class="text-sm font-medium mb-1">From</label>
            <input type="date" name="start_date" id="start_date" 
                   value="<?php echo htmlspecialchars($startDate ?? ''); ?>"
                   class="input input-bordered" />
        </div>
        <div>
            <label for="end_date" class="text-sm font-medium mb-1">To</label>
            <input type="date" name="end_date" id="end_date"
                   value="<?php echo htmlspecialchars($endDate ?? ''); ?>"
                   class="input input-bordered" />
        </div>
        <!-- Submit Button -->
        <div>
            <button type="submit" class="btn bg-primarycol text-white hover:bg-fourth">
                Search
            </button>
        </div>
    </form>

    <!-- Waste Data Table -->
    <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200">
        <h2 class="text-2xl font-semibold mb-4 text-primarycol">Waste Records</h2>
        
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">ID</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Image</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Waste Date</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Item Name</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Waste Quantity</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Waste Value</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Waste Reason</th>
                        <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">Responsible Person</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($wasteData): ?>
                        <?php foreach ($wasteData as $waste): ?>
                            <tr class="hover:bg-gray-100">
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($waste['id']) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?php if (!empty($waste['image'])): ?>
                                        <img src="<?= htmlspecialchars($waste['image']) ?>" class="w-8 h-8 mx-auto object-cover rounded" alt="<?= htmlspecialchars($waste['item_name'] ?? 'No Name'); ?>">
                                    <?php else: ?>
                                        <img src="../../assets/default-product.jpg" class="w-8 h-8 mx-auto object-cover rounded" alt="No Image Available">
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($waste['waste_date']) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($waste['item_name'] ?? 'N/A') ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($waste['total_waste']) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    ₱<?= htmlspecialchars(number_format($waste['waste_value'], 2)) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= ucfirst(htmlspecialchars($waste['waste_reason'])) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                    <?= htmlspecialchars($waste['responsible_person']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="py-4 px-6 text-center text-gray-500">
                                No waste records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Export Buttons -->
    <div class="flex gap-2 mb-4">
        <form method="POST" class="inline">
            <button type="submit" name="export_excel" class="btn btn-success">
                Export to Excel
            </button>
        </form>
        <form method="POST" class="inline">
            <button type="submit" name="export_pdf" class="btn btn-error">
                Export to PDF
            </button>
        </form>
    </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <div class="btn-group">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="table.php?page=<?= $i ?><?php 
                          // Preserve filters in pagination links
                          if ($startDate) echo "&start_date=".$startDate; 
                          if ($endDate) echo "&end_date=".$endDate;
                          if ($search) echo "&search=".$search;
                        ?>" 
                           class="btn <?= ($i == $page) ? 'btn-active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>