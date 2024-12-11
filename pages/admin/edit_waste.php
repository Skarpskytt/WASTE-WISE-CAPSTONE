<?php
// edit_waste.php

session_start();
 
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Include the database connection
include('../../config/db_connect.php'); // Adjust the path as necessary

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission to update waste record
    $id = $_POST['id'] ?? null;
    $waste_date = $_POST['waste_date'] ?? null;
    $inventory_id = $_POST['inventory_id'] ?? null;
    $waste_quantity = $_POST['waste_quantity'] ?? null;
    $waste_value = $_POST['waste_value'] ?? null;
    $waste_reason = $_POST['waste_reason'] ?? null;
    $responsible_person = $_POST['responsible_person'] ?? null;
    $comments = $_POST['comments'] ?? null;

    // Basic validation
    if (!$id || !$waste_date || !$inventory_id || !$waste_quantity || !$waste_value || !$waste_reason || !$responsible_person) {
        header('Location: table.php?error=Please+fill+in+all+required+fields');
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE waste 
            SET 
                waste_date = :waste_date, 
                inventory_id = :inventory_id, 
                waste_quantity = :waste_quantity, 
                waste_value = :waste_value, 
                waste_reason = :waste_reason, 
                responsible_person = :responsible_person, 
                comments = :comments
            WHERE id = :id
        ");
        $stmt->execute([
            ':waste_date' => $waste_date,
            ':inventory_id' => $inventory_id,
            ':waste_quantity' => $waste_quantity,
            ':waste_value' => $waste_value,
            ':waste_reason' => $waste_reason,
            ':responsible_person' => $responsible_person,
            ':comments' => $comments,
            ':id' => $id
        ]);

        header('Location: table.php?message=Waste+record+updated+successfully');
        exit();
    } catch (PDOException $e) {
        die("Error updating waste record: " . $e->getMessage());
    }
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Display the edit form with current waste data
    $id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("
            SELECT waste.*, inventory.name AS item_name 
            FROM waste 
            LEFT JOIN inventory ON waste.inventory_id = inventory.id 
            WHERE waste.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $waste = $stmt->fetch();

        if (!$waste) {
            header('Location: table.php?error=Waste+record+not+found');
            exit();
        }

        // Fetch inventory items for the dropdown
        $stmt = $pdo->query("SELECT id, name FROM inventory ORDER BY name ASC");
        $inventoryItems = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Error fetching waste record: " . $e->getMessage());
    }
} else {
    header('Location: table.php?error=Invalid+waste+record+ID');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Waste Record</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex justify-center items-center min-h-screen bg-gray-100">
    <div class="w-full max-w-2xl bg-white p-8 rounded shadow">
        <h2 class="text-2xl font-semibold mb-6">Edit Waste Record</h2>
        <?php
        // Display error or success messages
        if (isset($_GET['error'])) {
            echo '<div class="mb-4 text-red-600">' . htmlspecialchars($_GET['error']) . '</div>';
        }
        if (isset($_GET['message'])) {
            echo '<div class="mb-4 text-green-600">' . htmlspecialchars($_GET['message']) . '</div>';
        }
        ?>
        <form method="POST" action="edit_waste.php">
            <input type="hidden" name="id" value="<?= htmlspecialchars($waste['id'] ?? '') ?>">

            <div class="mb-4">
                <label class="block text-gray-700">Waste Date<span class="text-red-500">*</span></label>
                <input type="date" name="waste_date" value="<?= htmlspecialchars($waste['waste_date'] ?? '') ?>" required class="w-full px-3 py-2 border rounded">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Item Name<span class="text-red-500">*</span></label>
                <select name="inventory_id" required class="w-full px-3 py-2 border rounded">
                    <option value="">Select Item</option>
                    <?php foreach ($inventoryItems as $item): ?>
                        <option value="<?= htmlspecialchars($item['id']) ?>" <?= ($waste['inventory_id'] == $item['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Waste Quantity<span class="text-red-500">*</span></label>
                <input type="number" name="waste_quantity" value="<?= htmlspecialchars($waste['waste_quantity'] ?? '') ?>" required class="w-full px-3 py-2 border rounded" min="0" step="0.01">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Waste Value (₱)<span class="text-red-500">*</span></label>
                <input type="number" name="waste_value" value="<?= htmlspecialchars($waste['waste_value'] ?? '') ?>" required class="w-full px-3 py-2 border rounded" min="0" step="0.01">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Waste Reason<span class="text-red-500">*</span></label>
                <input type="text" name="waste_reason" value="<?= htmlspecialchars($waste['waste_reason'] ?? '') ?>" required class="w-full px-3 py-2 border rounded">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Responsible Person<span class="text-red-500">*</span></label>
                <input type="text" name="responsible_person" value="<?= htmlspecialchars($waste['responsible_person'] ?? '') ?>" required class="w-full px-3 py-2 border rounded">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Comments</label>
                <textarea name="comments" class="w-full px-3 py-2 border rounded"><?= htmlspecialchars($waste['comments'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="w-full bg-primarycol text-white py-2 rounded">Update Waste Record</button>
        </form>
    </div>
</body>
</html>