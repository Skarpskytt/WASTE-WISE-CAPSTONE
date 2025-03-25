<?php
namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Ensure BASE_URL is available - add error handling
if (!file_exists(__DIR__ . '/../../config/app_config.php')) {
    error_log("Critical error: app_config.php not found");
}
require_once __DIR__ . '/../../config/app_config.php';

// Check if autoload exists
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    // If not using Composer, include PHPMailer directly
    require_once __DIR__ . '/../../includes/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../../includes/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../../includes/phpmailer/src/SMTP.php';
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
                $itemsList .= "Pickup on: " . date('M d, Y', strtotime($item['pickup_date'])) . " at " . 
                             date('h:i A', strtotime($item['pickup_time']));
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
    private function sendEmail($email, $subject, $htmlBody) {
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
}