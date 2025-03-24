<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Choose ONE session method - standard PHP sessions recommended
session_start();

// Include necessary files
require_once '../config/app_config.php';
require_once '../config/db_connect.php';

// Get database connection
$pdo = getPDO();

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$now = date('Y-m-d H:i:s');

// Add debugging
error_log("Password reset requested with token: " . $token);
error_log("Current time: " . $now);

// Check if token exists and is valid
$tokenIsValid = false;
$userEmail = '';
$userName = '';

// Replace your token validation with this improved version
if (!empty($token)) {
    try {
        // Use a more secure query that validates both token and expiry in the SQL
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email, u.fname, u.id AS user_id
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? 
            AND pr.used = 0
            AND pr.expiry_date > NOW()
        ");
        $stmt->execute([trim($token)]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reset) {
            $tokenIsValid = true;
            $userEmail = $reset['email'];
            $userName = $reset['fname'];
            
            // Store user_id in session for security during password update
            $_SESSION['reset_user_id'] = $reset['user_id'];
            $_SESSION['reset_token'] = $token;
        } else {
            // Check if token exists but is expired
            $stmt = $pdo->prepare("
                SELECT expiry_date FROM password_resets 
                WHERE token = ? AND used = 0
            ");
            $stmt->execute([trim($token)]);
            $expiredToken = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($expiredToken && strtotime($expiredToken['expiry_date']) < time()) {
                $_SESSION['error'] = "This password reset link has expired. Please request a new one.";
            } else {
                $_SESSION['error'] = "This password reset link is invalid.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred. Please try again.";
        error_log("Reset validation error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Bea Bakes</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Add Toastify CSS and JS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primarycol: '#47663B',
                    sec: '#E8ECD7',
                }
            }
        }
    }
    </script>
    
    <!-- Toast notification script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded, checking for messages");
        
        <?php if (isset($_SESSION['error'])): ?>
            console.log("Found error message: <?= htmlspecialchars($_SESSION['error']) ?>");
            Toastify({
                text: "❌ <?= htmlspecialchars($_SESSION['error']) ?>",
                duration: 5000,
                close: true,
                gravity: "top",
                position: "center",
                backgroundColor: "#EF4444",
                stopOnFocus: true,
                className: "toast-message"
            }).showToast();
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            Toastify({
                text: "✅ <?= htmlspecialchars($_SESSION['success']) ?>",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "center",
                backgroundColor: "#10B981",
                stopOnFocus: true,
                className: "toast-message"
            }).showToast();
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    });
    
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(inputId + 'Icon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye-slash';
        }
    }
    
    function checkPasswordStrength() {
        const password = document.getElementById('password').value;
        const strengthMeter = document.getElementById('password-strength');
        const confirmPassword = document.getElementById('confirm_password').value;
        const matchIndicator = document.getElementById('password-match');
        const submitButton = document.getElementById('reset-button');
        
        // Check strength
        let strength = 0;
        
        // Length check
        if (password.length >= 8) strength++;
        
        // Character variety check
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        // Update strength indicator
        if (password.length === 0) {
            strengthMeter.className = 'mt-1 text-xs';
            strengthMeter.textContent = '';
        } else if (strength < 2) {
            strengthMeter.className = 'mt-1 text-xs text-red-500';
            strengthMeter.textContent = 'Weak password';
        } else if (strength < 4) {
            strengthMeter.className = 'mt-1 text-xs text-yellow-500';
            strengthMeter.textContent = 'Medium strength password';
        } else {
            strengthMeter.className = 'mt-1 text-xs text-green-500';
            strengthMeter.textContent = 'Strong password';
        }
        
        // Check match if confirm password has input
        if (confirmPassword.length > 0) {
            if (password === confirmPassword) {
                matchIndicator.className = 'mt-1 text-xs text-green-500';
                matchIndicator.textContent = 'Passwords match';
            } else {
                matchIndicator.className = 'mt-1 text-xs text-red-500';
                matchIndicator.textContent = 'Passwords do not match';
            }
        } else {
            matchIndicator.className = 'mt-1 text-xs';
            matchIndicator.textContent = '';
        }
        
        // Enable/disable submit button based on password criteria
        if (strength >= 3 && password === confirmPassword && password.length > 0) {
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            submitButton.classList.add('hover:bg-opacity-90');
        } else {
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            submitButton.classList.remove('hover:bg-opacity-90');
        }
    }
    </script>
    
    <style>
    .toast-message {
        font-family: 'Arial', sans-serif;
        font-weight: 500;
        font-size: 14px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border-radius: 6px;
        max-width: 350px;
    }
    .password-input-container {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #6B7280;
    }
    
    /* Add animation for better UI */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-container {
        animation: fadeIn 0.5s ease-out;
    }
    
    .password-requirements {
        font-size: 12px;
        color: #6B7280;
        margin-top: 8px;
    }
    
    .requirement {
        display: flex;
        align-items: center;
        margin-bottom: 2px;
    }
    
    .requirement i {
        margin-right: 5px;
        font-size: 10px;
    }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="flex w-full max-w-4xl shadow-xl rounded-lg overflow-hidden">
        <!-- Left side - Image/Brand section -->
        <div class="hidden lg:block w-1/2 bg-sec rounded-l-lg p-8 flex items-center justify-center">
            <div class="text-center">
                <img src="../assets/images/login.gif" alt="Password Reset" class="max-w-xs mx-auto">
                <h2 class="text-2xl font-bold text-primarycol mt-6">Bea Bakes</h2>
                <h3 class="text-lg text-primarycol mb-2">Food Waste Management Hub</h3>
                <p class="text-gray-700 mt-2">Secure your account with a strong password to protect your data.</p>
            </div>
        </div>
        
        <!-- Right side - Form section -->
        <div class="w-full lg:w-1/2 bg-white p-8 rounded-lg lg:rounded-l-none lg:rounded-r-lg">
            <div class="text-center mb-6">
                <i class="fas fa-lock text-primarycol text-4xl mb-3"></i>
                <h1 class="text-2xl font-bold text-primarycol">Reset Your Password</h1>
                <p class="text-gray-600 mt-2">
                    <?php if ($tokenIsValid): ?>
                        Hello <?= htmlspecialchars($userName ?? 'there') ?>, create a new password for your account
                    <?php else: ?>
                        The reset link appears to be invalid or has expired
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if ($tokenIsValid): ?>
                <form action="handle_reset_password.php" method="POST" class="space-y-6 form-container">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($userEmail) ?>">
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="password" name="password" required 
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primarycol focus:border-primarycol"
                                   onkeyup="checkPasswordStrength()">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i id="passwordIcon" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        <div id="password-strength" class="mt-1 text-xs"></div>
                        
                        <div class="password-requirements mt-2">
                            <p class="font-medium text-xs text-gray-700 mb-1">Password must:</p>
                            <div class="requirement">
                                <i class="fas fa-circle"></i>
                                <span>Be at least 8 characters long</span>
                            </div>
                            <div class="requirement">
                                <i class="fas fa-circle"></i>
                                <span>Include uppercase and lowercase letters</span>
                            </div>
                            <div class="requirement">
                                <i class="fas fa-circle"></i>
                                <span>Include at least one number</span>
                            </div>
                            <div class="requirement">
                                <i class="fas fa-circle"></i>
                                <span>Include at least one special character</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primarycol focus:border-primarycol"
                                   onkeyup="checkPasswordStrength()">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i id="confirm_passwordIcon" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        <div id="password-match" class="mt-1 text-xs"></div>
                    </div>
                    
                    <div>
                        <button id="reset-button" type="submit" 
                                class="w-full bg-primarycol text-white py-3 px-4 rounded-md opacity-50 cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primarycol transition-all duration-200" 
                                disabled>
                            <i class="fas fa-key mr-2"></i> Reset Password
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="bg-red-50 p-6 rounded-md text-center form-container">
                    <div class="mb-4 text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl"></i>
                    </div>
                    <p class="text-red-700 mb-4">This reset link is invalid or has expired.</p>
                    <div class="mt-4">
                        <a href="forgot_password.php" class="inline-block bg-primarycol text-white py-2 px-4 rounded-md hover:bg-opacity-90 transition-all duration-200">
                            <i class="fas fa-redo mr-2"></i> Request New Reset Link
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-6 text-center">
                <a href="<?= BASE_URL ?>" class="text-sm text-primarycol hover:underline">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>