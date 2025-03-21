<?php include('../config/session_handler.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/Company Logo.jpg">
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
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full text-center">
        <svg class="mx-auto h-16 w-16 text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <h1 class="text-2xl font-bold text-red-600 mb-4">Unauthorized Access</h1>
        <p class="text-gray-600 mb-6">You do not have permission to access this page.</p>
        <a href="../auth/login.php" class="inline-block bg-primarycol text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition-colors duration-300">
            Return to Login
        </a>
    </div>
</body>
</html>