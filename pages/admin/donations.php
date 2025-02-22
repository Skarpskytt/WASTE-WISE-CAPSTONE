<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);
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

// Add these near the top of the file, after your initial includes
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Excel/CSV Export Function
if (isset($_POST['export_excel'])) {
    try {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="donations_' . date('Y-m-d') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, [
            'ID', 
            'NGO', 
            'Food Type', 
            'Quantity', 
            'Preferred Date',
            'Preferred Time', 
            'Status', 
            'Expiry Date',
            'Created At'
        ]);
        
        // Fetch donations with NGO details
        $stmt = $pdo->query("
            SELECT 
                d.id,
                n.name as ngo_name,
                d.food_type,
                d.quantity,
                d.preferred_date,
                d.preferred_time,
                d.status,
                d.expiry_date,
                d.created_at
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
                $row['preferred_time'],
                $row['status'],
                $row['expiry_date'],
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
        // Extend TCPDF with custom header
        class MYPDF extends TCPDF {
            public function Header() {
                // Logo
                $image_file = '../../assets/images/Logo.png'; // Adjust path to your logo
                $this->Image($image_file, 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                
                // Set font
                $this->SetFont('helvetica', 'B', 20);
                
                // Title
                $this->Cell(0, 30, 'WasteWise Management System', 0, false, 'C', 0, '', 0, false, 'M', 'M');
                
                // Line break
                $this->Ln(20);
                
                // Subtitle
                $this->SetFont('helvetica', 'B', 15);
                $this->Cell(0, 10, 'Donations Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
                
                // Line break
                $this->Ln(15);
            }
        }

        // Create new PDF document
        $pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('WasteWise');
        $pdf->SetAuthor('WasteWise Admin');
        $pdf->SetTitle('Donations Report');
        
        // Set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        
        // Set margins
        $pdf->SetMargins(15, 50, 15);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font for the content
        $pdf->SetFont('helvetica', '', 10);
        
        // Add date of report
        $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y, g:i a'), 0, 1, 'R');
        $pdf->Ln(5);
        
        // Create the table
        $html = '<table border="1" cellpadding="4">
            <tr style="background-color: #47663B; color: white;">
                <th>ID</th>
                <th>NGO</th>
                <th>Food Type</th>
                <th>Quantity</th>
                <th>Preferred Date</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>';
        
        // Fetch donations
        $stmt = $pdo->query("
            SELECT 
                d.id,
                n.name as ngo_name,
                d.food_type,
                d.quantity,
                d.preferred_date,
                d.status,
                d.created_at
            FROM donations d
            JOIN ngos n ON d.ngo_id = n.id
            ORDER BY d.created_at DESC
        ");
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['id']) . '</td>
                <td>' . htmlspecialchars($row['ngo_name']) . '</td>
                <td>' . htmlspecialchars($row['food_type']) . '</td>
                <td>' . htmlspecialchars($row['quantity']) . '</td>
                <td>' . htmlspecialchars($row['preferred_date']) . '</td>
                <td>' . htmlspecialchars($row['status']) . '</td>
                <td>' . htmlspecialchars($row['created_at']) . '</td>
            </tr>';
        }
        
        $html .= '</table>';
        
        // Add table to PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Add footer text
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'WasteWise Management System - Page '.$pdf->getAliasNumPage().'/'.$pdf->getAliasNbPages(), 0, false, 'C');
        
        // Output PDF
        $pdf->Output('donations_report_' . date('Y-m-d') . '.pdf', 'D');
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

// Modify your donation query to include filters
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
    WHERE 1=1
";

$params = [];

if ($startDate && $endDate) {
    $donationQuery .= " AND donations.preferred_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
}

if (!empty($search)) {
    $donationQuery .= " AND (ngos.name LIKE :search OR donations.food_type LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$donationQuery .= " ORDER BY donations.created_at DESC";

$donationStmt = $pdo->prepare($donationQuery);
$donationStmt->execute($params);
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

        <!-- Add this after your <h1> tag -->
        <div class="mb-6">
            <!-- Search & Date Filter Form -->
            <form method="GET" class="flex flex-wrap gap-4 mb-6 items-end bg-white p-4 rounded-lg shadow">
                <!-- Search Field -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" 
                        name="search" 
                        id="search" 
                        value="<?= htmlspecialchars($search ?? '') ?>"
                        class="input input-bordered w-64"
                        placeholder="Search NGO or food type..."/>
                </div>

                <!-- Date Range Fields -->
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                    <input type="date" 
                        name="start_date" 
                        id="start_date" 
                        value="<?= htmlspecialchars($startDate ?? '') ?>"
                        class="input input-bordered"/>
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="date" 
                        name="end_date" 
                        id="end_date"
                        value="<?= htmlspecialchars($endDate ?? '') ?>"
                        class="input input-bordered"/>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                            class="btn bg-primarycol text-white hover:bg-fourth">
                        Search
                    </button>
                    <a href="donations.php" 
                    class="btn btn-ghost">
                    Reset
                    </a>
                </div>
            </form>
        </div>

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
                                        <div class='flex justify-center space-x-2'>
                                            <a href="#" 
                                               onclick="openEditModal(<?= htmlspecialchars(json_encode($donation)) ?>)" 
                                               class='rounded-md hover:bg-green-100 text-green-600 p-2 flex items-center'>
                                                <!-- Edit Icon -->
                                                <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                                                          d='M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-5-5l5 5m0 0l-5 5m5-5H13' />
                                                </svg>
                                                Edit
                                            </a>
                                            <a href="#" 
                                               onclick="openDeleteModal(<?= htmlspecialchars($donation['id']) ?>)" 
                                               class='rounded-md hover:bg-red-100 text-red-600 p-2 flex items-center'>
                                                <!-- Delete Icon -->
                                                <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' 
                                                          d='M6 18L18 6M6 6l12 12' />
                                                </svg>
                                                Delete
                                            </a>
                                        </div>
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