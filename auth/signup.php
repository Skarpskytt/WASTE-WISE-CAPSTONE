<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../config/app_config.php';
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';

use CustomSession\SessionHandler;
use function CustomSession\initSession;

// Get database connection
$pdo = getPDO();

// Initialize session with our custom handler
initSession($pdo);

// Get session handler instance
$session = SessionHandler::getInstance($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup</title>
  <link rel="icon" type="image/x-icon" href="../assets/images/Company Logo.jpg">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primarycol: '#47663B',
            sec: '#E8ECD7',
          }
        }
      }
    }
  </script>
  <!-- Toast notification script -->
  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <script src="https://cdn.jsdelivr.net/npm/face-api.js"></script>

  <style>
    .toast-message {
      font-family: 'Arial', sans-serif;
      font-weight: 500;
      font-size: 14px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      border-radius: 6px;
      max-width: 350px;
    }
    
    .password-requirements {
      font-size: 12px;
      color: #6B7280;
      margin-top: 4px;
    }
    
    .requirement {
      display: flex;
      align-items: center;
      margin-bottom: 2px;
    }
    
    .requirement-icon {
      margin-right: 5px;
      font-size: 10px;
      display: inline-block;
      width: 16px;
    }
    
    .requirement-met {
      color: #10B981;
    }
    
    .requirement-unmet {
      color: #6B7280;
    }

    .file-upload-area {
      min-height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Wider form container */
    .form-container {
      max-width: 1000px;
      width: 90%;
      margin: 0 auto;
      padding: 1.5rem;
    }

    /* Input group for side-by-side fields */
    .input-group {
      display: grid;
      gap: 1rem;
    }

    @media (min-width: 640px) {
      .input-group {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    /* Remove the form-side class */
    @media (min-width: 1024px) {
      /* Remove the width constraint that was for the split layout */
      /* .form-side {
        width: 60%;
      }
      
      .image-side {
        flex: 0 0 40%;
      } */
    }

    /* Better spacing for password requirements */
    .password-req-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.5rem;
    }

    /* Terms checkbox alignment */
    .terms-checkbox {
      align-items: flex-start;
    }

    /* Responsive adjustments */
    @media (max-width: 639px) {
      .password-req-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-height: 800px) {
      .form-container {
        font-size: 0.95rem;
      }
      
      .form-container h1.text-3xl {
        font-size: 1.5rem;
      }
    }

    /* Make form elements more compact */
    .space-y-4.md\:space-y-6 {
      gap: 0.75rem;
    }

    /* Reduce padding on verification documents section */
    .verification-section {
      padding: 0.75rem;
    }

    /* Fix conflicting camera container settings */
    #camera-container {
      height: 200px;
      width: 200px; 
      margin: 0 auto; 
      position: relative;
      border-radius: 8px;
    }

    #video, #canvas {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 8px;
    }

    #face-detection-box {
      position: absolute;
      border: 2px solid #10B981;
      border-radius: 4px;
      display: none;
      z-index: 10;
    }
  </style>
