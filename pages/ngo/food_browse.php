<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Dashboard - Food Browse</title>
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
        <div class="text-2xl font-bold text-primarycol">Browse Available Donations</div>

        <!-- Filter/Search Options -->
        <div class="bg-white p-4 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-primarycol mb-4">Filter/Search Options</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700">Type of Food</label>
                    <select class="w-full p-2 border rounded-lg">
                        <option>All</option>
                        <option>Pastries</option>
                        <option>Cakes</option>
                        <option>Bread</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700">Quantity Available</label>
                    <input type="number" class="w-full p-2 border rounded-lg" placeholder="Minimum quantity">
                </div>
                <div>
                    <label class="block text-gray-700">Expiration Date</label>
                    <input type="date" class="w-full p-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700">Donor Name</label>
                    <input type="text" class="w-full p-2 border rounded-lg" placeholder="Donor name">
                </div>
            </div>
            <button class="mt-4 px-4 py-2 bg-primarycol text-white rounded-lg">Search</button>
        </div>

        <!-- Food Listing -->
        <div class="bg-white p-4 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-primarycol mb-4">Available Donations</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Example Food Item -->
                <div class="bg-gray-100 p-4 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-700">Chocolate Croissants</h3>
                    <p class="text-gray-500">Quantity: 50 pieces</p>
                    <p class="text-gray-500">Expiration Date: 2025-03-10</p>
                    <p class="text-gray-500">Donor: Bakery X</p>
                    <p class="text-gray-500">Pickup Time: 9 AM - 5 PM</p>
                    <button class="mt-2 px-4 py-2 bg-primarycol text-white rounded-lg">Request Pickup</button>
                </div>
                <!-- Add more food items as needed -->
            </div>
        </div>
    </div>
</body>
</html>