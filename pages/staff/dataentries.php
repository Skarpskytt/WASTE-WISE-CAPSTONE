<?php ?>
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

<body class="flex h-auto">

<?php include ('../layout/sidebaruser.php' ) ?> 

<div class="grid grid-cols-3 size-auto p-7">
<div>
<h3 class="text-xl font-semibold mb-4">Sales Input</h3>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <form id="salesEntryForm">
        <div class="mb-4">
          <label for="salesDate" class="block text-sm font-medium text-gray-700">Date</label>
          <input type="date" id="salesDate" name="salesDate" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="productName" class="block text-sm font-medium text-gray-700">Product Name</label>
          <input type="text" id="productName" name="productName" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="quantitySold" class="block text-sm font-medium text-gray-700">Quantity Sold</label>
          <input type="number" id="quantitySold" name="quantitySold" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="revenue" class="block text-sm font-medium text-gray-700">Revenue</label>
          <input type="number" id="revenue" name="revenue" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <button type="submit" class="bg-blue-500 text-white p-2 rounded-md">Submit</button>
      </form>
    </div>
</div>
<div>
<h3 class="text-xl font-semibold mb-4">Waste Input</h3>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <form id="wasteEntryForm">
        <div class="mb-4">
          <label for="wasteDate" class="block text-sm font-medium text-gray-700">Date</label>
          <input type="date" id="wasteDate" name="wasteDate" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="wasteProductName" class="block text-sm font-medium text-gray-700">Product Name</label>
          <input type="text" id="wasteProductName" name="wasteProductName" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="quantityWasted" class="block text-sm font-medium text-gray-700">Quantity Wasted</label>
          <input type="number" id="quantityWasted" name="quantityWasted" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="wasteReason" class="block text-sm font-medium text-gray-700">Reason for Waste</label>
          <input type="text" id="wasteReason" name="wasteReason" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <button type="submit" class="bg-red-500 text-white p-2 rounded-md">Submit</button>
      </form>
    </div>
</div>
<div>
<h3 class="text-xl font-semibold mb-4">Inventory Management</h3>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <form id="inventoryUpdateForm">
        <div class="mb-4">
          <label for="ingredientName" class="block text-sm font-medium text-gray-700">Ingredient Name</label>
          <input type="text" id="ingredientName" name="ingredientName" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="quantityUsed" class="block text-sm font-medium text-gray-700">Quantity Used</label>
          <input type="number" id="quantityUsed" name="quantityUsed" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="quantityRemaining" class="block text-sm font-medium text-gray-700">Quantity Remaining</label>
          <input type="number" id="quantityRemaining" name="quantityRemaining" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <div class="mb-4">
          <label for="expirationDate" class="block text-sm font-medium text-gray-700">Expiration Date</label>
          <input type="date" id="expirationDate" name="expirationDate" class="mt-1 p-2 w-full border rounded-md" required>
        </div>
        <button type="submit" class="bg-green-500 text-white p-2 rounded-md">Update</button>
      </form>
    </div>

</div>
</div>




</body> 
</html>


