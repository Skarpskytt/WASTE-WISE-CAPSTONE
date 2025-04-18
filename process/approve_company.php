<?php
// Inside approval process
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
        'token' => $companyData['token']
    ];
    
    if (!$emailService->sendCompanyApprovalEmail($emailData)) {
        $_SESSION['error'] = "Approval was successful but email could not be sent.";
    } else {
        $_SESSION['success'] = "Company request approved and email sent successfully.";
    }
}