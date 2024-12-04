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
      session_start();
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
        <div class="relative">
          <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
          <input type="password" id="password" name="password" class="mt-1 p-2 pr-10 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          <span class="absolute inset-y-0 right-3 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" 
             onmouseover="document.getElementById('password').type='text'" 
             onmouseout="document.getElementById('password').type='password'">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          </span>
          <a href="#"
          class="text-xs text-gray-600 hover:text-gray-800 focus:underline">Forgot
          Password?</a>
        </div>
        <div class="flex items-center">
          <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-primarycol focus:ring-sec border-gray-300 rounded">
          <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
        </div>
        <div>
          <button type="submit" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">Sign In</button>
        </div>
      </form>
      <div class="mt-4 text-sm text-gray-600 text-center">
        <p>Don't have an account yet? <a href="../auth/signup.php" class="text-black hover:underline">Signup here</a>
        </p>
      </div>
    </div>
  </div>
</div>

</body>
</html>