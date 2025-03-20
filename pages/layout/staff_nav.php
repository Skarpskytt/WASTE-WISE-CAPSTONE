<?php
// Check if user is logged in as staff
if (!isset($_SESSION['role']) || (
    $_SESSION['role'] !== 'branch1_staff' && 
    $_SESSION['role'] !== 'branch2_staff'
)) {
    header("Location: /capstone/WASTE-WISE-CAPSTONE/auth/login.php");
    exit;
}

// Include database connection if not already included
if (!isset($pdo)) {
    include('../../config/db_connect.php');
}

// Get staff notifications
$notificationsQuery = $pdo->prepare("
    SELECT id, message, link, is_read, created_at
    FROM notifications
    WHERE user_id = ? OR target_role = ? OR target_branch_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

// Get the branch ID based on the staff role
$branchId = ($_SESSION['role'] === 'branch1_staff') ? 1 : 2;
$notificationsQuery->execute([$_SESSION['user_id'], $_SESSION['role'], $branchId]);
$notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);

// Get count of unread notifications
$unreadQuery = $pdo->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE is_read = 0 AND (user_id = ? OR target_role = ? OR target_branch_id = ?)
");
$unreadQuery->execute([$_SESSION['user_id'], $_SESSION['role'], $branchId]);
$unreadCount = $unreadQuery->fetchColumn();

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $markReadStmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND (user_id = ? OR target_role = ? OR target_branch_id = ?)
    ");
    $markReadStmt->execute([(int)$_GET['mark_read'], $_SESSION['user_id'], $_SESSION['role'], $branchId]);
    
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
        WHERE (user_id = ? OR target_role = ? OR target_branch_id = ?)
    ");
    $markAllReadStmt->execute([$_SESSION['user_id'], $_SESSION['role'], $branchId]);
    
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
        <a href="staff_dashboard.php" class="flex ms-2 md:me-24">
           <img src="/capstone/WASTE-WISE-CAPSTONE/assets/images/Company Logo.jpg" class="h-8 me-3" alt="WasteWise"/>
           <span class="self-center text-xl font-semibold sm:text-2xl whitespace-nowrap dark:text-white">Bea Bakes</span>
         </a>
        </div>
            <li>
             <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/staff_dashboard.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
               <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
              </svg>                
              <span class="ms-3">Dashboard</span>
             </a>
            </li>
            <li class="menu-title"><span class="font-bold">Product Data Management</span></li>
            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/product_data.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">Add Products</span>
            </a>
            </li>
            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/record_sales.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">Record Sales</span>
            </a>
            </li>

            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/product_stocks.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">Product Stocks</span>
            </a>
            </li>

            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/waste_product_input.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">Record Waste</span>
            </a>
            </li>

            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/waste_product_record.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 0 0 2.25 2.25h.75m0-3H12m-.75 3h.008v.008h-.008v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">View Waste Records</span>
            </a>
            </li>

            <li class="menu-title pt-4"><span class="font-bold">Ingredient Management</span></li>
            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/ingredients.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">Add Ingredients</span>
            </a>
            </li>

            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/ingredients_stocks.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">Ingredient Stocks</span>
            </a>
            </li>

            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/waste_ingredients_input.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">Record Waste</span>
            </a>
            </li>
            <li>
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/waste_ingredients_record.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 0 0 2.25 2.25h.75m0-3H12m-.75 3h.008v.008h-.008v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
              </svg>
              <span class="flex-1 ms-3 whitespace-nowrap">View Waste Records</span>
            </a>
            </li>

          <h3 class="px-2 pt-4 pb-2"><span class="font-bold">Settings</span></h3>
          <li>
             <a href="#" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>

                <span class="flex-1 ms-3 whitespace-nowrap">Settings</span>
             </a>
          </li>
          <li>
             <a href="/capstone/WASTE-WISE-CAPSTONE/auth/logout.php" class="flex self-edn p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
               <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />  
               </svg>

                <span class="flex-1 ms-3 whitespace-nowrap">Logout</span>
             </a>
          </li>
       </ul>
    </div>

 </aside>

 
 
          
<div class="flex-1">
    <div class="px-3 py-3 lg:px-5 lg:pl-3">
      <div class="flex items-center justify-between">
         <div class="flex items-center justify-start rtl:justify-end">
            <button id="toggleSidebar" class="inline-flex items-center p-2 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
                <span class="sr-only">Open sidebar</span>
                <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
                </svg>
            </button>
            
         


</script>
       </div>
       <div class="flex items-center">
           <div class="flex items-center ms-3 gap-4">
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
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors <?= $notification['is_read'] ? '' : 'bg-blue-50/30 dark:bg-blue-800/10' ?>">
                        <a href="<?= $notification['link'] ?>" class="block px-4 py-3 relative">
                            <?php if (!$notification['is_read']): ?>
                                <span class="absolute left-1 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-blue-500 rounded-full"></span>
                            <?php endif; ?>
                            <div class="flex items-start">
                                <div class="mr-3 bg-green-100 text-green-800 rounded-full p-2 flex-shrink-0">
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
                                    <button onclick="markAsRead(<?= $notification['id'] ?>)" class="ml-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline">
                                        Mark read
                                    </button>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
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
            <a href="/capstone/WASTE-WISE-CAPSTONE/pages/staff/all_notifications.php" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline">
                View all notifications
            </a>
        </div>
    </div>
</div>

            <div class="dropdown dropdown-end">
            <div class="dropdown dropdown-end">
               <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                 <div class="w-10 rounded-full">
                   <img
                     alt="Tailwind CSS Navbar component"
                     src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp" />
                 </div>
               </div>
               <ul
                 tabindex="0"
                 class="menu menu-sm dropdown-content bg-base-100 rounded-box z-[1] mt-3 w-52 p-2 shadow">
                 <li>
                   <a href= "/capstone/WASTE-WISE-CAPSTONE/pages/staff/edit_profile_staff.php" class="justify-between">
                     Profile
                     <span class="badge">New</span>
                   </a>
                 </li>
                 <li><a>Settings</a></li>
                 <li><a href="/capstone/WASTE-WISE-CAPSTONE/auth/logout.php">Logout</a></li>
               </ul>
             </div>
             
           </div>
         </div>
     </div>
   </div>
 </nav>
 
 <hr class="bg-sec">
<!-- Replace the existing notification dropdown with this enhanced version -->

<script>
function markAsRead(notificationId) {
    // Create a hidden form and submit it
    fetch('?mark_read=' + notificationId, {
        method: 'GET',
    })
    .then(response => {
        // Refresh the page to update the notification count
        window.location.reload();
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}
</script>