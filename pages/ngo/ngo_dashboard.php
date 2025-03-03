<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Dashboard - Waste Data</title>
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
    <?php include '../layout/ngo_nav.php' ?>

    <div class="flex flex-col w-full p-6 space-y-6">
        <div class="text-2xl font-bold text-primarycol">NGO Dashboard</div>

        <!-- Key Metrics and Overview Panel -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Total Donations Received -->
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold text-primarycol">Total Donations</h2>
                <p class="text-4xl font-bold text-gray-700">1500</p>
                <p class="text-sm text-gray-500">pastries donated this month</p>
            </div>

            <!-- Top Donor -->
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold text-primarycol">Top Donor</h2>
                <p class="text-4xl font-bold text-gray-700">Bakery X</p>
                <p class="text-sm text-gray-500">300 pastries donated this week</p>
            </div>

            <!-- Pending Requests -->
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold text-primarycol">Pending Requests</h2>
                <p class="text-4xl font-bold text-gray-700">5</p>
                <p class="text-sm text-gray-500">requests waiting to be processed</p>
            </div>

            <!-- Items Ready for Pickup -->
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold text-primarycol">Items Available for Pickup</h2>
                <p class="text-4xl font-bold text-gray-700">500</p>
                <p class="text-sm text-gray-500">pastries available for pickup</p>
            </div>

            <!-- Recipients Impacted -->
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold text-primarycol">Impact</h2>
                <p class="text-4xl font-bold text-gray-700">1000</p>
                <p class="text-sm text-gray-500">people served this month</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
            <!-- Total Donations Received -->
            <div class="bg-white p-4 rounded-lg shadow-md flex flex-col items-center">
                <h2 class="text-xl font-semibold text-primarycol mb-2">Total Donations Received</h2>
                <p class="text-4xl font-bold text-gray-700 mb-1">0</p>
                <p class="text-sm text-gray-500">Categorized by date or type</p>
            </div>

            <!-- Donor Statistics -->
            <div class="bg-white p-4 rounded-lg shadow-md flex flex-col items-center">
                <h2 class="text-xl font-semibold text-primarycol mb-2">Donor Statistics</h2>
                <div id="donor-stats-chart" class="h-40 w-full bg-gray-100 flex items-center justify-center">
                    <span class="text-gray-500">Chart Placeholder</span>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="bg-white p-4 rounded-lg shadow-md flex flex-col items-center">
                <h2 class="text-xl font-semibold text-primarycol mb-2">Pending Requests</h2>
                <ul class="list-disc list-inside text-gray-700">
                    <li>Request 1</li>
                    <li>Request 2</li>
                    <li>Request 3</li>
                </ul>
            </div>

            <!-- Donation Availability -->
            <div class="bg-white p-4 rounded-lg shadow-md flex flex-col items-center">
                <h2 class="text-xl font-semibold text-primarycol mb-2">Donation Availability</h2>
                <p class="text-gray-700 mb-1">Information about available food from your company and other contributors.</p>
                <p class="text-4xl font-bold text-gray-700 mb-1">0</p>
                <p class="text-sm text-gray-500">Available for pickup</p>
            </div>
        </div>
    </div>
</body>
</html>
