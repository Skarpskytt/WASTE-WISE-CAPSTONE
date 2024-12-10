<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php'); // Ensure the path is correct

// Initialize variables
$weeklySales = $weeklyWaste = $yearlySales = $yearlyWaste = $averageRevenuePerProduct = $topProductTrend = null;

// Fetch Sales Data for the Current Week
try {
    $stmt = $pdo->query("
        SELECT SUM(revenue) AS total_revenue
        FROM sales
        WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $weeklySales = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching weekly sales data: " . $e->getMessage());
}

// Fetch Waste Data for the Current Week
try {
    $stmt = $pdo->query("
        SELECT SUM(waste_value) AS total_waste
        FROM waste
        WHERE YEARWEEK(waste_date, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $weeklyWaste = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching weekly waste data: " . $e->getMessage());
}

// Fetch Total Sales for the Current Year
try {
    $stmt = $pdo->query("
        SELECT SUM(revenue) AS total_sales
        FROM sales
        WHERE YEAR(date) = YEAR(CURDATE())
    ");
    $yearlySales = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching yearly sales data: " . $e->getMessage());
}

// Fetch Total Waste for the Current Year
try {
    $stmt = $pdo->query("
        SELECT SUM(waste_value) AS total_waste
        FROM waste
        WHERE YEAR(waste_date) = YEAR(CURDATE())
    ");
    $yearlyWaste = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching yearly waste data: " . $e->getMessage());
}

// Fetch Average Revenue per Product for the Current Year
try {
    $stmt = $pdo->query("
        SELECT AVG(revenue) AS average_revenue_per_product
        FROM sales
        WHERE YEAR(date) = YEAR(CURDATE())
    ");
    $averageRevenuePerProduct = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Error fetching average revenue per product: " . $e->getMessage());
}

// Fetch Top Product Trend for the Current Week
try {
    $stmt = $pdo->query("
        SELECT products.name, SUM(sales.quantity_sold) AS total_sold
        FROM sales
        LEFT JOIN products ON sales.product_id = products.id
        WHERE YEARWEEK(sales.date, 1) = YEARWEEK(CURDATE(), 1)
        GROUP BY products.name
        ORDER BY total_sold DESC
        LIMIT 1
    ");
    $topProductTrend = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching top product trend: " . $e->getMessage());
}

// Fetch Top Loss Reasons
try {
    $stmt = $pdo->query("
        SELECT waste_reason, COUNT(*) AS count
        FROM waste
        WHERE waste_reason IN ('overproduction', 'expired', 'compost', 'donation', 'dumpster')
        GROUP BY waste_reason
        ORDER BY count DESC
    ");
    $topLossReasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching top loss reasons: " . $e->getMessage());
}

// Fetch Top Wasted Foods
try {
    $stmt = $pdo->query("
        SELECT products.name, SUM(waste.waste_quantity) AS total_waste
        FROM waste
        LEFT JOIN products ON waste.inventory_id = products.id
        WHERE waste.classification = 'product'
        GROUP BY products.name
        ORDER BY total_waste DESC
    ");
    $topWastedFoods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching top wasted foods: " . $e->getMessage());
}

// Fetch Product Trend Data
try {
    $stmt = $pdo->query("
        SELECT products.name, DATE_FORMAT(sales.date, '%Y-%m') AS month, SUM(sales.quantity_sold) AS total_sold
        FROM sales
        LEFT JOIN products ON sales.product_id = products.id
        GROUP BY products.name, month
        ORDER BY month ASC
    ");
    $productTrendData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching product trend data: " . $e->getMessage());
}

// Fetch Sales and Waste Data for Chart
try {
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(date, '%Y-%m-%d') AS date, SUM(revenue) AS total_revenue
        FROM sales
        GROUP BY date
        ORDER BY date ASC
    ");
    $dailySalesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(date, '%Y-%u') AS week, SUM(revenue) AS total_revenue
        FROM sales
        GROUP BY week
        ORDER BY week ASC
    ");
    $weeklySalesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(date, '%Y-%m') AS month, SUM(revenue) AS total_revenue
        FROM sales
        GROUP BY month
        ORDER BY month ASC
    ");
    $monthlySalesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(waste_date, '%Y-%m-%d') AS date, SUM(waste_value) AS total_waste
        FROM waste
        GROUP BY date
        ORDER BY date ASC
    ");
    $dailyWasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(waste_date, '%Y-%u') AS week, SUM(waste_value) AS total_waste
        FROM waste
        GROUP BY week
        ORDER BY week ASC
    ");
    $weeklyWasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(waste_date, '%Y-%m') AS month, SUM(waste_value) AS total_waste
        FROM waste
        GROUP BY month
        ORDER BY month ASC
    ");
    $monthlyWasteData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching sales and waste data: " . $e->getMessage());
}

// Prepare data for the chart
function prepareChartData($data, $key, $valueKey) {
    $dates = array_column($data, $key);
    $values = array_column($data, $valueKey);
    return ['dates' => $dates, 'values' => $values];
}

$dailySalesChartData = prepareChartData($dailySalesData, 'date', 'total_revenue');
$weeklySalesChartData = prepareChartData($weeklySalesData, 'week', 'total_revenue');
$monthlySalesChartData = prepareChartData($monthlySalesData, 'month', 'total_revenue');

$dailyWasteChartData = prepareChartData($dailyWasteData, 'date', 'total_waste');
$weeklyWasteChartData = prepareChartData($weeklyWasteData, 'week', 'total_waste');
$monthlyWasteChartData = prepareChartData($monthlyWasteData, 'month', 'total_waste');

// Prepare data for pie, bar, and line charts
function preparePieChartData($data) {
    $labels = array_column($data, 'waste_reason');
    $values = array_column($data, 'count');
    return ['labels' => $labels, 'values' => $values];
}

function prepareBarChartData($data) {
    $labels = array_column($data, 'name');
    $values = array_column($data, 'total_waste');
    return ['labels' => $labels, 'values' => $values];
}

function prepareLineChartData($data) {
    $products = array_unique(array_column($data, 'name'));
    $months = array_unique(array_column($data, 'month'));
    sort($months);
    $series = [];

    foreach ($products as $product) {
        $productData = array_filter($data, function($item) use ($product) {
            return $item['name'] === $product;
        });
        $productData = array_column($productData, 'total_sold', 'month');
        $values = [];
        foreach ($months as $month) {
            $values[] = isset($productData[$month]) ? (int)$productData[$month] : 0;
        }
        $series[] = ['name' => $product, 'data' => $values];
    }

    return ['months' => $months, 'series' => $series];
}

$pieChartData = preparePieChartData($topLossReasons);
$barChartData = prepareBarChartData($topWastedFoods);
$lineChartData = prepareLineChartData($productTrendData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- DaisyUI for Tailwind Components -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    
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
</head>
<body class="flex h-max">
    <?php include '../layout/nav.php'?>

    <div class="p-8 w-full">
        <!-- Statistics Section -->
        <div class="grid grid-cols-4 gap-6 p-8">
            <div class="stats stats-vertical shadow gap-4 border-4 border-sec">
                <div class="stat">
                    <div class="stat-title font-semibold">Sales (This Week)</div>
                    <div class="stat-value text-primarycol">₱<?php echo number_format($weeklySales, 2); ?></div>
                    <div class="stat-desc">This Week</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Waste Value (This Week)</div>
                    <div class="stat-value text-primarycol">₱<?php echo number_format($weeklyWaste, 2); ?></div>
                    <div class="stat-desc">This Week</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Total Sales (This Year)</div>
                    <div class="stat-value text-primarycol">₱<?php echo number_format($yearlySales, 2); ?></div>
                    <div class="stat-desc">This Year</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Total Waste (This Year)</div>
                    <div class="stat-value text-primarycol">₱<?php echo number_format($yearlyWaste, 2); ?></div>
                    <div class="stat-desc">This Year</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Average Revenue per Product (This Year)</div>
                    <div class="stat-value text-primarycol">₱<?php echo number_format($averageRevenuePerProduct, 2); ?></div>
                    <div class="stat-desc">This Year</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Product Trend</div>
                    <div class="stat-value text-primarycol"><?php echo htmlspecialchars($topProductTrend['name'] ?? 'N/A'); ?></div>
                    <div class="stat-desc">This Week</div>
                </div>
            </div>

            <!-- Sales & Waste Area Chart -->
            <div class="flex flex-col col-span-3 mb-4 rounded-2xl bg-gray-50 p-4 border-4 border-sec">
                <h2 class="font-extrabold text-3xl text-primarycol">Sales & Waste Data</h2>
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <button id="dailyBtn" class="btn btn-primary">Daily</button>
                        <button id="weeklyBtn" class="btn btn-secondary">Weekly</button>
                        <button id="monthlyBtn" class="btn btn-accent">Monthly</button>
                    </div>
                </div>
                <div id="areachart"></div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-4 gap-6 p-8">
            <!-- Top Loss Reason -->
            <div class="flex flex-col mb-4 bg-gray-50 p-6 border-4 rounded-2xl border-sec col-span-2">
                <h2 class="font-extrabold text-3xl text-primarycol mb-6">Top Loss Reason</h2>
                <div id="piechart"></div>
            </div>

            <!-- Top Wasted Foods -->
            <div class="flex flex-col mb-4 bg-gray-50 p-6 border-4 rounded-2xl border-sec col-span-2">
                <h2 class="font-extrabold text-3xl text-primarycol mb-6">Top Wasted Foods</h2>
                <div id="barchart"></div>
            </div>
        </div>

        <!-- Product Trend -->
        <div class="size-full p-8">
            <h2 class="font-extrabold text-3xl text-primarycol mb-6">Product Trend</h2>
            <div id="linechart"></div>
            <div class="overflow-x-auto mt-6">
                <table class="table mt-2 w-full">
                    <!-- head -->
                    <thead>
                        <tr class="bg-primarycol text-white">
                            <th></th>
                            <th>Name</th>
                            <th>Daily</th>
                            <th>Weekly</th>
                            <th>Monthly</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populate dynamically if needed -->
                        <tr>
                            <th>1</th>
                            <td>Ensaymada</td>
                            <td>80</td>
                            <td>30</td>
                            <td>21</td>
                        </tr>
                        <tr>
                            <th>2</th>
                            <td>Pandesal</td>
                            <td>100</td>
                            <td>300</td>
                            <td>800</td>
                        </tr>
                        <tr>
                            <th>3</th>
                            <td>Muffin</td>
                            <td>40</td>
                            <td>200</td>
                            <td>520</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    // Pie Chart - Top Loss Reason
    var pieOptions = {
        series: <?php echo json_encode($pieChartData['values']); ?>,
        chart: {
            type: 'donut',
        },
        labels: <?php echo json_encode($pieChartData['labels']); ?>,
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                },
                legend: {
                    position: 'bottom'
                }
            }
        }]
    };

    var pieChart = new ApexCharts(document.querySelector("#piechart"), pieOptions);
    pieChart.render();

    // Bar Chart - Top Wasted Foods
    var barOptions = {
        series: [{
            name: 'Total Waste',
            data: <?php echo json_encode($barChartData['values']); ?>
        }],
        chart: {
            type: 'bar',
            height: 350
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: true,
            }
        },
        dataLabels: {
            enabled: false
        },
        xaxis: {
            categories: <?php echo json_encode($barChartData['labels']); ?>,
            title: {
                text: 'Total Waste'
            }
        },
        title: {
            text: 'Top Wasted Foods'
        }
    };

    var barChart = new ApexCharts(document.querySelector("#barchart"), barOptions);
    barChart.render();

    // Line Chart - Product Trend
    var lineOptions = {
        series: <?php echo json_encode($lineChartData['series']); ?>,
        chart: {
            height: 350,
            type: 'line',
            zoom: {
                enabled: false
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth'
        },
        grid: {
            row: {
                colors: ['#f3f3f3', 'transparent'], // Takes an array which will be repeated on columns
                opacity: 0.5
            },
        },
        xaxis: {
            categories: <?php echo json_encode($lineChartData['months']); ?>,
            title: {
                text: 'Month'
            }
        },
        title: {
            text: 'Product Trend Over Time'
        }
    };

    var lineChart = new ApexCharts(document.querySelector("#linechart"), lineOptions);
    lineChart.render();

    // Area Chart - Sales & Waste Data
    var dailySalesData = <?php echo json_encode($dailySalesChartData['values']); ?>;
    var weeklySalesData = <?php echo json_encode($weeklySalesChartData['values']); ?>;
    var monthlySalesData = <?php echo json_encode($monthlySalesChartData['values']); ?>;

    var dailyWasteData = <?php echo json_encode($dailyWasteChartData['values']); ?>;
    var weeklyWasteData = <?php echo json_encode($weeklyWasteChartData['values']); ?>;
    var monthlyWasteData = <?php echo json_encode($monthlyWasteChartData['values']); ?>;

    var dailyDates = <?php echo json_encode($dailySalesChartData['dates']); ?>;
    var weeklyDates = <?php echo json_encode($weeklySalesChartData['dates']); ?>;
    var monthlyDates = <?php echo json_encode($monthlySalesChartData['dates']); ?>;

    var areaOptions = {
        series: [{
            name: 'Sales',
            data: dailySalesData
        }, {
            name: 'Waste',
            data: dailyWasteData
        }],
        chart: {
            height: 365,
            type: 'area'
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth'
        },
        xaxis: {
            type: 'datetime',
            categories: dailyDates
        },
        tooltip: {
            x: {
                format: 'yyyy-MM-dd'
            },
        },
    };

    var areaChart = new ApexCharts(document.querySelector("#areachart"), areaOptions);
    areaChart.render();

    document.getElementById('dailyBtn').addEventListener('click', function () {
        areaChart.updateOptions({
            series: [{
                name: 'Sales',
                data: dailySalesData
            }, {
                name: 'Waste',
                data: dailyWasteData
            }],
            xaxis: {
                categories: dailyDates
            }
        });
    });

    document.getElementById('weeklyBtn').addEventListener('click', function () {
        areaChart.updateOptions({
            series: [{
                name: 'Sales',
                data: weeklySalesData
            }, {
                name: 'Waste',
                data: weeklyWasteData
            }],
            xaxis: {
                categories: weeklyDates
            }
        });
    });

    document.getElementById('monthlyBtn').addEventListener('click', function () {
        areaChart.updateOptions({
            series: [{
                name: 'Sales',
                data: monthlySalesData
            }, {
                name: 'Waste',
                data: monthlyWasteData
            }],
            xaxis: {
                categories: monthlyDates
            }
        });
    });
});
</script>
</body>
</html>
