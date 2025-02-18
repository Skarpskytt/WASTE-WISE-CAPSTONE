<?php
include('../config/session_handler.php');
use CustomSession\SessionHandler;
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
</head>
<body>
<div class="flex h-screen">
  <div class="hidden lg:flex items-center justify-center flex-1 bg-sec text-black">
    <div class="max-w-md text-center">
     <img src="../assets/images/login.gif" alt="Login Animation">
    </div>
  </div>
  
  <div class="w-full bg-white lg:w-1/2 flex items-center justify-center">
    <div class="max-w-md w-full p-6">
      <h1 class="text-3xl font-semibold mb-6 text-black text-center">Sign in</h1>
      <h1 class="text-sm font-semibold mb-6 text-gray-500 text-center">Welcome to Wastewise: A Food Waste Management System</h1>
      
      <?php
      if (isset($_SESSION['error'])) {
          echo '<div class="mb-4 text-red-500">' . $_SESSION['error'] . '</div>';
          unset($_SESSION['error']);
      }
      if (isset($_SESSION['success'])) {
          echo '<div class="mb-4 text-green-500">' . $_SESSION['success'] . '</div>';
          unset($_SESSION['success']);
      }
      ?>
      
      <form action="save_login.php" method="POST" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
          <input type="email" id="email" name="email" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
          <input type="password" id="password" name="password" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
        </div>
        <div class="flex items-center">
          <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-primarycol focus:ring-primarycol border-gray-300 rounded">
          <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
        </div>
        <div>
          <button type="submit" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">Sign In</button>
        </div>
      </form>
      <div class="mt-4 text-sm text-gray-600 text-center">
        <p>Don't have an account? <a href="signup.php" class="text-black hover:underline">Sign up here</a></p>
      </div>
    </div>
  </div>
</div>
</body>
</html>