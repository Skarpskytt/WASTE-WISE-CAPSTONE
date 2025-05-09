<?php
// Include database connection if not already included
if (!isset($pdo)) {
    include('../../config/db_connect.php');
}

// First check if the user is an admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Get admin notifications (show admin-specific plus high-priority branch notifications)
    $notificationsQuery = $pdo->prepare("
        SELECT id, message, link, is_read, created_at, notification_type, target_branch_id
        FROM notifications
        WHERE (user_id = ? OR target_role = 'admin' OR target_role IS NULL)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $notificationsQuery->execute([$_SESSION['user_id']]);
    $notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);

    // Count unread notifications
    $unreadQuery = $pdo->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE is_read = 0 AND (user_id = ? OR target_role = 'admin' OR target_role IS NULL)
    ");
    $unreadQuery->execute([$_SESSION['user_id']]);
    $unreadCount = $unreadQuery->fetchColumn();
    
    // Group notifications by branch for easier display
    $branch1Notifications = [];
    $branch2Notifications = [];
    $generalNotifications = [];
    
    foreach ($notifications as $notification) {
        if ($notification['target_branch_id'] == 1) {
            $branch1Notifications[] = $notification;
        } else if ($notification['target_branch_id'] == 2) {
            $branch2Notifications[] = $notification;
        } else {
            $generalNotifications[] = $notification;
        }
    }
} else {
    // Non-admin users should not see admin notifications
    $notifications = [];
    $unreadCount = 0;
    $branch1Notifications = [];
    $branch2Notifications = [];
    $generalNotifications = [];
}

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $markReadStmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND (user_id = ? OR target_role = 'admin' OR target_role IS NULL)
    ");
    $markReadStmt->execute([(int)$_GET['mark_read'], $_SESSION['user_id']]);
    
    // Redirect to remove the query parameter
    $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirectUrl");
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $markAllReadStmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE (user_id = ? OR target_role = 'admin' OR target_role IS NULL)
    ");
    $markAllReadStmt->execute([$_SESSION['user_id']]);
    
    // Redirect to remove the query parameter
    $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirectUrl");
    exit;
}
?>

<aside id="sidebar" class="bg-base-100 w-full md:w-64 lg:w-64 h-full border-r border-gray-200 fixed md:relative lg:relative transform -translate-x-full transition-transform duration-300 ease-in-out md:translate-x-0 z-50">
    <div class="h-full px-3 py-4 overflow-y-auto bg-white dark:bg-gray-800">
        <button id="closeSidebar" class="btn btn-ghost block md:hidden lg:hidden">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" class="size-5">
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        <ul class="h-full flex flex-col items-stretch space-y-2 font-small">
            <div class="mb-3">
                <a href="admindashboard.php" class="flex ms-2 md:me-24">
                <img src="../../assets/images/LGU.png" class="h-8 me-3" alt="WasteWise"/>
                    <span class="self-center text-lg font-semibold sm:text-2xl whitespace-nowrap dark:text-white">Admin Panel</span>
                </a>
            </div>
            <li>
                <a href="../admin/admindashboard.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>                
                    <span class="ms-3">Dashboard</span>
                </a>
            </li>

            <li class="menu-title pt-4"><span class="font-bold">Branch Management</span></li>

            <!-- Branches Overview -->
            <li>
                <a href="../admin/branches_product_waste_data.php" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                    <span class="ms-3">All Branches Product Waste</span>
                </a>
            </li>

            <!-- Company Registration Management -->
            <li>
                <a href="../admin/company_requests.php" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>
                    <span class="ms-3">Company Registration Requests</span>
                </a>
            </li>

            <li>
                <a href="../admin/pending_companies.php" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="ms-3">Pending Companies</span>
                    
                    <?php 
                    // Show badge if there are pending companies
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE branch_type = 'company_main' AND approval_status = 'pending'");
                    $stmt->execute();
                    $pendingCount = $stmt->fetchColumn();
                    if ($pendingCount > 0): 
                    ?>
                    <span class="badge badge-accent ml-auto"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
    <a href="../admin/manage_branches.php" class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
        </svg>
        <span class="ms-3">Manage Branches</span>
    </a>
