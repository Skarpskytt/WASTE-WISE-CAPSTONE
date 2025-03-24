<?php
// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once 'config/app_config.php';
require_once 'config/db_connect.php';
require_once 'config/session_handler.php';

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
  <title>Login</title>
  <link rel="icon" type="image/x-icon" href="assets/images/Company Logo.jpg">
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
  <script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '👁️';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '👁️‍🗨️';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Error/Failed authentication notifications
    <?php if (isset($_SESSION['error'])): ?>
        Toastify({
            text: "<?php 
                $error = $_SESSION['error'];
                if (strpos($error, 'pending') !== false) {
                    echo '⏳ Pending Approval: ' . $error;
                } elseif (strpos($error, 'password') !== false) {
                    echo '🔒 Authentication Error: ' . $error;
                } elseif (strpos($error, 'locked') !== false) {
                    echo '🔐 Account Locked: ' . $error;
                } else {
                    echo '❌ ' . $error;
                }
            ?>",
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

    // Login-specific errors (with attempts tracking)
    <?php if (isset($_SESSION['login_error']) || isset($_SESSION['attempts_left'])): ?>
        console.log("Login error detected: <?= isset($_SESSION['login_error']) ? htmlspecialchars($_SESSION['login_error']) : '' ?>");
        console.log("Attempts left: <?= isset($_SESSION['attempts_left']) ? $_SESSION['attempts_left'] : 'not set' ?>");
        
        Toastify({
            text: "<?php 
                $error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : 'Authentication error';
                $attempts = isset($_SESSION['attempts_left']) ? $_SESSION['attempts_left'] : null;
                
                if (strpos($error, 'password') !== false) {
                    echo '🔒 Authentication Error: ' . $error;
                    if ($attempts !== null) {
                        echo ' (' . $attempts . ' attempts left)';
                    }
                } else {
                    echo '❌ ' . $error;
                    if ($attempts !== null) {
                        echo ' (' . $attempts . ' attempts left)';
                    }
                }
            ?>",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444",
            stopOnFocus: true,
            className: "toast-message"
        }).showToast();
        
        <?php 
        // Unset both variables
        if (isset($_SESSION['login_error'])) unset($_SESSION['login_error']); 
        if (isset($_SESSION['attempts_left'])) unset($_SESSION['attempts_left']);
        ?>
    <?php endif; ?>

    // Success messages
    <?php if (isset($_SESSION['success'])): ?>
        Toastify({
            text: "✅ <?= $_SESSION['success'] ?>",
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

    // Pending approval messages (warning style)
    <?php if (isset($_SESSION['pending_message'])): ?>
        Toastify({
            text: "⏳ Account Status: <?= $_SESSION['pending_message'] ?>",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#F59E0B",
            stopOnFocus: true,
            className: "toast-message"
        }).showToast();
        <?php unset($_SESSION['pending_message']); ?>
    <?php endif; ?>
});
</script>

<!-- Add this CSS for better toast styling -->
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

</head>
<body>
<div class="flex h-screen">
  <div class="hidden lg:flex items-center justify-center flex-1 bg-sec text-black">
    <div class="max-w-md text-center">
     <img src="assets/images/login.gif" alt="Login Animation">
    </div>
  </div>
  
  <div class="w-full bg-white lg:w-1/2 flex items-center justify-center">
    <div class="max-w-md w-full p-6">
      <h1 class="text-3xl font-semibold mb-6 text-black text-center">Sign in</h1>
      <h1 class="text-sm font-semibold mb-6 text-gray-500 text-center">Welcome to Bea Bakes: A Food Waste Management Hub System</h1>
      
      <form action="auth/save_login.php" method="POST" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
          <input type="email" id="email" name="email" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
        </div>
        <div>
    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
    <div class="relative">
        <input type="password" id="password" name="password" 
               class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" 
               required>
        <button type="button" 
                onclick="togglePassword()" 
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 mt-1"
                tabindex="-1">
            <span id="eyeIcon" class="text-gray-700">👁️‍🗨️</span>
        </button>
    </div>
</div>
<div class="flex justify-between items-center">
<div class="flex items-center">
          <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-primarycol focus:ring-primarycol border-gray-300 rounded">
          <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
        </div>
        <div>
          <div></div> <!-- Empty div for spacing -->
          <a href="auth/forgot_password.php" class="text-sm text-primarycol hover:underline">
            Forgot your password?
          </a>
        </div>
</div>
       
        <div>
          <button type="submit" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">Sign In</button>
        </div>
      </form>
      <div class="mt-4 text-sm text-gray-600 text-center">
        <p>Don't have an account? <a href="auth/signup.php" class="text-black hover:underline">Sign up here</a></p>
      </div>
    </div>
  </div>
</div>
</body>
</html>