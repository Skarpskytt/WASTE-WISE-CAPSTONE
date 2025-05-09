<?php
namespace App\Mail;

// Add this constant definition at the top of the file, just after the namespace declaration
const SITE_URL = 'http://localhost/capstone/WASTE-WISE-CAPSTONE';

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Define path to config using __DIR__ for portability across environments
$configPath = dirname(dirname(__DIR__)) . '/config/app_config.php';

// Check if config exists
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // Log error but don't crash
    error_log("Warning: app_config.php not found at {$configPath}");
    // Set minimal defaults to prevent errors
    if (!defined('BASE_URL')) define('BASE_URL', '');
    date_default_timezone_set('Asia/Manila');
}

// Similarly for PHPMailer includes
$vendorPath = dirname(dirname(__DIR__)) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
} else {
    // Load individual files instead
    $phpmailerPath = dirname(dirname(__DIR__)) . '/includes/phpmailer/src/';
    require_once $phpmailerPath . 'Exception.php';
    require_once $phpmailerPath . 'PHPMailer.php';
    require_once $phpmailerPath . 'SMTP.php';
}

class EmailService {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Enable debug output
        $this->mailer->SMTPDebug = 2;
        $this->mailer->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };

        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'bakesbea226@gmail.com'; // Your Gmail address
        $this->mailer->Password = 'ayqtxmeivyellcai'; // Your App Password
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        
        // Set default sender
        $this->mailer->setFrom('bakesbea226@gmail.com', 'Waste Wise System');
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

    public function sendPasswordResetEmail($userData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userData['email']); 
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Reset Request';
            
            $this->mailer->Body = $this->getPasswordResetTemplate($userData, $userData['resetLink']);

            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            throw new \Exception('Failed to send password reset email: ' . $e->getMessage());
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

    public function sendOTPEmail($userData) {
        try {
            // Add debug logging
            error_log("Sending OTP email with data: " . print_r($userData, true));
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userData['email']);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your OTP Verification Code';
            
            // Use the proper template method
            $this->mailer->Body = $this->getOTPTemplate($userData, $userData['otp']);

            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to send OTP email: " . $e->getMessage());
            throw new Exception("Failed to send OTP email: " . $e->getMessage());
        }
    }

    public function sendDonationRequestStatusEmail($data) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($data['email'], $data['name']);
            
            if ($data['status'] === 'approved') {
                $this->mailer->Subject = 'Your Donation Request Has Been Approved';
                $this->mailer->Body = $this->getDonationApprovalTemplate($data);
            } else {
                $this->mailer->Subject = 'Update on Your Donation Request';
                $this->mailer->Body = $this->getDonationRejectionTemplate($data);
            }
            
            $this->mailer->isHTML(true);
            $sent = $this->mailer->send();
            
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to send donation status email: " . $e->getMessage());
            return false;
        }
    }

    public function sendStaffApprovalEmail($staffData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($staffData['email'], $staffData['name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Staff Account Approved';
            
            $this->mailer->Body = $this->getStaffApprovalTemplate($staffData);
            
            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send staff approval email: " . $e->getMessage());
            throw new Exception("Failed to send approval email: " . $e->getMessage());
        }
    }

    public function sendStaffRejectionEmail($staffData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($staffData['email'], $staffData['name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Staff Account Application Status';
            
            $this->mailer->Body = $this->getStaffRejectionTemplate($staffData);
            
            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send staff rejection email: " . $e->getMessage());
            throw new Exception("Failed to send rejection email: " . $e->getMessage());
        }
    }

    public function sendBatchDonationStatusEmail($data) {
        $status = $data['status'];
        $name = $data['name'];
        $email = $data['email'];
        $notes = isset($data['notes']) ? $data['notes'] : '';
        
        if ($status === 'batch_approved') {
            $subject = "Your Food Donation Requests have been Approved";
            
            $approvedItems = $data['approved_items'];
            $itemsList = '';
            
            foreach ($approvedItems as $item) {
                $itemsList .= "<li style='margin-bottom: 10px;'>";
                $itemsList .= "<strong>{$item['product_name']}</strong> - {$item['quantity']} items<br>";
                $itemsList .= "Pickup from: {$item['branch_name']}<br>";
                $itemsList .= "Pickup on: " . date('F j, Y', strtotime($item['pickup_date'])) . " at " . 
                             date('g:i A', strtotime($item['pickup_time']));
                $itemsList .= "</li>";
            }
            
            $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #47663B;'>Donation Requests Approved</h2>
                    <p>Dear {$name},</p>
                    <p>We're pleased to inform you that your food donation requests have been <strong style='color: #47663B;'>approved</strong>.</p>
                    
                    <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='margin-top: 0; color: #47663B;'>Approved Items:</h3>
                        <ul style='padding-left: 20px;'>
                            {$itemsList}
                        </ul>
                    </div>
                    
                    " . (!empty($notes) ? "<p><strong>Admin Notes:</strong> {$notes}</p>" : "") . "
                    
                    <p>Please arrive on time for your pickup. The staff will have your items prepared.</p>
                    <p>Thank you for your contribution to reducing food waste and helping those in need!</p>
                    
                    <p style='margin-top: 30px;'>Best regards,<br>WasteWise Team</p>
                </div>
            ";
            
        } elseif ($status === 'batch_rejected') {
            $subject = "Your Food Donation Requests have been Declined";
            
            $rejectedItems = $data['rejected_items'];
            $itemsList = '';
            
            foreach ($rejectedItems as $item) {
                $itemsList .= "<li><strong>{$item['product_name']}</strong> - {$item['quantity']} items</li>";
            }
            
            $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #e53e3e;'>Donation Requests Declined</h2>
                    <p>Dear {$name},</p>
                    <p>We regret to inform you that your food donation requests have been <strong style='color: #e53e3e;'>declined</strong>.</p>
                    
                    <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='margin-top: 0; color: #e53e3e;'>Declined Items:</h3>
                        <ul style='padding-left: 20px;'>
                            {$itemsList}
                        </ul>
                    </div>
                    
                    " . (!empty($notes) ? "<p><strong>Reason:</strong> {$notes}</p>" : "") . "
                    
                    <p>We invite you to browse other available food donations or submit new requests.</p>
                    <p>Thank you for your understanding and continued support in our mission to reduce food waste.</p>
                    
                    <p style='margin-top: 30px;'>Best regards,<br>WasteWise Team</p>
                </div>
            ";
        } else {
            return false;
        }
        
        return $this->sendEmail($email, $subject, $htmlBody);
    }

    /**
     * Send a generic email with HTML content
     *
     * @param string $email Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML content for the email
     * @return bool True if email sent successfully, false otherwise
     */
    public function sendEmail($email, $subject, $htmlBody) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            
            $sent = $this->mailer->send();
            if (!$sent) {
                throw new Exception($this->mailer->ErrorInfo);
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
            return false;
        }
    }

    private function getNGOApprovalTemplate($data) {
        return "
        <h2>Welcome to WasteWise Management!</h2>
        <p>Dear {$data['name']},</p>
        <p>We are pleased to inform you that your NGO partnership account for {$data['organization_name']} has been approved.</p>
        <p>You can now log in to your account using your registered email and password.</p>
        <p>Visit our login page: <a href='" . BASE_URL . "/index.php'>Login Here</a></p>
        <p>Best regards,<br>WasteWise Team</p>
    ";
    }

    private function getNGORejectionTemplate($data) {
        return "
        <h2>NGO Partnership Application Status</h2>
        <p>Dear {$data['name']},</p>
        <p>We regret to inform you that your NGO partnership application for {$data['organization_name']} has not been approved at this time.</p>
        <p>If you have any questions or would like to submit a new application in the future, please feel free to contact us.</p>
        <p>Best regards,<br>WasteWise Team</p>
    ";
    }

    private function getPasswordResetTemplate($userData, $resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .content { margin-top: 20px; }
                .button {
                    background-color: #47663B;
                    color: white;
                    padding: 12px 25px;
                    text-decoration: none;
                    border-radius: 5px;
                    display: inline-block;
                    margin: 20px 0;
                }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Dear {$userData['fname']},</p>
                    <p>You recently requested to reset your password for your WasteWise account.</p>
                    <p>Click the button below to reset your password:</p>
                    <div style='text-align: center;'>
                        <a href='{$resetLink}' class='button text-stone-50'>Reset Password</a>
                    </div>
                    <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                    <p>This password reset link is only valid for the next hour.</p>
                    <p>Best regards,<br>WasteWise Team</p>
                </div>
                <div class='footer'>
                    <p>WasteWise &copy; " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>
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
                        <span>" . htmlspecialchars($data['quantity_requested'] ?? $data['received_quantity'] ?? 'N/A') . "</span>
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

    private function getOTPTemplate($userData, $otp) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .content { margin-top: 20px; }
                .otp-code { 
                    font-size: 32px; 
                    font-weight: bold; 
                    text-align: center; 
                    color: #47663B;
                    letter-spacing: 5px;
                    margin: 30px 0;
                }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Login Verification Code</h2>
                </div>
                <div class='content'>
                    <p>Dear {$userData['fname']},</p>
                    <p>Your login verification code is:</p>
                    <div class='otp-code'>{$otp}</div>
                    <p>This code will expire in 5 minutes.</p>
                    <p>If you did not request this code, please ignore this email or contact support if you have concerns.</p>
                </div>
                <div class='footer'>
                    <p>WasteWise &copy; " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>
    ";
    }

    private function getDonationApprovalTemplate($data) {
        return "
        <h2>Donation Request Approved</h2>
        <p>Dear {$data['name']},</p>
        <p>Your request for <strong>{$data['product_name']}</strong> has been approved!</p>
        <p><strong>Pickup Details:</strong></p>
        <ul>
            <li>Branch: {$data['branch_name']}</li>
            <li>Pickup Date: {$data['pickup_date']}</li>
            <li>Pickup Time: {$data['pickup_time']}</li>
        </ul>
        <p>Please bring your ID when you come to pick up the donation.</p>
        <p>If you have any questions, please contact us.</p>
        <p>Thank you for your partnership in reducing food waste!</p>
        <p>Regards,<br>WasteWise Team</p>
        ";
    }

    private function getDonationRejectionTemplate($data) {
        return "
        <h2>Update on Your Donation Request</h2>
        <p>Dear {$data['name']},</p>
        <p>We regret to inform you that your request for <strong>{$data['product_name']}</strong> could not be approved at this time.</p>
        <p><strong>Reason:</strong> {$data['notes']}</p>
        <p>We encourage you to check our available donations regularly, as new items become available frequently.</p>
        <p>If you have any questions, please contact us.</p>
        <p>Thank you for your understanding and continued partnership in reducing food waste!</p>
        <p>Regards,<br>WasteWise Team</p>
        ";
    }

    private function getStaffApprovalTemplate($data) {
        return "
        <h2>Welcome to WasteWise Management!</h2>
        <p>Dear {$data['name']},</p>
        <p>We are pleased to inform you that your staff account has been approved.</p>
        <p>You can now log in to your account using your registered email and password.</p>
        <p>Visit our login page: <a href='" . BASE_URL . "/index.php'>Login Here</a></p>
        <p>Best regards,<br>WasteWise Team</p>
        ";
    }

    private function getStaffRejectionTemplate($data) {
        return "
        <h2>Staff Account Application Status</h2>
        <p>Dear {$data['name']},</p>
        <p>We regret to inform you that your staff account application has not been approved at this time.</p>
        <p>If you have any questions, please contact the system administrator for more information.</p>
        <p>Best regards,<br>WasteWise Team</p>
        ";
    }

    public function sendBulkDonationReceiptEmail($data) {
        $subject = "Bulk Donation Receipt Confirmation";
        
        // Generate product list HTML
        $productsHtml = '';
        foreach ($data['products'] as $product) {
            $productsHtml .= "<tr>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$product['product_name']}</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$product['quantity']}</td>
            </tr>";
        }
        
        $htmlBody = "
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
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background-color: #f2f2f2; text-align: left; padding: 8px; border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='receipt'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Bulk Donation Receipt Confirmation</p>
                </div>
                <div class='details'>
                    <div class='row'>
                        <span class='label'>Receipt Number:</span>
                        <span>REC-BULK-" . date('Ymd') . "-" . rand(1000, 9999) . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Date:</span>
                        <span>" . date('F j, Y g:i A') . " PHT</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Recipient:</span>
                        <span>" . htmlspecialchars($data['ngo_name']) . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Received By:</span>
                        <span>" . htmlspecialchars($data['received_by']) . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Branch:</span>
                        <span>" . htmlspecialchars($data['branch_name']) . "</span>
                    </div>
                    <div class='row'>
                        <span class='label'>Branch Address:</span>
                        <span>" . htmlspecialchars($data['branch_address']) . "</span>
                    </div>
                    
                    <h3>Products Received (Total: " . count($data['products']) . " items)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th style='text-align: center;'>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$productsHtml}
                            <tr style='font-weight: bold; background-color: #f9f9f9;'>
                                <td style='border: 1px solid #ddd; padding: 8px;'>Total Items:</td>
                                <td style='border: 1px solid #ddd; padding: 8px;'>{$data['total_quantity']}</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    " . (!empty($data['remarks']) ? "<p><strong>Remarks:</strong> " . nl2br(htmlspecialchars($data['remarks'])) . "</p>" : "") . "
                    
                    <p>Thank you for participating in our food redistribution program.</p>
                    <p>WasteWise &copy; " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['ngo_email'], $subject, $htmlBody);
    }

    /**
     * Send email notification when a donation is prepared for pickup
     */
    public function sendDonationPreparationEmail($data) {
        $subject = "Your Donation is Ready for Pickup - WasteWise";
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .content { margin-top: 20px; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                .button {
                    display: inline-block;
                    background-color: #47663B;
                    color: white;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Donation Prepared Notification</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    
                    <p>Great news! Your donation request for <strong>{$data['product_name']}</strong> 
                    ({$data['quantity']} units) is now ready for pickup.</p>
                    
                    <div class='details'>
                        <p><strong>Pickup Details:</strong></p>
                        <p>
                            <strong>Branch:</strong> {$data['branch_name']}<br>
                            <strong>Address:</strong> {$data['branch_address']}<br>
                            <strong>Pickup Date:</strong> " . date('F j, Y', strtotime($data['pickup_date'])) . "<br>
                            <strong>Pickup Time:</strong> {$data['pickup_time']} PHT
                        </p>
                        
                        " . (!empty($data['staff_notes']) ? "<p><strong>Staff Notes:</strong><br>" . nl2br(htmlspecialchars($data['staff_notes'])) . "</p>" : "") . "
                    </div>
                    
                    <p>Please bring your identification for verification during pickup.</p>
                    
                    <p>Thank you for partnering with us to reduce food waste and feed communities!</p>
                    
                    <div style='text-align: center;'>
                        <a href='#' class='button'>View Donation Details</a>
                    </div>
                    
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                        <p>This is an automated message, please do not reply directly to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['email'], $subject, $htmlBody);
    }

    /**
     * Send bulk email notification for multiple prepared donations
     */
    public function sendBulkDonationPreparationEmail($data) {
        $subject = "{$data['products_count']} Donations Ready for Pickup - WasteWise";
        
        // Build the products table
        $productsTable = "";
        foreach ($data['products'] as $product) {
            $productsTable .= "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$product['product_name']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>{$product['quantity']}</td>
            </tr>";
        }
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .content { margin-top: 20px; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                .button {
                    display: inline-block;
                    background-color: #47663B;
                    color: white;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    margin-top: 20px;
                }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th { background-color: #f2f2f2; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Multiple Donations Ready for Pickup</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    
                    <p>Great news! <strong>{$data['products_count']} items</strong> from your donation requests 
                    are now ready for pickup.</p>
                    
                    <div class='details'>
                        <p><strong>Pickup Details:</strong></p>
                        <p>
                            <strong>Branch:</strong> {$data['branch_name']}<br>
                            <strong>Address:</strong> {$data['branch_address']}<br>
                            <strong>Pickup Date:</strong> " . date('F j, Y', strtotime($data['pickup_date'])) . " PHT
                        </p>
                        
                        <p><strong>Prepared Items:</strong></p>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style='text-align: center;'>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$productsTable}
                            </tbody>
                        </table>
                        
                        " . (!empty($data['staff_notes']) ? "<p><strong>Staff Notes:</strong><br>" . nl2br(htmlspecialchars($data['staff_notes'])) . "</p>" : "") . "
                    </div>
                    
                    <p>Please bring your identification for verification during pickup.</p>
                    
                    <p>Thank you for partnering with us to reduce food waste and feed communities!</p>
                    
                    <div style='text-align: center;'>
                        <a href='#' class='button'>View Donation Details</a>
                    </div>
                    
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                        <p>This is an automated message, please do not reply directly to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['email'], $subject, $htmlBody);
    }

    /**
     * Send a confirmation email to companies that have requested to join
     */
    public function sendCompanyRequestConfirmation($data) {
        $subject = "WasteWise - We've Received Your Request";
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .content { margin-top: 20px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Request Confirmation</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    
                    <p>Thank you for your interest in joining WasteWise with <strong>{$data['company_name']}</strong>!</p>
                    
                    <p>We have received your request to join our waste management system. Our team will review your application shortly.</p>
                    
                    <p>Once approved, you'll receive an email with instructions on how to complete your registration and start using our system.</p>
                    
                    <p>Thank you for your commitment to reducing food waste!</p>
                    
                    <p>Best regards,<br>WasteWise Team</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                    <p>This is an automated message, please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['email'], $subject, $htmlBody);
    }

    /**
     * Send approval email to companies with registration link
     */
    public function sendCompanyApprovalEmail($data) {
        $subject = "Your WasteWise Application Has Been Approved!";
        
        $registrationLink = SITE_URL . "/auth/register_company.php?token=" . $data['token'] . "&email=" . urlencode($data['email']);
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                /* Email styles */
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Application Approved</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    
                    <p>Great news! Your application for <strong>{$data['company_name']}</strong> has been approved to join WasteWise.</p>
                    
                    <p>You're now one step away from accessing our waste management system. Please click the button below to complete your registration and set up your account:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$registrationLink}' class='button'>Complete Registration</a>
                    </div>
                    
                    <p>This link will expire in 48 hours for security reasons.</p>
                    
                    <p>After registration, your company will be added as a branch in our system, allowing you to:</p>
                    <ul>
                        <li>Track excess food products</li>
                        <li>Manage inventory efficiently</li>
                        <li>Access waste reduction analytics</li>
                        <li>Coordinate donations to partner NGOs</li>
                    </ul>
                    
                    <p>Welcome to the WasteWise community!</p>
                    
                    <p>Best regards,<br>WasteWise Team</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['email'], $subject, $htmlBody);
    }

    /**
     * Send final approval email to company after their full registration has been approved
     */
    public function sendCompanyFinalApprovalEmail($data) {
        $subject = "Your WasteWise Account Has Been Approved!";
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .content { margin-top: 20px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Account Approved</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    
                    <p>Great news! Your company account for <strong>{$data['company_name']}</strong> has been approved by our administrators.</p>
                    
                    <p>You can now log in to your WasteWise account and start using our waste management system. You'll be able to:</p>
                    
                    <ul>
                        <li>Track excess food products</li>
                        <li>Manage inventory efficiently</li>
                        <li>Access waste reduction analytics</li>
                        <li>Donate excess products to NGOs</li>
                    </ul>
                    
                    <p>Login to your account to get started:</p>
                    <p><a href='" . SITE_URL . "/index.php'>Login to WasteWise</a></p>
                    
                    <p>Welcome to the WasteWise community!</p>
                    
                    <p>Best regards,<br>WasteWise Team</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                    <p>This is an automated message, please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['email'], $subject, $htmlBody);
    }

    /**
     * Send rejection email to company after their full registration has been rejected
     */
    public function sendCompanyRejectionEmail($data) {
        $subject = "Important Information About Your WasteWise Registration";
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .content { margin-top: 20px; }
                .reason { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>Registration Update</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['name']},</p>
                    
                    <p>Thank you for completing your registration for <strong>{$data['company_name']}</strong> with WasteWise.</p>
                    
                    <p>We've reviewed your application and unfortunately, we are unable to approve your account at this time.</p>
                    
                    <div class='reason'>
                        <p><strong>Reason for rejection:</strong></p>
                        <p>" . nl2br(htmlspecialchars($data['notes'])) . "</p>
                    </div>
                    
                    <p>If you would like to address these issues and reapply, please contact our support team at support@wastewise.com.</p>
                    
                    <p>Thank you for your interest in WasteWise.</p>
                    
                    <p>Best regards,<br>WasteWise Team</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                    <p>This is an automated message, please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['email'], $subject, $htmlBody);
    }

    /**
     * Send confirmation email to staff when an NGO confirms a pickup
     */
    public function sendStaffPickupConfirmationEmail($data) {
        $subject = "NGO Pickup Confirmation: {$data['product_name']}";
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .content { margin-top: 20px; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>NGO Pickup Confirmation</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['staff_name']},</p>
                    
                    <p>This is to inform you that an NGO has confirmed pickup of the following donation:</p>
                    
                    <div class='details'>
                        <p><strong>NGO:</strong> {$data['ngo_name']}</p>
                        <p><strong>Product:</strong> {$data['product_name']}</p>
                        <p><strong>Quantity:</strong> {$data['quantity']} units</p>
                        <p><strong>Branch:</strong> {$data['branch_name']}</p>
                        <p><strong>Pickup Date:</strong> " . date('F j, Y g:i A', strtotime($data['pickup_date'])) . " PHT</p>
                        
                        " . (!empty($data['remarks']) ? "<p><strong>NGO Remarks:</strong><br>" . nl2br(htmlspecialchars($data['remarks'])) . "</p>" : "") . "
                    </div>
                    
                    <p>The donation has been successfully completed and recorded in the system.</p>
                    
                    <p>Thank you for your dedication to reducing food waste!</p>
                    
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                        <p>This is an automated message, please do not reply directly to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['staff_email'], $subject, $htmlBody);
    }

    /**
     * Send confirmation email to staff when an NGO confirms a bulk pickup
     */
    public function sendStaffBulkPickupConfirmationEmail($data) {
        $subject = "NGO Bulk Pickup Confirmation";
        
        // Build products list
        $productsHtml = '';
        foreach ($data['products'] as $product) {
            $productsHtml .= "<tr>
                <td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($product['product_name']) . "</td>
                <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>" . $product['quantity'] . "</td>
            </tr>";
        }
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #47663B; }
                .logo { color: #47663B; font-size: 24px; font-weight: bold; }
                .content { margin-top: 20px; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background-color: #f2f2f2; text-align: left; padding: 8px; border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>WasteWise</div>
                    <p>NGO Bulk Pickup Confirmation</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$data['staff_name']},</p>
                    
                    <p>This is to inform you that <strong>{$data['ngo_name']}</strong> has confirmed pickup of multiple donation items from your branch.</p>
                    
                    <div class='details'>
                        <p><strong>Pickup Details:</strong></p>
                        <p><strong>NGO:</strong> {$data['ngo_name']}</p>
                        <p><strong>Branch:</strong> {$data['branch_name']}</p>
                        <p><strong>Pickup Date:</strong> " . date('F j, Y g:i A', strtotime($data['pickup_date'])) . " PHT</p>
                        
                        <h3>Items Confirmed Received:</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style='text-align: center;'>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$productsHtml}
                                <tr style='font-weight: bold; background-color: #f9f9f9;'>
                                    <td style='border: 1px solid #ddd; padding: 8px;'>Total Items:</td>
                                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$data['total_quantity']}</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        " . (!empty($data['remarks']) ? "<p><strong>NGO Remarks:</strong><br>" . nl2br(htmlspecialchars($data['remarks'])) . "</p>" : "") . "
                    </div>
                    
                    <p>All donations have been successfully completed and recorded in the system.</p>
                    
                    <p>Thank you for your dedication to reducing food waste!</p>
                    
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " WasteWise. All rights reserved.</p>
                        <p>This is an automated message, please do not reply directly to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($data['staff_email'], $subject, $htmlBody);
    }
}