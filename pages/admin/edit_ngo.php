<?php
include('../../config/db_connect.php');

// Get NGO ID from URL
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: ngo.php');
    exit();
}

// Fetch NGO details
$stmt = $pdo->prepare("SELECT * FROM ngos WHERE id = ?");
$stmt->execute([$id]);
$ngo = $stmt->fetch();

if (!$ngo) {
    header('Location: ngo.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $contact_email = $_POST['contact_email'];
    $contact_phone = $_POST['contact_phone'];
    $address = $_POST['address'];
    $category = $_POST['category'];
    $operating_hours = $_POST['operating_hours'];
    $capacity = $_POST['capacity'];

    $stmt = $pdo->prepare("UPDATE ngos SET name = ?, contact_email = ?, contact_phone = ?, address = ?, category = ?, operating_hours = ?, capacity = ? WHERE id = ?");
    $stmt->execute([$name, $contact_email, $contact_phone, $address, $category, $operating_hours, $capacity, $id]);

    header('Location: ngo.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit NGO</title>
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
<body class="flex">
    <?php include '../layout/nav.php'?>

    <div class="p-8 w-full">
        <h2 class="text-2xl font-bold mb-4">Edit NGO</h2>
        <form method="POST" class="space-y-4">
            <input type="text" name="name" value="<?= htmlspecialchars($ngo['name']) ?>" placeholder="NGO Name" class="input input-bordered w-full" required>
            <input type="email" name="contact_email" value="<?= htmlspecialchars($ngo['contact_email']) ?>" placeholder="Contact Email" class="input input-bordered w-full" required>
            <input type="text" name="contact_phone" value="<?= htmlspecialchars($ngo['contact_phone']) ?>" placeholder="Contact Phone" class="input input-bordered w-full">
            <input type="text" name="address" value="<?= htmlspecialchars($ngo['address']) ?>" placeholder="Address" class="input input-bordered w-full">
            <select name="category" class="select select-bordered w-full" required>
                <option disabled>Select Category</option>
                <option value="Homeless Shelter" <?= $ngo['category'] === 'Homeless Shelter' ? 'selected' : '' ?>>Homeless Shelter</option>
                <option value="Food Bank" <?= $ngo['category'] === 'Food Bank' ? 'selected' : '' ?>>Food Bank</option>
                <option value="Community Center" <?= $ngo['category'] === 'Community Center' ? 'selected' : '' ?>>Community Center</option>
                <option value="Other" <?= $ngo['category'] === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
            <input type="text" name="operating_hours" value="<?= htmlspecialchars($ngo['operating_hours']) ?>" placeholder="Operating Hours" class="input input-bordered w-full">
            <input type="number" name="capacity" value="<?= htmlspecialchars($ngo['capacity']) ?>" placeholder="Capacity" class="input input-bordered w-full">
            <button type="submit" class="btn btn-primary">Update NGO</button>
        </form>
    </div>
</body>
</html>