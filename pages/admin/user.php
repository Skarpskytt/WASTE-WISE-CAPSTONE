<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management</title>
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
    <h2 class="text-2xl font-semibold mb-10">User Management</h2>
        <div class="stats shadow-2xl ml-7">
          <div class="stat">
            <div class="grid grid-cols-2 place-content-end">
            <div class="stat-title">Active  Staffs</div>
            <div class="stat-figure text-primarycol justify-self-end">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
              </svg>
             </div>
            </div>
           
         <div class="stat-value text-primarycol">18</div> 
         <div class="mt-4"> 
          <!-- You can open the modal using ID.showModal() method -->
        <button class="btn btn-wide bg-primarycol text-white" onclick="my_modal_4.showModal()">Add User</button>
          <dialog id="my_modal_4" class="modal">
        <div class="modal-box w-auto max-w-5xl">
          <h3 class="text-lg font-bold">Hello!</h3>
          <p class="py-4">Click the button below to close</p>
          <div class="mt-4 flex flex-col lg:flex-row items-center justify-between">
          </div>
          <form action="#" method="POST" class="space-y-4">
          <!-- Your form elements go here -->
           <div class="flex flex-row gap-2">
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">First Name</label>
            <input type="text" id="fname" name="fname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Last Name</label>
            <input type="text" id="lname" name="lame" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
        </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="text" id="email" name="email" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Role</label>
            <input type="text" id="role" name="role" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" id="password" name="password" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" id="conpassword" name="conpassword" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
        
          <div>
            <button type="submit" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">Sign Up</button>
          </div>
        </form>
        <div class="modal-action">
          <form method="dialog">
           <button class="btn">Close</button>
          </form>
          </div>
         </div>
        </dialog>
        </div>
        </div>
        
    
</div>


<div class="overflow-x-auto p-6 mt-8">
  <table class="table w-full">
    <thead>
      <tr class="bg-sec">
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Last Login</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>John Doe</td>
        <td>johndoe11@gmail.com</td>
        <td>Cashier</td>
        <td>11/24/2024</td>
        <td class="p-2">
                <div class="flex justify-center">
                <a href="#" class="rounded-md hover:bg-green-100 text-green-600 p-2 flex justify-between items-center">
                    <span><FaEdit class="w-4 h-4 mr-1"/>
                    </span> Edit
                </a>
                <button class="rounded-md hover:bg-red-100 text-red-600 p-2 flex justify-between items-center">
                    <span><FaTrash class="w-4 h-4 mr-1" /></span> Delete
                </button>
                </div>
            </td>
            </tr>
</div>



</body>
</html>
      