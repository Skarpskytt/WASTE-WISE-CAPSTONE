<?php
session_start();
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <div class="hidden lg:flex items-center justify-center flex-1 bg-sec text-black">
            <div class="max-w-md text-center">
                <img src="../assets/images/login.gif" alt="Login Animation">
            </div>
        </div>
        
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6">
            <div class="max-w-md w-full">
                <h1 class="text-3xl font-semibold mb-6 text-black text-center">Verify OTP</h1>
                <p class="text-sm text-gray-600 mb-8 text-center">A verification code has been sent to your email</p>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="verify_otp_process.php" method="POST" class="space-y-6">
                    <div>
                        <input type="text" name="otp" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-sec"
                               placeholder="Enter 6-digit OTP"
                               required
                               pattern="[0-9]{6}"
                               maxlength="6">
                    </div>
                    <button type="submit" 
                            class="w-full bg-black text-white py-2 rounded-lg hover:bg-sec hover:text-black transition-colors">
                        Verify OTP
                    </button>
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">Didn't receive the code?</p>
                        <button type="button" 
                                id="resendOTP"
                                onclick="window.location.href='resend_otp.php'"
                                class="text-black hover:text-sec text-sm font-medium">
                            Resend OTP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>