<?php
session_start();
// Add error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../config/app_config.php';
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

use CustomSession\SessionHandler;
use function CustomSession\initSession;

// Get database connection
$pdo = getPDO();

// Initialize session with our custom handler
initSession($pdo);

// Get session handler instance
$session = SessionHandler::getInstance($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/Logo.png">
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
            <h1 class="text-3xl font-semibold mb-6 text-black text-center">Forgot Password</h1>
            <h1 class="text-sm font-semibold mb-6 text-gray-500 text-center">Enter your email address to receive a password reset link</h1>
            
            <form action="process_forgot_password.php" method="POST" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email" required 
                           class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300">
                </div>
                
                <div>
                    <button type="submit" 
                            class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">
                        Send Reset Link
                    </button>
                </div>
            </form>
            <div class="mt-4 text-sm text-gray-600 text-center">
                <a href="<?= BASE_URL ?>/index.php" class="text-black hover:underline">Back to Login</a>
            </div>
        </div>
    </div>
</div>

<!-- Toast notification script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, checking for notifications");
    
    <?php if (isset($_SESSION['error'])): ?>
        console.log("Error notification found");
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
        console.log("Success notification found");
        Toastify({
            text: "✉️ <?= $_SESSION['success'] ?>",
            duration: 5000,
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
</style>
</body>
</html>