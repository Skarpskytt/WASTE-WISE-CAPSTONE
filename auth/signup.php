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
  <link rel="stylesheet" href="../assets/style/signup.css">
  <link rel="icon" type="image/x-icon" href="../assets/images/Logo.png">
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

 
</head>
<body>
  <div class="flex min-h-screen">
    <!-- Form (now full width) with sec background -->
    <div class="w-full bg-sec flex items-center justify-center py-2">
      <div class="form-container w-full max-w-4xl">
        <h1 class="text-2xl md:text-3xl font-semibold mb-2 text-black text-center">Sign Up</h1>
        <h1 class="text-sm md:text-base font-semibold mb-6 text-gray-500 text-center">Join our community with all-time access and free</h1>
        
        <form action="save_signup.php" method="POST" enctype="multipart/form-data" class="space-y-4 md:space-y-6">
          <!-- Add this right after your form opening tag -->
          <div class="step-indicator mb-8">
            <div class="step active" id="step-1">1</div>
            <div class="step" id="step-2">2</div>
            <div class="step" id="step-3">3</div>
          </div>

          <!-- Restructure your form into steps -->
          <div id="form-step-1" class="form-step">
            <!-- Personal Information Section -->
            <div class="form-section">
              <h3>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
                Personal Information
              </h3>
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
                  <select id="role" name="role" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required onchange="handleSignupRoleChange()">
                    <option value="">Select a role</option>
                    <option value="staff">Staff</option>
                    <option value="company">Company</option>
                    <option value="ngo">NGO Partner</option>
                  </select>
                </div>
                <div id="branch-selection" class="hidden">
                  <label for="branch_id" class="block text-sm font-medium text-gray-700">Select Branch</label>
                  <select id="branch_id" name="branch_id" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300">
                    <option value="">Select a branch</option>
                    <?php
                    // Reset pointer to beginning of result set
                    $stmt = $pdo->query('SELECT id, name FROM branches ORDER BY id');
                    while ($branch = $stmt->fetch()) {
                      echo '<option value="' . htmlspecialchars($branch['id']) . '">' . 
                           htmlspecialchars($branch['name']) . '</option>';
                    }
                    ?>
                  </select>
                  <p class="text-xs text-gray-500 mt-1">Select the branch you'll be associated with</p>
                </div>
              </div>
            </div>
            
            <!-- Step navigation -->
            <div class="flex justify-end mt-6">
              <button type="button" onclick="goToStep(2)" class="submit-button">
                Continue
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 inline" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>

          <div id="form-step-2" class="form-step hidden">
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
            
            <!-- Replace your existing password requirements with this -->
            <div class="password-requirements mt-2">
              <p class="font-medium text-xs text-gray-700 mb-1">Password strength:</p>
              <div class="password-strength-meter">
                <div id="password-strength-bar" style="width: 0%; background-color: #EF4444;"></div>
              </div>
              <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="requirement" id="length-check">
                  <svg class="inline-block h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                  </svg>
                  <span class="text-xs">8+ characters</span>
                </div>
                <div class="requirement" id="case-check">
                  <svg class="inline-block h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                  </svg>
                  <span class="text-xs">Upper & lowercase</span>
                </div>
                <div class="requirement" id="number-check">
                  <svg class="inline-block h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                  </svg>
                  <span class="text-xs">Contains number</span>
                </div>
                <div class="requirement" id="special-check">
                  <svg class="inline-block h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                  </svg>
                  <span class="text-xs">Special character</span>
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
            
            <!-- Step navigation -->
            <div class="flex justify-between mt-6">
              <button type="button" onclick="goToStep(1)" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 inline" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
                Back
              </button>
              <button type="button" onclick="goToStep(3)" class="submit-button">
                Continue
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 inline" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>

          <div id="form-step-3" class="form-step hidden">
            <!-- Verification Documents Section -->
            <div class="space-y-3 p-3 border border-gray-200 rounded-md bg-gray-50 verification-section">
              <h3 class="font-medium text-gray-700 text-sm">Verification Documents</h3>
              
              <!-- Government ID Upload - Modified for front and back -->
              <div>
                <label class="block text-sm font-medium text-gray-700">Upload Government ID</label>
                
                <!-- Front of ID Upload -->
                <div>
                  <label for="gov_id_front" class="block text-sm text-gray-600 mb-1">Front of ID</label>
                  <div class="modern-file-upload cursor-pointer" id="gov_id_front_dropzone">
                    <input id="gov_id_front" name="gov_id_front" type="file" accept="image/*,.pdf" class="hidden" required>
                    <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="text-sm text-primarycol font-medium">Upload Front</span>
                    <p class="text-xs text-gray-500 mt-1">Click or drop file here (PNG, JPG, PDF up to 5MB)</p>
                  </div>
                  <div id="gov_id_front_preview" class="mt-2 hidden">
                    <div class="flex items-center p-2 bg-green-50 rounded-md border border-green-200">
                      <svg class="h-4 w-4 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                      </svg>
                      <span id="gov_id_front_filename" class="text-xs text-gray-700 flex-grow truncate"></span>
                      <button type="button" onclick="clearFileInput('gov_id_front')" class="text-red-500 hover:text-red-700">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                      </button>
                    </div>  
                  </div>
                </div>
                
                <!-- Back of ID Upload -->
                <div class="mt-3">
                  <label for="gov_id_back" class="block text-sm text-gray-600 mb-1">Back of ID</label>
                  <div class="modern-file-upload cursor-pointer" id="gov_id_back_dropzone">
                    <input id="gov_id_back" name="gov_id_back" type="file" accept="image/*,.pdf" class="hidden" required>
                    <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="text-sm text-primarycol font-medium">Upload Back</span>
                    <p class="text-xs text-gray-500 mt-1">Click or drop file here (PNG, JPG, PDF up to 5MB)</p>
                  </div>
                  <div id="gov_id_back_preview" class="mt-2 hidden">
                    <div class="flex items-center p-2 bg-green-50 rounded-md border border-green-200">
                      <svg class="h-4 w-4 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                      </svg>
                      <span id="gov_id_back_filename" class="text-xs text-gray-700 flex-grow truncate"></span>
                      <button type="button" onclick="clearFileInput('gov_id_back')" class="text-red-500 hover:text-red-700">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                      </button>
                    </div>  
                  </div>
                </div>
              </div>

              <!-- Selfie Capture - Updated instructions -->
              <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Take a Selfie with your ID</label>
                <p class="text-xs text-gray-500 mb-2">Hold your ID next to your face in the photo for verification purposes.</p>
                
                <div id="camera-container" class="relative overflow-hidden rounded-xl" style="height: 220px;">
                  <video id="video" class="w-full h-full object-cover hidden" autoplay playsinline></video>
                  <canvas id="canvas" class="w-full h-full object-cover hidden"></canvas>
                  <div id="start-camera" class="absolute inset-0 flex items-center justify-center bg-gray-100 cursor-pointer p-6 rounded-xl transition-all hover:bg-gray-200">
                    <div class="text-center">
                      <div class="w-16 h-16 bg-primarycol/10 flex items-center justify-center rounded-full mx-auto mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primarycol" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 006 0z" />
                        </svg>
                      </div>
                      <p class="font-medium text-gray-700">Click to activate camera</p>
                      <p class="mt-1 text-xs text-gray-500">Make sure you and your ID are clearly visible</p>
                    </div>
                  </div>
                  <!-- Face detection box -->
                  <div id="face-detection-box" class="absolute border-2 border-green-500"></div>
                </div>
                
                <!-- Hidden input to store the captured image -->
                <input type="hidden" name="selfie_data" id="selfie_data">
                
                <div class="mt-4 flex justify-center space-x-3">
                  <button type="button" id="capture-button" class="camera-button" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                    Take Photo
                  </button>
                  <button type="button" id="retake-button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1 inline" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                    Retake
                  </button>
                </div>
                <div id="selfie-status" class="mt-2 text-center text-sm"></div>
              </div>
            </div>
            
            <!-- Terms and Submit -->
            <div class="terms-checkbox flex gap-2 justify-start items-start">
              <input type="checkbox" id="terms" name="terms" required class="mt-1">
              <label for="terms" class="text-xs text-gray-600 hover:text-gray-800">Accept <span id="showTerms" class="text-black cursor-pointer underline hover:text-gray-800">Terms and Conditions</span></label>
            </div>
            <div class="flex justify-between mt-6">
              <button type="button" onclick="goToStep(2)" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 inline" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
                Back
              </button>
              <button type="submit" class="submit-button">
                Complete Registration
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 inline" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>
        </form>
        <div class="mt-4 text-sm text-gray-600 text-center">
          <p>Already have an account? <a href="../index.php" class="text-black hover:underline">Login here</a></p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Terms and Conditions Modal -->
  <div id="termsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
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

    // Replace your existing checkPasswordStrength function with this
    function checkPasswordStrength(password) {
      // Check various requirements
      const lengthCheck = password.length >= 8;
      const caseCheck = password.match(/[a-z]/) && password.match(/[A-Z]/);
      const numberCheck = password.match(/[0-9]/);
      const specialCheck = password.match(/[^a-zA-Z0-9]/);
      
      // Update requirement checks with better visuals
      updateRequirement('length-check', lengthCheck);
      updateRequirement('case-check', caseCheck);
      updateRequirement('number-check', numberCheck);
      updateRequirement('special-check', specialCheck);
      
      // Calculate password strength
      let strength = 0;
      if (lengthCheck) strength += 25;
      if (caseCheck) strength += 25;
      if (numberCheck) strength += 25;
      if (specialCheck) strength += 25;
      
      // Update strength meter
      const strengthBar = document.getElementById('password-strength-bar');
      if (strengthBar) {
        strengthBar.style.width = strength + '%';
        
        // Change color based on strength
        if (strength <= 25) {
          strengthBar.style.backgroundColor = '#EF4444'; // Red (weak)
        } else if (strength <= 50) {
          strengthBar.style.backgroundColor = '#F59E0B'; // Yellow (medium)
        } else if (strength <= 75) {
          strengthBar.style.backgroundColor = '#60A5FA'; // Blue (good)
        } else {
          strengthBar.style.backgroundColor = '#10B981'; // Green (strong)
        }
      }
      
      // Update overall strength message
      const strengthEl = document.getElementById('password-strength');
      if (strengthEl) {
        if (password.length === 0) {
          strengthEl.textContent = '';
        } else if (strength === 100) {
          strengthEl.textContent = 'Strong password';
          strengthEl.className = 'mt-1 text-xs text-green-600';
        } else if (strength >= 50) {
          strengthEl.textContent = 'Medium strength password';
          strengthEl.className = 'mt-1 text-xs text-yellow-600';
        } else {
          strengthEl.textContent = 'Weak password';
          strengthEl.className = 'mt-1 text-xs text-red-600';
        }
      }
    }

    // Update the updateRequirement function
    function updateRequirement(id, isValid) {
      const el = document.getElementById(id);
      if (el) {
        const icon = el.querySelector('svg');
        
        if (icon) {
          if (isValid) {
            // Change to checkmark with animation
            icon.innerHTML = '<circle cx="12" cy="12" r="10" stroke-width="2" class="text-green-500" fill="none"/>' +
                             '<path class="text-green-500 animated-check" stroke-width="2" fill="none" d="M8 12l3 3 6-6" stroke-linecap="round" stroke-linejoin="round"/>';
            el.classList.add('text-green-600');
            el.classList.remove('text-gray-600');
          } else {
            // Reset to default state
            icon.innerHTML = '<circle cx="12" cy="12" r="10" stroke-width="2"/>';
            el.classList.remove('text-green-600');
            el.classList.add('text-gray-600');
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
    // Replace your existing clearFileInput function with this simpler version
    function clearFileInput(id) {
      const input = document.getElementById(id);
      if (input) {
        input.value = '';
        const preview = document.getElementById(`${id}_preview`);
        if (preview) preview.classList.add('hidden');
      }
    }

    // Wait for DOM to be loaded before adding event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Remove duplicate form variable declaration and declaration of duplicate handlers
      const form = document.querySelector('form');
      const passwordInput = document.getElementById('password');
      const confirmPasswordInput = document.getElementById('conpassword');
      
      // Initialize requirement checks if password field exists
      if (passwordInput) {
        checkPasswordStrength(passwordInput.value);
      }
      
      // Single form submission handler to avoid duplicates
      if (form) {
        form.onsubmit = function(event) {
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
            return false;
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
            return false;
          }
          
          // Check if selfie is captured
          const selfieData = document.getElementById('selfie_data');
          if (!selfieData.value) {
            event.preventDefault();
            Toastify({
              text: "❌ Please take a selfie with your ID for verification",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
            return false;
          }
          
          // Check if government ID front is uploaded
          const govIdFront = document.getElementById('gov_id_front');
          if (govIdFront && !govIdFront.files[0]) {
            event.preventDefault();
            Toastify({
              text: "❌ Please upload the front of your government ID",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
            return false;
          }
          
          // Check if government ID back is uploaded
          const govIdBack = document.getElementById('gov_id_back');
          if (govIdBack && !govIdBack.files[0]) {
            event.preventDefault();
            Toastify({
              text: "❌ Please upload the back of your government ID",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
            return false;
          }
          
          // Continue with form submission
          return true;
        };
      }
      
      // Rest of your initialization code...

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
              window.cameraStream = await navigator.mediaDevices.getUserMedia({ 
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
            video.srcObject = window.cameraStream;
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
            selfieStatus.innerHTML = (err.message || 'Could not access camera. Please ensure camera permissions are granted.') + 
                                     ' <button type="button" class="text-blue-600 underline" onclick="startCameraRetry()">Retry</button>';
            selfieStatus.className = 'mt-1 text-xs text-red-600';
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

      // File input handlers for the front and back ID uploads
      // Front of ID file input change event
      const govIdFrontInput = document.getElementById('gov_id_front');
      if (govIdFrontInput) {
        govIdFrontInput.addEventListener('change', function() {
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
            
            document.getElementById('gov_id_front_filename').textContent = file.name;
            document.getElementById('gov_id_front_preview').classList.remove('hidden');
          } else {
            document.getElementById('gov_id_front_preview').classList.add('hidden');
          }
        });
      }
      
      // Back of ID file input change event
      const govIdBackInput = document.getElementById('gov_id_back');
      if (govIdBackInput) {
        govIdBackInput.addEventListener('change', function() {
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
            
            document.getElementById('gov_id_back_filename').textContent = file.name;
            document.getElementById('gov_id_back_preview').classList.remove('hidden');
          } else {
            document.getElementById('gov_id_back_preview').classList.add('hidden');
          }
        });
      }
      
      // Update form submission validation for new ID fields
      if (form) {
        const originalSubmitHandler = form.onsubmit;
        form.onsubmit = function(event) {
          // Check if selfie is captured
          const selfieData = document.getElementById('selfie_data');
          if (!selfieData.value) {
            event.preventDefault();
            Toastify({
              text: "❌ Please take a selfie with your ID for verification",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
            return false;
          }
          
          // Check if government ID front is uploaded
          const govIdFront = document.getElementById('gov_id_front');
          if (govIdFront && !govIdFront.files[0]) {
            event.preventDefault();
            Toastify({
              text: "❌ Please upload the front of your government ID",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
            return false;
          }
          
          // Check if government ID back is uploaded
          const govIdBack = document.getElementById('gov_id_back');
          if (govIdBack && !govIdBack.files[0]) {
            event.preventDefault();
            Toastify({
              text: "❌ Please upload the back of your government ID",
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
  <script>
    // Add this as a global function outside of document.ready
    window.startCameraRetry = function() {
      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      const startButton = document.getElementById('start-camera');
      const captureButton = document.getElementById('capture-button');
      const retakeButton = document.getElementById('retake-button');
      const selfieStatus = document.getElementById('selfie-status');
      
      // Get the global stream variable
      let stream = window.cameraStream;
      
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
        window.cameraStream = null;
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
    };
  </script>
  <script>
    // Add this at the top of your script section
    window.cameraStream = null;

    // Then modify your camera activation code
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
            window.cameraStream = await navigator.mediaDevices.getUserMedia({ 
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
          video.srcObject = window.cameraStream;
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
          selfieStatus.innerHTML = (err.message || 'Could not access camera. Please ensure camera permissions are granted.') + 
                                   ' <button type="button" class="text-blue-600 underline" onclick="startCameraRetry()">Retry</button>';
          selfieStatus.className = 'mt-1 text-xs text-red-600';
        }
      });
    }

    // Add this to your existing script section
    function goToStep(stepNumber) {
      // Validate current step before proceeding
      if (stepNumber > 1 && !validateStep(stepNumber - 1)) {
        return false;
      }
      
      // Update step indicators
      document.querySelectorAll('.step').forEach((step, index) => {
        if (index + 1 < stepNumber) {
          step.classList.remove('active');
          step.classList.add('completed');
          step.innerHTML = '✓'; // Checkmark for completed steps
        } else if (index + 1 === stepNumber) {
          step.classList.add('active');
          step.classList.remove('completed');
          step.innerHTML = stepNumber;
        } else {
          step.classList.remove('active', 'completed');
          step.innerHTML = index + 1;
        }
      });
      
      // Hide all steps and show the current one
      document.querySelectorAll('.form-step').forEach((formStep, index) => {
        formStep.classList.add('hidden');
      });
      document.getElementById(`form-step-${stepNumber}`).classList.remove('hidden');
      
      // Scroll to top of form
      document.querySelector('.form-container').scrollIntoView({ behavior: 'smooth' });
    }

    function validateStep(stepNumber) {
      let isValid = true;
      const currentStep = document.getElementById(`form-step-${stepNumber}`);
      
      // For step 1, check personal information
      if (stepNumber === 1) {
        const requiredFields = currentStep.querySelectorAll('input[required], select[required]');
        requiredFields.forEach(field => {
          if (!field.value.trim()) {
            isValid = false;
            highlightInvalidField(field);
          } else {
            removeInvalidHighlight(field);
          }
        });
        
        if (!isValid) {
          Toastify({
            text: "❌ Please fill in all required fields in the Personal Information section",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444"
          }).showToast();
        }
      }
      
      // For step 2, check password requirements and NGO fields if applicable
      if (stepNumber === 2) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('conpassword').value;
        
        // Check password complexity
        const lengthCheck = password.length >= 8;
        const caseCheck = password.match(/[a-z]/) && password.match(/[A-Z]/);
        const numberCheck = password.match(/[0-9]/);
        const specialCheck = password.match(/[^a-zA-Z0-9]/);
        
        if (!(lengthCheck && caseCheck && numberCheck && specialCheck)) {
          isValid = false;
          Toastify({
            text: "❌ Password does not meet complexity requirements",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444"
          }).showToast();
        }
        
        // Check passwords match
        if (password !== confirmPassword) {
          isValid = false;
          Toastify({
            text: "❌ Passwords do not match",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444"
          }).showToast();
        }
        
        // Check NGO fields if applicable
        const role = document.getElementById('role').value;
        if (role === 'ngo') {
          const ngoRequiredFields = currentStep.querySelectorAll('#ngo-fields input[required], #ngo-fields textarea[required]');
          ngoRequiredFields.forEach(field => {
            if (!field.value.trim()) {
              isValid = false;
              highlightInvalidField(field);
            } else {
              removeInvalidHighlight(field);
            }
          });
          
          if (!isValid) {
            Toastify({
              text: "❌ Please fill in all required NGO information fields",
              duration: 3000,
              close: true,
              gravity: "top",
              position: "center",
              backgroundColor: "#EF4444"
            }).showToast();
          }
        }
      }
      
      return isValid;
    }

    function highlightInvalidField(field) {
      field.classList.add('border-red-500');
      field.classList.add('bg-red-50');
    }

    function removeInvalidHighlight(field) {
      field.classList.remove('border-red-500');
      field.classList.remove('bg-red-50');
    }
  </script>
  <script>
    // Replace the file upload interaction code with this optimized version
    document.addEventListener('DOMContentLoaded', function() {
      // Simplify file upload handling for front and back ID
      const fileInputs = ['gov_id_front', 'gov_id_back'];
      
      fileInputs.forEach(inputId => {
        const dropzone = document.getElementById(`${inputId}_dropzone`);
        const input = document.getElementById(inputId);
        const preview = document.getElementById(`${inputId}_preview`);
        const filename = document.getElementById(`${inputId}_filename`);
        
        if (dropzone && input) {
          // Direct click to trigger file dialog
          dropzone.addEventListener('click', function(e) {
            input.click();
          });
          
          // Handle file selection
          input.addEventListener('change', function() {
            const file = this.files[0];
            handleFileSelection(file, preview, filename, inputId);
          });
          
          // Drag and drop functionality (simplified)
          dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-primarycol');
            this.classList.add('bg-sec/20');
          });
          
          dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-primarycol');
            this.classList.remove('bg-sec/20');
          });
          
          dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-primarycol');
            this.classList.remove('bg-sec/20');
            
            const file = e.dataTransfer.files[0];
            input.files = e.dataTransfer.files;
            handleFileSelection(file, preview, filename, inputId);
          });
        }
      });
      
      function handleFileSelection(file, preview, filename, inputId) {
        if (!file) {
          if (preview) preview.classList.add('hidden');
          return;
        }
        
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
          Toastify({
            text: "❌ File size exceeds 5MB limit",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "center",
            backgroundColor: "#EF4444"
          }).showToast();
          
          document.getElementById(inputId).value = '';
          return;
        }
        
        if (filename) filename.textContent = file.name;
        if (preview) preview.classList.remove('hidden');
      }
    });
  </script>
  <script>
    // Add this to your JavaScript section
    function handleSignupRoleChange() {
      const role = document.getElementById('role').value;
      const ngoFields = document.getElementById('ngo-fields');
      const branchSelection = document.getElementById('branch-selection');
      
      // Hide all conditional fields first
      ngoFields.classList.add('hidden');
      branchSelection.classList.add('hidden');
      
      // Remove required attribute from all conditional fields
      document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = false);
      document.getElementById('branch_id').required = false;
      
      // Show relevant fields based on role
      if (role === 'ngo') {
        ngoFields.classList.remove('hidden');
        document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = true);
      } else if (role === 'company' || role === 'staff') {
        branchSelection.classList.remove('hidden');
        document.getElementById('branch_id').required = true;
      }
    }
  </script>
  <script>
    // Make sure this event listener is set up correctly
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        if (roleSelect) {
            roleSelect.addEventListener('change', handleSignupRoleChange);
        }
    });
  </script>
</body>
</html>