<?php
// notification_handler.php

require '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
try {
    $dotenv->load();
} catch (Exception $e) {
    error_log("Error loading .env file: " . $e->getMessage());
    // Fallback SMTP settings if .env fails
    $_ENV['SMTP_HOST'] = 'smtp.gmail.com';
    $_ENV['SMTP_USER'] = 'joshuabchua11@gmail.com';
    $_ENV['SMTP_PASS'] = 'knur xepr tbsl bvzq';
    $_ENV['SMTP_PORT'] = '587';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendDonationNotification($pdo, $donationId) {
    // Verify donation exists first
    $checkStmt = $pdo->prepare("SELECT id FROM donations WHERE id = ?");
    $checkStmt->execute([$donationId]);
    if (!$checkStmt->fetch()) {
        error_log("Donation ID {$donationId} not found");
        return false;
    }

    // Get donation details
    $stmt = $pdo->prepare("
        SELECT d.*, n.name as ngo_name, n.contact_email, i.name as food_name
        FROM donations d
        JOIN ngos n ON d.ngo_id = n.id
        JOIN waste w ON d.waste_id = w.id
        JOIN inventory i ON w.inventory_id = i.id
        WHERE d.id = ?
    ");
    $stmt->execute([$donationId]);
    $donation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donation) {
        error_log("Could not fetch donation details for ID {$donationId}");
        return false;
    }

    // Generate and store token
    try {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("
            INSERT INTO donation_tokens (donation_id, token, expires_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        $stmt->execute([$donationId, $token]);

        // Send email
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom('your-email@gmail.com', 'WasteWise Admin');
        $mail->addAddress($donation['contact_email'], $donation['ngo_name']);

        $baseUrl = "http://localhost/WASTE-WISE-CAPSTONE/pages/admin";
        $acceptLink = "{$baseUrl}/handle_response.php?token={$token}&action=accept&id={$donationId}";
        $declineLink = "{$baseUrl}/handle_response.php?token={$token}&action=decline&id={$donationId}";

        $mail->isHTML(true);
        $mail->Subject = 'New Food Donation Available';
        $mail->Body = "
            <h2>New Food Donation Available</h2>
            <p>Dear {$donation['ngo_name']},</p>
            <p>A new food donation is available:</p>
            
            <div style='background-color: #f5f5f5; padding: 15px; margin: 10px 0;'>
                <p><strong>Food Type:</strong> {$donation['food_name']}</p>
                <p><strong>Quantity:</strong> {$donation['quantity']}</p>
                <p><strong>Expiry Date:</strong> {$donation['expiry_date']}</p>
                <p><strong>Preferred Date:</strong> {$donation['preferred_date']}</p>
                <p><strong>Notes:</strong> {$donation['notes']}</p>
            </div>

            <div style='margin: 20px 0;'>
                <a href='{$acceptLink}' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; margin-right: 10px;'>Accept Donation</a>
                <a href='{$declineLink}' style='background-color: #f44336; color: white; padding: 10px 20px; text-decoration: none;'>Decline Donation</a>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return false;
    }
}

function storeDonationToken($donationId, $token) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO donation_tokens (donation_id, token, expires_at) 
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ");
    $stmt->execute([$donationId, $token]);
}