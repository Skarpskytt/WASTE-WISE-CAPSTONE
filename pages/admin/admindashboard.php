<?php
// admindashboard.php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
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
 </script>
   <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body class="flex h-max">
<?php include '../layout/nav.php'?>


<div class="p-8">
<div class="grid grid-cols-4 gap-6 p-8 ">
<div class="stats stats-vertical shadow gap-4 border-4 border-sec">
  <div class="stat">
    <div class="stat-title font-semibold">Sales</div>
    <div class="stat-value text-primarycol">₱10,559.93</div>
    <div class="stat-desc">Jan 1st - Feb 1st</div>
  </div>
  <div class="stat">
    <div class="stat-title">Waste Value</div>
    <div class="stat-value text-primarycol">₱4,220.44</div>
    <div class="stat-desc">Jan 1st - Feb 1st</div>
  </div>
  <div class="stat">
    <div class="stat-title">Production</div>
    <div class="stat-value text-primarycol">80.23</div>
    <div class="stat-desc">Past Month</div>
  </div>

  <div class="stat">
    <div class="stat-title">Product Trend</div>
    <div class="stat-value text-primarycol">Ensaymada</div>
    <div class="stat-desc">This Week</div>
  </div>
</div>

<div class="flex flex-col col-span-3 mb-4 rounded-2xl bg-gry-50 size-full p-4 border-4 border-sec ">
      <h2 class="font-extrabold text-3xl text-primarycol">Sales & Waste Data</h2>
    <?php include '../../charts/areachart.php'?>
    <div>
    <div class="overflow-x-auto">
  <table class="table">
    <!-- head -->
    <thead>
      <tr class="bg-primarycol text-white">
        <th></th>
        <th>Name</th>
        <th>Jan1-Feb1</th>
        <th>Feb1-March1</th>
        <th>April-May1</th>
      </tr>
    </thead>
    <tbody>
      <!-- row 1 -->
      <tr>
        <th>1</th>
        <td>Sales</td>
        <td>0</td>
        <td>6.0</td>
        <td>4.0</td>
      </tr>
      <!-- row 2 -->
      <tr>
        <th>2</th>
        <td>Waste</td>
        <td>7.0</td>
        <td>0</td>
        <td>3.0</td>
      </tr> 
    </tbody>
  </table>
</div>
    </div>
    

</div>
</div>
<div class="grid grid-cols-4 p-8 gap-6 ">
  <div class="flex flex-col mb-4 bg-gray-50 size-full p-6 border-4 rounded-2xl border-sec">
    <h2 class="font-extrabold text-3xl text-primarycol mb-6">Top Loss Reason</h2>
    <?php include '../../charts/piechart.php'?>
    </div>
    <div class="col-span-3 flex flex-col mb-4 bg-gray-50 size-full p-6 rounded-2xl border-4 border-sec">
      <h2 class="font-extrabold text-3xl text-primarycol">Top Wasted Foods</h2>
    <?php include '../../charts/barchart.php'?>
    </div>
</div>
<div class=" size-full p-8">
  <h2 class="font-extrabold text-3xl text-primarycol mb-6">Product Trend</h2>
    <?php include '../../charts/linechart.php'?>
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
  
</div>




</body>
</html>
