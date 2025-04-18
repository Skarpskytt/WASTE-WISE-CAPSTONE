<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us | Waste Wise</title>
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
                <a href="about.php" class="font-medium bg-primarycol text-white rounded-lg">
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
              <a href="about.php" class="font-medium text-accent bg-gray-100 rounded-lg transition-all duration-300 flex items-center space-x-2">
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

  <!-- About Hero Section -->
  <div class="relative min-h-[60vh] overflow-hidden">
    <div class="blob top-[-150px] left-[-100px]"></div>
    <div class="blob bottom-[-150px] right-[-100px]"></div>
    
    <div class="relative min-h-[60vh] overflow-hidden bg-gradient-to-r from-fourth to-primarycol">
      <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/binding-dark.png')] opacity-10"></div>
      
      <div class="container mx-auto px-4 text-center flex flex-col items-center justify-center min-h-[60vh] text-white relative z-10">
        <h1 class="text-5xl md:text-6xl font-bold mb-6" data-aos="fade-down" data-aos-duration="1000">About WasteWise</h1>
        <p class="text-xl max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
          We're on a mission to transform how businesses manage waste, reduce environmental impact, and create sustainable solutions for excess food products.
        </p>
      </div>
    </div>
  </div>

  <!-- Our Story Section -->
  <div class="py-16 bg-white relative overflow-hidden">
    <div class="container mx-auto px-4">
      <div class="flex flex-col md:flex-row items-center gap-12">
        <div class="md:w-1/2" data-aos="fade-right" data-aos-duration="1000">
          <img src="https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Our Story" class="rounded-lg shadow-xl w-full transform hover:scale-105 transition duration-500">
        </div>
        <div class="md:w-1/2" data-aos="fade-left" data-aos-delay="200">
          <h2 class="text-4xl font-bold mb-6 gradient-text">Our Story</h2>
          <p class="text-lg mb-4 text-gray-700">
            WasteWise began with a simple observation: businesses in the food industry were struggling with excess product management, often resulting in unnecessary waste.
          </p>
          <p class="text-lg mb-4 text-gray-700">
            Founded in 2024, our team set out to create a comprehensive solution that would help businesses track, analyze, and optimize their waste management processes while connecting them with NGOs that could benefit from excess food donations.
          </p>
          <p class="text-lg text-gray-700">
            Today, WasteWise serves as a bridge between food businesses and community organizations, transforming potential waste into valuable resources for those in need.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Our Mission Section -->
  <div class="py-16 bg-gradient-to-br from-sec to-white relative overflow-hidden">
    <div class="container mx-auto px-4 text-center">
      <h2 class="text-4xl font-bold mb-8 gradient-text" data-aos="fade-up">Our Mission</h2>
      <p class="text-xl max-w-4xl mx-auto mb-12 text-gray-700" data-aos="fade-up" data-aos-delay="100">
        To empower businesses with intelligent tools to minimize waste, maximize resources, and strengthen community connections through strategic donation partnerships.
      </p>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-white p-8 rounded-xl shadow-lg card-hover" data-aos="fade-up" data-aos-delay="100">
          <div class="bg-gradient-to-r from-primarycol to-fourth w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-4 text-primarycol">Reduce Food Waste</h3>
          <p class="text-gray-700">We help businesses track and analyze excess products to identify patterns and implement strategies that minimize waste from the source.</p>
        </div>
        
        <div class="bg-white p-8 rounded-xl shadow-lg card-hover" data-aos="fade-up" data-aos-delay="200">
          <div class="bg-gradient-to-r from-primarycol to-fourth w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-4 text-primarycol">Connect Communities</h3>
          <p class="text-gray-700">Our platform bridges the gap between businesses with excess food and NGOs serving communities in need, creating meaningful partnerships.</p>
        </div>
        
        <div class="bg-white p-8 rounded-xl shadow-lg card-hover" data-aos="fade-up" data-aos-delay="300">
          <div class="bg-gradient-to-r from-primarycol to-fourth w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-4 text-primarycol">Environmental Impact</h3>
          <p class="text-gray-700">By reducing food waste, we help businesses decrease their carbon footprint and contribute to a more sustainable future for our planet.</p>
        </div>
      </div>
    </div>
  </div>

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

  <!-- Benefits Section -->
  <div id="benefits" class="py-16 bg-gradient-to-r from-fourth to-darkgreen text-white relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/binding-dark.png')] opacity-10"></div>
    
    <div class="container mx-auto px-4 relative z-10">
      <h2 class="text-4xl font-bold text-center mb-12" data-aos="fade-up">Why Choose WasteWise?</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <div class="text-center p-6" data-aos="fade-up" data-aos-delay="100">
          <div class="bg-white rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4 shadow-lg transform transition-transform duration-500 hover:rotate-6 hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-2 text-accent">Cost Savings</h3>
          <p>Reduce operational costs by optimizing inventory management and minimizing waste</p>
        </div>
        
        <div class="text-center p-6" data-aos="fade-up" data-aos-delay="200">
          <div class="bg-white rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4 shadow-lg transform transition-transform duration-500 hover:rotate-6 hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-2 text-accent">Enhanced Reputation</h3>
          <p>Build your brand as an environmentally responsible business committed to sustainability</p>
        </div>
        
        <div class="text-center p-6" data-aos="fade-up" data-aos-delay="300">
          <div class="bg-white rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4 shadow-lg transform transition-transform duration-500 hover:rotate-6 hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-2 text-accent">Data-Driven Insights</h3>
          <p>Make informed decisions with comprehensive analytics and reporting tools</p>
        </div>
        
        <div class="text-center p-6" data-aos="fade-up" data-aos-delay="400">
          <div class="bg-white rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4 shadow-lg transform transition-transform duration-500 hover:rotate-6 hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-fourth" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-2 text-accent">Community Impact</h3>
          <p>Create meaningful connections with local organizations and support those in need</p>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA Section -->
  <div class="bg-gradient-to-br from-third to-white py-20">
    <div class="container mx-auto px-4 text-center" data-aos="fade-up">
      <h2 class="text-4xl md:text-5xl font-bold mb-6 text-primarycol">Join Our Mission</h2>
      <p class="text-xl mb-10 max-w-2xl mx-auto text-gray-700">Become part of the WasteWise community and help us build a more sustainable future for businesses and communities alike.</p>
      <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
        <button class="btn-animated btn bg-primarycol text-white border-none shadow-xl px-8 py-3 text-lg rounded-lg">
          Register Now
        </button>
        <button class="btn bg-transparent border-2 border-primarycol text-primarycol hover:bg-primarycol hover:text-white transition-all duration-300 px-8 py-3 text-lg rounded-lg shadow-md hover:shadow-xl transform hover:scale-105">
          Contact Us
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