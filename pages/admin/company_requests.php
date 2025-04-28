<?php
require_once '../../config/db_connect.php';
require_once '../../config/app_config.php';
require_once '../../config/auth_middleware.php';
require_once '../../includes/mail/EmailService.php';

use App\Mail\EmailService;

// Make sure user is admin - FIX: Pass an array instead of a string
checkAuth(['admin']);

// Handle approval/rejection
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $action = $_POST['action'];
    $requestId = (int)$_POST['request_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    $pdo = getPDO();  // FIXED: Use getPDO() instead of connectDB()
    
    if ($action === 'approve') {
        // Update status
        $stmt = $pdo->prepare("UPDATE company_requests SET status = 'approved', updated_at = NOW(), admin_notes = ? WHERE id = ?");
        $stmt->execute([$notes, $requestId]);
        
        // Get company data
        $stmt = $pdo->prepare("SELECT * FROM company_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $companyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send approval email
        $emailService = new EmailService();
        $emailData = [
            'name' => $companyData['contact_person'],
            'company_name' => $companyData['company_name'],
            'email' => $companyData['email'],
            'token' => $companyData['token'],
            'registration_url' => BASE_URL . '/auth/register_company.php?token=' . $companyData['token'] . '&email=' . $companyData['email']
        ];
        
        // The email should contain a special link with the token that will be used to auto-approve the branch
        if (!$emailService->sendCompanyApprovalEmail($emailData)) {
            $_SESSION['error'] = "Approval was successful but email could not be sent.";
        } else {
            $_SESSION['success'] = "Company request approved and email sent successfully.";
        }
    } else if ($action === 'reject') {
        // Update status
        $stmt = $pdo->prepare("UPDATE company_requests SET status = 'rejected', updated_at = NOW(), admin_notes = ? WHERE id = ?");
        $stmt->execute([$notes, $requestId]);
        
        $_SESSION['success'] = "Company request rejected successfully.";
    }
    
    // Redirect to refresh the page
    header("Location: company_requests.php");
    exit();
}

// Get all company requests
$pdo = getPDO();  // FIXED: Use getPDO() instead of connectDB()
$stmt = $pdo->prepare("
    SELECT * FROM company_requests 
    ORDER BY 
        CASE 
            WHEN status = 'pending' THEN 1
            WHEN status = 'approved' THEN 2
            WHEN status = 'rejected' THEN 3
        END, 
        created_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Requests - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/LGU.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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
<body class="flex h-screen">
<?php include '../layout/nav.php'?>

    <div class="flex-1 p-6 overflow-auto space-y-6">
        <!-- Page Header -->
        <div class="bg-sec p-4 rounded-lg shadow">
            <h1 class="text-3xl font-bold text-primarycol">Food Company Applications</h1>
            <p class="text-gray-600">Manage company requests to join WasteWise</p>
        </div>
        
        <!-- Status Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($_SESSION['success']) ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($_SESSION['error']) ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Requests Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requests) === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">No company requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                                <tr class="hover">
                                    <td class="font-medium"><?= htmlspecialchars($request['company_name']) ?></td>
                                    <td><?= htmlspecialchars($request['contact_person']) ?></td>
                                    <td><?= htmlspecialchars($request['email']) ?></td>
                                    <td><?= htmlspecialchars($request['phone']) ?></td>
                                    <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php elseif ($request['status'] === 'approved'): ?>
                                            <span class="badge badge-success">Approved</span>
                                        <?php elseif ($request['status'] === 'registered'): ?>
                                            <span class="badge badge-info">Registered</span>
                                        <?php else: ?>
                                            <span class="badge badge-error">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button 
                                                onclick="viewDetails(<?= $request['id'] ?>, '<?= htmlspecialchars($request['company_name']) ?>', '<?= htmlspecialchars($request['contact_person']) ?>', '<?= htmlspecialchars($request['email']) ?>', '<?= htmlspecialchars($request['phone']) ?>', '<?= htmlspecialchars($request['description']) ?>', '<?= htmlspecialchars($request['status']) ?>', '<?= htmlspecialchars($request['admin_notes'] ?? '') ?>')"
                                                class="btn btn-xs btn-ghost">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <button 
                                                    onclick="approveRequest(<?= $request['id'] ?>, '<?= htmlspecialchars($request['company_name']) ?>')"
                                                    class="btn btn-xs btn-success text-white">
                                                    Approve
                                                </button>
                                                <button 
                                                    onclick="rejectRequest(<?= $request['id'] ?>, '<?= htmlspecialchars($request['company_name']) ?>')"
                                                    class="btn btn-xs btn-error text-white">
                                                    Reject
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <dialog id="detailsModal" class="modal">
        <div class="modal-box max-w-2xl">
            <h3 class="font-bold text-lg" id="modalTitle">Company Request Details</h3>
            <div class="py-4 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Company</p>
                        <p class="font-medium" id="modalCompany"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Contact Person</p>
                        <p id="modalContact"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Email</p>
                        <p id="modalEmail"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Phone</p>
                        <p id="modalPhone"></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm font-semibold text-gray-500">Description</p>
                        <p id="modalDescription" class="whitespace-pre-line"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Status</p>
                        <p id="modalStatus"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Admin Notes</p>
                        <p id="modalNotes" class="whitespace-pre-line"></p>
                    </div>
                </div>
            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Close</button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- Approve Modal -->
    <dialog id="approveModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Approve Company Request</h3>
            <form method="POST" class="py-4">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" id="approveRequestId">
                
                <p class="mb-4">Are you sure you want to approve <span id="approveCompanyName" class="font-bold"></span>?</p>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Admin Notes (Optional)</span>
                    </label>
                    <textarea name="notes" class="textarea textarea-bordered h-24" placeholder="Add any notes or special instructions"></textarea>
                </div>
                
                <div class="modal-action">
                    <button type="button" class="btn" onclick="document.getElementById('approveModal').close()">Cancel</button>
                    <button type="submit" class="btn btn-success text-white">Approve</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Reject Modal -->
    <dialog id="rejectModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Reject Company Request</h3>
            <form method="POST" class="py-4">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="rejectRequestId">
                
                <p class="mb-4">Are you sure you want to reject <span id="rejectCompanyName" class="font-bold"></span>?</p>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Reason for Rejection (Optional)</span>
                    </label>
                    <textarea name="notes" class="textarea textarea-bordered h-24" placeholder="Provide reason for rejection"></textarea>
                </div>
                
                <div class="modal-action">
                    <button type="button" class="btn" onclick="document.getElementById('rejectModal').close()">Cancel</button>
                    <button type="submit" class="btn btn-error text-white">Reject</button>
                </div>
            </form>
        </div>
    </dialog>
    
    <script>
        function viewDetails(id, company, contact, email, phone, description, status, notes) {
            document.getElementById('modalCompany').textContent = company;
            document.getElementById('modalContact').textContent = contact;
            document.getElementById('modalEmail').textContent = email;
            document.getElementById('modalPhone').textContent = phone;
            document.getElementById('modalDescription').textContent = description || 'No description provided';
            
            // Format status with badge
            let statusHtml = '';
            if (status === 'pending') {
                statusHtml = '<span class="badge badge-warning">Pending</span>';
            } else if (status === 'approved') {
                statusHtml = '<span class="badge badge-success">Approved</span>';
            } else if (status === 'registered') {
                statusHtml = '<span class="badge badge-info">Registered</span>';
            } else {
                statusHtml = '<span class="badge badge-error">Rejected</span>';
            }
            document.getElementById('modalStatus').innerHTML = statusHtml;
            
            document.getElementById('modalNotes').textContent = notes || 'No notes added';
            
            document.getElementById('detailsModal').showModal();
        }
        
        function approveRequest(id, company) {
            document.getElementById('approveRequestId').value = id;
            document.getElementById('approveCompanyName').textContent = company;
            document.getElementById('approveModal').showModal();
        }
        
        function rejectRequest(id, company) {
            document.getElementById('rejectRequestId').value = id;
            document.getElementById('rejectCompanyName').textContent = company;
            document.getElementById('rejectModal').showModal();
        }
    </script>
</body>
</html>