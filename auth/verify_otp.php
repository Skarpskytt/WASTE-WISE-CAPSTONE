<?php
// Use standard PHP session only
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the session for debugging
error_log("SESSION in verify_otp.php: " . print_r($_SESSION, true));

// If no temp_user_id, redirect to login
if (!isset($_SESSION['temp_user_id'])) {
    $_SESSION['error'] = "Session expired. Please login again.";
    header('Location: ../index.php');
    exit();
}

// Include necessary files for UI only
require_once '../config/app_config.php';
require_once '../config/db_connect.php';

// Create database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=wastewise', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add Toastify CSS and JS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    
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
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <div class="hidden lg:flex items-center justify-center flex-1 bg-sec text-black">
            <div class="max-w-md text-center">
                <img src="../assets/images/login.gif" alt="Login Animation">
            </div>
        </div>
        
        <div class="w-full bg-white lg:w-1/2 flex items-center justify-center">
            <div class="max-w-md w-full p-6">
                <h1 class="text-3xl font-semibold mb-6 text-black text-center">Verify OTP</h1>
                <h1 class="text-sm font-semibold mb-6 text-gray-500 text-center">
                    We've sent a verification code to your email. Please enter it below.
                </h1>
                
                <form action="verify_otp_process.php" method="POST" class="space-y-4">
                    <div>
                        <label for="otp" class="block text-sm font-medium text-gray-700">OTP Code</label>
                        <input type="text" id="otp" name="otp" 
                               class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration:300" 
                               required>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-black text-white py-2 rounded-lg hover:bg-sec hover:text-black transition-colors">
                        Verify OTP
                    </button>
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">Didn't receive the code?</p>
                        <button type="button" 
                                id="resendOTP"
                                onclick="window.location.href='resend_otp.php'"
                                class="text-black hover:text-sec text-sm font-medium">
                            Resend OTP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast notification script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded, checking for OTP messages");
        
        <?php if (isset($_SESSION['error'])): ?>
            console.log("OTP error found: <?= $_SESSION['error'] ?>");
            Toastify({
                text: "❌ <?= $_SESSION['error'] ?>",
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
            console.log("OTP success found");
            Toastify({
                text: "✅ <?= $_SESSION['success'] ?>",
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
        
        <?php if (isset($_SESSION['info'])): ?>
            console.log("OTP info found");
            Toastify({
                text: "ℹ️ <?= $_SESSION['info'] ?>",
                duration: 4000,
                close: true,
                gravity: "top",
                position: "center",
                backgroundColor: "#3B82F6",
                stopOnFocus: true,
                className: "toast-message"
            }).showToast();
            <?php unset($_SESSION['info']); ?>
        <?php endif; ?>
    });
    </script>

    <style>
    .toast-message {
        font-family: 'Arial', sans-serif;
        font-weight: 500;
        font-size: 14px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border-radius: 6px;
        max-width: 350px;
        line-height: 1.5;
    }
    </style>
</body>
</html>