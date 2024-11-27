<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Table</title>
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

<body class="flex h-screen">
<?php include '../layout/nav.php'?>

  <div class="p-6">
   
    <div class="overflow-x-auto">
      <h2 class="text-2xl font-semibold mb-10">Sales Data</h2>
      <table class="table table-zebra">
        <!-- head -->
        <thead>
          <tr class="bg-sec">
            <th class="flex justify-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
            </th>
            <th>Date</th>
            <th>Product Name</th>
            <th>Quantity Sold</th>
            <th>Revenue</th>
            <th>Inventory Level</th>
            <th>Staff Member</th>
            <th>Comments</th>
          </tr>
        </thead>
        <tbody>
          <!-- row 1 -->
          <tr>
            <td><img src="https://gregoryscoffee.com/cdn/shop/files/chocolate-croissant-gregorys-coffee-538041.jpg?v=1717580418" class="w-8 h-8 mx-auto"></td>
            <td>2023-10-01</td>
            <td>Chocolate Croissant</td>
            <td>50</td>
            <td>$150.00</td>
            <td>200</td>
            <td>John Doe</td>
            <td>High demand in the morning</td>
          </tr>
          <!-- row 2 -->
          <tr>
            <td><img src="https://sugarspunrun.com/wp-content/uploads/2021/05/Best-Blueberry-Muffins-Recipe-1-of-1.jpg" class="w-8 h-8 mx-auto"></td>
            <td>2023-10-01</td>
            <td>Blueberry Muffin</td>
            <td>30</td>
            <td>$90.00</td>
            <td>300</td>
            <td>Jane Smith</td>
            <td>Low stock in the afternoon</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="overflow-x-auto mt-3">
      <h2 class="text-2xl font-semibold mb-10">Waste Data</h2>
      <table class="table table-zebra">
        <!-- head -->
        <thead>
          <tr class="bg-sec">
            <th class="flex justify-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
            </th>
            <th>Date</th>
            <th>Product Name</th>
            <th>Waste Ranking</th>
            <th>Quantity Wasted</th>
            <th>Waste Value</th>
            <th>Staff Member</th>
            <th>Comments</th>
          </tr>
        </thead>
        <tbody>
          <!-- row 1 -->
          <tr>
            <td><img src="https://gregoryscoffee.com/cdn/shop/files/chocolate-croissant-gregorys-coffee-538041.jpg?v=1717580418" class="w-8 h-8 mx-auto"></td>
            <td>2023-10-01</td>
            <td>Chocolate Croissant</td>
            <td>5</td>
            <td>50</td>
            <td>₱400.00</td>
            <td>John Doe</td>
            <td>High demand in the morning</td>
          </tr>
          <!-- row 2 -->
          <tr>
            <td><img src="https://sugarspunrun.com/wp-content/uploads/2021/05/Best-Blueberry-Muffins-Recipe-1-of-1.jpg" class="w-8 h-8 mx-auto"></td>
            <td>2023-10-01</td>
            <td>Blueberry Muffin</td>
            <td>1</td>
            <td>30</td>
            <td>₱300.00</td>
            <td>Jane Smith</td>
            <td>More sells on winter</td>
          </tr>
        </tbody>
      </table>
    <div class="overflow-x-auto">
      <h2 class="text-2xl font-semibold mb-10">Inventory Data</h2>
      <table class="table table-zebra">
        <!-- head -->
        <thead>
          <tr class="bg-sec">
            <th class="flex justify-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
            </th>
            <th>Date</th>
            <th>Product Name</th>
            <th>Price</th>
            <th>Inventory Level</th>
            <th>Staff Member</th>
            <th>Comments</th>
          </tr>
        </thead>
        <tbody>
          <!-- row 1 -->
          <tr>
            <td><img src="https://hips.hearstapps.com/hmg-prod/images/active-dry-baking-yeast-granules-in-wooden-spoon-royalty-free-image-1697143125.jpg" class="w-8 h-8 mx-auto"></td>
            <td>2023-10-01</td>
            <td>Yeast</td>
            <td>₱1,000</td>
            <td>15 grams</td>
            <td>John Doe</td>
            <td>More use when morning</td>
          </tr>
          <!-- row 2 -->
          <tr>
            <td><img src="https://bakerpedia.com/wp-content/uploads/2019/08/Flour_baking-ingredients-e1565912286151.jpg" class="w-8 h-8 mx-auto"></td>
            <td>2023-10-01</td>
            <td>Flour</td>
            <td>₱3,000.00</td>
            <td>20 kilograms</td>
            <td>Jane Smith</td>
            <td>Expiring in 7 days</td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>

</body>
</html>


 
