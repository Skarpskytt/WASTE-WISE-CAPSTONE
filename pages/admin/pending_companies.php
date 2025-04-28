<?php
require_once '../../config/db_connect.php';
require_once '../../config/auth_middleware.php';
require_once '../../includes/mail/EmailService.php';

use App\Mail\EmailService;

// Make sure user is admin - FIX: Use checkAuth with an array instead of checkUserRole
checkAuth(['admin']);

// Handle approval/rejection
if (isset($_POST['action']) && isset($_POST['branch_id'])) {
    $action = $_POST['action'];
    $branchId = (int)$_POST['branch_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    $pdo = getPDO();  // FIXED: Use getPDO() instead of connectDB()
    
    if ($action === 'approve') {
        // Update status
        $stmt = $pdo->prepare("UPDATE branches SET approval_status = 'approved', admin_notes = ? WHERE id = ?");
        $stmt->execute([$notes, $branchId]);
        
        // Get company data
        $stmt = $pdo->prepare("
            SELECT b.*, u.email FROM branches b 
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$branchId]);
        $companyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send approval email
        $emailService = new EmailService();
        $emailData = [
            'name' => $companyData['contact_person'],
            'company_name' => $companyData['name'],
            'email' => $companyData['email']
        ];
        
        if (!$emailService->sendCompanyFinalApprovalEmail($emailData)) {
            $_SESSION['error'] = "Approval was successful but email could not be sent.";
        } else {
            $_SESSION['success'] = "Company approved and email sent successfully.";
        }
    } else if ($action === 'reject') {
        // Update status
        $stmt = $pdo->prepare("UPDATE branches SET approval_status = 'rejected', admin_notes = ? WHERE id = ?");
        $stmt->execute([$notes, $branchId]);
        
        // Get company data
        $stmt = $pdo->prepare("
            SELECT b.*, u.email FROM branches b 
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$branchId]);
        $companyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send rejection email
        $emailService = new EmailService();
        $emailData = [
            'name' => $companyData['contact_person'],
            'company_name' => $companyData['name'],
            'email' => $companyData['email'],
            'notes' => $notes
        ];
        
        $emailService->sendCompanyRejectionEmail($emailData);
        $_SESSION['success'] = "Company rejected and notification sent.";
    }
    
    // Redirect to refresh the page
    header("Location: pending_companies.php");
    exit();
}

