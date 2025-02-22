<?php
require_once '../../../config/auth_middleware.php';
require_once '../../../config/db_connect.php';

// Check for Branch 1 staff access only
checkAuth(['branch1_staff']);


// ... rest of your staff dashboard code ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Branch 1 Dashboard</title>
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
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('-translate-x-full');
    });

     $('#closeSidebar').on('click', function() {
        $('#sidebar').addClass('-translate-x-full');
    });
});
    function markTaskDone(button) {
      button.parentElement.style.textDecoration = 'line-through';
      button.disabled = true;
    }

 </script>
   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-auto">

<?php include ('../../layout/nav_branch1.php' ) ?> 

<div class="grid grid-rows-2 grid-flow-col gap-5 p-8 rounded-2xl border-4 border-sec mt-4">
  <div class="row-span-3">
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h3 class="text-xl font-semibold mb-4">Daily Tasks</h3>
      <h4 class="text-lg font-semibold mb-2">Production Schedule</h4>
      <ul class="list-disc pl-5">
        <li>
          Task 1 - Prepare ingredients
          <button class="ml-2 bg-primarycol text-white px-2 py-1 rounded" onclick="markTaskDone(this)">Mark as Done</button>
        </li>
        <li>
          Task 2 - Bake bread
          <button class="ml-2 bg-primarycol text-white px-2 py-1 rounded" onclick="markTaskDone(this)">Mark as Done</button>
        </li>
        <li>
          Task 3 - Package products
          <button class="ml-2 bg-primarycol text-white px-2 py-1 rounded" onclick="markTaskDone(this)">Mark as Done</button>
        </li>
        <li>
          Task 4 - Waste goal for today: 50 items
          <button class="ml-2 bg-primarycol text-white px-2 py-1 rounded" onclick="markTaskDone(this)">Mark as Done</button>
        </li>
      </ul>
    </div>
  </div>
  <div class="col-span-1">
  <div class="stats shadow">
  <div class="stat">
    <div class="stat-figure text-black">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
</svg>

    </div>
    <div class="stat-title">Total Revenue Today</div>
    <div class="stat-value text-primarycol">₱2,545.98</div>
  </div>

  <div class="stat">
    <div class="stat-figure text-secondary text-black">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
</svg>

    </div>
    <div class="stat-title">Total Quantity Wasted Today</div>
    <div class="stat-value text-primarycol">250 items</div>
  </div>

  <div class="stat">
    <div class="stat-figure text-secondary">
      <div class="avatar online">
        <div class="w-16 rounded-full">
          <img src="https://img.daisyui.com/images/stock/photo-1534528741775-53994a69daeb.webp" />
        </div>
      </div>
    </div>
    <div class="stat-value text-primarycol">86%</div>
    <div class="stat-title">Tasks done</div>
    <div class="stat-desc text-secondary text-black">4 tasks remaining</div>
  </div>
</div>
  </div>
 
  </div>
  <div class=" size-full p-8 rounded-2xl border-4 border-sec mt-4">
  <h2 class="font-extrabold text-3xl text-primarycol mb-6">Product Trend</h2>
    <?php include '../../../charts/linechart.php'?>
    <div class="overflow-x-auto">
  <table class="table mt-2">
    <!-- head -->
    <thead>
      <tr class="bg-primarycol text-white">
        <th></th>
        <th>Name</th>
        <th>Daily</th>
        <th>Weeks</th>
        <th>Monthly</th>
      </tr>
    </thead>
    <tbody>
      <!-- row 1 -->
      <tr>
        <th>1</th>
        <td>Ensaymada</td>
        <td>80</td>
        <td>30</td>
        <td>21</td>
      </tr>
      <!-- row 2 -->
      <tr>
        <th>2</th>
        <td>Pandesal</td>
        <td>100</td>
        <td>300</td>
        <td>800</td>
      </tr> 
      <tr>
        <th>3</th>
        <td>Muffin</td>
        <td>40</td>
        <td>200</td>
        <td>520</td>
      </tr> 
    </tbody>
  </table>
</div>

</body>
</html>
