<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact | Waste Wise</title>
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
    .input-animated {
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }
    .input-animated:focus {
      border-color: #47663B;
      box-shadow: 0 0 0 3px rgba(71, 102, 59, 0.2);
      transform: translateY(-2px);
    }
    .social-icon {
      transition: all 0.3s ease;
    }
    .social-icon:hover {
      transform: translateY(-5px);
      color: #FF8A00;
    }
    .faq-card {
      transition: all 0.3s ease;
    }
    .faq-card:hover {
      background-color: #F7F9EF;
      transform: scale(1.02);
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
                <a href="contact.php" class="font-medium bg-primarycol text-white rounded-lg">
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
              <a href="contact.php" class="font-medium text-accent bg-gray-100 rounded-lg transition-all duration-300 flex items-center space-x-2">
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

  <!-- Contact Hero Section -->
  <div class="relative min-h-[40vh] overflow-hidden">
    <div class="blob top-[-150px] left-[-100px]"></div>
    <div class="blob bottom-[-150px] right-[-100px]"></div>
    
    <div class="relative min-h-[40vh] overflow-hidden bg-gradient-to-r from-fourth to-primarycol">
      <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/binding-dark.png')] opacity-10"></div>
      
      <div class="container mx-auto px-4 text-center flex flex-col items-center justify-center min-h-[40vh] text-white relative z-10">
        <h1 class="text-5xl md:text-6xl font-bold mb-6" data-aos="fade-down" data-aos-duration="1000">Contact Us</h1>
        <p class="text-xl max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
          Have questions about WasteWise? We're here to help! Get in touch with our team and we'll be happy to assist you.
        </p>
      </div>
    </div>
  </div>

  <!-- Contact Form and Info Section -->
  <div class="py-16 bg-white relative overflow-hidden">
    <div class="container mx-auto px-4">
      <div class="flex flex-col md:flex-row gap-12">
        <!-- Contact Form -->
        <div class="md:w-2/3" data-aos="fade-right" data-aos-duration="1000">
          <div class="bg-gradient-to-br from-sec to-white p-8 rounded-xl shadow-xl">
            <h2 class="text-3xl font-bold mb-6 gradient-text">Send Us a Message</h2>
            <form action="#" method="POST">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <label for="name" class="block text-gray-700 mb-2 font-medium">Your Name</label>
                  <input type="text" id="name" name="name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none input-animated" required placeholder="John Doe">
                </div>
                <div>
                  <label for="email" class="block text-gray-700 mb-2 font-medium">Your Email</label>
                  <input type="email" id="email" name="email" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none input-animated" required placeholder="john@example.com">
                </div>
              </div>
              
              <div class="mb-6">
                <label for="subject" class="block text-gray-700 mb-2 font-medium">Subject</label>
                <input type="text" id="subject" name="subject" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none input-animated" required placeholder="How can we help you?">
              </div>
              
              <div class="mb-6">
                <label for="message" class="block text-gray-700 mb-2 font-medium">Your Message</label>
                <textarea id="message" name="message" rows="5" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none input-animated" required placeholder="Type your message here..."></textarea>
              </div>
              
              <button type="submit" class="btn-animated btn bg-primarycol text-white border-none shadow-md hover:shadow-xl transition-all duration-300 ease-in-out transform hover:scale-105 rounded-lg py-3 px-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Send Message
              </button>
            </form>
          </div>
        </div>
        
        <!-- Contact Information -->
        <div class="md:w-1/3" data-aos="fade-left" data-aos-delay="200" data-aos-duration="1000">
          <div class="bg-gradient-to-br from-fourth to-darkgreen text-white p-8 rounded-xl shadow-xl mb-8 transform transition-all duration-500 hover:shadow-2xl">
            <h3 class="text-2xl font-bold mb-6 border-b border-white/30 pb-2">Contact Information</h3>
            
            <div class="mb-6">
              <div class="flex items-start mb-5 group">
                <div class="bg-white/20 rounded-full p-3 mr-4 group-hover:bg-accent transition-all duration-300 transform group-hover:scale-110">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div class="group-hover:translate-x-2 transition-all duration-300">
                  <h4 class="font-bold mb-1 text-accent group-hover:text-white">Address</h4>
                  <p>123 Green Street, Metro Manila, Philippines</p>
                </div>
              </div>
              
              <div class="flex items-start mb-5 group">
                <div class="bg-white/20 rounded-full p-3 mr-4 group-hover:bg-accent transition-all duration-300 transform group-hover:scale-110">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <div class="group-hover:translate-x-2 transition-all duration-300">
                  <h4 class="font-bold mb-1 text-accent group-hover:text-white">Email</h4>
                  <p>info@wastewise.com</p>
                </div>
              </div>
              
              <div class="flex items-start group">
                <div class="bg-white/20 rounded-full p-3 mr-4 group-hover:bg-accent transition-all duration-300 transform group-hover:scale-110">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                </div>
                <div class="group-hover:translate-x-2 transition-all duration-300">
                  <h4 class="font-bold mb-1 text-accent group-hover:text-white">Phone</h4>
                  <p>+63 (2) 8123 4567</p>
                </div>
              </div>
            </div>
            
            <h3 class="text-xl font-bold mb-4 border-b border-white/30 pb-2">Follow Us</h3>
            <div class="flex space-x-4">
              <a href="#" class="social-icon bg-white/10 hover:bg-accent p-3 rounded-full transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/>
                </svg>
              </a>
              <a href="#" class="social-icon bg-white/10 hover:bg-accent p-3 rounded-full transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                </svg>
              </a>
              <a href="#" class="social-icon bg-white/10 hover:bg-accent p-3 rounded-full transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
              </a>
              <a href="#" class="social-icon bg-white/10 hover:bg-accent p-3 rounded-full transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/>
                </svg>
              </a>
            </div>
          </div>
          
          <div class="bg-gradient-to-br from-sec to-white p-8 rounded-xl shadow-xl transform transition-all duration-500 hover:scale-105 hover:shadow-2xl" data-aos="fade-up" data-aos-delay="300">
            <h3 class="text-2xl font-bold mb-4 gradient-text">Business Hours</h3>
            <ul class="space-y-3">
              <li class="flex justify-between items-center border-b border-gray-200 pb-2">
                <span class="font-medium">Monday - Friday:</span> 
                <span class="badge badge-primary bg-primarycol border-none text-white">9:00 AM - 6:00 PM</span>
              </li>
              <li class="flex justify-between items-center border-b border-gray-200 pb-2">
                <span class="font-medium">Saturday:</span> 
                <span class="badge badge-primary bg-primarycol border-none text-white">10:00 AM - 4:00 PM</span>
              </li>
              <li class="flex justify-between items-center">
                <span class="font-medium">Sunday:</span> 
                <span class="badge bg-gray-200 text-gray-700 border-none">Closed</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Map Section -->
  <div class="py-16 bg-gradient-to-b from-white to-sec">
    <div class="container mx-auto px-4">
      <h2 class="text-3xl font-bold text-center mb-8 gradient-text" data-aos="fade-up">Find Us</h2>
      <div class="bg-white p-4 rounded-xl shadow-xl transform transition-all duration-500 hover:shadow-2xl" data-aos="zoom-in" data-aos-delay="100">
        <!-- Replace with actual Google Maps embed code -->
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d123552.9534073005!2d120.9338137066336!3d14.582461692602747!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397ca03571ec38b%3A0x69d1d5751069c11f!2sManila%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1649825137175!5m2!1sen!2sph" width="100%" height="450" style="border:0; border-radius: 0.5rem;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="rounded-lg"></iframe>
      </div>
    </div>
  </div>

  <!-- FAQ Section -->
  <div class="py-16 bg-white">
    <div class="container mx-auto px-4">
      <h2 class="text-3xl font-bold text-center mb-12 gradient-text" data-aos="fade-up">Frequently Asked Questions</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
        <div class="bg-gradient-to-br from-sec to-white p-6 rounded-xl shadow-lg faq-card" data-aos="fade-up" data-aos-delay="100">
          <h3 class="text-xl font-bold mb-3 text-primarycol flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            How do I register my business?
          </h3>
          <p class="text-gray-700">Registration is simple! Click on the "Register Now" button, fill out your business information, and our team will guide you through the setup process.</p>
        </div>
        
        <div class="bg-gradient-to-br from-sec to-white p-6 rounded-xl shadow-lg faq-card" data-aos="fade-up" data-aos-delay="200">
          <h3 class="text-xl font-bold mb-3 text-primarycol flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            What types of businesses can use WasteWise?
          </h3>
          <p class="text-gray-700">WasteWise is designed for food-related businesses including bakeries, restaurants, cafes, grocery stores, and food manufacturing facilities.</p>
        </div>
        
        <div class="bg-gradient-to-br from-sec to-white p-6 rounded-xl shadow-lg faq-card" data-aos="fade-up" data-aos-delay="300">
          <h3 class="text-xl font-bold mb-3 text-primarycol flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            How does the donation process work?
          </h3>
          <p class="text-gray-700">Businesses log excess food items, and NGOs can browse available donations. Once a match is made, our system facilitates the pickup and delivery process.</p>
        </div>
        
        <div class="bg-gradient-to-br from-sec to-white p-6 rounded-xl shadow-lg faq-card" data-aos="fade-up" data-aos-delay="400">
          <h3 class="text-xl font-bold mb-3 text-primarycol flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Is there a cost to use WasteWise?
          </h3>
          <p class="text-gray-700">We offer tiered pricing plans based on business size and needs. Contact our sales team for detailed information about our pricing structure.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Newsletter Section -->
  <div class="bg-gradient-to-r from-fourth to-darkgreen text-white py-16 relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/binding-dark.png')] opacity-10"></div>
    <div class="blob bottom-[-350px] left-[-150px]"></div>
    <div class="blob top-[-350px] right-[-150px]"></div>
    
    <div class="container mx-auto px-4 text-center relative z-10">
      <h2 class="text-3xl font-bold mb-6" data-aos="fade-up">Stay Updated</h2>
      <p class="text-lg mb-8 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">Subscribe to our newsletter to receive the latest updates, tips, and news about WasteWise and sustainable business practices.</p>
      
      <form class="max-w-md mx-auto flex flex-col sm:flex-row gap-4" data-aos="fade-up" data-aos-delay="200">
        <input type="email" placeholder="Your email address" class="flex-grow p-3 rounded-lg focus:outline-none text-black border-2 border-transparent focus:border-accent transition-all duration-300 input-animated" required>
        <button type="submit" class="btn-animated btn bg-accent text-white border-none shadow-md hover:shadow-xl transition-all duration-300 ease-in-out transform hover:scale-105 rounded-lg">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          Subscribe
        </button>
      </form>
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