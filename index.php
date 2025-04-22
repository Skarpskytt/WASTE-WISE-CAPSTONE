<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/error.log');

// Add debug logging
error_log("Login attempt - POST data: " . print_r($_POST, true));

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

        // Debug log
        error_log("Processing login for email: $email");

        if (empty($email) || empty($password)) {
            $errorMessage = 'Please enter both email and password.';
        } else {
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       np.status as ngo_status,
                       sp.status as staff_status,
                       b.id as branch_id,
                       b.name as branch_name,
                       u.failed_attempts,
                       u.locked_until,
                       u.role,
                       u.is_active
                FROM users u
                LEFT JOIN ngo_profiles np ON u.id = np.user_id
                LEFT JOIN staff_profiles sp ON u.id = sp.user_id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $errorMessage = 'No account found with that email.';
            } 
            // Rest of your existing checks...
            else if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                $timeRemaining = ceil((strtotime($user['locked_until']) - time()) / 60);
                $errorMessage = "Your account is locked. Try again in $timeRemaining minutes.";
            }
            else if (!password_verify($password, $user['password'])) {
                // Increment failed attempts
                $newAttempts = $user['failed_attempts'] + 1;
                $lockedUntil = null;
                
                // Check if we should lock the account (after 5 attempts)
                if ($newAttempts >= 5) {
                    // Lock for 30 minutes
                    $lockedUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $loginErrorMessage = "Too many failed attempts. Your account has been locked.";
                    $attemptsLeft = 0;
                } else {
                    // Still has attempts left
                    $attemptsLeft = 5 - $newAttempts;
                    $loginErrorMessage = "Incorrect password.";
                }
                
                // Update the database
                $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                $stmt->execute([$newAttempts, $lockedUntil, $user['id']]);
                
                // Log the failed attempt
                error_log("Failed login attempt for user {$user['email']}. Attempts: $newAttempts");
            }
            else {
                // Reset failed attempts
                $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);

                if (!$user['is_active']) {
                    $errorMessage = 'Your account is inactive. Please contact administrator.';
                } else {
                    // Set temporary session variables
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['temp_email'] = $user['email'];
                    $_SESSION['temp_role'] = $user['role'];
                    $_SESSION['temp_fname'] = $user['fname'];
                    $_SESSION['temp_lname'] = $user['lname'];

                    // For staff and company users
                    if (in_array($user['role'], ['staff', 'company'])) {
                        if (empty($user['branch_id'])) {
                            $errorMessage = 'No branch assigned to your account.';
                        } else {
                            $_SESSION['temp_branch_id'] = $user['branch_id'];
                            $_SESSION['temp_branch_name'] = $user['branch_name'];
                            
                            // Debug log
                            error_log("Staff/Company login - Branch ID: {$user['branch_id']}, Role: {$user['role']}");
                        }
                    }

                    if (!isset($errorMessage)) {
                        // Generate and send OTP
                        $otp = sprintf("%06d", mt_rand(100000, 999999));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                        
                        // Clear existing OTPs
                        $clearStmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?");
                        $clearStmt->execute([$user['id']]);
                        
                        // Insert new OTP
                        $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$user['id'], $otp, $expires_at]);

                        // Send OTP email
                        $emailService = new EmailService();
                        $emailService->sendOTPEmail([
                            'email' => $user['email'],
                            'fname' => $user['fname'],
                            'otp' => $otp
                        ]);

                        // Debug log
                        error_log("OTP generated and sent. Redirecting to verification.");
                        
                        // Ensure session is written
                        session_write_close();
                        header('Location: auth/verify_otp.php');
                        exit();
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $errorMessage = "An error occurred. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="icon" type="image/x-icon" href="assets/images/Logo.png">
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primarycol: '#47663B',
            sec: '#E8ECD7',
            third: '#EED3B1',
            fourth: '#1F4529',
            accent: '#FF8A00',
            lightgreen: '#B5D99C',
            darkgreen: '#0E2E1D',
          },
          animation: {
            'float': 'float 6s ease-in-out infinite',
            'fade-in-up': 'fadeInUp 0.7s ease-out',
            'fade-in': 'fadeIn 1s ease-out',
            'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
          },
          keyframes: {
            float: {
              '0%, 100%': { transform: 'translateY(0)' },
              '50%': { transform: 'translateY(-20px)' },
            },
            fadeInUp: {
              '0%': { opacity: '0', transform: 'translateY(20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' },
            },
            fadeIn: {
              '0%': { opacity: '0' },
              '100%': { opacity: '1' },
            }
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <!-- Add AOS animation library -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  
  <script>
   tailwind.config = {
     theme: {
       extend: {
         colors: {
           primarycol: '#47663B',
           sec: '#E8ECD7',
           third: '#EED3B1',
           fourth: '#1F4529',
           accent: '#FF8A00',
           lightgreen: '#B5D99C',
           darkgreen: '#0E2E1D',
         },
         animation: {
          'float': 'float 6s ease-in-out infinite',
          'fade-in-up': 'fadeInUp 0.7s ease-out',
          'fade-in': 'fadeIn 1s ease-out',
          'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
         },
         keyframes: {
           float: {
             '0%, 100%': { transform: 'translateY(0)' },
             '50%': { transform: 'translateY(-20px)' },
           },
           fadeInUp: {
             '0%': { opacity: '0', transform: 'translateY(20px)' },
             '100%': { opacity: '1', transform: 'translateY(0)' },
           },
           fadeIn: {
             '0%': { opacity: '0' },
             '100%': { opacity: '1' },
           }
         }
       }
     }
   }
  </script>
  <style>
    .toast-message {
      font-family: 'Arial', sans-serif;
    }
    
    /* Animated form elements */
    .input-animated {
      transition: all 0.3s ease;
    }
    
    .input-animated:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    /* Button pulse animation */
    .btn-pulse {
      position: relative;
      overflow: hidden;
    }
    
    .btn-pulse::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 5px;
      height: 5px;
      background: rgba(255, 255, 255, 0.4);
      opacity: 0;
      border-radius: 100%;
      transform: scale(1, 1) translate(-50%);
      transform-origin: 50% 50%;
    }
    
    .btn-pulse:focus::after {
      animation: pulse 1s ease-out;
    }
    
    @keyframes pulse {
      0% {
        transform: scale(0, 0);
        opacity: 0;
      }
      25% {
        transform: scale(0, 0);
        opacity: 0.1;
      }
      50% {
        transform: scale(0.1, 0.1);
        opacity: 0.3;
      }
      75% {
        transform: scale(0.5, 0.5);
        opacity: 0.5;
      }
      100% {
        transform: scale(1, 1);
        opacity: 0;
      }
    }
    
    /* Gradient background animation */
    .gradient-background {
      background: linear-gradient(135deg, #47663B 0%, #1F4529 100%);
      background-size: 400% 400%;
      animation: gradient 15s ease infinite;
    }
    
    @keyframes gradient {
      0% {
        background-position: 0% 50%;
      }
      50% {
        background-position: 100% 50%;
      }
      100% {
        background-position: 0% 50%;
      }
    }
    
    /* Blob animation */
    .blob {
      position: absolute;
      width: 500px;
      height: 500px;
      background: rgba(181, 217, 156, 0.3);
      border-radius: 50%;
      filter: blur(80px);
      z-index: 0;
      animation: blob-movement 15s infinite alternate;
    }
    
    @keyframes blob-movement {
      0% { transform: translate(0, 0) scale(1); }
      33% { transform: translate(50px, -50px) scale(1.1); }
      66% { transform: translate(-30px, 50px) scale(0.9); }
      100% { transform: translate(20px, -20px) scale(1); }
    }
    
    /* Background pattern */
    .bg-pattern {
      background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23476639' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
    }
    
    /* Enhanced field focus effects */
    .field-focus-effect {
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }
    
    .field-focus-effect:focus {
      border-color: #47663B;
      box-shadow: 0 0 0 3px rgba(71, 102, 59, 0.2);
      background-color: rgba(232, 236, 215, 0.1);
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
    
    // Initialize AOS animation library
    AOS.init({
      duration: 800,
      easing: 'ease-in-out',
      once: true
    });

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
    
    // Add animation for input fields when focused
    const formInputs = document.querySelectorAll('.input-animated');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('scale-105');
            this.parentElement.style.transition = 'all 0.3s ease';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('scale-105');
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Your JavaScript code here
});
</script>

