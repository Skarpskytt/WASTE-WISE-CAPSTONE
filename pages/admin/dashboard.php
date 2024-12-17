<?php
// filepath: /c:/xampp/htdocs/WASTE-WISE-CAPSTONE/pages/admin/dashboard.php
session_start();
include('../../config/db_connect.php');
require '../../vendor/autoload.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Fetch data for Top Wasted Food Items
$topWastedFoodQuery = "
    SELECT inventory.name AS item_name, SUM(waste.waste_quantity) AS total_waste_quantity
    FROM waste
    LEFT JOIN inventory ON waste.inventory_id = inventory.id
";
$params = [];
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
$wasteTransactionsQuery .= " ORDER BY waste.waste_date DESC LIMIT 10";
$wasteTransactionsStmt = $pdo->prepare($wasteTransactionsQuery);
$wasteTransactionsStmt->execute($params);
$wasteTransactions = $wasteTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for Waste Quantity by Reason Over Time
$wasteByReasonOverTimeQuery = "
    SELECT waste.waste_reason, DATE(waste.waste_date) AS waste_date, SUM(waste.waste_quantity) AS total_quantity
    FROM waste
";
$params = [];
$wasteByReasonOverTimeQuery .= " GROUP BY waste.waste_reason, DATE(waste.waste_date) ORDER BY DATE(waste.waste_date)";
$wasteByReasonOverTimeStmt = $pdo->prepare($wasteByReasonOverTimeQuery);
$wasteByReasonOverTimeStmt->execute($params);
$wasteByReasonOverTimeData = $wasteByReasonOverTimeStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Total Waste Quantity and Value for Alerts
$totalWasteQuantityStmt = $pdo->prepare("
    SELECT SUM(waste_quantity) AS total_quantity, SUM(waste_value) AS total_value
    FROM waste
");
$totalWasteQuantityStmt->execute();
$totalWasteData = $totalWasteQuantityStmt->fetch(PDO::FETCH_ASSOC);

// Fetch NGOs
$location = $_GET['location'] ?? '';
$type = $_GET['type'] ?? '';
$availability = $_GET['availability'] ?? '';

$ngoQuery = "SELECT * FROM ngos WHERE 1=1";
$params = [];

if ($location) {
    $ngoQuery .= " AND address LIKE ?";
    $params[] = "%$location%";
}
if ($type) {
    $ngoQuery .= " AND category = ?";
    $params[] = $type;
}
if ($availability) {
    $ngoQuery .= " AND capacity >= ?";
    $params[] = $availability;
}

$ngoStmt = $pdo->prepare($ngoQuery);
$ngoStmt->execute($params);
$ngos = $ngoStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Wasted Items for Donation
$wasteQuery = "
    SELECT 
        waste.id, 
        inventory.name AS food_type, 
        waste.waste_quantity, 
        inventory.image
    FROM waste
    JOIN inventory ON waste.inventory_id = inventory.id
    WHERE waste.waste_reason = 'donation' AND waste.waste_quantity > 0
";
$wasteStmt = $pdo->prepare($wasteQuery);
$wasteStmt->execute();
$wastedItems = $wasteStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize variables for notifications
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $selectedItems = $_POST['items'] ?? [];
        $quantities = $_POST['quantity_to_donate'] ?? [];
        $ngo_id = $_POST['ngo_id'] ?? null;
        $preferred_date = $_POST['preferred_date'] ?? null;
        $preferred_time = $_POST['preferred_time'] ?? null;
        $notes = $_POST['notes'] ?? null;
        $expiry_date = $_POST['expiry_date'] ?? null;

        // Validate required fields
        if (empty($ngo_id) || empty($selectedItems)) {
            throw new Exception("Missing required fields");
        }

        foreach ($selectedItems as $item_id) {
            $quantity = floatval($quantities[$item_id] ?? 0);
            if ($quantity <= 0) continue;

            // Check waste quantity
            $wasteCheckStmt = $pdo->prepare("SELECT waste_quantity, inventory_id FROM waste WHERE id = ?");
            $wasteCheckStmt->execute([$item_id]);
            $waste = $wasteCheckStmt->fetch(PDO::FETCH_ASSOC);

            if ($waste && $waste['waste_quantity'] >= $quantity) {
                // Get food type
                $foodTypeStmt = $pdo->prepare("SELECT name FROM inventory WHERE id = ?");
                $foodTypeStmt->execute([$waste['inventory_id']]);
                $foodType = $foodTypeStmt->fetchColumn();

                if (!$foodType) {
                    throw new Exception("Inventory item not found");
                }

                // Insert donation
                $insertStmt = $pdo->prepare("
                    INSERT INTO donations (
                        ngo_id, 
                        waste_id,
                        food_type,
                        quantity, 
                        preferred_date, 
                        preferred_time, 
                        notes, 
                        expiry_date, 
                        status,
                        created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW()
                    )
                ");

                $insertStmt->execute([
                    $ngo_id,
                    $item_id,
                    $foodType,
                    $quantity,
                    $preferred_date,
                    $preferred_time,
                    $notes,
                    $expiry_date
                ]);

                // Get donation ID and send notification
                $donationId = $pdo->lastInsertId();
                $notificationSent = sendDonationNotification($pdo, $donationId);

                // Update waste quantity
                $updateWasteStmt = $pdo->prepare("
                    UPDATE waste 
                    SET waste_quantity = waste_quantity - ? 
                    WHERE id = ?
                ");
                $updateWasteStmt->execute([$quantity, $item_id]);
            } else {
                throw new Exception("Insufficient quantity available");
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Donation created successfully" . 
            ($notificationSent ? " and notification sent" : " but notification failed");
        header('Location: dashboard.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header('Location: dashboard.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        
        <!-- Surplus Food Items -->
        <h2>Surplus Food Items</h2>
        <div class="food-items">
            <?php foreach ($wastedItems as $item): ?>
                <div class="food-item">
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['food_type']) ?>">
                    <p><?= htmlspecialchars($item['food_type']) ?> - <?= htmlspecialchars($item['waste_quantity']) ?> units</p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- NGO Filtering -->
        <h2>Filter NGOs</h2>
        <form method="GET" action="dashboard.php">
            <label for="location">Location:</label>
            <input type="text" id="location" name="location" value="<?= htmlspecialchars($location) ?>">
            
            <label for="type">Type:</label>
            <select id="type" name="type">
                <option value="">All</option>
                <option value="Homeless Shelter" <?= $type == 'Homeless Shelter' ? 'selected' : '' ?>>Homeless Shelter</option>
                <option value="Food Bank" <?= $type == 'Food Bank' ? 'selected' : '' ?>>Food Bank</option>
                <option value="Community Center" <?= $type == 'Community Center' ? 'selected' : '' ?>>Community Center</option>
                <option value="Other" <?= $type == 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
            
            <label for="availability">Availability:</label>
            <input type="number" id="availability" name="availability" value="<?= htmlspecialchars($availability) ?>">
            
            <button type="submit">Filter</button>
        </form>

        <!-- NGO List -->
        <h2>NGOs</h2>
        <div class="ngos">
            <?php foreach ($ngos as $ngo): ?>
                <div class="ngo">
                    <p><?= htmlspecialchars($ngo['name']) ?></p>
                    <p><?= htmlspecialchars($ngo['address']) ?></p>
                    <p><?= htmlspecialchars($ngo['category']) ?></p>
                    <p>Capacity: <?= htmlspecialchars($ngo['capacity']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Donation Request Creation -->
        <h2>Create Donation</h2>
        <?php if ($success): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="dashboard.php">
            <label for="ngo_id">Select NGO:</label>
            <select id="ngo_id" name="ngo_id" required>
                <option value="">Select NGO</option>
                <?php foreach ($ngos as $ngo): ?>
                    <option value="<?= htmlspecialchars($ngo['id']) ?>"><?= htmlspecialchars($ngo['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="preferred_date">Preferred Date:</label>
            <input type="date" id="preferred_date" name="preferred_date" required>

            <label for="preferred_time">Preferred Time:</label>
            <input type="time" id="preferred_time" name="preferred_time" required>

            <label for="notes">Notes:</label>
            <textarea id="notes" name="notes"></textarea>

            <label for="expiry_date">Expiry Date:</label>
            <input type="date" id="expiry_date" name="expiry_date">

            <h2>Select Items to Donate</h2>
            <?php foreach ($wastedItems as $item): ?>
                <div class="food-item">
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['food_type']) ?>">
                    <p><?= htmlspecialchars($item['food_type']) ?> - <?= htmlspecialchars($item['waste_quantity']) ?> units</p>
                    <label for="quantity_to_donate_<?= htmlspecialchars($item['id']) ?>">Quantity to Donate:</label>
                    <input type="number" id="quantity_to_donate_<?= htmlspecialchars($item['id']) ?>" name="quantity_to_donate[<?= htmlspecialchars($item['id']) ?>]" min="1" max="<?= htmlspecialchars($item['waste_quantity']) ?>">
                    <input type="checkbox" name="items[]" value="<?= htmlspecialchars($item['id']) ?>"> Select
                </div>
            <?php endforeach; ?>

            <button type="submit">Create Donation</button>
        </form>

        <!-- Metrics and Graphs -->
        <h2>Metrics and Graphs</h2>
        <div>
            <h3>Top Wasted Food Items</h3>
            <canvas id="topWastedFoodChart"></canvas>
        </div>
        <div>
            <h3>Waste Quantity by Reason Over Time</h3>
            <canvas id="wasteByReasonChart"></canvas>
        </div>
        <div>
            <h3>Total Waste Quantity and Value</h3>
            <p>Total Quantity: <?= htmlspecialchars($totalWasteData['total_quantity']) ?> units</p>
            <p>Total Value: $<?= htmlspecialchars($totalWasteData['total_value']) ?></p>
        </div>
    </div>

    <script>
        // Top Wasted Food Items Chart
        const topWastedFoodCtx = document.getElementById('topWastedFoodChart').getContext('2d');
        const topWastedFoodChart = new Chart(topWastedFoodCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($topWastedFoodData, 'item_name')) ?>,
                datasets: [{
                    label: 'Total Waste Quantity',
                    data: <?= json_encode(array_column($topWastedFoodData, 'total_waste_quantity')) ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Waste Quantity by Reason Over Time Chart
        const wasteByReasonCtx = document.getElementById('wasteByReasonChart').getContext('2d');
        const wasteByReasonData = <?= json_encode($wasteByReasonOverTimeData) ?>;
        const wasteByReasonLabels = [...new Set(wasteByReasonData.map(item => item.waste_date))];
        const wasteByReasonDatasets = [...new Set(wasteByReasonData.map(item => item.waste_reason))].map(reason => {
            return {
                label: reason,
                data: wasteByReasonLabels.map(date => {
                    const item = wasteByReasonData.find(d => d.waste_date === date && d.waste_reason === reason);
                    return item ? item.total_quantity : 0;
                }),
                fill: false,
                borderColor: getRandomColor(),
                tension: 0.1
            };
        });

        const wasteByReasonChart = new Chart(wasteByReasonCtx, {
            type: 'line',
            data: {
                labels: wasteByReasonLabels,
                datasets: wasteByReasonDatasets
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function getRandomColor() {
            const letters = '0123456789ABCDEF';
            let color = '#';
            for (let i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }
    </script>
</body>
</html>