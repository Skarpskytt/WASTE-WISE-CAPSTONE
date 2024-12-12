<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct

// Fetch Waste Data with Quantity Sold and Date Filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Pagination Variables
$limit = 10; // Records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Modify SQL Query to Include LIMIT and OFFSET
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
    FROM 
        waste
    LEFT JOIN 
        inventory ON waste.inventory_id = inventory.id
    WHERE 1=1
";

$params = [];

if ($startDate && $endDate) {
    $query .= " AND waste.waste_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
}

// Group by waste.id if necessary
$query .= " ORDER BY waste.waste_date DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);

// Bind parameters
if ($startDate && $endDate) {
    $stmt->bindParam(':start_date', $params[':start_date']);
    $stmt->bindParam(':end_date', $params[':end_date']);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$wasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total records for pagination
$countQuery = "
    SELECT COUNT(*) FROM (
        SELECT waste.id
        FROM waste
        LEFT JOIN inventory ON waste.inventory_id = inventory.id
        WHERE 1=1
";
if ($startDate && $endDate) {
    $countQuery .= " AND waste.waste_date BETWEEN :start_date AND :end_date";
}
$countQuery .= "
    GROUP BY waste.id
) AS subquery
";

$countStmt = $pdo->prepare($countQuery);
if ($startDate && $endDate) {
    $countStmt->bindParam(':start_date', $startDate);
    $countStmt->bindParam(':end_date', $endDate);
}
$countStmt->execute();
$totalRecords = $countStmt->rowCount();
$totalPages = ceil($totalRecords / $limit);
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

        $('#filter_btn').on('click', function() {
            let startDate = $('#start_date').val();
            let endDate = $('#end_date').val();

            if(startDate && endDate){
                window.location.href = `table.php?start_date=${startDate}&endDate=${endDate}`;
            } else {
                alert('Please select both start and end dates.');
            }
        });
    });
     </script>
       <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-screen">
<?php include '../layout/nav.php'?>

  <div class="p-6 overflow-y-auto w-full">
   <!-- Export Button -->
<div class="mb-4">
    <a href="export_waste_report.php" class="btn btn-primary">Export Waste Report as CSV</a>
</div>
<!-- Export PDF Button -->
<div class="mb-4">
    <a href="export_waste_report_pdf.php" class="btn btn-secondary">Export Waste Report as PDF</a>
</div>

<!-- Date Range Filter -->
<div class="mb-4 flex space-x-2">
    <input type="date" id="start_date" class="input input-bordered" placeholder="Start Date">
    <input type="date" id="end_date" class="input input-bordered" placeholder="End Date">
    <button id="filter_btn" class="btn btn-primary">Filter</button>
</div>

    <!-- Waste Data Table -->
    <div class="overflow-x-auto mb-10">
        <h2 class="text-2xl font-semibold mb-5">Waste Data</h2>
        <table class="table table-zebra w-full">
            <thead>
                <tr class="bg-sec">
                    <th>#</th>
                    <th class="flex justify-center">Image</th>
                    <th>Waste Date</th>
                    <th>Item Name</th>
                    <th>Waste Quantity</th>
                    <th>Waste Value</th>
                    <th>Waste Reason</th>
                    <th>Responsible Person</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($wasteData) {
                    foreach ($wasteData as $waste): ?>
                        <tr>
                            <td><?= htmlspecialchars($waste['id']) ?></td>
                            <td>
                                <?php if (!empty($waste['image'])): ?>
                                    <img src="<?= htmlspecialchars($waste['image']) ?>" class="w-8 h-8 mx-auto" alt="<?= htmlspecialchars($waste['item_name'] ?? 'No Name'); ?>">
                                <?php else: ?>
                                    <img src="../../assets/default-product.jpg" class="w-8 h-8 mx-auto" alt="No Image Available">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($waste['waste_date']); ?></td>
                            <td><?= htmlspecialchars($waste['item_name'] ?? 'N/A'); ?></td>
                            <td><?= htmlspecialchars($waste['total_waste']); ?></td>
                            <td>₱<?= htmlspecialchars(number_format($waste['waste_value'], 2)); ?></td>
                            <td><?= ucfirst(htmlspecialchars($waste['waste_reason'])); ?></td>
                            <td><?= htmlspecialchars($waste['responsible_person']); ?></td>
                            
                        </tr>
                <?php endforeach; 
                } else { ?>
                    <tr><td colspan="10" class="text-center">No waste records found.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div class="flex justify-center mt-4">
        <?php if ($totalPages > 1): ?>
            <div class="btn-group">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="table.php?page=<?= $i ?>" class="btn <?= ($i == $page) ? 'btn-active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

  </div>
</body>
</html>