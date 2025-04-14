<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Features | Waste Wise</title>
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
    .feature-icon {
      transition: all 0.3s ease;
    }
    .feature-card:hover .feature-icon {
      transform: scale(1.1) rotate(5deg);
      background-color: #FF8A00;
    }
    .feature-item {
      transition: all 0.3s ease;
    }
    .feature-item:hover {
      transform: translateX(5px);
    }
    .feature-check {
      transition: all 0.3s ease;
    }
    .feature-item:hover .feature-check {
      background-color: #FF8A00;
      transform: scale(1.2);
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
                <a href="features.php" class="font-medium bg-primarycol text-white rounded-lg">
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
              <a href="features.php" class="font-medium text-accent bg-gray-100 rounded-lg transition-all duration-300 flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                <span>Features</span>
              </a>
            </li>
          </ul>
        </div>
        <div class="navbar-end gap-4 mr-6">
          <a href="../auth/login.php" class="btn bg-white hover:bg-primarycol text-primarycol hover:text-white border-2 border-primarycol shadow-md hover:shadow-lg transition-all duration-300 ease-in-out transform hover:scale-105">
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

  <!-- Hero Section -->
  <div class="relative min-h-[50vh] overflow-hidden">
    <div class="blob top-[-150px] left-[-100px]"></div>
    <div class="blob bottom-[-150px] right-[-100px]"></div>
    
    <div class="relative min-h-[50vh] overflow-hidden bg-gradient-to-r from-sec to-white">
      <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/binding-dark.png')] opacity-10"></div>
      
      <div class="container mx-auto px-4 text-center flex flex-col items-center justify-center min-h-[50vh] relative z-10">
        <h1 class="text-5xl md:text-6xl font-bold mb-6 gradient-text" data-aos="fade-down" data-aos-duration="1000">Our Features</h1>
        <p class="text-xl max-w-3xl mx-auto text-gray-700" data-aos="fade-up" data-aos-delay="200">
          Discover the powerful tools and functionality that WasteWise offers to help businesses reduce waste, optimize operations, and make a positive impact on the environment.
        </p>
      </div>
    </div>
  </div>

  <!-- Admin Role Section -->
  <div class="py-16 bg-white relative overflow-hidden">
    <div class="blob top-[-350px] right-[-250px] opacity-30"></div>
    
    <div class="container mx-auto px-4 relative z-10">
      <div class="flex flex-col md:flex-row items-center gap-12">
        <div class="md:w-1/2" data-aos="fade-right" data-aos-duration="1000">
          <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-3 inline-block rounded-full mb-4 shadow-lg feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
            </svg>
          </div>
          <h2 class="text-4xl font-bold mb-4 gradient-text">Admin Dashboard</h2>
          <p class="text-lg mb-8 text-gray-700">Powerful administrative tools to oversee all operations, analyze data, and make informed decisions to optimize your business.</p>
          
          <div class="space-y-6">
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Smart Recommendation System</h3>
                <p class="text-gray-700">AI-powered insights that suggest actions to minimize waste based on historical data and trends.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Branch Performance Overview</h3>
                <p class="text-gray-700">Compare and contrast performance metrics between branches to identify best practices and areas for improvement.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Data Analytics & Reports</h3>
                <p class="text-gray-700">Comprehensive analytics tools with customizable reports to track waste reduction progress and identify patterns.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Donation Management</h3>
                <p class="text-gray-700">Approve and manage donations from branches to partner NGOs, ensuring efficient distribution of excess food.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">User & Partnership Management</h3>
                <p class="text-gray-700">Manage staff accounts and NGO partnerships, with approval workflows for new registration requests.</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="md:w-1/2" data-aos="fade-left" data-aos-delay="200" data-aos-duration="1000">
          <div class="relative rounded-xl overflow-hidden shadow-2xl transform transition-all duration-500 hover:scale-105">
            <div class="absolute inset-0 bg-gradient-to-r from-primarycol/20 to-fourth/20 hover:opacity-0 transition-opacity duration-300"></div>
            <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Admin Dashboard" class="w-full rounded-lg">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Branch Role Section -->
  <div class="py-16 bg-gradient-to-br from-sec to-white relative overflow-hidden">
    <div class="blob bottom-[-350px] left-[-250px] opacity-30"></div>
    
    <div class="container mx-auto px-4 relative z-10">
      <div class="flex flex-col md:flex-row-reverse items-center gap-12">
        <div class="md:w-1/2" data-aos="fade-left" data-aos-duration="1000">
          <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-3 inline-block rounded-full mb-4 shadow-lg feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <h2 class="text-4xl font-bold mb-4 gradient-text">Branch Management</h2>
          <p class="text-lg mb-8 text-gray-700">Streamlined tools for branch managers to track products, monitor excess, and coordinate donations efficiently.</p>
          
          <div class="space-y-6">
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Real-time Excess Product Tracking</h3>
                <p class="text-gray-700">Monitor and record excess pastries and ingredients as they occur, enabling quick decision-making.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Efficient Product Management</h3>
                <p class="text-gray-700">Add new products, manage existing inventory, and track product lifecycles from creation to sale or donation.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Excess Donation Coordination</h3>
                <p class="text-gray-700">Easily tag excess food for donation and track its status through the approval and pickup process.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Sales Recording & Analytics</h3>
                <p class="text-gray-700">Record daily sales data and access visual analytics to understand product performance and customer preferences.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Inventory Management</h3>
                <p class="text-gray-700">Keep track of all product stocks with alerts for low inventory and excess product accumulation.</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="md:w-1/2" data-aos="fade-right" data-aos-delay="200" data-aos-duration="1000">
          <div class="relative rounded-xl overflow-hidden shadow-2xl transform transition-all duration-500 hover:scale-105">
            <div class="absolute inset-0 bg-gradient-to-r from-fourth/20 to-primarycol/20 hover:opacity-0 transition-opacity duration-300"></div>
            <img src="https://images.unsplash.com/photo-1556155092-490a1ba16284?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Branch Management" class="w-full rounded-lg">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- NGO Role Section -->
  <div class="py-16 bg-white relative overflow-hidden">
    <div class="blob top-[-250px] right-[-150px] opacity-30"></div>
    
    <div class="container mx-auto px-4 relative z-10">
      <div class="flex flex-col md:flex-row items-center gap-12">
        <div class="md:w-1/2" data-aos="fade-right" data-aos-duration="1000">
          <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-3 inline-block rounded-full mb-4 shadow-lg feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
            </svg>
          </div>
          <h2 class="text-4xl font-bold mb-4 gradient-text">NGO Partnership</h2>
          <p class="text-lg mb-8 text-gray-700">Dedicated tools for NGO partners to discover available donations, request food items, and coordinate pickups.</p>
          
          <div class="space-y-6">
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Food Donation Visibility</h3>
                <p class="text-gray-700">Browse all available food donations with detailed information about quantity, type, and expiration.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Streamlined Request Process</h3>
                <p class="text-gray-700">Simple interface to request available food items with just a few clicks, making the process quick and efficient.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Donation Status Tracking</h3>
                <p class="text-gray-700">Track the status of all donation requests from pending approval to ready for pickup in real-time.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Pick-up Confirmation</h3>
                <p class="text-gray-700">Digital confirmation system to acknowledge successful pickup of donations, ensuring accountability.</p>
              </div>
            </div>
            
            <div class="flex items-start gap-3 feature-item">
              <div class="bg-gradient-to-r from-primarycol to-fourth text-white p-1 rounded-full mt-1 shadow-md feature-check">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-primarycol">Donation History & Analytics</h3>
                <p class="text-gray-700">Comprehensive history of all received donations with analytics to help plan future needs and distribution.</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="md:w-1/2" data-aos="fade-left" data-aos-delay="200" data-aos-duration="1000">
          <div class="relative rounded-xl overflow-hidden shadow-2xl transform transition-all duration-500 hover:scale-105">
            <div class="absolute inset-0 bg-gradient-to-r from-primarycol/20 to-fourth/20 hover:opacity-0 transition-opacity duration-300"></div>
            <img src="https://images.unsplash.com/photo-1532629345422-7515f3d16bb6?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="NGO Partnership" class="w-full rounded-lg">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA Section -->
  <div class="bg-gradient-to-br from-third to-white py-20 relative overflow-hidden">
    <div class="blob top-[-350px] left-[-150px] opacity-30"></div>
    <div class="blob bottom-[-350px] right-[-150px] opacity-30"></div>
    
    <div class="container mx-auto px-4 text-center relative z-10" data-aos="fade-up">
      <h2 class="text-4xl md:text-5xl font-bold mb-6 text-primarycol">Ready to Start Reducing Waste?</h2>
      <p class="text-xl mb-10 max-w-2xl mx-auto text-gray-700">Join our network of responsible businesses and NGOs making a difference in food waste management and community support.</p>
      <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
        <button class="btn-animated btn bg-primarycol text-white border-none shadow-xl px-8 py-3 text-lg rounded-lg">
          Register Now
        </button>
        <button class="btn bg-transparent border-2 border-primarycol text-primarycol hover:bg-primarycol hover:text-white transition-all duration-300 px-8 py-3 text-lg rounded-lg shadow-md hover:shadow-xl transform hover:scale-105">
          Request Demo
        </button>
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