</head>
<body class="bg-pattern">
<div class="flex h-screen">
  <!-- Left side with animation -->
  <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden gradient-background">
    <div class="blob top-[-150px] left-[-100px]"></div>
    <div class="blob bottom-[-150px] right-[-100px]"></div>
    
    <div class="flex flex-col items-center justify-center w-full relative z-10">
      <div class="animate-float" data-aos="fade-up">
        <img src="assets/images/login.gif" alt="Login Animation" class="mx-auto max-w-md rounded-xl shadow-2xl">
      </div>
      <div class="text-white text-center mt-8 px-8" data-aos="fade-up" data-aos-delay="300">
        <h2 class="text-3xl font-bold mb-4">Welcome Back!</h2>
        <p class="text-lg max-w-sm mx-auto">Join our mission to reduce food waste and create a more sustainable future.</p>
      </div>
    </div>
  </div>
  
  <!-- Right side login form -->
  <div class="w-full bg-white lg:w-1/2 flex items-center justify-center relative overflow-hidden">
    <div class="blob opacity-30 top-[-350px] right-[-250px]"></div>
    
    <div class="max-w-md w-full p-8 relative z-10" data-aos="fade-left">
      <div class="text-center mb-8">
        <img src="assets/images/Logo.png" alt="Waste Wise Logo" class="h-16 mx-auto mb-4 animate-pulse-slow">
        <h1 class="text-3xl font-bold mb-2 text-primarycol animate-fade-in">Sign in</h1>
        <p class="text-gray-600 animate-fade-in">Welcome to Bea Bakes: A Food Waste Management Hub System</p>
      </div>
      
      <form method="POST" class="space-y-6 animate-fade-in-up">
        <div class="transition-all duration-300">
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <div class="relative group">
            <input type="email" id="email" name="email" 
                  class="input-animated field-focus-effect pl-10 mt-1 p-3 w-full border rounded-lg focus:outline-none transition-all duration-300" 
                  required>
            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-hover:text-primarycol transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </div>
        
        <div class="transition-all duration-300">
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div class="relative group">
            <input type="password" id="password" name="password" 
                  class="input-animated field-focus-effect pl-10 mt-1 p-3 w-full border rounded-lg focus:outline-none transition-all duration-300" 
                  required>
            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-hover:text-primarycol transition-colors duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <button type="button" 
                  onclick="togglePassword()" 
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 mt-1 text-gray-500 hover:text-primarycol transition-colors duration-300"
                  tabindex="-1">
              <span id="eyeIcon" class="text-gray-700">👁️‍🗨️</span>
            </button>
          </div>
        </div>
        
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <input type="checkbox" id="remember" name="remember" 
                  class="h-4 w-4 text-primarycol focus:ring-primarycol border-gray-300 rounded transition-all duration-300 hover:scale-110">
            <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
          </div>
          <div>
            <a href="auth/forgot_password.php" class="text-sm text-primarycol hover:text-accent hover:underline transition-colors duration-300">
              Forgot your password?
            </a>
          </div>
        </div>
         
        <div>
          <button type="submit" 
                  class="btn-pulse w-full bg-primarycol text-white p-3 rounded-lg hover:bg-accent focus:outline-none transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
            <span class="flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14" />
              </svg>
              Sign In
            </span>
          </button>
        </div>
      </form>
      
      <div class="mt-8 text-center animate-fade-in" data-aos="fade-up" data-aos-delay="400">
        <p class="text-gray-600">Don't have an account? 
          <a href="auth/signup.php" class="text-primarycol font-medium hover:text-accent hover:underline transition-all duration-300">
            Sign up here
            <span class="inline-block transition-transform duration-300 group-hover:translate-x-1">→</span>
          </a>
        </p>
      </div>
      
      <div class="mt-10 pt-6 border-t border-gray-200 text-center text-xs text-gray-500" data-aos="fade-up" data-aos-delay="500">
        <p>© <?php echo date('Y'); ?> Waste Wise. All rights reserved.</p>
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