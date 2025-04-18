<?php
require_once '../config/db_connect.php';
require_once '../includes/mail/EmailService.php';

use App\Mail\EmailService;

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $companyName = trim($_POST['company_name']);
    $contactPerson = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $description = trim($_POST['description'] ?? '');
    
    try {
        // Connect to database
        $pdo = getPDO();
        
        // Check if email already exists in company_requests table
        $stmt = $pdo->prepare("SELECT id FROM company_requests WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            // Email already exists
            header("Location: ../pages/homepage.php?error=email_exists");
            exit();
        }
        
        // Check if email already exists in users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            // Email already exists as a user
            header("Location: ../pages/homepage.php?error=email_exists");
            exit();
        }
        
        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        
        // Insert request into database
        $stmt = $pdo->prepare("
            INSERT INTO company_requests 
            (company_name, contact_person, email, phone, description, status, token, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        
        $stmt->execute([
            $companyName, 
            $contactPerson, 
            $email, 
            $phone, 
            $description, 
            $token
        ]);
        
        // Send notification to admin
        $stmt = $pdo->prepare("
            SELECT id FROM users WHERE role = 'admin' LIMIT 1
        ");
        $stmt->execute();
        if ($adminUser = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, message, link, is_read, created_at) 
                VALUES (?, ?, ?, 0, NOW())
            ");
            
            $notificationMessage = "New company request: $companyName";
            $notificationLink = "/pages/admin/company_requests.php";
            $stmt->execute([$adminUser['id'], $notificationMessage, $notificationLink]);
        }
        
        // Send confirmation email to company
        $emailService = new EmailService();
        $emailData = [
            'name' => $contactPerson,
            'company_name' => $companyName,
            'email' => $email
        ];
        
        $emailService->sendCompanyRequestConfirmation($emailData);
        
        // Redirect with success message
        header("Location: ../pages/homepage.php?success=request_submitted");
        exit();
        
    } catch (PDOException $e) {
        // Log error and redirect with error message
        error_log("Database error: " . $e->getMessage());
        header("Location: ../pages/homepage.php?error=system_error");
        exit();
    }
} else {
    // If not POST request, redirect back to homepage
    header("Location: ../pages/homepage.php");
    exit();
}