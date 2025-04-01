<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/app_config.php';
require_once 'config/db_connect.php';

require_once 'includes/mail/EmailService.php';

session_start();


if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['pending_approval'])) {
    $pendingMessage = $_SESSION['pending_approval'];
    unset($_SESSION['pending_approval']);
}


use App\Mail\EmailService;

$pdo = getPDO();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    try {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        error_log("Processing login for email: $email");

    
        if (empty($email) || empty($password)) {
            $errorMessage = 'Please enter both email and password.';
        } else {
      
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       np.status as ngo_status,
                       sp.status as staff_status,
                       u.failed_attempts,
                       u.locked_until
                FROM users u
                LEFT JOIN ngo_profiles np ON u.id = np.user_id
                LEFT JOIN staff_profiles sp ON u.id = sp.user_id
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

     
            if (!$user) {
                $error = 'No account found with that email.';
           
                $errorMessage = $error;
            }
     
            else if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                $timeRemaining = ceil((strtotime($user['locked_until']) - time()) / 60);
                $errorMessage = "Your account is locked due to multiple failed login attempts. Try again in $timeRemaining minutes.";
            }
    
            else if (!password_verify($password, $user['password'])) {
          
                $newAttempts = ($user['failed_attempts'] ?? 0) + 1;
                
            
                if ($newAttempts >= 5) {
              
                    $lockedUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                    $stmt->execute([$newAttempts, $lockedUntil, $user['id']]);
                    
                    $errorMessage = 'Your account has been locked due to multiple failed attempts. Try again after 30 minutes.';
                } else {
           
                    $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                    $stmt->execute([$newAttempts, $user['id']]);
                    
               
                    $loginErrorMessage = "Wrong password";
                    $attemptsLeft = 5 - $newAttempts;
                }
            }
      
            else {
         
                $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);

         
                if ($user['role'] === 'ngo') {
                    $ngoStatusStmt = $pdo->prepare('SELECT status FROM ngo_profiles WHERE user_id = ?');
                    $ngoStatusStmt->execute([$user['id']]);
                    $ngoStatus = $ngoStatusStmt->fetchColumn();
                    
                    if (!$user['is_active'] && $ngoStatus === 'pending') {
                        $pendingMessage = "Your NGO account is currently under review. You will be notified via email once approved.";
                    } elseif (!$user['is_active'] && $ngoStatus === 'rejected') {
                        $errorMessage = "Your NGO account application has been rejected.";
                    }
                }

                if ($user['role'] === 'branch1_staff' || $user['role'] === 'branch2_staff') {
                    $staffStatusStmt = $pdo->prepare('SELECT status FROM staff_profiles WHERE user_id = ?');
                    $staffStatusStmt->execute([$user['id']]);
                    $staffStatus = $staffStatusStmt->fetchColumn();
                    
                    if (!$user['is_active'] && $staffStatus === 'pending') {
                        $pendingMessage = "Your staff account is currently under review. You will be notified via email once approved.";
                    } elseif (!$user['is_active'] && $staffStatus === 'rejected') {
                        $errorMessage = "Your staff account application has been rejected. Contact administration for details.";
                    }
                }

                if (!$user['is_active']) {
                    $errorMessage = 'Your account is inactive. Please contact administrator.';
                }

          
                if (!isset($errorMessage) && !isset($pendingMessage)) {
            
                    $otp = sprintf("%06d", mt_rand(100000, 999999));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                    $stmt = $pdo->prepare("
                        INSERT INTO otp_codes (user_id, code, expires_at) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$user['id'], $otp, $expires_at]);

        
                    $emailService = new \App\Mail\EmailService();
                    $emailService->sendOTPEmail([
                        'email' => $user['email'],
                        'fname' => $user['fname'],
                        'otp' => $otp
                    ]);
              
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['temp_email'] = $user['email']; 
                    
             
                    error_log("Session before redirect - ID: " . session_id() . ", temp_user_id: " . $_SESSION['temp_user_id']);
                    
                
                    session_write_close();
                    
           
                    header('Location: auth/verify_otp.php');
                    exit();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="icon" type="image/x-icon" href="assets/images/Company Logo.jpg">
  <script src="https://cdn.tailwindcss.com"></script>
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
  <style>
    .toast-message {
      font-family: 'Arial', sans-serif;
    }
  </style>
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
    console.log("DOM loaded, checking for notifications"); 

    <?php if (isset($errorMessage)): ?>
        console.log("Error notification should display: <?= addslashes($errorMessage) ?>"); // Debug
        Toastify({
            text: "❌ <?= addslashes($errorMessage) ?>",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444",
            stopOnFocus: true
        }).showToast();
    <?php endif; ?>

    <?php if (isset($loginErrorMessage) && isset($attemptsLeft)): ?>
        console.log("Login error notification should display"); 
        Toastify({
            text: "🔒 <?= addslashes($loginErrorMessage) ?> (<?= $attemptsLeft ?> attempts remaining)",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444",
            stopOnFocus: true
        }).showToast();
    <?php endif; ?>
 
    <?php if (isset($pendingMessage)): ?>
        console.log("Pending notification should display"); 
        Toastify({
            text: "⏳ Account Status: <?= addslashes($pendingMessage) ?>",
            duration: 5000,
            close: true,
            gravity: "top", 
            position: "center",
            backgroundColor: "#F59E0B",
            stopOnFocus: true
        }).showToast();
    <?php endif; ?>
    
    <?php if (isset($successMessage)): ?>
        console.log("Success notification should display");
        Toastify({
            text: "✅ <?= addslashes($successMessage) ?>",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#10B981",
            stopOnFocus: true
        }).showToast();
    <?php endif; ?>
});
</script>

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
      

      <form method="POST" class="space-y-4">
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
            <div></div> 
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


<div id="debug" style="display: none">
<?php
echo "Current PHP Session ID: " . session_id() . "<br>";
echo "Form submission: " . ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'Yes' : 'No') . "<br>";
if (isset($_POST)) {
    echo "POST variables: <pre>" . print_r($_POST, true) . "</pre><br>";
}
if (isset($_SESSION)) {
    echo "Session variables: <pre>" . print_r($_SESSION, true) . "</pre><br>";
}
?>
</div>

<div id="error-debug" style="display: none">
<?php
echo "Error Variables:<br>";
echo "errorMessage: " . (isset($errorMessage) ? $errorMessage : 'not set') . "<br>";
echo "loginErrorMessage: " . (isset($loginErrorMessage) ? $loginErrorMessage : 'not set') . "<br>";
echo "attemptsLeft: " . (isset($attemptsLeft) ? $attemptsLeft : 'not set') . "<br>";
echo "pendingMessage: " . (isset($pendingMessage) ? $pendingMessage : 'not set') . "<br>";
echo "successMessage: " . (isset($successMessage) ? $successMessage : 'not set') . "<br>";
?>
</div>

<script>
document.addEventListener('keydown', function(e) {
    if (e.key === 'F12') {
        const debug = document.getElementById('debug');
        const errorDebug = document.getElementById('error-debug');
        debug.style.display = debug.style.display === 'none' ? 'block' : 'none';
        errorDebug.style.display = errorDebug.style.display === 'none' ? 'block' : 'none';
    }
});

console.log("Toastify loaded:", typeof Toastify !== 'undefined');
</script>
</body>
</html>