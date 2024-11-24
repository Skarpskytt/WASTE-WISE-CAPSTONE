<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homepage | WasteWise</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
  <link href="../assets/style/style.css" rel="stylesheet" type="text/css" />
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
        },
       }
      }
  </script>
</head>
<body>
  <header>
    <nav>
      <div class="navbar bg-sec text-black">
        <div class="navbar-start">
          <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-ghost lg:hidden">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h8m-8 6h16" />
              </svg>
            </div>
            <ul
              tabindex="0"
              class="menu menu-sm dropdown-content bg-white rounded-box z-[1] mt-3 w-52 p-2 shadow">
              <li><a>Home</a></li>
              <li>
                <a>About</a>
                <ul class="p-2">
                  <li><a>Developers</a></li>
                  <li><a>Benefits</a></li>
                </ul>
              </li>
              <li><a>Contact</a></li>
              <li><a>Features</a></li>
            </ul>
          </div>
          <a href="homepage.php" class="flex ms-2 md:me-24">
            <img src="../assets/images/Logo.png" class="h-8 me-3" alt="WasteWise Logo" />
            <span class="text-xl font-semibold sm:text-2xl whitespace-nowrap dark:text-white">Wastewise</span>
          </a>
        </div>
        <div class="navbar-center hidden lg:flex">
          <ul class="menu menu-horizontal px-1">
            <li><a>Home</a></li>
            <li>
              <details>
                <summary>About us</summary>
                <ul class="p-2 bg-white">
                  <li><a>Developers</a></li>
                  <li><a>Benefits</a></li>
                </ul>
              </details>
            </li>
            <li><a>Contact</a></li>
            <li><a>Features</a></li>
          </ul>
        </div>
        <div class="navbar-end gap-3 mr-5">
          <button class="btn btn-outline"> <a href="../auth/login.php">Login</a></button>
       <button class="btn btn-active btn-neutral text-white hover:bg-transparent hover:text-black"><a href="../auth/signup.php">Signup</a></button>   
        </div>
      </div>
    </nav>
  </header>

  <div class="flex h-auto">
  <div class="hero bg-sec min-h-screen flex-1">
    <div class="hero-content text-center text-black">
      <div class="max-w-md">
        <h1 class="text-6xl font-bold textbal">WasteWise</h1>
        <p class="py-6">
          An innovative waste management system designed to help businesses efficiently track, analyze, and reduce waste. By leveraging data analytics and reporting,
           the system enables organizations to optimize their waste disposal processes, minimize environmental impact, and achieve sustainability goals.
        </p>
        <button class="btn btn-outline text-black hover:border-black border-2">Get Started</button>
      </div>
    </div>
  </div>

  <div class="hidden lg:flex items-center justify-center flex-1 b text-black">
   <img src="../assets/images/hero-donut.jpg" alt="">
  </div>
  
</div>

<div class="flex h-auto">
  <div class="bg-primarycol">
    Mema
  </div>
  
  <div class="bg-sec">
    Mema
  </div>
    </div>
  
  <footer class="footer footer-center bg-sec text-base-content rounded p-10 text-black">
    <nav class="grid grid-flow-col gap-4">
      <a class="link link-hover">About us</a>
      <a class="link link-hover">Contact</a>
      <a class="link link-hover">Jobs</a>
      <a class="link link-hover">Press kit</a>
    </nav>
    <nav>
      <div class="grid grid-flow-col gap-4">
        <a>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            class="fill-current">
            <path
              d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"></path>
          </svg>
        </a>
        <a>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            class="fill-current">
            <path
              d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"></path>
          </svg>
        </a>
        <a>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            class="fill-current">
            <path
              d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"></path>
          </svg>
        </a>
      </div>
    </nav>
    <aside>
      <p>Copyright © {new Date().getFullYear()} - All right reserved by ACME Industries Ltd</p>
    </aside>
  </footer>
</body>
</html>