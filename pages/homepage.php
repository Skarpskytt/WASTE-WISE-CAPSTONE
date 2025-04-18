<!DOCTYPE html>
<html lang="en" style="scroll-behavior: smooth;">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homepage | Waste Wise</title>
  <link rel="icon" type="image/x-icon" href="../assets/images/Logo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="../assets/style/style.css" rel="stylesheet" type="text/css" />
  <!-- AOS Animation Library -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
     tailwind.config = {
     theme: {
       extend: {
         colors: {
           primarycol: '#47663B',
           sec: '#E8ECD7',
           third: '#EED3B1',
           fourth: '#1F4529',
           accent: '#FF8A00',
           lightgreen: '#B5D99C',
           darkgreen: '#0E2E1D',
         },
         fontFamily: {
           'poppins': ['Poppins', 'sans-serif'],
         },
         animation: {
           'fade-in': 'fadeIn 1s ease-in-out',
           'slide-up': 'slideUp 0.7s ease-in-out',
           'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
         },
         keyframes: {
           fadeIn: {
             '0%': { opacity: '0' },
             '100%': { opacity: '1' },
           },
           slideUp: {
             '0%': { transform: 'translateY(30px)', opacity: '0' },
             '100%': { transform: 'translateY(0)', opacity: '1' },
           }
         }
        },
       }
      }
  </script>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
    .gradient-text {
      background: linear-gradient(90deg, #47663B 0%, #1F4529 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .card-hover {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card-hover:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    .btn-animated {
      position: relative;
      overflow: hidden;
      z-index: 1;
    }
    .btn-animated:after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: #1F4529;
      z-index: -2;
    }
    .btn-animated:before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 0%;
      height: 100%;
      background-color: #FF8A00;
      transition: all .3s;
      z-index: -1;
    }
    .btn-animated:hover:before {
      width: 100%;
    }
    .animated-count {
      display: inline-block;
      position: relative;
    }
    .animated-count:after {
      content: '';
      position: absolute;
      width: 100%;
      transform: scaleX(0);
      height: 2px;
      bottom: -5px;
      left: 0;
      background-color: #FF8A00;
      transform-origin: bottom right;
      transition: transform 0.3s ease-out;
    }
    .animated-count:hover:after {
      transform: scaleX(1);
      transform-origin: bottom left;
    }
    .blob {
      position: absolute;
      width: 500px;
      height: 500px;
      background: rgba(181, 217, 156, 0.3);
      border-radius: 50%;
      filter: blur(80px);
      z-index: 0;
      animation: blob-movement 15s infinite alternate;
    }
    @keyframes blob-movement {
      0% { transform: translate(0, 0) scale(1); }
      33% { transform: translate(50px, -50px) scale(1.1); }
      66% { transform: translate(-30px, 50px) scale(0.9); }
      100% { transform: translate(20px, -20px) scale(1); }
    }
  </style>
