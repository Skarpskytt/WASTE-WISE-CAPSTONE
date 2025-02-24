<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';
require_once '../../vendor/autoload.php';

use App\Mail\EmailService;

// Check for admin access
checkAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['user_id'];
        
        // First verify the user exists and is an NGO
        $checkStmt = $pdo->prepare("
            SELECT u.id, u.fname, u.lname, u.email, np.organization_name 
            FROM users u 
            JOIN ngo_profiles np ON u.id = np.user_id 
            WHERE u.id = ? AND u.role = 'ngo'
        ");
        $checkStmt->execute([$userId]);
        $ngoData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ngoData) {
            throw new Exception("Invalid user or not an NGO");
        }
        
        // Update user active status
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Update NGO profile status
        $stmt = $pdo->prepare("UPDATE ngo_profiles SET status = 'approved' WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Get admin user ID
        $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminStmt->execute();
        $adminId = $adminStmt->fetchColumn();
        
        if ($adminId) {
            // Create notification for admin
            $adminNotification = "NGO {$ngoData['organization_name']} has been approved.";
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, type, is_read, created_at) 
                VALUES (?, ?, 'ngo_approval', 0, NOW())
            ");
            $notifStmt->execute([$adminId, $adminNotification]);
        }
        
        // Create notification for NGO user
        $ngoNotification = "Your NGO account has been approved. You can now log in.";
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, type, is_read, created_at) 
            VALUES (?, ?, 'ngo_approval', 0, NOW())
        ");
        $notifStmt->execute([$userId, $ngoNotification]);
        
        // Prepare data for email
        $emailData = [
            'name' => $ngoData['fname'] . ' ' . $ngoData['lname'],
            'email' => $ngoData['email'],
            'organization_name' => $ngoData['organization_name']
        ];
        
        // Send approval email
        $emailService = new EmailService();
        if (!$emailService->sendNGOApprovalEmail($emailData)) {
            throw new Exception("Failed to send approval email");
        }
        
        $pdo->commit();
        $_SESSION['success'] = "NGO account approved and notification email sent successfully.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error approving NGO account: " . $e->getMessage();
    }
    
    header("Location: user.php");
    exit();
}