// Get pending companies
$pdo = getPDO();  // FIXED: Use getPDO() instead of connectDB()
$stmt = $pdo->prepare("
    SELECT b.*, u.email 
    FROM branches b
    JOIN users u ON b.user_id = u.id
    WHERE b.branch_type = 'company_main'
    ORDER BY 
        CASE 
            WHEN b.approval_status = 'pending' THEN 1
            WHEN b.approval_status = 'approved' THEN 2
            WHEN b.approval_status = 'rejected' THEN 3
        END, 
        b.created_at DESC
");
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Companies - WasteWise</title>
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
            <h1 class="text-3xl font-bold text-primarycol">Pending Company Registrations</h1>
            <p class="text-gray-600">Review and approve company registrations</p>
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
        
        <!-- Companies Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact Person</th>
                            <th>Address</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($companies) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No company registrations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies as $company): ?>
                                <tr class="hover">
                                    <td class="font-medium"><?= htmlspecialchars($company['name']) ?></td>
                                    <td><?= htmlspecialchars($company['contact_person']) ?></td>
                                    <td><?= htmlspecialchars($company['address']) ?></td>
                                    <td><?= date('M j, Y', strtotime($company['created_at'])) ?></td>
                                    <td>
                                        <?php if ($company['approval_status'] === 'pending'): ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php elseif ($company['approval_status'] === 'approved'): ?>
                                            <span class="badge badge-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge badge-error">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <button 
                                                onclick="viewDetails(<?= $company['id'] ?>, '<?= htmlspecialchars($company['name']) ?>', '<?= htmlspecialchars($company['contact_person']) ?>', '<?= htmlspecialchars($company['phone']) ?>', '<?= htmlspecialchars($company['address']) ?>', '<?= htmlspecialchars($company['business_permit_number']) ?>', '<?= htmlspecialchars($company['business_permit_file']) ?>', '<?= htmlspecialchars($company['approval_status']) ?>', '<?= htmlspecialchars($company['admin_notes'] ?? '') ?>')"
                                                class="btn btn-xs btn-ghost">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            
                                            <?php if ($company['approval_status'] === 'pending'): ?>
                                                <button 
                                                    onclick="approveCompany(<?= $company['id'] ?>, '<?= htmlspecialchars($company['name']) ?>')"
                                                    class="btn btn-xs btn-success text-white">
                                                    Approve
                                                </button>
                                                <button 
                                                    onclick="rejectCompany(<?= $company['id'] ?>, '<?= htmlspecialchars($company['name']) ?>')"
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
            <h3 class="font-bold text-lg" id="modalTitle">Company Details</h3>
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
                        <p class="text-sm font-semibold text-gray-500">Phone</p>
                        <p id="modalPhone"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Address</p>
                        <p id="modalAddress"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Business Permit #</p>
                        <p id="modalPermitNumber"></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500">Business Permit</p>
                        <a id="modalPermitLink" href="#" target="_blank" class="text-blue-500 hover:underline">View Document</a>
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
            <h3 class="font-bold text-lg">Approve Company</h3>
            <form method="POST" class="py-4">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="branch_id" id="approveBranchId">
                
                <p class="mb-4">Are you sure you want to approve <span id="approveCompanyName" class="font-bold"></span>?</p>
                <p class="mb-4">This will activate their account and allow them to use the system.</p>
                
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
            <h3 class="font-bold text-lg">Reject Company</h3>
            <form method="POST" class="py-4">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="branch_id" id="rejectBranchId">
                
                <p class="mb-4">Are you sure you want to reject <span id="rejectCompanyName" class="font-bold"></span>?</p>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Reason for Rejection (Required)</span>
                    </label>
                    <textarea name="notes" class="textarea textarea-bordered h-24" placeholder="Provide reason for rejection" required></textarea>
                </div>
                
                <div class="modal-action">
                    <button type="button" class="btn" onclick="document.getElementById('rejectModal').close()">Cancel</button>
                    <button type="submit" class="btn btn-error text-white">Reject</button>
                </div>
            </form>
        </div>
    </dialog>
    
    <script>
        function viewDetails(id, company, contact, phone, address, permitNumber, permitFile, status, notes) {
            document.getElementById('modalCompany').textContent = company;
            document.getElementById('modalContact').textContent = contact;
            document.getElementById('modalPhone').textContent = phone;
            document.getElementById('modalAddress').textContent = address;
            document.getElementById('modalPermitNumber').textContent = permitNumber;
            
            // Setup permit link
            const permitLink = document.getElementById('modalPermitLink');
            if (permitFile) {
                permitLink.href = "../../uploads/permits/" + permitFile;
                permitLink.style.display = "inline";
            } else {
                permitLink.style.display = "none";
            }
            
            // Format status with badge
            let statusHtml = '';
            if (status === 'pending') {
                statusHtml = '<span class="badge badge-warning">Pending</span>';
            } else if (status === 'approved') {
                statusHtml = '<span class="badge badge-success">Approved</span>';
            } else {
                statusHtml = '<span class="badge badge-error">Rejected</span>';
            }
            document.getElementById('modalStatus').innerHTML = statusHtml;
            document.getElementById('modalNotes').textContent = notes || 'No notes added';
            
            document.getElementById('detailsModal').showModal();
        }
        
        function approveCompany(id, company) {
            document.getElementById('approveBranchId').value = id;
            document.getElementById('approveCompanyName').textContent = company;
            document.getElementById('approveModal').showModal();
        }
        
        function rejectCompany(id, company) {
            document.getElementById('rejectBranchId').value = id;
            document.getElementById('rejectCompanyName').textContent = company;
            document.getElementById('rejectModal').showModal();
        }
    </script>
</body>
</html>