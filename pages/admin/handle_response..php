<?php
// handle_response.php

include('../../config/db_connect.php');
require '../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';
$donationId = $_GET['id'] ?? '';

function sendStatusEmail($donation, $ngo, $status) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'joshuabchua11@gmail.com'; // Replace with your actual email
        $mail->Password = 'knur xepr tbsl bvzq'; // Replace with your actual app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 2; // Enable debug output

        // Recipients
        $mail->setFrom('joshuabchua11@gmail.com', 'WasteWise Admin');
        $mail->addAddress($ngo['contact_email']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Donation Status Update - {$status}";
        
        $statusColor = $status === 'Accepted' ? 'green' : 'red';
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif;'>
                <h2>Donation Status Update</h2>
                <p>Your response has been recorded for donation #{$donation['id']}</p>
                
                <div style='background-color: #f5f5f5; padding: 15px; margin: 10px 0;'>
                    <p><strong>Status:</strong> 
                        <span style='color: {$statusColor};'>{$status}</span>
                    </p>
                    <p><strong>Food Type:</strong> {$donation['food_type']}</p>
                    <p><strong>Quantity:</strong> {$donation['quantity']}</p>
                    <p><strong>Preferred Date:</strong> {$donation['preferred_date']}</p>
                </div>
            </div>
        ";

        $mail->send();
        error_log("Email sent successfully to {$ngo['contact_email']}");
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

try {
    // Verify token and get donation details
    $stmt = $pdo->prepare("
        SELECT d.*, n.name as ngo_name, n.contact_email 
        FROM donations d
        JOIN ngos n ON d.ngo_id = n.id
        WHERE d.id = ?
    ");
    $stmt->execute([$donationId]);
    $donationData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($donationData) {
        // Update donation status
        $status = ($action === 'accept') ? 'Accepted' : 'Declined';
        $stmt = $pdo->prepare("UPDATE donations SET status = ? WHERE id = ?");
        $stmt->execute([$status, $donationId]);

        // Send confirmation email
        $emailSent = sendStatusEmail($donationData, [
            'contact_email' => $donationData['contact_email']
        ], $status);

        echo "
        <html>
        <head>
            <style>
                body { font-family: Arial; text-align: center; margin-top: 50px; }
                .message { padding: 20px; background-color: #f0f0f0; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='message'>
                <h2>Thank you for your response</h2>
                <p>The donation has been {$status}.</p>
                " . ($emailSent ? "<p>A confirmation email has been sent.</p>" : "") . "
            </div>
        </body>
        </html>";
    } else {
        throw new Exception("Invalid donation ID");
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<h2>Error processing your response</h2>";
}