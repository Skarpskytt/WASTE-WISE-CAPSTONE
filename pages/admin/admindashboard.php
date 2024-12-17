<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php');

// Initialize date filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Function to append date filters to SQL queries
function appendDateFilters($baseQuery, $startDate, $endDate, &$params) {
    if ($startDate && $endDate) {
        $baseQuery .= " WHERE waste_date BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }
    return $baseQuery;
}

// Fetch data for Total Waste Quantity per Week
$wasteQuantityByWeekQuery = "
    SELECT YEAR(waste_date) AS year, WEEK(waste_date, 1) AS week, SUM(waste_quantity) AS total_quantity
    FROM waste
";
$params = [];
$wasteQuantityByWeekQuery = appendDateFilters($wasteQuantityByWeekQuery, $startDate, $endDate, $params);
$wasteQuantityByWeekQuery .= " GROUP BY YEAR(waste_date), WEEK(waste_date, 1) ORDER BY YEAR(waste_date), WEEK(waste_date, 1)";
$wasteQuantityByWeekStmt = $pdo->prepare($wasteQuantityByWeekQuery);
$wasteQuantityByWeekStmt->execute($params);
$wasteQuantityByWeekData = $wasteQuantityByWeekStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for Total Waste Value per Week
$wasteValueByWeekQuery = "
    SELECT YEAR(waste_date) AS year, WEEK(waste_date, 1) AS week, SUM(waste_value) AS total_value
    FROM waste
";
$params = [];
$wasteValueByWeekQuery = appendDateFilters($wasteValueByWeekQuery, $startDate, $endDate, $params);
$wasteValueByWeekQuery .= " GROUP BY YEAR(waste_date), WEEK(waste_date, 1) ORDER BY YEAR(waste_date), WEEK(waste_date, 1)";
$wasteValueByWeekStmt = $pdo->prepare($wasteValueByWeekQuery);
$wasteValueByWeekStmt->execute($params);
$wasteValueByWeekData = $wasteValueByWeekStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for Top Loss Reasons
$lossReasonQuery = "
    SELECT waste_reason, COUNT(*) AS count
    FROM waste
";
$params = [];
$lossReasonQuery = appendDateFilters($lossReasonQuery, $startDate, $endDate, $params);
$lossReasonQuery .= " GROUP BY waste_reason";
$lossReasonStmt = $pdo->prepare($lossReasonQuery);
$lossReasonStmt->execute($params);
$lossReasonData = $lossReasonStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for Top Wasted Food Items
$topWastedFoodQuery = "
    SELECT inventory.name AS item_name, SUM(waste.waste_quantity) AS total_waste_quantity
    FROM waste
    LEFT JOIN inventory ON waste.inventory_id = inventory.id
";
$params = [];
$topWastedFoodQuery = appendDateFilters($topWastedFoodQuery, $startDate, $endDate, $params);
$topWastedFoodQuery .= " GROUP BY waste.inventory_id ORDER BY total_waste_quantity DESC LIMIT 5";
$topWastedFoodStmt = $pdo->prepare($topWastedFoodQuery);
$topWastedFoodStmt->execute($params);
$topWastedFoodData = $topWastedFoodStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent waste transactions
$wasteTransactionsQuery = "
    SELECT waste.id, waste.waste_date, inventory.name AS item_name, waste.waste_quantity, waste.waste_value, waste.waste_reason
    FROM waste
    LEFT JOIN inventory ON waste.inventory_id = inventory.id
";
$params = [];
$wasteTransactionsQuery = appendDateFilters($wasteTransactionsQuery, $startDate, $endDate, $params);
$wasteTransactionsQuery .= " ORDER BY waste.waste_date DESC LIMIT 10";
$wasteTransactionsStmt = $pdo->prepare($wasteTransactionsQuery);
$wasteTransactionsStmt->execute($params);
$wasteTransactions = $wasteTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for Waste Quantity by Reason Over Time (Additional Suggestion)
$wasteByReasonOverTimeQuery = "
    SELECT waste_reason, DATE(waste_date) AS waste_date, SUM(waste_quantity) AS total_quantity
    FROM waste
";
$params = [];
$wasteByReasonOverTimeQuery = appendDateFilters($wasteByReasonOverTimeQuery, $startDate, $endDate, $params);
$wasteByReasonOverTimeQuery .= " GROUP BY waste_reason, DATE(waste_date) ORDER BY DATE(waste_date)";
$wasteByReasonOverTimeStmt = $pdo->prepare($wasteByReasonOverTimeQuery);
$wasteByReasonOverTimeStmt->execute($params);
$wasteByReasonOverTimeData = $wasteByReasonOverTimeStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Total Waste Quantity and Value for Alerts
$totalWasteQuantityStmt = $pdo->prepare("
    SELECT SUM(waste_quantity) AS total_quantity, SUM(waste_value) AS total_value
    FROM waste
" . ($startDate && $endDate ? " WHERE waste_date BETWEEN :start_date AND :end_date" : "")
);
if ($startDate && $endDate) {
    $totalWasteQuantityStmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
} else {
    $totalWasteQuantityStmt->execute();
}
$totalWaste = $totalWasteQuantityStmt->fetch(PDO::FETCH_ASSOC);
$totalWasteQuantity = $totalWaste['total_quantity'] ?? 0;
$totalWasteValue = $totalWaste['total_value'] ?? 0;

// Define Waste Thresholds
$thresholdQuantity = 1000; // Example threshold for quantity
$thresholdValue = 50000;   // Example threshold for value

// In your admindashboard.php where you check thresholds
if ($totalWasteQuantity > $thresholdQuantity || $totalWasteValue > $thresholdValue) {
    // Create notification messages
    if ($totalWasteQuantity > $thresholdQuantity) {
        $message = "Warning: Total Waste Quantity exceeds the threshold of {$thresholdQuantity} units.";
        $insertNotification = $pdo->prepare("
            INSERT INTO notifications (message, type) 
            VALUES (:message, 'warning')
        ");
        $insertNotification->execute([':message' => $message]);
    }
    
    if ($totalWasteValue > $thresholdValue) {
        $message = "Warning: Total Waste Value exceeds the threshold of ₱" . number_format($thresholdValue, 2) . ".";
        $insertNotification = $pdo->prepare("
            INSERT INTO notifications (message, type) 
            VALUES (:message, 'warning')
        ");
        $insertNotification->execute([':message' => $message]);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Waste Data</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
    </script>
</head>

<body class="flex h-screen bg-slate-100">
<?php include '../layout/nav.php' ?>

<div class="p-6 overflow-y-auto w-full">
    <!-- Date Range Filter -->
    

    <!-- Alerts and Notifications -->
    <?php if ($totalWasteQuantity > $thresholdQuantity || $totalWasteValue > $thresholdValue): ?>
        <div class="alert alert-warning shadow-lg mb-6">
            <div>
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
                </svg>
                <span>
                    <?= ($totalWasteQuantity > $thresholdQuantity) ? "Warning: Total Waste Quantity exceeds the threshold of {$thresholdQuantity} units." : ""; ?>
                    <?= ($totalWasteValue > $thresholdValue) ? "Warning: Total Waste Value exceeds the threshold of ₱" . number_format($thresholdValue, 2) . "." : ""; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Waste Quantity -->
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold">Total Waste Quantity</h3>
            <p class="text-2xl font-bold"><?= number_format($totalWasteQuantity, 2); ?> Units</p>
        </div>
        <!-- Total Waste Value -->
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold">Total Waste Value</h3>
            <p class="text-2xl font-bold">₱<?= number_format($totalWasteValue, 2); ?></p>
        </div>
        <div class="mb-6 flex space-x-2">
        <form method="GET" class="flex space-x-2">
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate); ?>" class="input input-bordered" placeholder="Start Date">
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate); ?>" class="input input-bordered" placeholder="End Date">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="admindashboard.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>
    </div>

    <!-- Charts Container -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Top Loss Reasons -->
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-2">Top Loss Reasons</h3>
            <div id="lossReasonChart"></div>
        </div>

        <!-- Top Wasted Food Items -->
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-2">Top Wasted Food Items</h3>
            <div id="topWastedFoodChart"></div>
        </div>

        <!-- Total Waste Quantity per Week -->
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-2">Total Waste Quantity per Week</h3>
            <div id="wasteQuantityByWeekChart"></div>
        </div>

        <!-- Total Waste Value per Week -->
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-2">Total Waste Value per Week</h3>
            <div id="wasteValueByWeekChart"></div>
        </div>

        <!-- Waste Quantity by Reason Over Time (Line Chart) -->
        <div class="bg-white shadow-md rounded-lg p-4 md:col-span-2">
            <h3 class="text-lg font-semibold mb-2">Waste Quantity by Reason Over Time</h3>
            <div id="wasteByReasonOverTimeChart"></div>
        </div>
    </div>

    <!-- Waste Transactions Table -->
    <div class="overflow-x-auto mb-10 mt-6">
        <h2 class="text-2xl font-semibold mb-5">Recent Waste Transactions</h2>
        <table class="table table-zebra w-full">
            <thead>
                <tr class="bg-sec">
                    <th>ID</th>
                    <th>Waste Date</th>
                    <th>Item Name</th>
                    <th>Waste Quantity</th>
                    <th>Waste Value</th>
                    <th>Waste Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($wasteTransactions) {
                    foreach ($wasteTransactions as $transaction): ?>
                        <tr>
                            <td><?= htmlspecialchars($transaction['id']) ?></td>
                            <td><?= htmlspecialchars($transaction['waste_date']); ?></td>
                            <td><?= htmlspecialchars($transaction['item_name'] ?? 'N/A'); ?></td>
                            <td><?= htmlspecialchars($transaction['waste_quantity']); ?></td>
                            <td>₱<?= htmlspecialchars(number_format($transaction['waste_value'], 2)); ?></td>
                            <td><?= ucfirst(htmlspecialchars($transaction['waste_reason'])); ?></td>
                        </tr>
                <?php endforeach; 
                } else { ?>
                    <tr><td colspan="6" class="text-center">No waste transactions found.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ApexCharts Scripts -->
<script>
    // Total Waste Quantity per Week
    var wasteQuantityByWeekOptions = {
        chart: {
            type: 'bar',
            height: 350,
            toolbar: {
                show: true
            }
        },
        series: [{
            name: 'Waste Quantity',
            data: [
                <?php foreach ($wasteQuantityByWeekData as $data): ?>
                    {
                        x: 'Week <?= $data['week'] ?>/<?= $data['year'] ?>',
                        y: <?= $data['total_quantity'] ?>
                    },
                <?php endforeach; ?>
            ]
        }],
        xaxis: {
            type: 'category'
        },
        tooltip: {
            enabled: true
        },
   
    };
    var wasteQuantityByWeekChart = new ApexCharts(document.querySelector("#wasteQuantityByWeekChart"), wasteQuantityByWeekOptions);
    wasteQuantityByWeekChart.render();

    // Total Waste Value per Week
    var wasteValueByWeekOptions = {
        chart: {
            type: 'bar',
            height: 350,
            toolbar: {
                show: true
            }
        },
        series: [{
            name: 'Waste Value',
            data: [
                <?php foreach ($wasteValueByWeekData as $data): ?>
                    {
                        x: 'Week <?= $data['week'] ?>/<?= $data['year'] ?>',
                        y: <?= $data['total_value'] ?>
                    },
                <?php endforeach; ?>
            ]
        }],
        xaxis: {
            type: 'category'
        },
        tooltip: {
            enabled: true,
            y: {
                formatter: function (val) {
                    return '₱' + val.toLocaleString();
                }
            }
        },
    
    };
    var wasteValueByWeekChart = new ApexCharts(document.querySelector("#wasteValueByWeekChart"), wasteValueByWeekOptions);
    wasteValueByWeekChart.render();

    // Top Loss Reasons (Pie Chart)
    var lossReasonOptions = {
        chart: {
            type: 'pie',
            height: 350,
            toolbar: {
                show: true
            }
        },
        series: [
            <?php foreach ($lossReasonData as $data): ?>
                <?= $data['count'] ?>,
            <?php endforeach; ?>
        ],
        labels: [
            <?php foreach ($lossReasonData as $data): ?>
                '<?= ucfirst($data['waste_reason']) ?>',
            <?php endforeach; ?>
        ],
        tooltip: {
            enabled: true,
            y: {
                formatter: function (val) {
                    return val + ' times';
                }
            }
        },
   
    };
    var lossReasonChart = new ApexCharts(document.querySelector("#lossReasonChart"), lossReasonOptions);
    lossReasonChart.render();

    // Top Wasted Food Items (Bar Chart)
    var topWastedFoodOptions = {
        chart: {
            type: 'bar',
            height: 350,
            toolbar: {
                show: true
            }
        },
        series: [{
            name: 'Total Waste Quantity',
            data: [
                <?php foreach ($topWastedFoodData as $data): ?>
                    {
                        x: '<?= addslashes($data['item_name']) ?>',
                        y: <?= $data['total_waste_quantity'] ?>
                    },
                <?php endforeach; ?>
            ]
        }],
        xaxis: {
            type: 'category'
        },
        tooltip: {
            enabled: true
        },
    
    };
    var topWastedFoodChart = new ApexCharts(document.querySelector("#topWastedFoodChart"), topWastedFoodOptions);
    topWastedFoodChart.render();

    // Waste Quantity by Reason Over Time (Line Chart)
    var wasteByReasonOverTimeOptions = {
        chart: {
            type: 'line',
            height: 350,
            toolbar: {
                show: true
            },
            zoom: {
                enabled: true
            }
        },
        series: [
            <?php
            // Prepare data for the chart
            $wasteReasons = [];
            foreach ($wasteByReasonOverTimeData as $row) {
                $wasteReasons[$row['waste_reason']][] = [
                    'x' => $row['waste_date'],
                    'y' => $row['total_quantity']
                ];
            }
            foreach ($wasteReasons as $reason => $data): ?>
            {
                name: '<?= ucfirst($reason) ?>',
                data: [
                    <?php foreach ($data as $point): ?>
                        { x: '<?= $point['x'] ?>', y: <?= $point['y'] ?> },
                    <?php endforeach; ?>
                ]
            },
            <?php endforeach; ?>
        ],
        xaxis: {
            type: 'datetime',
            title: {
                text: 'Date'
            }
        },
        yaxis: {
            title: {
                text: 'Waste Quantity'
            }
        },
        tooltip: {
            x: {
                format: 'dd MMM yyyy'
            }
        },
     
    };
    var wasteByReasonOverTimeChart = new ApexCharts(document.querySelector("#wasteByReasonOverTimeChart"), wasteByReasonOverTimeOptions);
    wasteByReasonOverTimeChart.render();
</script>

</body>
</html>