</head>
<body>
  <div class="flex min-h-screen">
    <!-- Form (now full width) with sec background -->
    <div class="w-full bg-sec flex items-center justify-center py-2">
      <div class="form-container w-full max-w-4xl">
        <h1 class="text-2xl md:text-3xl font-semibold mb-2 text-black text-center">Sign Up</h1>
        <h1 class="text-sm md:text-base font-semibold mb-6 text-gray-500 text-center">Join our community with all-time access and free</h1>
        
        <form action="save_signup.php" method="POST" enctype="multipart/form-data" class="space-y-4 md:space-y-6">
          
          <!-- Personal Information Section -->
          <div class="input-group">
            <div>
              <label for="fname" class="block text-sm font-medium text-gray-700">First Name</label>
              <input type="text" id="fname" name="fname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
            </div>
            <div>
              <label for="lname" class="block text-sm font-medium text-gray-700">Last Name</label>
              <input type="text" id="lname" name="lname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
            </div>
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
              <input type="email" id="email" name="email" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
            </div>
            <div>
              <label for="role" class="block text-sm font-medium text-gray-700">Select Role</label>
              <select id="role" name="role" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
                <option value="">Select a role</option>
                <?php
                // Fetch branches from database
                $stmt = $pdo->query('SELECT id, name FROM branches ORDER BY id');
                while ($branch = $stmt->fetch()) {
                  // Create staff option for each branch
                  echo '<option value="branch' . htmlspecialchars($branch['id']) . '_staff">Staff (' . 
                       htmlspecialchars($branch['name']) . ')</option>';
                }
                ?>
                <option value="ngo">NGO Partner</option>
              </select>
            </div>
          </div>
          
          <!-- Password Section -->
          <div class="input-group">
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
              <div class="relative">
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" 
                       required
                       onkeyup="checkPasswordStrength(this.value)">
                <button type="button" 
                        onclick="togglePassword('password', 'eyeIcon1')"
                        class="absolute right-2 top-2.5 text-gray-500">
                    <span id="eyeIcon1">👁️‍🗨️</span>
                </button>
              </div>
              <div id="password-strength" class="mt-1 text-xs"></div>
            </div>
            <div>
              <label for="conpassword" class="block text-sm font-medium text-gray-700">Confirm Password</label>
              <div class="relative">
                <input type="password" 
                       id="conpassword" 
                       name="conpassword" 
                       class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" 
                       required
                       onkeyup="checkPasswordMatch()">
                <button type="button" 
                        onclick="togglePassword('conpassword', 'eyeIcon2')"
                        class="absolute right-2 top-2.5 text-gray-500">
                    <span id="eyeIcon2">👁️‍🗨️</span>
                </button>
              </div>
              <div id="password-match" class="mt-1 text-xs"></div>
            </div>
          </div>
          
          <!-- Password Requirements -->
          <div class="password-requirements">
            <p class="font-medium text-xs text-gray-700 mb-1">Password must:</p>
            <div class="password-req-grid">
              <div class="requirement" id="length-check">
                <i class="requirement-icon">⭕</i>
                <span>Be at least 8 characters long</span>
              </div>
              <div class="requirement" id="case-check">
                <i class="requirement-icon">⭕</i>
                <span>Include uppercase and lowercase letters</span>
              </div>
              <div class="requirement" id="number-check">
                <i class="requirement-icon">⭕</i>
                <span>Include at least one number</span>
              </div>
              <div class="requirement" id="special-check">
                <i class="requirement-icon">⭕</i>
                <span>Include at least one special character</span>
              </div>
            </div>
          </div>
          
          <!-- NGO Fields (hidden by default) -->
          <div id="ngo-fields" class="hidden space-y-4">
            <div class="input-group">
              <div>
                <label for="org_name" class="block text-sm font-medium text-gray-700">Organization Name</label>
                <input type="text" id="org_name" name="org_name" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300">
              </div>
              <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Contact Phone</label>
                <input type="tel" id="phone" name="phone" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300">
              </div>
            </div>
            <div>
              <label for="address" class="block text-sm font-medium text-gray-700">Organization Address</label>
              <textarea id="address" name="address" rows="3" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300"></textarea>
            </div>
          </div>
          
          <!-- Verification Documents Section - more compact -->
          <div class="space-y-3 p-3 border border-gray-200 rounded-md bg-gray-50 verification-section">
            <h3 class="font-medium text-gray-700 text-sm">Verification Documents</h3>
            
            <!-- Government ID Upload -->
            <div>
              <label for="gov_id" class="block text-sm font-medium text-gray-700">Upload Government ID</label>
              <div class="mt-1 file-upload-area px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                <div class="space-y-1 text-center">
                  <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <div class="flex text-sm text-gray-600 justify-center">
                    <label for="gov_id" class="relative cursor-pointer bg-white rounded-md font-medium text-primarycol hover:text-primarycol/80">
                      <span>Upload ID</span>
                      <input id="gov_id" name="gov_id" type="file" accept="image/*,.pdf" class="sr-only" required>
                    </label>
                    <p class="pl-1">or drag and drop</p>
                  </div>
                  <p class="text-xs text-gray-500">Valid government ID (PNG, JPG, PDF up to 5MB)</p>
                </div>
              </div>
              <div id="gov_id_preview" class="mt-2 hidden">
                <div class="flex items-center p-2 bg-white rounded-md border">
                  <span id="gov_id_filename" class="text-sm text-gray-700 flex-grow"></span>
                  <button type="button" onclick="clearFileInput('gov_id')" class="text-red-500 hover:text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </div>  
              </div>
            </div>
            
            <!-- Selfie Capture -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Take a Selfie</label>
              <div class="mt-1">
                <!-- Camera feed will be shown here -->
                <div id="camera-container" class="bg-gray-200 rounded-md overflow-hidden relative" style="height: 150px;">
                  <video id="video" class="w-full h-full object-cover hidden" autoplay playsinline></video>
                  <canvas id="canvas" class="w-full h-full object-cover hidden"></canvas>
                  <div id="start-camera" class="absolute inset-0 flex items-center justify-center bg-gray-200 cursor-pointer">
                    <div class="text-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      <p class="mt-1 text-sm text-gray-500">Click to activate camera</p>
                    </div>
                  </div>
                </div>
                <!-- Hidden input to store the captured image -->
                <input type="hidden" name="selfie_data" id="selfie_data">
                <div class="mt-2 flex justify-center space-x-2">
                  <button type="button" id="capture-button" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-black hover:bg-sec hover:text-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" style="display:none;">
                    Take Photo
                  </button>
                  <button type="button" id="retake-button" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" style="display:none;">
                    Retake
                  </button>
                </div>
                <div id="selfie-status" class="mt-1 text-xs text-gray-500"></div>
              </div>
            </div>
          </div>
          
          <!-- Terms and Submit -->
          <div class="terms-checkbox flex gap-2 justify-start items-start">
            <input type="checkbox" id="terms" name="terms" required class="mt-1">
            <label for="terms" class="text-xs text-gray-600 hover:text-gray-800">Accept <span id="showTerms" class="text-black cursor-pointer underline hover:text-gray-800">Terms and Conditions</span></label>
          </div>
          <div>
            <button type="submit" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec focus:outline-none focus:bg-sec focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300 hover:text-black">Sign Up</button>
          </div>
        </form>
        <div class="mt-4 text-sm text-gray-600 text-center">
          <p>Already have an account? <a href="../index.php" class="text-black hover:underline">Login here</a></p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Terms and Conditions Modal -->
  <div id="termsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[80vh] flex flex-col">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">Terms and Conditions</h3>
        <button id="closeTerms" class="text-gray-400 hover:text-gray-500">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-6 overflow-y-auto">
        <div class="space-y-4 text-sm">
          <h4 class="font-bold">1. Introduction</h4>
          <p>Welcome to Waste-Wise. By using our service, you agree to these terms and conditions.</p>
          
          <h4 class="font-bold">2. User Responsibilities</h4>
          <p>Users are responsible for providing accurate information during registration and when using the platform.</p>
          
          <h4 class="font-bold">3. Data Privacy</h4>
          <p>We collect and process personal data in accordance with our Privacy Policy. By accepting these terms, you consent to our data practices.</p>
          
          <h4 class="font-bold">4. Account Security</h4>
          <p>Users are responsible for maintaining the confidentiality of their account credentials and for all activities that occur under their account.</p>
          
          <h4 class="font-bold">5. Prohibited Activities</h4>
          <p>Users may not use the service for any illegal purposes or in any manner that could damage, disable, or impair the service.</p>
          
          <h4 class="font-bold">6. Termination</h4>
          <p>We reserve the right to terminate or suspend access to our service immediately, without prior notice, for conduct that we believe violates these Terms or is harmful to other users or us.</p>
        </div>
      </div>
      <div class="p-4 border-t">
        <button id="acceptTerms" class="w-full bg-black text-white p-2 rounded-md hover:bg-sec hover:text-black transition-colors duration-300">Accept Terms</button>
      </div>
    </div>
  </div>
  
  <script>
    // Password toggle function
    function togglePassword(inputId, iconId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = '👁️';
      } else {
        input.type = 'password';
        icon.textContent = '👁️‍🗨️';
      }
    }

    // Password strength checking function
    function checkPasswordStrength(password) {
      // Check various requirements
      const lengthCheck = password.length >= 8;
      const caseCheck = password.match(/[a-z]/) && password.match(/[A-Z]/);
      const numberCheck = password.match(/[0-9]/);
      const specialCheck = password.match(/[^a-zA-Z0-9]/);
      
      // Update visual indicators for each requirement
      updateRequirement('length-check', lengthCheck);
      updateRequirement('case-check', caseCheck);
      updateRequirement('number-check', numberCheck);
      updateRequirement('special-check', specialCheck);
      
      // Update overall strength message
      const strengthEl = document.getElementById('password-strength');
      if(strengthEl) {
        if (password.length === 0) {
          strengthEl.textContent = '';
        } else if (lengthCheck && caseCheck && numberCheck && specialCheck) {
          strengthEl.textContent = 'Strong password';
          strengthEl.className = 'mt-1 text-xs text-green-600';
        } else if ((lengthCheck && caseCheck) || (lengthCheck && numberCheck) || (numberCheck && caseCheck)) {
          strengthEl.textContent = 'Medium strength password';
          strengthEl.className = 'mt-1 text-xs text-yellow-600';
        } else {
          strengthEl.textContent = 'Weak password';
          strengthEl.className = 'mt-1 text-xs text-red-600';
        }
      }
    }

    // Update individual requirement indicators
    function updateRequirement(id, isValid) {
      const el = document.getElementById(id);
      if(el) {
        const icon = el.querySelector('.requirement-icon');
        if(icon) {
          if (isValid) {
            icon.textContent = '✅';
            icon.className = 'requirement-icon requirement-met';
            el.className = 'requirement requirement-met';
          } else {
            icon.textContent = '⭕';
            icon.className = 'requirement-icon requirement-unmet';
            el.className = 'requirement';
          }
        }
      }
    }

    // Check if passwords match
    function checkPasswordMatch() {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('conpassword').value;
      const matchEl = document.getElementById('password-match');
      
      if (matchEl) {
        if (confirmPassword.length === 0) {
          matchEl.textContent = '';
        } else if (password === confirmPassword) {
          matchEl.textContent = 'Passwords match';
          matchEl.className = 'mt-1 text-xs text-green-600';
        } else {
          matchEl.textContent = 'Passwords do not match';
          matchEl.className = 'mt-1 text-xs text-red-600';
        }
      }
    }

    // Clear file input
    function clearFileInput(id) {
      document.getElementById(id).value = '';
      document.getElementById(id + '_preview').classList.add('hidden');
    }

    // Wait for DOM to be loaded before adding event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Form validation
      const form = document.querySelector('form');
      const passwordInput = document.getElementById('password');
      const confirmPasswordInput = document.getElementById('conpassword');
      
      // Initialize requirement checks if password field exists
      if (passwordInput) {
        checkPasswordStrength(passwordInput.value);
      }
      
      // Validate form before submission
      if (form) {
        form.addEventListener('submit', function(event) {
          const password = passwordInput.value;
          const confirmPassword = confirmPasswordInput.value;
          
          // Check if passwords match
          if (password !== confirmPassword) {
            event.preventDefault();
            Toastify({
              text: "❌ Passwords do not match",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444",
              stopOnFocus: true
            }).showToast();
            return;
          }
          
          // Check password complexity
          const lengthCheck = password.length >= 8;
          const caseCheck = password.match(/[a-z]/) && password.match(/[A-Z]/);
          const numberCheck = password.match(/[0-9]/);
          const specialCheck = password.match(/[^a-zA-Z0-9]/);
          
          if (!(lengthCheck && caseCheck && numberCheck && specialCheck)) {
            event.preventDefault();
            Toastify({
              text: "❌ Password does not meet complexity requirements",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444",
              stopOnFocus: true
            }).showToast();
          }
        });
      }

      // Role selector functionality
      const roleSelect = document.getElementById('role');
      if (roleSelect) {
        roleSelect.addEventListener('change', function() {
          const ngoFields = document.getElementById('ngo-fields');
          
          // Hide all conditional fields first
          ngoFields.classList.add('hidden');
          
          // Make all NGO fields not required by default
          document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = false);
          
          // Show relevant fields based on selection
          if (this.value === 'ngo') {
            // Show and require NGO fields
            ngoFields.classList.remove('hidden');
            document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = true);
          }
        });
      }

      // Terms and Conditions Modal functionality
      const showTerms = document.getElementById('showTerms');
      const closeTerms = document.getElementById('closeTerms');
      const acceptTerms = document.getElementById('acceptTerms');
      const termsModal = document.getElementById('termsModal');
      
      if (showTerms) {
        showTerms.addEventListener('click', function() {
          termsModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
        });
      }
      
      if (closeTerms) {
        closeTerms.addEventListener('click', function() {
          termsModal.classList.add('hidden');
          document.body.style.overflow = 'auto'; // Re-enable scrolling
        });
      }
      
      if (acceptTerms) {
        acceptTerms.addEventListener('click', function() {
          document.getElementById('terms').checked = true;
          termsModal.classList.add('hidden');
          document.body.style.overflow = 'auto'; // Re-enable scrolling
        });
      }
      
      // Close modal when clicking outside
      if (termsModal) {
        termsModal.addEventListener('click', function(event) {
          if (event.target === this) {
            this.classList.add('hidden');
            document.body.style.overflow = 'auto';
          }
        });
      }

      // Government ID file input change event
      const govIdInput = document.getElementById('gov_id');
      if (govIdInput) {
        govIdInput.addEventListener('change', function() {
          const file = this.files[0];
          if (file) {
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
              Toastify({
                text: "❌ File size exceeds 5MB limit",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "center",
                backgroundColor: "#EF4444"
              }).showToast();
              this.value = '';
              return;
            }
            
            document.getElementById('gov_id_filename').textContent = file.name;
            document.getElementById('gov_id_preview').classList.remove('hidden');
          } else {
            document.getElementById('gov_id_preview').classList.add('hidden');
          }
        });
      }

      // Selfie Capture Implementation
      const startButton = document.getElementById('start-camera');
      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      const captureButton = document.getElementById('capture-button');
      const retakeButton = document.getElementById('retake-button');
      const selfieData = document.getElementById('selfie_data');
      const selfieStatus = document.getElementById('selfie-status');
      let stream = null;
      
      // Check if camera is actually available before attempting access
      async function checkCameraAvailability() {
        try {
          const devices = await navigator.mediaDevices.enumerateDevices();
          const videoDevices = devices.filter(device => device.kind === 'videoinput');
          return videoDevices.length > 0;
        } catch (e) {
          console.error('Error checking camera availability:', e);
          return false;
        }
      }

      // Single camera access event listener
      if (startButton) {
        startButton.addEventListener('click', async function() {
          try {
            // First check if camera is available
            const cameraAvailable = await checkCameraAvailability();
            if (!cameraAvailable) {
              throw new Error('No camera detected on your device. Please connect a camera and try again.');
            }
            
            // Clear previous errors
            selfieStatus.textContent = 'Starting camera...';
            selfieStatus.className = 'mt-1 text-xs text-gray-500';
            
            console.log('Attempting to access camera...');
            
            // Check for camera support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
              throw new Error('Camera API not supported in this browser');
            }
            
            // Request camera access with explicit error handling
            try {
              stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                  width: { ideal: 200 },  // Reduced size
                  height: { ideal: 200 }, // Reduced size
                  facingMode: "user"
                }
              });
              console.log('Camera access granted');
            } catch (cameraError) {
              console.error('Camera access specific error:', cameraError.name, cameraError.message);
              if (cameraError.name === 'NotAllowedError') {
                throw new Error('Camera access denied. Please allow camera access in your browser settings and try again.');
              } else if (cameraError.name === 'NotFoundError') {
                throw new Error('No camera found. Please connect a camera and try again.');
              } else if (cameraError.name === 'NotReadableError') {
                throw new Error('Camera is already in use by another application. Please: <br>1. Close other applications using your camera (Zoom, Teams, etc.)<br>2. Close other browser tabs that might be using the camera<br>3. Try restarting your browser');
              } else {
                throw cameraError;
              }
            }
            
            // Camera access successful
            video.srcObject = stream;
            video.classList.remove('hidden');
            startButton.classList.add('hidden');
            captureButton.style.display = 'inline-flex';
            
            video.onloadedmetadata = function() {
              video.play();
              selfieStatus.textContent = 'Position your face and click "Take Photo"';
              selfieStatus.className = 'mt-1 text-xs text-green-600';
            };
          } catch (err) {
            console.error('Camera access error:', err);
            selfieStatus.textContent = err.message || 'Could not access camera. Please ensure camera permissions are granted.';
            selfieStatus.className = 'mt-1 text-xs text-red-600';
            selfieStatus.innerHTML += ' <button class="text-blue-600 underline" onclick="startCameraRetry()">Retry</button>';
          }
        });
      }
      
      // Add this retry function
      function startCameraRetry() {
        // More aggressive cleanup of video streams
        if (stream) {
          try {
            stream.getTracks().forEach(track => {
              console.log('Stopping track:', track.kind);
              track.stop();
            });
          } catch (e) {
            console.error('Error stopping tracks:', e);
          }
          stream = null;
        }
        
        // Also clear video source object
        if (video.srcObject) {
          try {
            const tracks = video.srcObject.getTracks();
            tracks.forEach(track => {
              console.log('Releasing track:', track.kind);
              track.stop();
            });
          } catch (e) {
            console.error('Error clearing video source:', e);
          }
          video.srcObject = null;
        }
        
        // Reset UI state
        video.classList.add('hidden');
        canvas.classList.add('hidden');
        startButton.classList.remove('hidden');
        captureButton.style.display = 'none';
        retakeButton.style.display = 'none';
        
        // Update status with more helpful information
        selfieStatus.innerHTML = 'Preparing camera for retry... <span class="text-xs">(releasing resources)</span>';
        
        // Try camera with a longer delay and show countdown
        let countdown = 3;
        const countdownTimer = setInterval(() => {
          selfieStatus.innerHTML = `Retrying in ${countdown} seconds...`;
          countdown--;
          
          if (countdown < 0) {
            clearInterval(countdownTimer);
            selfieStatus.textContent = 'Accessing camera...';
            startButton.click();
          }
        }, 1000);
      }
      
      // Function to capture photo
      if (captureButton) {
        captureButton.addEventListener('click', function() {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
          
          // Convert canvas to data URL and store in hidden input
          const imageData = canvas.toDataURL('image/jpeg', 0.8);
          selfieData.value = imageData;
          
          // Show canvas and hide video
          canvas.classList.remove('hidden');
          video.classList.add('hidden');
          captureButton.style.display = 'none';
          retakeButton.style.display = 'inline-flex';
          
          selfieStatus.textContent = 'Selfie captured. Click "Retake" if needed.';
          selfieStatus.className = 'mt-1 text-xs text-green-600';
        });
      }
      
      // Function to retake photo
      if (retakeButton) {
        retakeButton.addEventListener('click', function() {
          canvas.classList.add('hidden');
          video.classList.remove('hidden');
          captureButton.style.display = 'inline-flex';
          retakeButton.style.display = 'none';
          selfieData.value = '';
          
          selfieStatus.textContent = 'Position your face and click "Take Photo"';
          selfieStatus.className = 'mt-1 text-xs text-gray-500';
        });
      }

      // Form submission validation enhancement
      if (form) {
        const originalSubmitHandler = form.onsubmit;
        form.onsubmit = function(event) {
          // Check if selfie is captured
          if (!selfieData.value) {
            event.preventDefault();
            Toastify({
              text: "❌ Please take a selfie for verification",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
            return false;
          }
          
          // Check if government ID is uploaded
          const govId = document.getElementById('gov_id');
          if (govId && !govId.files[0]) {
            event.preventDefault();
            Toastify({
              text: "❌ Please upload your government ID",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
            return false;
          }
          
          // If we have the original handler, call it
          if (typeof originalSubmitHandler === 'function') {
            return originalSubmitHandler.call(this, event);
          }
        };
      }

      // Add this right after the DOMContentLoaded event listener starts

      // Release camera when page is closed/refreshed
      window.addEventListener('beforeunload', function() {
        if (stream) {
          stream.getTracks().forEach(track => {
            track.stop();
          });
          stream = null;
        }
      });
    });

    // Error notifications
    <?php if (isset($_SESSION['error'])): ?>
        Toastify({
            text: "❌ <?= htmlspecialchars($_SESSION['error']) ?>",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444",
            stopOnFocus: true,
            className: "toast-message"
        }).showToast();
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Success messages
    <?php if (isset($_SESSION['success'])): ?>
        Toastify({
            text: "✅ <?= htmlspecialchars($_SESSION['success']) ?>",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#10B981",
            stopOnFocus: true,
            className: "toast-message"
        }).showToast();
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    // Pending approval messages
    <?php if (isset($_SESSION['pending_approval'])): ?>
        Toastify({
            text: "⏳ <?= htmlspecialchars($_SESSION['pending_approval']) ?>",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#F59E0B",
            stopOnFocus: true,
            className: "toast-message"
        }).showToast();
        <?php unset($_SESSION['pending_approval']); ?>
    <?php endif; ?>
  </script>
</body>
</html>