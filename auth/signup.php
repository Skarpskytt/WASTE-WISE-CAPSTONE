<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup</title>
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
    <div class="w-full bg-white lg:w-1/2 flex items-center justify-center">
      <div class="max-w-md w-full p-6">
        <h1 class="text-3xl font-semibold mb-6 text-black text-center">Sign Up</h1>
        <h1 class="text-sm font-semibold mb-6 text-gray-500 text-center">Join our community with all-time access and free</h1>
        
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
        
        <form action="save_signup.php" method="POST" class="space-y-4">
          <div class="flex flex-row gap-2">
            <div>
              <label for="fname" class="block text-sm font-medium text-gray-700">First Name</label>
              <input type="text" id="fname" name="fname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
            </div>
            <div>
              <label for="lname" class="block text-sm font-medium text-gray-700">Last Name</label>
              <input type="text" id="lname" name="lname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
            </div>
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" id="email" name="email" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" id="password" name="password" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="conpassword" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" id="conpassword" name="conpassword" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div class="flex gap-2">
            <input type="checkbox" id="terms" name="terms" required>
            <label for="terms" class="text-xs text-gray-600 hover:text-gray-800">Accept Terms and Conditions</label>
          </div>
          <div>
            <button type="submit" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">Sign Up</button>
          </div>
        </form>
        <div class="mt-4 text-sm text-gray-600 text-center">
          <p>Already have an account? <a href="../auth/login.php" class="text-black hover:underline">Login here</a></p>
        </div>
      </div>
    </div>
    <div class="hidden lg:flex items-center justify-center flex-1 bg-sec text-black">
      <div class="max-w-md">
        <img src="../assets/images/isometric-recycling-plastic-and-making-shoes.gif" alt="">
      </div>
    </div>
  </div>
</body>
</html>