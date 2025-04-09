<?php
session_start();
// Enable full error reporting (add at the very top, before other code)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Keep only auth middleware and DB connection
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Debugging: Log session data
error_log("Admin Dashboard - Session data: " . print_r($_SESSION, true));

$pdo = getPDO();

// Maintain the authentication check
checkAuth(['admin']);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Branch Comparison</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primarycol: '#47663B',
                        sec: '#E8ECD7',
                        third: '#EED3B1',
                        fourth: '#1F4529',
                    }
                }
            }
        }
    </script>
</head>

<body class="flex h-screen bg-slate-100">
<?php include '../layout/nav.php' ?>


</body>
</html>
