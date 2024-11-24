<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Data</title>
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

<?php include '../layout/nav.php' ?>

 <div class="p-7">

  <div>
    <h1 class="text-2xl font-semibold">Product Data</h1>
    <p class="text-gray-500">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
  </div>

  <div class="flex flex-col mx-3 mt-6 lg:flex-row gap-4">
    <div class="w-full lg:w-1/3 m-1">
        <form class="w-full bg-white shadow-xl p-6 border">
            <div class="flex flex-wrap -mx-3 mb-6">
                <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Product Name</label>
                    <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                    id="productname" type="text" name="name" placeholder="Product Name" required />
                </div>
                <div class="flex flex-1">
                  <div class="w-full md:w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Product Price</label>
                    <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
                    id="productprice" type="text" name="name" placeholder="Product Price" required />
                </div>
                <div class="w-full md:w-full px-3 mb-6">
                  <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Product Type</label>
                  <input type="text" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" type="text" 
                   id="producttype" name="name" placeholder="Product Type" required />
              </div>
                </div>
                
                <div class="w-full px-3 mb-6">
                    <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Product Description</label>
                    <textarea textarea rows="4" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                   id="description" type="text" name="description" required > </textarea>
                </div>                        
                
                <div class="w-full md:w-full px-3 mb-6">
                    <button class="appearance-none block w-full bg-green-700 text-gray-100 font-bold border border-gray-200 rounded-lg py-3 px-3 leading-tight 
                    hover:bg-green-600 focus:outline-none focus:bg-white focus:border-gray-500" id="productimage">Add Product</button>
                </div>
                
                <div class="w-full px-3 mb-8">
                    <label class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center" htmlFor="dropzone-file">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>

                    <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Product Image</h2>

                    <p class="mt-2 text-gray-500 tracking-wide">Upload or drag & drop your file SVG, PNG, JPG or GIF. </p>

                    <input id="dropzone-file" type="file" class="hidden" name="category_image" accept="image/png, image/jpeg, image/webp"/>
                    </label>
                </div>
                
            </div>
        </form>
    </div>

    <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border border-gray-200 ">
      <div class="overflow-x-auto">
        <table class="table table-zebra">
          <!-- head -->
          <thead>
            <tr class="bg-sec">
              <th></th>
              <th class="flex justify-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
              </svg>
              </th>
              <th>Product Name</th>
              <th>Description</th>
              <th>Price</th>
              <th>Type</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <!-- row 1 -->
            <tr>
              <th>1</th>
              <td><img src="https://www.juliesbakeshop.com.ph/wp-content/uploads/Ensaymada-Cheese-1.png" class="h-8 w-8 mx-auto"></td>
              <td>Ensaymada</td>
              <td>Bread known for its softness and scroll-like appearance</td>
              <td>₱15.00</td>
              <td>Bread</td>
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
          </tbody>
        </table>
      </div>
  </div>
    
  </div>
 </div>

 <div class="p-7">

  <div>
    <h1 class="text-2xl font-semibold">Ingredient Data</h1>
    <p class="text-gray-500">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
  </div>

  <div class="flex flex-col mx-3 mt-6 lg:flex-row gap-4">
    <div class="w-full lg:w-1/3 m-1">
        <form class="w-full bg-white shadow-xl p-6 border">
          <div class="flex flex-wrap -mx-3 mb-6">
            <div class="w-full md:w-full px-3 mb-6">
                <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Ingredient Name</label>
                <input class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
                id="productname" type="text" name="name" placeholder="Ingredient Name" required />
            </div>
            <div class="flex flex-1">
              <div class="w-full md:w-full px-3 mb-6">
                <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Ingredient Price</label>
                <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]"
                id="productprice" type="text" name="name" placeholder="Ingredient Price" required />
            </div>
            <div class="w-full md:w-full px-3 mb-6">
              <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Ingredient Type</label>
              <input type="text" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" type="text" 
               id="producttype" name="name" placeholder="Ingredient Type" required />
          </div>
            </div>
            
            <div class="w-full px-3 mb-6">
                <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" htmlFor="category_name">Ingredient Description</label>
                <textarea textarea rows="4" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-[#98c01d]" 
               id="description" type="text" name="description" required > </textarea>
            </div>                        
            
            <div class="w-full md:w-full px-3 mb-6">
                <button class="appearance-none block w-full bg-green-700 text-gray-100 font-bold border border-gray-200 rounded-lg py-3 px-3 leading-tight 
                hover:bg-green-600 focus:outline-none focus:bg-white focus:border-gray-500" id="productimage">Add Ingredient</button>
            </div>
            
            <div class="w-full px-3 mb-8">
                <label class="mx-auto cursor-pointer flex w-full max-w-lg flex-col items-center justify-center rounded-xl border-2 border-dashed border-primarycol bg-white p-6 text-center" htmlFor="dropzone-file">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>

                <h2 class="mt-4 text-xl font-medium text-gray-700 tracking-wide">Ingredient Image</h2>

                <p class="mt-2 text-gray-500 tracking-wide">Upload or drag & drop your file SVG, PNG, JPG or GIF. </p>

                <input id="dropzone-file" type="file" class="hidden" name="category_image" accept="image/png, image/jpeg, image/webp"/>
                </label>
            </div>
            
        </div>
        </form>
    </div>

    <div class="w-full lg:w-2/3 m-1 bg-slate-100 shadow-xl text-lg rounded-sm border">
      <div class="overflow-x-auto">
        <table class="table table-zebra">
          <!-- head -->
          <thead>
            <tr class="bg-sec">
              <th></th>
              <th class="flex justify-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
              </svg>
              </th>
              <th>Ingredient Name</th>
              <th>Description</th>
              <th>Price</th>
              <th>Type</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <!-- row 1 -->
            <tr>
              <th>1</th>
              <td><img src="https://www.juliesbakeshop.com.ph/wp-content/uploads/Ensaymada-Cheese-1.png" class="h-8 w-8 mx-auto"></td>
              <td>Gluten Free Flour</td>
              <td>a finely ground powder</td>
              <td>₱699.75</td>
              <td>Flour</td>
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
          </tbody>
        </table>
      </div>
  </div>
    
  </div>
 </div>
 
 

 



</body>
</html>
