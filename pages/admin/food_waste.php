<?php
include('../../config/db_connect.php');

// Fetch NGOs for the dropdown
$stmt = $pdo->query("SELECT id, name FROM ngos");
$ngos = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ngo_id = $_POST['ngo_id'] ?? null;
    $food_type = $_POST['food_type'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $preferred_date = $_POST['preferred_date'] ?? '';
    $preferred_time = $_POST['preferred_time'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? null;

    if (!$ngo_id || !$food_type || !$quantity || !$preferred_date || !$preferred_time) {
        die('Please fill in all required fields.');
    }

    $stmt = $pdo->prepare("INSERT INTO donations (ngo_id, food_type, quantity, preferred_date, preferred_time, notes, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ngo_id, $food_type, $quantity, $preferred_date, $preferred_time, $notes, $expiry_date]);

    // Fetch the last inserted donation ID
    $donation_id = $pdo->lastInsertId();

    // Send notification to the NGO
    // Include your notification logic here (e.g., send_email.php)
    // Example:
    // sendEmailNotification($ngo_email, $subject, $message);

    header('Location: donations.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Donation Request</title>
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

    <div class="flex-1 p-7">
        <div class="space-y-8">
            <!-- Create Donation Request Form -->
            <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200">
                <h2 class="text-2xl font-semibold mb-4">Create Donation Request</h2>
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="ngo_id">Select NGO</label>
                        <select name="ngo_id" class="select select-bordered w-full" required>
                            <option disabled selected>Select NGO</option>
                            <?php foreach($ngos as $ngo): ?>
                                <option value="<?= htmlspecialchars($ngo['id']) ?>"><?= htmlspecialchars($ngo['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="food_type">Type of Food</label>
                        <input type="text" name="food_type" placeholder="Type of Food" class="input input-bordered w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="quantity">Quantity</label>
                        <input type="number" name="quantity" placeholder="Quantity" class="input input-bordered w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="preferred_date">Preferred Date</label>
                        <input type="date" name="preferred_date" class="input input-bordered w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="preferred_time">Preferred Time</label>
                        <input type="time" name="preferred_time" class="input input-bordered w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">Notes or Instructions</label>
                        <textarea name="notes" placeholder="Notes or Instructions" class="textarea textarea-bordered w-full"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" class="input input-bordered w-full" placeholder="Expiry Date">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary w-full">Create Donation</button>
                    </div>
                </form>
            </div>

            <!-- Donation Management Table -->
            <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200">
                <h2 class="text-2xl font-semibold mb-4">Donation Management</h2>
                <a href="donations.php" class="btn btn-secondary mb-4">View Donations</a>
            </div>
        </div>
    </div>

</body>
</html>