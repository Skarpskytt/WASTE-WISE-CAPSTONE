<?php
include('../../config/db_connect.php');

// Handle form submission for adding a new NGO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $contact_email = $_POST['contact_email'] ?? '';
    $contact_phone = $_POST['contact_phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $category = $_POST['category'] ?? null;
    $operating_hours = $_POST['operating_hours'] ?? '';
    $capacity = $_POST['capacity'] ?? 0;

    if ($category === null) {
        die('Category is required.');
    }

    $stmt = $pdo->prepare("INSERT INTO ngos (name, contact_email, contact_phone, address, category, operating_hours, capacity) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $contact_email, $contact_phone, $address, $category, $operating_hours, $capacity]);

    header('Location: ngo.php');
    exit();
}

// Fetch all NGOs or apply search/filter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';

$query = "SELECT * FROM ngos WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND name LIKE ?";
    $params[] = "%$search%";
}

if ($category) {
    $query .= " AND category = ?";
    $params[] = $category;
}

if ($location) {
    $query .= " AND address LIKE ?";
    $params[] = "%$location%";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$ngos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGO Management</title>
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
</head>

<body class="flex h-screen bg-slate-100">

    <?php include '../layout/nav.php' ?>
    <div class="p-7">
      <div>
      <h2 class="text-3xl font-bold mb-6 text-primarycol">NGO Management</h2>
      </div>
  
            <!-- Filter Form -->
            <form method="GET" class="flex space-x-4 mb-4">
                <input type="text" name="search" placeholder="Search by name" class="input input-bordered">
                <select name="category" class="select select-bordered">
                    <option value="">All Categories</option>
                    <option value="Homeless Shelter">Homeless Shelter</option>
                    <option value="Food Bank">Food Bank</option>
                    <option value="Community Center">Community Center</option>
                    <option value="Other">Other</option>
                </select>
                <input type="text" name="location" placeholder="Search by location" class="input input-bordered">
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>

            <!-- Add New NGO Form -->
            <div class="flex flex-col mx-3 mt-6 lg:flex-row gap-4">
             <div class="w-full lg:w-1/3 m-1">
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">NGO Name</label>
                        <input type="text" name="name" placeholder="NGO Name" class="input input-bordered w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="contact_email">Contact Email</label>
                        <input type="email" name="contact_email" placeholder="Contact Email" class="input input-bordered w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="contact_phone">Contact Phone</label>
                        <input type="text" name="contact_phone" placeholder="Contact Phone" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="address">Address</label>
                        <input type="text" name="address" placeholder="Address" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="category">Category</label>
                        <select name="category" required>
                            <option value="Homeless Shelter">Homeless Shelter</option>
                            <option value="Food Bank">Food Bank</option>
                            <option value="Community Center">Community Center</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="operating_hours">Operating Hours</label>
                        <input type="text" name="operating_hours" placeholder="Operating Hours" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="capacity">Capacity</label>
                        <input type="number" name="capacity" placeholder="Capacity" class="input input-bordered w-full">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primarycol text-white font-bold py-2 px-4 rounded hover:bg-green-600 transition-colors">Add NGO</button>
                    </div>
                </form>
            </div>

            <!-- NGO Management Table -->
            <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200 ">
            <div class="overflow-x-auto p-4"> 
                <table class="table table-zebra w-full">
                    <thead class="border-s-gray-400">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact Email</th>
                            <th>Category</th>
                            <th>Capacity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ngos as $ngo): ?>
                        <tr>
                            <td><?= htmlspecialchars($ngo['id']) ?></td>
                            <td><?= htmlspecialchars($ngo['name']) ?></td>
                            <td><?= htmlspecialchars($ngo['contact_email']) ?></td>
                            <td><?= htmlspecialchars($ngo['category']) ?></td>
                            <td><?= htmlspecialchars($ngo['capacity']) ?></td>
                            <td class="flex space-x-2">
                                <a href="edit_ngo.php?id=<?= urlencode($ngo['id']) ?>" class="btn btn-sm btn-secondary flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13" />
                                    </svg>
                                    Edit
                                </a>
                                <a href="delete_ngo.php?id=<?= urlencode($ngo['id']) ?>" onclick="return confirm('Are you sure you want to delete this NGO?');" class="btn btn-sm btn-error flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($ngos)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No NGOs found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>