</li>

         

            <!-- Donation Management section -->
            <li class="menu-title pt-4"><span class="font-bold">Donation Management</span></li>
          
            <li>
                <a href="../admin/foods.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        <!-- Pastry icon -->
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25q1.5 0 2.625 1.125T15.75 12q0 1.5-1.125 2.625T12 15.75q-1.5 0-2.625-1.125T8.25 12q0-1.5 1.125-2.625T12 8.25Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12m-6 0a6 6 0 1 0 12 0a6 6 0 1 0-12 0"/>
                    </svg>
                    <span class="flex-1 ms-3 whitespace-nowrap">Foods</span>
                </a>
            </li>
            <li>
                <a href="../admin/donation_history_admin.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75M3 18.75v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                    </svg>
                    <span class="flex-1 ms-3 whitespace-nowrap">Donation Logs</span>
                </a>
            </li>
            <li>
                <a href="../admin/export_donation.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span class="flex-1 ms-3 whitespace-nowrap">Export Reports</span>
                </a>
            </li>

            <li class="menu-title pt-4"><span class="font-bold">User Management</span></li>
            <li>
                <a href="../admin/user.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                    <span class="flex-1 ms-3 whitespace-nowrap">Users</span>
                </a>
            </li>
           

            <h3 class="px-2 pt-4 pb-2"><span class="font-bold">Settings</span></h3>
            
            <li>
                <a href="../admin/settings.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-stone-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span class="flex-1 ms-3 whitespace-nowrap text-stone-600">Settings</span>
                </a>
            </li>

            <li>
                <a href="./../../auth/logout.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-red-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                    </svg>
                    <span class="flex-1 ms-3 whitespace-nowrap text-red-500">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</aside>

 

          
  <div class="flex-1">
  <nav>
   <div class="px-3 py-3 lg:px-5 lg:pl-3">
     <div class="flex items-center justify-between">
       <div class="flex items-center justify-start rtl:justify-end">
         <button  id="toggleSidebar" class="inline-flex items-center p-2 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
            <span class="sr-only">Open sidebar</span>
            <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
               <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
            </svg>
         </button>
         

       </div>
       <div class="flex items-center">
           <div class="flex items-center ms-3 gap-4">
    <!-- Notification Bell with enhanced styling -->
    <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" class="btn btn-ghost btn-circle relative">
            <div class="indicator">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge badge-sm badge-error animate-pulse absolute -top-1 -right-1 px-1.5 py-0.5 text-xs text-white font-bold rounded-full"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-96 p-0 shadow-lg overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b border-gray-200 flex justify-between items-center sticky top-0">
                <h3 class="font-medium text-lg">Notifications</h3>
                <?php if ($unreadCount > 0): ?>
                    <a href="?mark_all_read=1" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline">Mark all read</a>
                <?php endif; ?>
            </div>
            
            <div class="max-h-[70vh] overflow-y-auto">
                <?php if (!empty($branch1Notifications)): ?>
                    <div class="px-4 py-2 bg-blue-50 dark:bg-blue-900/20 border-b border-gray-100">
                        <span class="text-xs font-medium text-blue-800 dark:text-blue-300">Branch 1</span>
                    </div>
                    <?php foreach ($branch1Notifications as $notification): ?>
                        <div class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors <?= $notification['is_read'] ? '' : 'bg-blue-50/30 dark:bg-blue-800/10' ?>">
                            <a href="<?= $notification['link'] ?>" class="block px-4 py-3 relative">
                                <?php if (!$notification['is_read']): ?>
                                    <span class="absolute left-1 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-blue-500 rounded-full"></span>
                                <?php endif; ?>
                                <div class="flex items-start">
                                    <div class="mr-3 bg-blue-100 text-blue-800 rounded-full p-2 flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium mb-0.5 <?= $notification['is_read'] ? 'text-gray-600 dark:text-gray-300' : 'text-gray-900 dark:text-white' ?>">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= date('M d, h:i A', strtotime($notification['created_at'])) ?>
                                        </p>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="?mark_read=<?= $notification['id'] ?>" class="ml-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline">
                                            Mark read
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($branch2Notifications)): ?>
                    <div class="px-4 py-2 bg-green-50 dark:bg-green-900/20 border-b border-gray-100">
                        <span class="text-xs font-medium text-green-800 dark:text-green-300">Branch 2</span>
                    </div>
                    <?php foreach ($branch2Notifications as $notification): ?>
                        <div class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors <?= $notification['is_read'] ? '' : 'bg-green-50/30 dark:bg-green-800/10' ?>">
                            <a href="<?= $notification['link'] ?>" class="block px-4 py-3 relative">
                                <?php if (!$notification['is_read']): ?>
                                    <span class="absolute left-1 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-green-500 rounded-full"></span>
                                <?php endif; ?>
                                <div class="flex items-start">
                                    <div class="mr-3 bg-green-100 text-green-800 rounded-full p-2 flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium mb-0.5 <?= $notification['is_read'] ? 'text-gray-600 dark:text-gray-300' : 'text-gray-900 dark:text-white' ?>">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= date('M d, h:i A', strtotime($notification['created_at'])) ?>
                                        </p>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="?mark_read=<?= $notification['id'] ?>" class="ml-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline">
                                            Mark read
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($generalNotifications)): ?>
                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">General</span>
                    </div>
                    <?php foreach ($generalNotifications as $notification): ?>
                        <div class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors <?= $notification['is_read'] ? '' : 'bg-gray-200/50 dark:bg-gray-600/20' ?>">
                            <a href="<?= $notification['link'] ?>" class="block px-4 py-3 relative">
                                <?php if (!$notification['is_read']): ?>
                                    <span class="absolute left-1 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-gray-500 rounded-full"></span>
                                <?php endif; ?>
                                <div class="flex items-start">
                                    <div class="mr-3 bg-gray-100 text-gray-700 rounded-full p-2 flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium mb-0.5 <?= $notification['is_read'] ? 'text-gray-600 dark:text-gray-300' : 'text-gray-900 dark:text-white' ?>">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= date('M d, h:i A', strtotime($notification['created_at'])) ?>
                                        </p>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="?mark_read=<?= $notification['id'] ?>" class="ml-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline">
                                            Mark read
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (empty($notifications)): ?>
                    <div class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <p class="font-medium">No notifications</p>
                        <p class="text-sm mt-1">You're all caught up!</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-t border-gray-200 text-center">
                <a href="../admin/all_notifications.php" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline">
                    View all notifications
                </a>
            </div>
        </div>
    </div>

    <!-- Profile Dropdown -->
    <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
            <div class="w-10 rounded-full">
                <img alt="Profile" src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp" />
            </div>
        </div>
        <ul tabindex="0" class="menu menu-sm dropdown-content bg-base-100 rounded-box z-[1] mt-3 w-52 p-2 shadow">
            <li>
                <a href="../admin/editprofile.php" class="justify-between">
                    Profile
                    <span class="badge">New</span>
                </a>
            </li>
            <li><a>Settings</a></li>
            <li><a href="./../../auth/logout.php" >Logout</a></li>
        </ul>
    </div>
</div>

         </div>
     </div>
   </div>
 </nav>
 
 <hr class="bg-sec">