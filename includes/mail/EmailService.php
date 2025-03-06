<?php
namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use Dotenv\Dotenv;

require_once __DIR__ . '/../../vendor/autoload.php';

class EmailService {
    private $mailer;
    
    public function __construct() {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        
        $this->initializeMailer();
    }

    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        // Disable debugging in production
        $this->mailer->SMTPDebug = 0;
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $_ENV['MAIL_PORT'];
        
        // Set default sender
        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    }

    public function sendNGOApprovalEmail($ngoData) {
        try {
            $this->mailer->clearAddresses(); // Clear any previous addresses
            $this->mailer->addAddress($ngoData['email'], $ngoData['name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'NGO Partnership Account Approved';
            
            $this->mailer->Body = $this->getNGOApprovalTemplate($ngoData);
            
            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send NGO approval email: " . $e->getMessage());
            throw new Exception("Failed to send approval email: " . $e->getMessage());
        }
    }

    public function sendNGORejectionEmail($ngoData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($ngoData['email'], $ngoData['name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'NGO Partnership Application Status';
            
            $this->mailer->Body = $this->getNGORejectionTemplate($ngoData);
            
            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send NGO rejection email: " . $e->getMessage());
            throw new Exception("Failed to send rejection email: " . $e->getMessage());
        }
    }

    public function sendPasswordResetEmail($userData, $resetLink) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userData['email'], $userData['fname'] . ' ' . $userData['lname']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Reset Request';
            
            $this->mailer->Body = $this->getPasswordResetTemplate($userData, $resetLink);
            
            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            throw new Exception("Failed to send password reset email: " . $e->getMessage());
        }
    }

    public function sendDonationReceiptEmail($ngoDonationData) {
        // Make sure branch_address is included in template variables
        if (!isset($ngoDonationData['branch_address']) && isset($ngoDonationData['branch_name'])) {
            // Fallback to just showing branch name if address is missing
            $ngoDonationData['branch_address'] = $ngoDonationData['branch_name'];
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($ngoDonationData['ngo_email'], $ngoDonationData['ngo_name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Donation Receipt Confirmation #' . $ngoDonationData['id'];
            
            $this->mailer->Body = $this->getDonationReceiptTemplate($ngoDonationData);
            
            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send donation receipt email: " . $e->getMessage());
            throw new Exception("Failed to send receipt email: " . $e->getMessage());
        }
    }

    private function getNGOApprovalTemplate($data) {
        return "
            <h2>Welcome to Bea Bakes Waste Management!</h2>
            <p>Dear {$data['name']},</p>
            <p>We are pleased to inform you that your NGO partnership account for {$data['organization_name']} has been approved.</p>
            <p>You can now log in to your account using your registered email and password.</p>
            <p>Visit our login page: <a href='http://localhost/capstone/WASTE-WISE-CAPSTONE/auth/login.php'>Login Here</a></p>
            <p>Best regards,<br>Bea Bakes Team</p>
        ";
    }

    private function getNGORejectionTemplate($data) {
        return "
            <h2>NGO Partnership Application Status</h2>
            <p>Dear {$data['name']},</p>
            <p>We regret to inform you that your NGO partnership application for {$data['organization_name']} has not been approved at this time.</p>
            <p>If you have any questions or would like to submit a new application in the future, please feel free to contact us.</p>
            <p>Best regards,<br>Bea Bakes Team</p>
        ";
    }

    private function getPasswordResetTemplate($userData, $resetLink) {
        return "
        <h2>Password Reset Request</h2>
        <p>Dear {$userData['fname']},</p>
        <p>You recently requested to reset your password for your Bea Bakes account.</p>
        <p>Click the button below to reset your password:</p>
        <p style='margin: 20px 0;'>
            <a href='{$resetLink}' 
               style='background-color: #47663B; 
                      color: white; 
                      padding: 10px 20px; 
                      text-decoration: none; 
                      border-radius: 5px;
                      display: inline-block;'>
                Reset Password
            </a>
        </p>
        <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
        <p>This password reset link is only valid for the next hour.</p>
        <p>Best regards,<br>Bea Bakes Team</p>
    ";
    }

    private function getDonationReceiptTemplate($data) {
        $notes = isset($data['notes']) ? $data['notes'] : '';
        $requestId = $data['id'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .receipt { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
                .header { text-align: center; padding-bottom: 10px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .details { margin-top: 20px; }
                .row { display: flex; justify-content: space-between; margin-bottom: 10px; }
                .label { font-weight: bold; }
                .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='receipt'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Donation Receipt Confirmation</p>
                </div>
                <div class='details'>
                    <div class='row'>
                        <span class='label'>Receipt Number:</span>
                        <span>REC-" . date('Ymd') . "-" . $requestId . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Date:</span>
                        <span>" . date('F j, Y') . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Recipient:</span>
                        <span>" . htmlspecialchars($data['ngo_name']) . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Branch:</span>
                        <span>" . htmlspecialchars($data['branch_name']) . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Product:</span>
                        <span>" . htmlspecialchars($data['product_name']) . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Quantity:</span>
                        <span>" . htmlspecialchars($data['quantity_requested']) . "</span>
                    </div>
                    
                    " . (!empty($notes) ? "
                    <div class='row'>
                        <span class='label'>Notes:</span>
                        <span>" . htmlspecialchars($notes) . "</span>
                    </div>" : "") . "
                    
                    <div class='row' style='margin-top: 40px; border-top: 1px solid #ddd; padding-top: 10px;'>
                        <span class='label'>Status:</span>
                        <span style='color: green; font-weight: bold;'>RECEIVED</span>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Thank you for participating in our food redistribution program.</p>
                    <p>WasteWise &copy; " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}