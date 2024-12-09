<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct

// Fetch Sales Data
try {
    $stmt = $pdo->query("
        SELECT sales.*, products.name AS product_name, products.image 
        FROM sales 
        LEFT JOIN products ON sales.product_id = products.id 
        ORDER BY sales.date DESC
    ");
    $salesData = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching sales data: " . $e->getMessage());
}

// Fetch Waste Data
try {
    $stmt = $pdo->query("
        SELECT waste.*, 
               CASE 
                   WHEN waste.classification = 'product' THEN products.name 
                   ELSE inventory.name 
               END AS item_name,
               CASE 
                   WHEN waste.classification = 'product' THEN products.image 
                   ELSE inventory.image 
               END AS item_image
        FROM waste 
        LEFT JOIN products ON waste.inventory_id = products.id AND waste.classification = 'product'
        LEFT JOIN inventory ON waste.inventory_id = inventory.id AND waste.classification = 'inventory'
        ORDER BY waste.waste_date DESC
    ");
    $wasteData = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching waste data: " . $e->getMessage());
}

// Fetch Inventory Data
try {
    $stmt = $pdo->query("SELECT * FROM inventory ORDER BY created_at DESC");
    $inventoryData = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching inventory data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Table</title>
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
});
 </script>
   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-screen">
<?php include '../layout/nav.php'?>

  <div class="p-6 overflow-y-auto w-full">
   
    <!-- Sales Data Table -->
    <div class="overflow-x-auto mb-10">
        <h2 class="text-2xl font-semibold mb-5">Sales Data</h2>
        <table class="table table-zebra w-full">
            <thead>
                <tr class="bg-sec">
                    <th class="flex justify-center">Image</th>
                    <th>Date</th>
                    <th>Product Name</th>
                    <th>Quantity Sold</th>
                    <th>Revenue (₱)</th>
                    <th>Inventory Level</th>
                    <th>Staff Member</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salesData as $sale): ?>
                    <tr>
                        <td>
                            <?php if (!empty($sale['image'])): ?>
                                <img src="<?php echo htmlspecialchars($sale['image']); ?>" class="w-8 h-8 mx-auto" alt="<?php echo htmlspecialchars($sale['product_name'] ?? 'No Name'); ?>">
                            <?php else: ?>
                                <img src="../../assets/default-product.jpg" class="w-8 h-8 mx-auto" alt="No Image Available">
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($sale['date']); ?></td>
                        <td><?php echo htmlspecialchars($sale['product_name'] ?? 'No Name'); ?></td>
                        <td><?php echo htmlspecialchars($sale['quantity_sold']); ?></td>
                        <td>₱<?php echo htmlspecialchars(number_format($sale['revenue'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($sale['inventory_level']); ?></td>
                        <td><?php echo htmlspecialchars($sale['staff_member']); ?></td>
                        <td><?php echo htmlspecialchars($sale['comments'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Waste Data Table -->
    <div class="overflow-x-auto mb-10">
        <h2 class="text-2xl font-semibold mb-5">Waste Data</h2>
        <table class="table table-zebra w-full">
            <thead>
                <tr class="bg-sec">
                    <th class="flex justify-center">Image</th>
                    <th>Date</th>
                    <th>Item Name</th>
                    <th>Waste Quantity</th>
                    <th>Waste Value (₱)</th>
                    <th>Waste Reason</th>
                    <th>Classification</th>
                    <th>Responsible Person</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wasteData as $waste): ?>
                    <tr>
                        <td>
                            <?php if (!empty($waste['item_image'])): ?>
                                <img src="<?php echo htmlspecialchars($waste['item_image']); ?>" class="w-8 h-8 mx-auto" alt="<?php echo htmlspecialchars($waste['item_name'] ?? 'No Name'); ?>">
                            <?php else: ?>
                                <img src="../../assets/default-product.jpg" class="w-8 h-8 mx-auto" alt="No Image Available">
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($waste['waste_date']); ?></td>
                        <td><?php echo htmlspecialchars($waste['item_name'] ?? 'No Name'); ?></td>
                        <td><?php echo htmlspecialchars($waste['waste_quantity']); ?></td>
                        <td>₱<?php echo htmlspecialchars(number_format($waste['waste_value'], 2)); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($waste['waste_reason'])); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($waste['classification'])); ?></td>
                        <td><?php echo htmlspecialchars($waste['responsible_person']); ?></td>
                        <td><?php echo htmlspecialchars($waste['comments'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
  </div>
</body>
</html>