<?php
// Remove session_start() since it's handled in session_handler.php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Verify token
    if (!isset($_GET['token'])) {
        $_SESSION['error'] = "Invalid reset link.";
        header('Location: /capstone/WASTE-WISE-CAPSTONE/index.php');
        exit();
    }

    $token = $_GET['token'];
    $now = date('Y-m-d H:i:s');

    // Check if token is valid and not expired
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email, u.fname 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.expiry_date > ? AND pr.used = 0
    ");
    $stmt->execute([$token, $now]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $_SESSION['error'] = "This password reset link has expired or is invalid.";
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again later.";
    header('Location: /capstone/WASTE-WISE-CAPSTONE/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Bea Bakes</title>
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
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Left side with image -->
        <div class="hidden lg:flex items-center justify-center flex-1 bg-sec text-black">
            <div class="max-w-md text-center">
                <img src="../assets/images/login.gif" alt="Login Animation" class="w-full">
            </div>
        </div>
        
        <!-- Right side with form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6">
            <div class="max-w-md w-full">
                <h1 class="text-3xl font-semibold mb-2 text-black text-center">Reset Password</h1>
                <p class="text-sm text-gray-600 mb-8 text-center">Please enter your new password</p>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">
                        <p class="text-center"><?= htmlspecialchars($_SESSION['error']) ?></p>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form action="handle_reset_password.php" method="POST" class="space-y-6">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <!-- Password field -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <div class="relative">
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primarycol"
                                   required
                                   minlength="8">
                            <button type="button" 
                                    onclick="togglePassword('password', 'eyeIcon1')"
                                    class="absolute right-2 top-2.5 text-gray-500">
                                <span id="eyeIcon1">👁️‍🗨️</span>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password field -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <div class="relative">
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primarycol"
                                   required
                                   minlength="8">
                            <button type="button" 
                                    onclick="togglePassword('confirm_password', 'eyeIcon2')"
                                    class="absolute right-2 top-2.5 text-gray-500">
                                <span id="eyeIcon2">👁️‍🗨️</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-black text-white p-2 rounded-md hover:bg-primarycol focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primarycol transition-colors duration-300">
                        Reset Password
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <a href="login.php" class="text-sm text-primarycol hover:underline">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '👁️';
            } else {
                input.type = 'password';
                icon.textContent = '👁️‍🗨️';
            }
        }
    </script>
</body>
</html>