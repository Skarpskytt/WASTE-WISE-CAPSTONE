<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

// Fetch the user's name from the session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$branchId = $_SESSION['branch_id'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingredient Waste Tracking - WasteWise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        $(document).ready(function() {
            // Sidebar toggling
            $('#toggleSidebar').on('click', function() {
                $('#sidebar').toggleClass('-translate-x-full');
            });

            $('#closeSidebar').on('click', function() {
                $('#sidebar').addClass('-translate-x-full');
            });
            
            // Auto-hide notification after 3 seconds
            setTimeout(function() {
                $('.notification').fadeOut();
            }, 3000);
        });
    </script>

    <style>
        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
        }
        
        .notification-success {
            background-color: #47663B;
        }
        
        .notification-error {
            background-color: #ef4444;
        }
        
        /* Form section styling */
        .form-section {
            @apply bg-white p-4 rounded-lg shadow mb-4;
        }
        
        .form-section-title {
            @apply text-lg font-semibold mb-3 text-gray-800 border-b pb-2;
        }
    </style>
</head>

<body class="flex min-h-screen bg-gray-50">

    <?php include(__DIR__ . '/../layout/staff_nav.php'); ?>
