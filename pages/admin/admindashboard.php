<?php
// Keep only auth middleware and DB connection
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Maintain the authentication check
checkAuth(['admin']);

// Simple date range for filter controls
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Waste Data</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
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
    <!-- Date filter form -->
    <div class="mb-6 flex space-x-2">
        <form method="GET" class="flex space-x-2">
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate); ?>" class="input input-bordered" placeholder="Start Date">
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate); ?>" class="input input-bordered" placeholder="End Date">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="admindashboard.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>

    <!-- Recommendations Panel -->
    <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
        <h3 class="font-bold text-lg mb-2">Smart Recommendations</h3>
        <ul class="list-disc pl-5">
            <li class="mb-1">Consider reviewing inventory management practices</li>
            <li class="mb-1">Review high-value items that are wasted frequently</li>
            <li class="mb-1">Implement immediate waste reduction measures</li>
        </ul>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold">Total Waste Quantity</h3>
            <p class="text-2xl font-bold">125.50 Units</p>
            <p class="text-sm text-gray-500">Threshold: 100.00 Units</p>
        </div>

        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold">Total Waste Value</h3>
            <p class="text-2xl font-bold">₱2,350.75</p>
            <p class="text-sm text-gray-500">Threshold: ₱2,000.00</p>
        </div>

        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold">Monthly Waste Trend</h3>
            <p class="text-2xl font-bold text-red-500">↑ 12.50%</p>
            <p class="text-sm text-gray-500">Compared to last month</p>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-4">
            <h3 class="text-lg font-semibold">Disposal Methods</h3>
            <p class="text-2xl font-bold">4</p>
            <p class="text-sm text-gray-500">Active methods used</p>
        </div>
    </div>

    <!-- Top Wasted Food Items Table -->
    <div class="bg-white shadow-md rounded-lg p-4 mb-6">
        <h3 class="text-lg font-semibold mb-3">Top Wasted Food Items</h3>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="bg-sec">
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Waste Quantity</th>
                        <th>Waste Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Flour</td>
                        <td>Ingredient</td>
                        <td>45.75</td>
                        <td>₱915.00</td>
                    </tr>
                    <tr>
                        <td>Bread Loaf</td>
                        <td>Product</td>
                        <td>25.00</td>
                        <td>₱750.00</td>
                    </tr>
                    <tr>
                        <td>Butter</td>
                        <td>Ingredient</td>
                        <td>15.50</td>
                        <td>₱542.50</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Loss Reasons Table -->
    <div class="bg-white shadow-md rounded-lg p-4 mb-6">
        <h3 class="text-lg font-semibold mb-3">Loss Reasons</h3>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="bg-sec">
                        <th>Reason</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Expired</td>
                        <td>15</td>
                    </tr>
                    <tr>
                        <td>Failed batch</td>
                        <td>8</td>
                    </tr>
                    <tr>
                        <td>Spilled</td>
                        <td>6</td>
                    </tr>
                    <tr>
                        <td>Quality control</td>
                        <td>4</td>
                    </tr>
                </tbody>
            </table>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>2025-02-28 14:30:00</td>
                    <td>Flour</td>
                    <td>12.5</td>
                    <td>₱250.00</td>
                    <td>Spilled</td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline">Analyze</a>
                    </td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>2025-02-27 09:15:00</td>
                    <td>Bread Loaf</td>
                    <td>8.0</td>
                    <td>₱240.00</td>
                    <td>Expired</td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline">Analyze</a>
                    </td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>2025-02-26 16:45:00</td>
                    <td>Butter</td>
                    <td>5.0</td>
                    <td>₱175.00</td>
                    <td>Quality control</td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline">Analyze</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