</head>
<body>
  <header class="sticky top-0 z-50">
    <nav>
      <div class="navbar bg-white bg-opacity-95 backdrop-blur-lg text-black shadow-lg border-b border-gray-200">
        <div class="navbar-start">
          <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-ghost lg:hidden hover:bg-primarycol hover:text-white transition-all duration-300">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-6 w-6"
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
              class="menu menu-sm dropdown-content bg-white rounded-xl z-[1] mt-3 w-52 p-4 shadow-2xl border border-gray-100 space-y-2">
              <li>
                <a href="homepage.php" class="font-medium hover:bg-primarycol hover:text-white rounded-lg transition-all duration-300">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                  </svg>
                  Home
                </a>
              </li>
              <li>
                <a href="about.php" class="font-medium hover:bg-primarycol hover:text-white rounded-lg transition-all duration-300">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  About
                </a>
              </li>
              <li>
                <a href="contact.php" class="font-medium hover:bg-primarycol hover:text-white rounded-lg transition-all duration-300">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  Contact
                </a>
              </li>
              <li>
                <a href="features.php" class="font-medium hover:bg-primarycol hover:text-white rounded-lg transition-all duration-300">
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                  </svg>
                  Features
                </a>
              </li>
            </ul>
          </div>
          <a href="homepage.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-100 transition-all duration-300">
            <img src="../assets/images/Logo.png" class="h-12 animate-pulse-slow" alt="WasteWise Logo" />
            <span class="text-2xl font-bold whitespace-nowrap bg-gradient-to-r from-primarycol to-fourth bg-clip-text text-transparent hover:from-accent hover:to-primarycol transition-all duration-500">Wastewise</span>
          </a>
        </div>
        <div class="navbar-center hidden lg:flex">
          <ul class="menu menu-horizontal px-2 space-x-2">
            <li>
              <a href="homepage.php" class="font-medium hover:text-accent hover:bg-gray-100 rounded-lg transition-all duration-300 flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Home</span>
              </a>
            </li>
            <li>
              <a href="about.php" class="font-medium hover:text-accent hover:bg-gray-100 rounded-lg transition-all duration-300 flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>About</span>
              </a>
            </li>
            <li>
              <a href="contact.php" class="font-medium hover:text-accent hover:bg-gray-100 rounded-lg transition-all duration-300 flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span>Contact</span>
              </a>
            </li>
            <li>
              <a href="features.php" class="font-medium hover:text-accent hover:bg-gray-100 rounded-lg transition-all duration-300 flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                <span>Features</span>
              </a>
            </li>
          </ul>
        </div>
        <div class="navbar-end gap-4 mr-6">
          <a href="../index.php" class="btn bg-white hover:bg-primarycol text-primarycol hover:text-white border-2 border-primarycol shadow-md hover:shadow-lg transition-all duration-300 ease-in-out transform hover:scale-105">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            Login
          </a>
          <a href="../auth/signup.php" class="btn-animated btn bg-primarycol text-white border-none shadow-md hover:shadow-xl transition-all duration-300 ease-in-out transform hover:scale-105">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Sign Up
          </a>
        </div>
      </div>
    </nav>
  </header>

  <div class="flex flex-col lg:flex-row h-screen relative overflow-hidden">
    <div class="blob top-[-150px] left-[-100px]"></div>
    <div class="blob bottom-[-150px] right-[-100px]"></div>
    
    <div class="hero bg-gradient-to-br from-sec to-white h-screen flex-1 relative z-10">
      <div class="hero-content text-center text-black" data-aos="fade-up" data-aos-duration="1000">
        <div class="max-w-md">
          <h1 class="text-6xl font-bold mb-6 gradient-text">Wastewise</h1>
          <p class="py-6 text-lg leading-relaxed">
            An innovative waste management system designed to help businesses efficiently track, analyze, and reduce waste. By leveraging data analytics and reporting,
            the system enables organizations to optimize their waste disposal processes, minimize environmental impact, and achieve sustainability goals.
          </p>
          <a href="#register-company" class="btn bg-primarycol hover:bg-accent text-white border-none shadow-lg transform transition duration-300 hover:scale-105 hover:shadow-xl">
            Get Started
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </a>
        </div>
      </div>
    </div>

    <div class="hidden lg:flex items-center justify-center flex-1 bg-gradient-to-bl from-white to-sec text-black h-screen relative z-10" data-aos="fade-left" data-aos-duration="1200">
      <img src="../assets/images/titlehehe.png" alt="" class="rounded-xl shadow-2xl transform hover:scale-105 transition duration-500 max-w-[80%] max-h-[80%] object-contain">
    </div>
  </div>

  <!-- Features Section -->
  <div class="bg-white py-20">
    <div class="container mx-auto px-4">
      <h2 class="text-4xl md:text-5xl font-bold text-center mb-16 gradient-text" data-aos="fade-up">Our Features</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <!-- Admin Role -->
        <div class="bg-gradient-to-br from-white to-sec rounded-xl p-8 shadow-lg card-hover" data-aos="fade-up" data-aos-delay="100">
          <div class="flex items-center justify-center mb-6">
            <div class="bg-gradient-to-r from-primarycol to-fourth w-20 h-20 rounded-full flex items-center justify-center shadow-lg">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
            </div>
          </div>
          <h3 class="text-2xl font-bold text-center mb-4 text-primarycol">Admin Dashboard</h3>
          <ul class="list-disc pl-6 space-y-3 text-gray-700">
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Smart Recommendation System</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Branch Performance Overview</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Data Analytics & Reports</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Donation Management</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">User & Partnership Management</li>
          </ul>
        </div>
        
        <!-- Branch Role -->
        <div class="bg-gradient-to-br from-white to-sec rounded-xl p-8 shadow-lg card-hover" data-aos="fade-up" data-aos-delay="200">
          <div class="flex items-center justify-center mb-6">
            <div class="bg-gradient-to-r from-primarycol to-fourth w-20 h-20 rounded-full flex items-center justify-center shadow-lg">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
          </div>
          <h3 class="text-2xl font-bold text-center mb-4 text-primarycol">Branch Management</h3>
          <ul class="list-disc pl-6 space-y-3 text-gray-700">
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Real-time Excess Product Tracking</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Efficient Product Management</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Excess Donation Coordination</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Sales Recording & Analytics</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Inventory Management</li>
          </ul>
        </div>
        
        <!-- NGO Role -->
        <div class="bg-gradient-to-br from-white to-sec rounded-xl p-8 shadow-lg card-hover" data-aos="fade-up" data-aos-delay="300">
          <div class="flex items-center justify-center mb-6">
            <div class="bg-gradient-to-r from-primarycol to-fourth w-20 h-20 rounded-full flex items-center justify-center shadow-lg">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
              </svg>
            </div>
          </div>
          <h3 class="text-2xl font-bold text-center mb-4 text-primarycol">NGO Partnership</h3>
          <ul class="list-disc pl-6 space-y-3 text-gray-700">
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Food Donation Visibility</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Streamlined Request Process</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Donation Status Tracking</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Pick-up Confirmation</li>
            <li class="transition-all duration-300 hover:text-accent hover:translate-x-2">Donation History & Analytics</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- How It Works -->
  <div class="bg-gradient-to-r from-fourth to-darkgreen text-white py-20">
    <div class="container mx-auto px-4">
      <h2 class="text-4xl md:text-5xl font-bold text-center mb-16" data-aos="fade-up">How It Works</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <div class="text-center relative group" data-aos="fade-up" data-aos-delay="100">
          <div class="inline-block bg-white rounded-full p-6 mb-6 shadow-lg transform transition-transform duration-500 group-hover:rotate-6 group-hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          
          <div class="absolute top-12 -right-5 hidden md:block text-6xl font-bold text-white opacity-10">1</div>
          
          <h3 class="text-2xl font-bold mb-4 text-accent">Track</h3>
          <p class="text-lg leading-relaxed">Branches record excess food products and ingredients in real-time, creating complete visibility across your organization.</p>
        </div>
        
        <div class="text-center relative group" data-aos="fade-up" data-aos-delay="200">
          <div class="inline-block bg-white rounded-full p-6 mb-6 shadow-lg transform transition-transform duration-500 group-hover:rotate-6 group-hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          
          <div class="absolute top-12 -right-5 hidden md:block text-6xl font-bold text-white opacity-10">2</div>
          
          <h3 class="text-2xl font-bold mb-4 text-accent">Analyze</h3>
          <p class="text-lg leading-relaxed">Our intelligent system analyzes patterns, identifies opportunities, and provides smart recommendations to reduce waste.</p>
        </div>
        
        <div class="text-center relative group" data-aos="fade-up" data-aos-delay="300">
          <div class="inline-block bg-white rounded-full p-6 mb-6 shadow-lg transform transition-transform duration-500 group-hover:rotate-6 group-hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11" />
            </svg>
          </div>
          
          <div class="absolute top-12 -right-5 hidden md:block text-6xl font-bold text-white opacity-10">3</div>
          
          <h3 class="text-2xl font-bold mb-4 text-accent">Donate</h3>
          <p class="text-lg leading-relaxed">Excess food is efficiently donated to partner NGOs, reducing waste and helping communities in need with seamless coordination.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Team Section -->
  <div class="bg-gradient-to-b from-white to-sec py-20">
    <div class="container mx-auto px-4">
      <h2 class="text-4xl md:text-5xl font-bold text-center mb-16 gradient-text" data-aos="fade-up">Meet Our Team</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <!-- Team Member 1 -->
        <div class="bg-white rounded-xl overflow-hidden shadow-xl card-hover" data-aos="fade-up" data-aos-delay="100">
          <div class="relative overflow-hidden">
            <img src="../assets/images/rom.jpg" alt="Team Member" class="w-full h-72 object-cover transition-transform duration-700 hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-0 hover:opacity-70 transition-opacity duration-300 flex items-end">
              <div class="p-4">
              <p class="text-white text-sm">Rom leads the team's management and direction, ensuring clear communication and efficient project delivery while maintaining high quality standards.</p>
              </div>
            </div>
            </div>
            <div class="p-6">
            <h3 class="text-xl font-bold mb-1 text-primarycol">Rom Castro</h3>
            <p class="text-accent font-medium mb-4">Team Manager</p>
            <div class="flex space-x-4">
              <a href="#" class="text-gray-500 hover:text-blue-500 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/>
                </svg>
              </a>
              <a href="#" class="text-gray-500 hover:text-blue-400 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                </svg>
              </a>
              <a href="#" class="text-gray-500 hover:text-blue-600 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/>
                </svg>
              </a>
            </div>
          </div>
        </div>
        
        <!-- Team Member 2 -->
        <div class="bg-white rounded-xl overflow-hidden shadow-xl card-hover" data-aos="fade-up" data-aos-delay="200">
          <div class="relative overflow-hidden">
            <img src="../assets/images/fullstack guy.jpg" alt="Team Member" class="w-full h-72 object-cover transition-transform duration-700 hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-0 hover:opacity-70 transition-opacity duration-300 flex items-end">
              <div class="p-4">
              <p class="text-white text-sm">John leads the development of our platform with expertise in full-stack development and system architecture, ensuring a robust and scalable solution.</p>
              </div>
            </div>
            </div>
            <div class="p-6">
            <h3 class="text-xl font-bold mb-1 text-primarycol">John Jushua B. Chua</h3>
            <p class="text-accent font-medium mb-4">Lead Developer</p>
            <div class="flex space-x-4">
              <a href="#" class="text-gray-500 hover:text-blue-500 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/>
                </svg>
              </a>
              <a href="#" class="text-gray-500 hover:text-blue-400 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                </svg>
              </a>
              <a href="#" class="text-gray-500 hover:text-blue-600 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/>
                </svg>
              </a>
            </div>
          </div>
        </div>
        
        <!-- Team Member 3 -->
        <div class="bg-white rounded-xl overflow-hidden shadow-xl card-hover" data-aos="fade-up" data-aos-delay="300">
          <div class="relative overflow-hidden">
            <img src="../assets/images/rr.jpg" alt="Team Member" class="w-full h-72 object-cover transition-transform duration-700 hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-0 hover:opacity-70 transition-opacity duration-300 flex items-end">
              <div class="p-4">
              <p class="text-white text-sm">Ronrick specializes in system security and data protection, with expertise in implementing robust security measures for web applications.</p>
              </div>
            </div>
            </div>
            <div class="p-6">
            <h3 class="text-xl font-bold mb-1 text-primarycol">Ronrick Furigay</h3>
            <p class="text-accent font-medium mb-4">Security Specialist</p>
            <div class="flex space-x-4">
              <a href="#" class="text-gray-500 hover:text-blue-500 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/>
                </svg>
              </a>
              <a href="#" class="text-gray-500 hover:text-blue-400 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                </svg>
              </a>
              <a href="#" class="text-gray-500 hover:text-blue-600 transition-colors duration-300 transform hover:scale-125">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/>
                </svg>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Impact Stats -->
  <div class="bg-gradient-to-r from-primarycol to-fourth text-white py-20 relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/binding-dark.png')] opacity-10"></div>
    
    <div class="container mx-auto px-4 relative z-10">
      <h2 class="text-4xl md:text-5xl font-bold text-center mb-16" data-aos="fade-up">Our Impact</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-10 text-center">
        <div data-aos="zoom-in" data-aos-delay="100">
          <div class="text-6xl font-bold mb-4 animated-count">8.5K+</div>
          <p class="text-xl text-accent">Meals Donated</p>
        </div>
        
        <div data-aos="zoom-in" data-aos-delay="200">
          <div class="text-6xl font-bold mb-4 animated-count">24</div>
          <p class="text-xl text-accent">Partner NGOs</p>
        </div>
        
        <div data-aos="zoom-in" data-aos-delay="300">
          <div class="text-6xl font-bold mb-4 animated-count">15%</div>
          <p class="text-xl text-accent">Average Waste Reduction</p>
        </div>
        
        <div data-aos="zoom-in" data-aos-delay="400">
          <div class="text-6xl font-bold mb-4 animated-count">12</div>
          <p class="text-xl text-accent">Branches Connected</p>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA Section -->
  <div id="register-company" class="bg-gradient-to-br from-third to-white py-20">
    <div class="container mx-auto px-4 text-center" data-aos="fade-up">
      <h2 class="text-4xl md:text-5xl font-bold mb-6 text-primarycol">Ready to Reduce Waste?</h2>
      <!-- Food Company Application Form -->
      <?php if (isset($_GET['success']) && $_GET['success'] === 'request_submitted'): ?>
        <div class="alert alert-success max-w-md mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span>Thank you! Your request has been submitted. We'll contact you soon.</span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error max-w-md mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span>
            <?php if ($_GET['error'] === 'email_exists'): ?>
              This email is already registered. Please use a different email.
            <?php else: ?>
              There was an error processing your request. Please try again.
            <?php endif; ?>
          </span>
        </div>
      <?php endif; ?>

      <div class="max-w-md mx-auto bg-white p-6 rounded-xl shadow-lg mb-10" data-aos="fade-up" data-aos-delay="100">
        <h3 class="text-2xl font-bold text-primarycol mb-4">Food Companies</h3>
        <p class="text-gray-600 mb-6">Apply to join our waste management system and start making a difference today.</p>
        <form action="../process/subscribe_company.php" method="POST" class="space-y-4">
          <div class="flex flex-col">
            <input type="text" name="company_name" placeholder="Company Name" required 
                   class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" />
          </div>
          <div class="flex flex-col">
            <input type="text" name="contact_person" placeholder="Contact Person Name" required 
                   class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" />
          </div>
          <div class="flex flex-col">
            <input type="email" name="email" placeholder="Business Email" required 
                   class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" />
          </div>
          <div class="flex flex-col">
            <input type="tel" name="phone" placeholder="Contact Number" required
                   class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" />
          </div>
          <div class="flex flex-col">
            <textarea name="description" placeholder="Brief description of your company and waste management needs" rows="3"
                   class="textarea textarea-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300"></textarea>
          </div>
          <button type="submit" class="btn-animated btn bg-primarycol text-white border-none shadow-xl w-full text-lg rounded-lg">
            Subscribe Now
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </button>
        </form>
      </div>
      
   
    </div>
  </div>
  
  <footer class="footer footer-center bg-gradient-to-r from-fourth to-darkgreen text-white rounded p-10">
    <nav class="grid grid-flow-col gap-6" data-aos="fade-up">
      <a href="about.php" class="link link-hover text-lg hover:text-accent transition-colors duration-300">About us</a>
      <a href="contact.php" class="link link-hover text-lg hover:text-accent transition-colors duration-300">Contact</a>
      <a class="link link-hover text-lg hover:text-accent transition-colors duration-300">Jobs</a>
      <a class="link link-hover text-lg hover:text-accent transition-colors duration-300">Press kit</a>
    </nav>
    <nav data-aos="fade-up" data-aos-delay="100">
      <div class="grid grid-flow-col gap-6">
        <a class="bg-white bg-opacity-10 p-3 rounded-full hover:bg-accent transition-colors duration-300 transform hover:scale-110">
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
        <a class="bg-white bg-opacity-10 p-3 rounded-full hover:bg-accent transition-colors duration-300 transform hover:scale-110">
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
        <a class="bg-white bg-opacity-10 p-3 rounded-full hover:bg-accent transition-colors duration-300 transform hover:scale-110">
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
    <aside data-aos="fade-up" data-aos-delay="200">
      <p>Copyright © <?php echo date('Y'); ?> - All rights reserved by WasteWise Inc.</p>
    </aside>
  </footer>
  
  <script>
    // Initialize AOS animations
    document.addEventListener('DOMContentLoaded', function() {
      AOS.init({
        duration: 800,
        easing: 'ease-in-out',
        once: true
      });
    });
  </script>
</body>
</html>