<?php
include('../config/session_handler.php');
use CustomSession\SessionHandler;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
            <h1 class="text-3xl font-semibold mb-6 text-black text-center">Reset Password</h1>
            <h1 class="text-sm font-semibold mb-6 text-gray-500 text-center">Enter your email to receive password reset instructions</h1>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">
                    <p class="text-center"><?= $_SESSION['error'] ?></p>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-4">
                    <p class="text-center"><?= $_SESSION['success'] ?></p>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <form action="process_forgot_password.php" method="POST" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email" 
                           class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" 
                           required>
                </div>
                <div>
                    <button type="submit" 
                            class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">
                        Send Reset Link
                    </button>
                </div>
            </form>
            <div class="mt-4 text-sm text-gray-600 text-center">
                <a href="login.php" class="text-black hover:underline">Back to Login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>