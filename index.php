<?php
// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use absolute path for includes
require_once __DIR__ . '/config/session_handler.php';
use CustomSession\SessionHandler;

// Test session functionality
try {
    $pdo = new \PDO('mysql:hos t=localhost;dbname=wastewise', 'root', '');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $session = SessionHandler::getInstance($pdo);
} catch (Exception $e) {
    error_log("Session Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
      
      <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">
            <p class="text-center">
                <?php 
                $error = $_SESSION['error'];
                if (strpos($error, 'NGO account is still pending') !== false) {
                    echo '<span class="font-semibold">Pending Approval:</span> ' . $error;
                } elseif (strpos($error, 'Wrong password') !== false) {
                    echo '<span class="font-semibold">Authentication Error:</span> ' . $error;
                } else {
                    echo $error;
                }
                ?>
            </p>
        </div>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['login_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">
            <p class="text-center">
                <?php 
                $error = $_SESSION['login_error'];
                if (strpos($error, 'Wrong password') !== false) {
                    echo '<span class="font-semibold">Authentication Error:</span> ' . $error;
                } else {
                    echo $error;
                }
                ?>
            </p>
        </div>
        <?php unset($_SESSION['login_error']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-4">
            <p class="text-center"><?= $_SESSION['success'] ?></p>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['pending_message'])): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-md mb-4">
            <p class="text-center">
                <span class="font-semibold">Account Status:</span> 
                <?php echo $_SESSION['pending_message']; ?>
            </p>
        </div>
        <?php unset($_SESSION['pending_message']); ?>
      <?php endif; ?>
      
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