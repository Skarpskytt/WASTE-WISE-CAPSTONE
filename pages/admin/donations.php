<?php
// donations.php

// Include necessary files and start session
session_start();
include('../../config/db_connect.php');
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize variables for notifications
$success = '';
$error = '';

// CSV Export Function (doesn't require additional extensions)
if (isset($_POST['export_excel'])) {
    try {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="donations.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, ['ID', 'NGO', 'Food Type', 'Quantity', 'Preferred Date', 'Status', 'Created At']);
        
        // Fetch donations
        $stmt = $pdo->query("
            SELECT d.id, n.name as ngo_name, d.food_type, d.quantity, 
                   d.preferred_date, d.status, d.created_at
            FROM donations d
            JOIN ngos n ON d.ngo_id = n.id
            ORDER BY d.created_at DESC
        ");
        
        // Add data rows
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['ngo_name'],
                $row['food_type'],
                $row['quantity'],
                $row['preferred_date'],
                $row['status'],
                $row['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        $error = "Failed to export file: " . $e->getMessage();
    }
}

// PDF Export Function
if (isset($_POST['export_pdf'])) {
    require_once '../../vendor/autoload.php';
    
    try {
        // Create new PDF document
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('WasteWise');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Donations Report');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 12);
        
        // Title
        $pdf->Cell(0, 10, 'Donations Report', 0, 1, 'C');
        $pdf->Ln(10);
        
        // Table headers
        $headers = ['ID', 'NGO', 'Food Type', 'Quantity', 'Preferred Date', 'Status'];
        $width = [20, 40, 35, 25, 35, 30];
        
        // Header row
        for($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($width[$i], 10, $headers[$i], 1, 0, 'C');
        }
        $pdf->Ln();
        
        // Data rows
        $stmt = $pdo->query("
            SELECT d.id, n.name as ngo_name, d.food_type, d.quantity, 
                   d.preferred_date, d.status
            FROM donations d
            JOIN ngos n ON d.ngo_id = n.id
            ORDER BY d.created_at DESC
        ");
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell($width[0], 10, $row['id'], 1, 0, 'C');
            $pdf->Cell($width[1], 10, $row['ngo_name'], 1, 0, 'L');
            $pdf->Cell($width[2], 10, $row['food_type'], 1, 0, 'L');
            $pdf->Cell($width[3], 10, $row['quantity'], 1, 0, 'R');
            $pdf->Cell($width[4], 10, $row['preferred_date'], 1, 0, 'C');
            $pdf->Cell($width[5], 10, $row['status'], 1, 0, 'C');
            $pdf->Ln();
        }
        
        // Output PDF
        $pdf->Output('donations_report.pdf', 'D');
        exit;
    } catch (Exception $e) {
        $error = "Failed to generate PDF: " . $e->getMessage();
    }
}

// Handle Edit
if (isset($_POST['edit_donation'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET quantity = ?, preferred_date = ?, preferred_time = ?, 
                notes = ?, expiry_date = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['quantity'],
            $_POST['preferred_date'],
            $_POST['preferred_time'],
            $_POST['notes'],
            $_POST['expiry_date'],
            $_POST['status'],
            $_POST['donation_id']
        ]);
        $success = "Donation updated successfully!";
    } catch (Exception $e) {
        $error = "Failed to update donation.";
    }
}

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
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($donation)) ?>)" 
                                                class="text-blue-500 hover:underline mr-2">
                                            Edit
                                        </button>
                                        <button onclick="openDeleteModal(<?= htmlspecialchars($donation['id']) ?>)" 
                                                class="text-red-500 hover:underline">
                                            Delete
                                        </button>
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

            <!-- Add these buttons after your table -->
            <div class="flex gap-2 mt-4">
                <form method="POST">
                    <button type="submit" name="export_excel" class="btn btn-success">
                        Export to Excel
                    </button>
                </form>
                <form method="POST">
                    <button type="submit" name="export_pdf" class="btn btn-error">
                        Export to PDF
                    </button>
                </form>
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

    <!-- Edit Modal -->
    <dialog id="edit_modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Edit Donation</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="donation_id" id="edit_donation_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Quantity</label>
                    <input type="number" name="quantity" id="edit_quantity" class="input input-bordered w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Preferred Date</label>
                    <input type="date" name="preferred_date" id="edit_preferred_date" class="input input-bordered w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Preferred Time</label>
                    <input type="time" name="preferred_time" id="edit_preferred_time" class="input input-bordered w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" id="edit_notes" class="textarea textarea-bordered w-full"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
                    <input type="date" name="expiry_date" id="edit_expiry_date" class="input input-bordered w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="edit_status" class="select select-bordered w-full" required>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div class="modal-action">
                    <button type="submit" name="edit_donation" class="btn btn-primary">Save Changes</button>
                    <button type="button" onclick="closeEditModal()" class="btn">Cancel</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Delete Confirmation Modal -->
    <dialog id="delete_modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Confirm Deletion</h3>
            <p class="py-4">Are you sure you want to delete this donation?</p>
            <div class="modal-action">
                <form method="POST">
                    <input type="hidden" name="delete_id" id="delete_donation_id">
                    <button type="submit" class="btn btn-error">Delete</button>
                    <button type="button" onclick="closeDeleteModal()" class="btn">Cancel</button>
                </form>
            </div>
        </div>
    </dialog>

    <script>
        $(document).ready(function() {
            $('.delete-button').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                $('#confirmDelete').attr('href', url);
                $('#deleteModal').modal('show');
            });
        });

        function openEditModal(donation) {
            document.getElementById('edit_donation_id').value = donation.id;
            document.getElementById('edit_quantity').value = donation.quantity;
            document.getElementById('edit_preferred_date').value = donation.preferred_date;
            document.getElementById('edit_preferred_time').value = donation.preferred_time;
            document.getElementById('edit_notes').value = donation.notes;
            document.getElementById('edit_expiry_date').value = donation.expiry_date;
            document.getElementById('edit_status').value = donation.status;
            document.getElementById('edit_modal').showModal();
        }

        function closeEditModal() {
            document.getElementById('edit_modal').close();
        }

        function openDeleteModal(donationId) {
            document.getElementById('delete_donation_id').value = donationId;
            document.getElementById('delete_modal').showModal();
        }

        function closeDeleteModal() {
            document.getElementById('delete_modal').close();
        }
    </script>
</body>
</html>