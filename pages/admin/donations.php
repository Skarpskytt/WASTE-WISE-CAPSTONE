<?php
// donations.php

// Include necessary files and start session
session_start();
include('../../config/db_connect.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize variables for notifications
$success = '';
$error = '';

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Begin transaction
    $pdo->beginTransaction();
    try {
        // Fetch the donation to retrieve associated waste_id
        $stmt = $pdo->prepare("SELECT * FROM donations WHERE id = ?");
        $stmt->execute([$delete_id]);
        $donation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($donation) {
            $waste_id = $donation['waste_id'];
            $quantity = $donation['quantity'];

            // Delete the donation
            $deleteStmt = $pdo->prepare("DELETE FROM donations WHERE id = ?");
            $deleteStmt->execute([$delete_id]);

            // Update the waste quantity
            $updateWaste = "UPDATE waste SET waste_quantity = waste_quantity + :quantity WHERE id = :waste_id";
            $updateStmt = $pdo->prepare($updateWaste);
            $updateStmt->execute([
                ':quantity' => $quantity,
                ':waste_id' => $waste_id
            ]);

            $pdo->commit();
            $success = 'Donation successfully deleted!';
        } else {
            throw new Exception("Donation not found.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        // Temporarily display the error message for debugging
        $error = 'An unexpected error occurred while deleting the donation: ' . htmlspecialchars($e->getMessage());
        // For production, use:
        // error_log("Donation Deletion Error: " . $e->getMessage());
        // $error = 'An unexpected error occurred while deleting the donation.';
    }
}

// Fetch Donations
$donationQuery = "
    SELECT 
        donations.id,
        donations.quantity,
        donations.preferred_date,
        donations.preferred_time,
        donations.notes,
        donations.expiry_date,
        donations.status,
        donations.created_at,
        ngos.name AS ngo_name,
        donations.food_type
    FROM donations
    JOIN ngos ON donations.ngo_id = ngos.id
    ORDER BY donations.created_at DESC
";
$donationStmt = $pdo->prepare($donationQuery);
$donationStmt->execute();
$donations = $donationStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Donations</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- DaisyUI for additional UI components -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <!-- jQuery (optional for additional interactions) -->
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
            // Toggle Sidebar (if applicable)
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

    <div class="flex-1 p-6 overflow-auto">
        <h1 class="text-3xl font-bold mb-6 text-primarycol">Manage Donations</h1>

        <!-- Notification Section -->
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                <span><?= $error ?></span>
            </div>
        <?php elseif($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-lg rounded-lg p-6 border border-gray-200">
            <h2 class="text-2xl font-semibold mb-4 text-primarycol">All Donations</h2>
            
            <!-- Donations Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                ID
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                NGO
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                Food
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                Quantity
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                Preferred Date
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                Preferred Time
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                Expiry Date
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                Status
                            </th>
                            <th class="py-2 px-4 border-b-2 border-gray-200 text-left text-sm font-semibold text-gray-700">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($donations)): ?>
                            <?php foreach($donations as $donation): ?>
                                <tr class="hover:bg-gray-100">
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?= htmlspecialchars($donation['id']) ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?= htmlspecialchars($donation['ngo_name']) ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?= htmlspecialchars($donation['food_type']) ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?= htmlspecialchars($donation['quantity']) ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?= htmlspecialchars($donation['preferred_date']) ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?= htmlspecialchars($donation['preferred_time']) ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?= htmlspecialchars($donation['expiry_date']) ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm text-gray-700">
                                        <?php
                                            // Display status with badges
                                            switch(strtolower($donation['status'])) {
                                                case 'pending':
                                                    echo '<span class="inline-block bg-yellow-200 text-yellow-800 text-xs px-2 py-1 rounded-full">Pending</span>';
                                                    break;
                                                case 'in progress':
                                                    echo '<span class="inline-block bg-blue-200 text-blue-800 text-xs px-2 py-1 rounded-full">In Progress</span>';
                                                    break;
                                                case 'completed':
                                                    echo '<span class="inline-block bg-green-200 text-green-800 text-xs px-2 py-1 rounded-full">Completed</span>';
                                                    break;
                                                default:
                                                    echo htmlspecialchars($donation['status']);
                                            }
                                        ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200 text-sm">
                                        <a href="edit_donation.php?id=<?= htmlspecialchars($donation['id']) ?>" class="text-blue-500 hover:underline mr-2">Edit</a>
                                        <a href="donations.php?delete_id=<?= htmlspecialchars($donation['id']) ?>" class="text-red-500 hover:underline delete-button">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="py-4 px-6 text-center text-gray-500">
                                    No donations found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Confirm Deletion</h3>
            <p class="py-4">Are you sure you want to delete this donation? This action cannot be undone.</p>
            <div class="modal-action">
                <button id="confirmDelete" class="btn btn-error">Delete</button>
                <label for="deleteModal" class="btn">Cancel</label>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.delete-button').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                $('#confirmDelete').attr('href', url);
                $('#deleteModal').modal('show');
            });
        });
    </script>
</body>
</html>